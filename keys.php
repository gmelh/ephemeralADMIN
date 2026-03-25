<?php
/**
 * ephemeralREST — Swiss Ephemeris REST API
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * AGPL v3 is used to maintain licensing compatibility with the Swiss Ephemeris
 * library by Astrodienst AG, which is itself licensed under the AGPL v3.
 * See https://www.astro.com/swisseph/ for details.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
auth_require('admin');

$page_title = 'Keys';


// Extract the most useful error text from an API result array
function extractApiError(array $result): string {
    $d = $result['data'] ?? [];
    $msg = $d['error'] ?? $d['message'] ?? null;
    if (!$msg) $msg = 'HTTP ' . ($result['status'] ?? '?');
    if (($result['status'] ?? 0) === 429) $msg = 'Rate limit exceeded (429) — please wait before retrying.';
    return $msg;
}

// Handle AJAX POST (modal save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $key_id = (int)($input['key_id'] ?? 0);

    if (!$key_id) {
        echo json_encode(['ok' => false, 'error' => 'Missing key ID']); exit;
    }

    if ($action === 'save-limits') {
        $result = my_api_post("/admin/keys/{$key_id}/limits", [
            'rate_per_minute' => strlen((string)($input['rpm'] ?? '')) ? (int)$input['rpm'] : null,
            'rate_per_hour'   => strlen((string)($input['rph'] ?? '')) ? (int)$input['rph'] : null,
            'rate_per_day'    => strlen((string)($input['rpd'] ?? '')) ? (int)$input['rpd'] : null,
        ]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Rate limits updated.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'disable') {
        $result = my_api_post("/admin/keys/{$key_id}/disable");
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Key disabled.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'enable') {
        $result = my_api_post("/admin/keys/{$key_id}/enable");
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Key enabled.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'rotate') {
        $result = my_api_post("/admin/keys/{$key_id}/rotate");
        if ($result['ok']) {
            $new_key = $result['data']['api_key'] ?? '';
            // Email is handled server-side in the API — just return the key
            echo json_encode(['ok' => true, 'message' => 'Key rotated.', 'new_key' => $new_key]);
        } else {
            echo json_encode(['ok' => false, 'error' => $result['data']['error'] ?? 'Rotation failed.']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}

// Fetch all keys
$type_filter     = $_GET['type']    ?? 'all';
$show_inactive   = isset($_GET['inactive']);

$result = my_api_get('/admin/keys' . ($show_inactive ? '?inactive=1' : ''));
$keys   = [];
$api_ok = $result['ok'];

if ($api_ok) {
    $keys = $result['data']['keys'] ?? [];
    if ($type_filter !== 'all') {
        $keys = array_values(array_filter($keys, fn($k) => ($k['key_type'] ?? '') === $type_filter));
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">API Keys</h1>
    <p class="page-subtitle">Manage all domain and user API keys</p>
  </div>
</div>

<div style="display:flex; gap:14px; align-items:center; margin-bottom:20px; flex-wrap:wrap;">
  <div class="tabs" style="margin-bottom:0;">
    <?php foreach (['all' => 'All', 'domain' => 'Domain', 'user' => 'User'] as $val => $label): ?>
      <a href="?type=<?= $val ?><?= $show_inactive ? '&inactive=1' : '' ?>"
         class="tab <?= $type_filter === $val ? 'tab--active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <a href="?type=<?= $type_filter ?><?= $show_inactive ? '' : '&inactive=1' ?>"
     class="btn btn--ghost btn--sm">
    <?= $show_inactive ? 'Hide disabled' : 'Show disabled' ?>
  </a>
</div>

<?php if (!$api_ok): ?>
  <div class="alert alert--warning">
    Could not load keys — API returned status <?= $result['status'] ?>.
    <?= htmlspecialchars($result['data']['error'] ?? '') ?>
  </div>
<?php elseif (empty($keys)): ?>
  <div class="alert alert--info">
    No keys found. Create keys using <code>key_manager.py create</code> or via the registration form.
  </div>
<?php else: ?>
  <div class="card">
    <div class="card__head">
      <span class="card__title"><?= $type_filter === 'all' ? 'All' : ucfirst($type_filter) ?> Keys</span>
      <span class="count-label"><?= count($keys) ?> key<?= count($keys) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Identifier</th>
            <th>Name</th>
            <th>Type</th>
            <th>Prefix</th>
            <th>Status</th>
            <th>rpm</th>
            <th>rph</th>
            <th>rpd</th>
            <th>Output</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($keys as $key): ?>
            <tr id="key-row-<?= (int)$key['id'] ?>">
              <td>
                <strong><?= htmlspecialchars($key['identifier'] ?? '—') ?></strong>
                <?php if (!empty($key['admin'])): ?>
                  <span class="badge badge--warning" style="margin-left:6px;font-size:10px;">admin</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--ink-light);font-size:13px;"><?= htmlspecialchars($key['name'] ?? '—') ?></td>
              <td><span class="<?= status_badge($key['key_type'] ?? '') ?>"><?= htmlspecialchars($key['key_type'] ?? '—') ?></span></td>
              <td class="mono" style="font-size:11.5px;color:var(--ink-light);"><?= htmlspecialchars($key['key_prefix'] ?? '—') ?></td>
              <td>
                <span class="<?= status_badge(!empty($key['active']) ? 'active' : 'disabled') ?>"
                      id="key-status-<?= (int)$key['id'] ?>">
                  <?= !empty($key['active']) ? 'active' : 'disabled' ?>
                </span>
              </td>
              <td class="mono" id="key-rpm-<?= (int)$key['id'] ?>"><?= $key['rate_per_minute'] ?? '<span style="color:var(--ink-faint)">—</span>' ?></td>
              <td class="mono" id="key-rph-<?= (int)$key['id'] ?>"><?= $key['rate_per_hour']   ?? '<span style="color:var(--ink-faint)">—</span>' ?></td>
              <td class="mono" id="key-rpd-<?= (int)$key['id'] ?>"><?= $key['rate_per_day']    ?? '<span style="color:var(--ink-faint)">—</span>' ?></td>
              <td>
                <a href="/key-output.php?key_id=<?= (int)$key['id'] ?>"
                   class="btn btn--ghost btn--sm"
                   title="Edit output configuration">
                  Output
                </a>
              </td>
              <td>
                <button class="btn btn--ghost btn--sm"
                  onclick="openKeyModal(<?= htmlspecialchars(json_encode($key), ENT_QUOTES) ?>)">
                  Edit
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Key edit modal -->
<div class="modal-overlay" id="keyModal">
  <div class="modal">
    <div class="modal__head">
      <span class="modal__title" id="key-modal-title">Edit Key</span>
      <button class="modal__close" onclick="closeModal('keyModal')">×</button>
    </div>
    <div class="modal__body">
      <input type="hidden" id="key-id">

      <!-- Identity -->
      <div class="detail-list" style="margin-bottom:20px;">
        <dt>Identifier</dt> <dd id="key-detail-id" style="font-family:var(--font-mono);font-size:13px;"></dd>
        <dt>Type</dt>       <dd id="key-detail-type"></dd>
        <dt>Prefix</dt>     <dd id="key-detail-prefix" style="font-family:var(--font-mono);font-size:13px;"></dd>
      </div>

      <!-- Rate limits -->
      <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:12px;">
        Rate Limits <span style="font-weight:400;text-transform:none;letter-spacing:0;">— leave blank to use class default</span>
      </p>
      <div class="form-grid" style="gap:14px; margin-bottom:20px;">
        <div class="form-group">
          <label for="key-rpm">Per minute</label>
          <input type="number" id="key-rpm" min="0" placeholder="class default">
        </div>
        <div class="form-group">
          <label for="key-rph">Per hour</label>
          <input type="number" id="key-rph" min="0" placeholder="class default">
        </div>
        <div class="form-group">
          <label for="key-rpd">Per day</label>
          <input type="number" id="key-rpd" min="0" placeholder="class default">
        </div>
      </div>

      <!-- Rotate -->
      <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:4px;">
        <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:8px;">Rotate Key</p>
        <p style="font-size:13px;color:var(--ink-light);margin-bottom:12px;">
          Generate a new API key. The current key stops working immediately. The new key is
          emailed to the registrant and shown here once.
        </p>
        <button class="btn btn--secondary btn--sm" onclick="rotateKey()" id="btn-rotate">
          Generate New Key
        </button>
        <div id="rotated-key-display" style="display:none; margin-top:12px;">
          <div style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:12px; font-family:var(--font-mono); font-size:12.5px; word-break:break-all; color:var(--ink);" id="rotated-key-value"></div>
          <div style="display:flex; gap:8px; margin-top:8px; align-items:center;">
            <button class="btn btn--ghost btn--sm" onclick="copyKey()">Copy</button>
            <span style="font-size:12px;color:var(--warning);">⚠ Save this — not shown again</span>
          </div>
        </div>
      </div>

      <!-- Enable / Disable -->
      <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
        <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:10px;">Status</p>
        <div style="display:flex; gap:8px;" id="status-actions"></div>
      </div>

      <p id="key-modal-error" style="color:var(--error);font-size:13px;margin-top:12px;display:none;"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--primary" onclick="saveKeyLimits()" id="btn-save-limits">Save Limits</button>
      <button class="btn btn--ghost"   onclick="closeModal('keyModal')">Close</button>
      <span id="key-saving" style="font-size:13px;color:var(--ink-faint);display:none;margin-left:auto;">Saving…</span>
    </div>
  </div>
</div>

<script>
let currentKeyActive = true;

function openKeyModal(key) {
  document.getElementById('key-id').value                   = key.id;
  document.getElementById('key-modal-title').textContent    = key.identifier;
  document.getElementById('key-detail-id').textContent      = key.identifier;
  document.getElementById('key-detail-prefix').textContent  = key.key_prefix || '—';
  document.getElementById('key-detail-type').innerHTML      = '<span class="badge badge--' + (key.key_type === 'domain' ? 'blue' : 'purple') + '">' + (key.key_type || '—') + '</span>';
  document.getElementById('key-rpm').value                  = key.rate_per_minute ?? '';
  document.getElementById('key-rph').value                  = key.rate_per_hour   ?? '';
  document.getElementById('key-rpd').value                  = key.rate_per_day    ?? '';
  document.getElementById('key-modal-error').style.display  = 'none';
  document.getElementById('rotated-key-display').style.display = 'none';

  currentKeyActive = !!key.active;
  const statusDiv = document.getElementById('status-actions');
  if (currentKeyActive) {
    statusDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="toggleKey(\'disable\')">Disable Key</button>';
  } else {
    statusDiv.innerHTML = '<button class="btn btn--success btn--sm" onclick="toggleKey(\'enable\')">Enable Key</button>';
  }

  document.getElementById('keyModal').classList.add('is-open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
}

async function saveKeyLimits() {
  const keyId   = document.getElementById('key-id').value;
  const rpmVal  = document.getElementById('key-rpm').value;
  const rphVal  = document.getElementById('key-rph').value;
  const rpdVal  = document.getElementById('key-rpd').value;
  const errEl   = document.getElementById('key-modal-error');
  const saving  = document.getElementById('key-saving');
  const saveBtn = document.getElementById('btn-save-limits');

  errEl.style.display  = 'none';
  saving.style.display = 'inline';
  saveBtn.disabled     = true;

  try {
    const res  = await fetch('/keys.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        action: 'save-limits',
        key_id: parseInt(keyId),
        rpm: rpmVal !== '' ? parseInt(rpmVal) : null,
        rph: rphVal !== '' ? parseInt(rphVal) : null,
        rpd: rpdVal !== '' ? parseInt(rpdVal) : null,
      })
    });
    const data = await res.json();
    if (!data.status) data.status = res.status;
    if (data.ok) {
      // Update table cells
      document.getElementById('key-rpm-' + keyId).textContent = rpmVal || '—';
      document.getElementById('key-rph-' + keyId).textContent = rphVal || '—';
      document.getElementById('key-rpd-' + keyId).textContent = rpdVal || '—';
      showFlash('success', data.message);
    } else {
      errEl.textContent   = apiError(data);
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
    errEl.style.display = 'block';
  } finally {
    saving.style.display = 'none';
    saveBtn.disabled     = false;
  }
}

async function toggleKey(action) {
  const keyId  = document.getElementById('key-id').value;
  const errEl  = document.getElementById('key-modal-error');
  errEl.style.display = 'none';

  if (!confirm(action === 'disable' ? 'Disable this key? It will stop working immediately.' : 'Enable this key?')) return;

  const res  = await fetch('/keys.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ action, key_id: parseInt(keyId) })
  });
  const data = await res.json();
    if (!data.status) data.status = res.status;

  if (data.ok) {
    const isActive = action === 'enable';
    currentKeyActive = isActive;
    const statusBadge = document.getElementById('key-status-' + keyId);
    if (statusBadge) {
      statusBadge.className   = isActive ? 'badge badge--success' : 'badge badge--error';
      statusBadge.textContent = isActive ? 'active' : 'disabled';
    }
    const statusDiv = document.getElementById('status-actions');
    if (isActive) {
      statusDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="toggleKey(\'disable\')">Disable Key</button>';
    } else {
      statusDiv.innerHTML = '<button class="btn btn--success btn--sm" onclick="toggleKey(\'enable\')">Enable Key</button>';
    }
    showFlash('success', data.message);
  } else {
    errEl.textContent   = apiError(data);
    errEl.style.display = 'block';
  }
}

async function rotateKey() {
  const keyId = document.getElementById('key-id').value;
  const errEl = document.getElementById('key-modal-error');
  errEl.style.display = 'none';

  if (!confirm('Generate a new key? The current key will stop working immediately.')) return;

  document.getElementById('btn-rotate').disabled = true;

  const res  = await fetch('/keys.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ action: 'rotate', key_id: parseInt(keyId) })
  });
  const data = await res.json();
    if (!data.status) data.status = res.status;

  if (data.ok) {
    const display = document.getElementById('rotated-key-display');
    document.getElementById('rotated-key-value').textContent = data.new_key || '(check email)';
    display.style.display = 'block';
    // Update prefix in table
    showFlash('success', data.message);
  } else {
    errEl.textContent   = apiError(data);
    errEl.style.display = 'block';
  }
  document.getElementById('btn-rotate').disabled = false;
}

function copyKey() {
  const key = document.getElementById('rotated-key-value').textContent;
  navigator.clipboard.writeText(key).then(() => {
    showFlash('success', 'Key copied to clipboard.');
  });
}

document.getElementById('keyModal').addEventListener('click', e => {
  if (e.target === document.getElementById('keyModal')) closeModal('keyModal');
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.is-open')
    .forEach(m => m.classList.remove('is-open'));
});


// Extract the most useful error message from any API response shape
function apiError(data) {
  if (data && data.status === 429) return 'Rate limit exceeded (429) — too many requests. Please wait and try again.';
  if (data) return data.error || data.message || (data.status ? 'HTTP ' + data.status : 'Unknown error');
  return 'Unknown error';
}

function showFlash(type, message) {
  const existing = document.querySelector('.flash');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className   = 'flash flash--' + type;
  el.textContent = message;
  document.querySelector('.main__inner').prepend(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3500);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
