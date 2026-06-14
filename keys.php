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
            echo json_encode(['ok' => true, 'message' => 'Key rotated.', 'new_key' => $new_key]);
        } else {
            echo json_encode(['ok' => false, 'error' => $result['data']['error'] ?? 'Rotation failed.']);
        }
        exit;
    }

    if ($action === 'set-admin' && portal_setting('allow_admin_promotion', true)) {
        $grant  = (bool)($input['admin'] ?? false);
        $result = my_api_post("/admin/keys/{$key_id}/set-admin", ['admin' => $grant]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => $grant ? 'Admin access granted.' : 'Admin access revoked.', 'admin' => $grant]
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}

// Fetch all keys
$show_inactive = isset($_GET['inactive']);

$result = my_api_get('/admin/keys' . ($show_inactive ? '?inactive=1' : ''));
$keys   = [];
$api_ok = $result['ok'];

if ($api_ok) {
    $keys = $result['data']['keys'] ?? [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">API Keys</h1>
    <p class="page-subtitle">Manage all API keys</p>
  </div>
  <div class="actions">
    <a href="?<?= $show_inactive ? '' : 'inactive=1' ?>"
       class="btn btn--ghost btn--sm">
      <?= $show_inactive ? 'Hide disabled' : 'Show disabled' ?>
    </a>
  </div>
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
      <span class="card__title">Keys</span>
      <span class="count-label"><?= count($keys) ?> key<?= count($keys) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Identifier</th>
            <th>Name</th>
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
                  <td class="mono" style="font-size:11.5px;color:var(--ink-light);"><?= htmlspecialchars($key['key_prefix'] ?? '—') ?></td>
              <td>
                <span class="<?= status_badge(!empty($key['active']) ? 'active' : 'disabled') ?>"
                      id="key-status-<?= (int)$key['id'] ?>">
                  <?= !empty($key['active']) ? 'active' : 'disabled' ?>
                </span>
              </td>
              <td class="mono" id="key-rpm-<?= (int)$key['id'] ?>"><?= $key['rate_per_minute'] ?? '<span style="color:var(--ink-light)">—</span>' ?></td>
              <td class="mono" id="key-rph-<?= (int)$key['id'] ?>"><?= $key['rate_per_hour']   ?? '<span style="color:var(--ink-light)">—</span>' ?></td>
              <td class="mono" id="key-rpd-<?= (int)$key['id'] ?>"><?= $key['rate_per_day']    ?? '<span style="color:var(--ink-light)">—</span>' ?></td>
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

      <!-- Key Type toggle -->
      <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
        <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:10px;">Key Type</p>
        <div style="display:flex; align-items:center; gap:12px;">
          <span id="key-type-badge"></span>
        </div>
      </div>

      <?php if (portal_setting('allow_admin_promotion', true)): ?>
      <!-- Admin Access -->
      <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
        <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:10px;">Admin Access</p>
        <div style="display:flex; gap:8px;" id="admin-actions"></div>
      </div>
      <?php endif; ?>

      <!-- Enable / Disable -->
      <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
        <p style="font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-light);margin-bottom:10px;">Status</p>
        <div style="display:flex; gap:8px;" id="status-actions"></div>
      </div>

      <p id="key-modal-error" style="color:var(--error);font-size:13px;margin-top:12px;display:none;"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--primary" onclick="saveKeyLimits()" id="btn-save-limits">Save Settings</button>
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
  document.getElementById('key-rpm').value                  = key.rate_per_minute ?? '';
  document.getElementById('key-rph').value                  = key.rate_per_hour   ?? '';
  document.getElementById('key-rpd').value                  = key.rate_per_day    ?? '';
  document.getElementById('key-modal-error').style.display  = 'none';
  document.getElementById('rotated-key-display').style.display = 'none';

  currentKeyActive = !!key.active;

  const adminDiv = document.getElementById('admin-actions');
  if (adminDiv) {
    if (key.admin) {
      adminDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="setAdmin(false)">Revoke Admin</button>';
    } else {
      adminDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="setAdmin(true)">Grant Admin</button>';
    }
  }

  const statusDiv = document.getElementById('status-actions');
  if (currentKeyActive) {
    statusDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="toggleKey(\'disable\')">Disable Key</button>';
  } else {
    statusDiv.innerHTML = '<button class="btn btn--success btn--sm" onclick="toggleKey(\'enable\')">Enable Key</button>';
  }

  document.getElementById('keyModal').classList.add('is-open');
}

// updateKeyTypeBadge and setKeyType removed — flat key structure

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

async function setAdmin(grant) {
  const keyId  = document.getElementById('key-id').value;
  const errEl  = document.getElementById('key-modal-error');
  errEl.style.display = 'none';

  const action = grant ? 'grant' : 'revoke';
  if (!confirm((grant ? 'Grant' : 'Revoke') + ' admin access for this key?')) return;

  try {
    const res  = await fetch('/keys.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'set-admin', key_id: parseInt(keyId), admin: grant }),
    });
    const data = await res.json();
    if (!data.status) data.status = res.status;

    if (data.ok) {
      const adminDiv = document.getElementById('admin-actions');
      if (adminDiv) {
        if (grant) {
          adminDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="setAdmin(false)">Revoke Admin</button>';
        } else {
          adminDiv.innerHTML = '<button class="btn btn--danger btn--sm" onclick="setAdmin(true)">Grant Admin</button>';
        }
      }
      // Update admin badge in table row
      const row = document.getElementById('key-row-' + keyId);
      if (row) {
        const idCell   = row.querySelector('td:first-child');
        const existing = idCell ? idCell.querySelector('.badge--warning') : null;
        if (grant && !existing && idCell) {
          const badge = document.createElement('span');
          badge.className   = 'badge badge--warning';
          badge.style.cssText = 'margin-left:6px;font-size:10px;';
          badge.textContent = 'admin';
          idCell.appendChild(badge);
        } else if (!grant && existing) {
          existing.remove();
        }
      }
      showFlash('success', data.message);
    } else {
      errEl.textContent   = apiError(data);
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
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