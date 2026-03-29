<?php
/**
 * ephemeralADMIN — Administration portal for ephemeralREST
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
auth_require('admin');

$page_title = 'Class Limits';

// Handle AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $action   = $input['action'] ?? 'save';

    if ($action === 'fetch') {
        // Return fresh data for a class
        $key_type = $input['key_type'] ?? '';
        $r = my_api_get("/admin/class-limits/{$key_type}");
        echo json_encode($r['ok'] ? ['ok' => true, 'data' => $r['data']] : ['ok' => false, 'error' => $r['data']['error'] ?? 'Fetch failed.', 'status' => $r['status']]);
        exit;
    }

    $key_type = $input['key_type'] ?? '';
    $rpm      = isset($input['rate_per_minute']) ? (int)$input['rate_per_minute'] : null;
    $rph      = isset($input['rate_per_hour'])   ? (int)$input['rate_per_hour']   : null;
    $rpd      = isset($input['rate_per_day'])    ? (int)$input['rate_per_day']    : null;

    if (!in_array($key_type, ['domain', 'user', 'wildcard']) || $rpm === null || $rph === null || $rpd === null) {
        echo json_encode(['ok' => false, 'error' => 'Invalid input']); exit;
    }
    $result = my_api_post('/admin/class-limits', [
        'key_type'        => $key_type,
        'rate_per_minute' => $rpm,
        'rate_per_hour'   => $rph,
        'rate_per_day'    => $rpd,
    ]);
    echo json_encode($result['ok']
        ? ['ok' => true,  'message' => ucfirst($key_type) . ' class limits updated.']
        : ['ok' => false, 'error' => extractError($result), 'status' => $result['status']]);
    exit;
}

function extractError(array $result): string {
    $d = $result['data'] ?? [];
    return $d['error'] ?? $d['message'] ?? ('HTTP ' . $result['status']);
}

// Fetch limits
$classes    = ['domain', 'user', 'wildcard'];
$limits     = [];
$class_desc = [
    'domain'   => 'Keys registered with a specific domain name.',
    'user'     => 'User-type keys (email-based personal keys).',
    'wildcard' => 'Keys where domain is set to * — no domain specified.',
];
$class_icon = ['domain' => '◇', 'user' => '○', 'wildcard' => '✦'];

foreach ($classes as $class) {
    $r = my_api_get("/admin/class-limits/{$class}");
    $limits[$class] = $r['ok'] ? $r['data'] : [
        'key_type'        => $class,
        'rate_per_minute' => $class === 'domain' ? 20 : ($class === 'wildcard' ? 5  : 10),
        'rate_per_hour'   => $class === 'domain' ? 200 : ($class === 'wildcard' ? 30 : 100),
        'rate_per_day'    => $class === 'domain' ? 1000 : ($class === 'wildcard' ? 100 : 500),
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Key Class Limits</h1>
    <p class="page-subtitle">Default rate limits per key class — applied when no per-key override is set</p>
  </div>
</div>

<div class="alert alert--info" style="max-width:720px; margin-bottom:24px;">
  These are class defaults. Individual keys can override any value via the key detail page.
</div>

<div class="card">
  <div class="card__head">
    <span class="card__title">Current Defaults</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Class</th>
          <th>Description</th>
          <th>Per Minute</th>
          <th>Per Hour</th>
          <th>Per Day</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($classes as $class): $lim = $limits[$class]; ?>
          <tr>
            <td>
              <span style="opacity:.5; margin-right:6px;"><?= $class_icon[$class] ?></span>
              <strong><?= htmlspecialchars($class) ?></strong>
            </td>
            <td style="font-size:13px; color:var(--ink-light);"><?= htmlspecialchars($class_desc[$class]) ?></td>
            <td class="mono" id="disp-rpm-<?= $class ?>"><?= number_format($lim['rate_per_minute'] ?? 0) ?></td>
            <td class="mono" id="disp-rph-<?= $class ?>"><?= number_format($lim['rate_per_hour']   ?? 0) ?></td>
            <td class="mono" id="disp-rpd-<?= $class ?>"><?= number_format($lim['rate_per_day']    ?? 0) ?></td>
            <td>
              <!-- Pass only the class name — fetch fresh values on click -->
              <button class="btn btn--ghost btn--sm" onclick="openClassModal('<?= $class ?>')">Edit</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="classModal">
  <div class="modal">
    <div class="modal__head">
      <span class="modal__title" id="modal-title">Edit Class Limits</span>
      <button class="modal__close" onclick="closeModal('classModal')">×</button>
    </div>
    <div class="modal__body">
      <input type="hidden" id="modal-key-type">
      <div id="modal-loading" style="text-align:center; padding:24px; color:var(--ink-light);">Loading…</div>
      <div id="modal-form" style="display:none;">
        <div class="form-grid" style="gap:16px;">
          <div class="form-group">
            <label for="modal-rpm">Requests per minute</label>
            <input type="number" id="modal-rpm" min="0" max="10000" required>
          </div>
          <div class="form-group">
            <label for="modal-rph">Requests per hour</label>
            <input type="number" id="modal-rph" min="0" max="100000" required>
          </div>
          <div class="form-group">
            <label for="modal-rpd">Requests per day</label>
            <input type="number" id="modal-rpd" min="0" max="1000000" required>
          </div>
        </div>
      </div>
      <p id="modal-error" style="color:var(--error); font-size:13px; margin-top:12px; display:none;"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--primary" onclick="saveClassLimits()" id="modal-save-btn" style="display:none;">Save Limits</button>
      <button class="btn btn--ghost"   onclick="closeModal('classModal')">Cancel</button>
      <span id="modal-saving" style="font-size:13px; color:var(--ink-faint); display:none;">Saving…</span>
    </div>
  </div>
</div>

<script>
async function openClassModal(keyType) {
  document.getElementById('modal-key-type').value      = keyType;
  document.getElementById('modal-title').textContent   = 'Edit ' + keyType.charAt(0).toUpperCase() + keyType.slice(1) + ' Class Limits';
  document.getElementById('modal-error').style.display = 'none';
  document.getElementById('modal-loading').style.display = 'block';
  document.getElementById('modal-form').style.display    = 'none';
  document.getElementById('modal-save-btn').style.display = 'none';
  document.getElementById('classModal').classList.add('is-open');

  // Fetch fresh values from API
  try {
    const res  = await fetch('/class-limits.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'fetch', key_type: keyType })
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('modal-rpm').value = data.data.rate_per_minute ?? 0;
      document.getElementById('modal-rph').value = data.data.rate_per_hour   ?? 0;
      document.getElementById('modal-rpd').value = data.data.rate_per_day    ?? 0;
      document.getElementById('modal-loading').style.display  = 'none';
      document.getElementById('modal-form').style.display     = 'block';
      document.getElementById('modal-save-btn').style.display = 'inline-flex';
      document.getElementById('modal-rpm').focus();
    } else {
      document.getElementById('modal-loading').textContent = 'Failed to load: ' + apiError(data);
    }
  } catch(e) {
    document.getElementById('modal-loading').textContent = 'Network error loading data.';
  }
}

function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
}

async function saveClassLimits() {
  const keyType = document.getElementById('modal-key-type').value;
  const rpm     = parseInt(document.getElementById('modal-rpm').value);
  const rph     = parseInt(document.getElementById('modal-rph').value);
  const rpd     = parseInt(document.getElementById('modal-rpd').value);
  const errEl   = document.getElementById('modal-error');
  const saveBtn = document.getElementById('modal-save-btn');
  const saving  = document.getElementById('modal-saving');

  errEl.style.display  = 'none';
  saveBtn.disabled     = true;
  saving.style.display = 'inline';

  try {
    const res  = await fetch('/class-limits.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'save', key_type: keyType, rate_per_minute: rpm, rate_per_hour: rph, rate_per_day: rpd })
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('disp-rpm-' + keyType).textContent = rpm.toLocaleString();
      document.getElementById('disp-rph-' + keyType).textContent = rph.toLocaleString();
      document.getElementById('disp-rpd-' + keyType).textContent = rpd.toLocaleString();
      closeModal('classModal');
      showFlash('success', data.message);
    } else {
      errEl.textContent   = apiError(data);
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
    errEl.style.display = 'block';
  } finally {
    saveBtn.disabled     = false;
    saving.style.display = 'none';
  }
}

// Extract the most useful error message from any API response shape
function apiError(data) {
  if (data.status === 429) return 'Rate limit exceeded (429) — too many requests. Please wait and try again.';
  return data.error || data.message || (data.status ? 'HTTP ' + data.status : 'Unknown error');
}

document.getElementById('classModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal('classModal');
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.is-open')
    .forEach(m => m.classList.remove('is-open'));
});

function showFlash(type, message) {
  const existing = document.querySelector('.flash');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className = 'flash flash--' + type;
  el.textContent = message;
  document.querySelector('.main__inner').prepend(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>