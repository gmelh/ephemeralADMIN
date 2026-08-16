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

$page_title = 'Federated Services';

// Extract the most useful error text from an API result array
function extractApiError(array $result): string {
    $d = $result['data'] ?? [];
    $msg = $d['error'] ?? $d['message'] ?? null;
    if (!$msg) $msg = 'HTTP ' . ($result['status'] ?? '?');
    if (($result['status'] ?? 0) === 429) $msg = 'Rate limit exceeded (429) — please wait before retrying.';
    return $msg;
}

// Handle AJAX POST (modal save / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $slug = trim($input['slug'] ?? '');
        $name = trim($input['display_name'] ?? '');
        if ($slug === '' || $name === '') {
            echo json_encode(['ok' => false, 'error' => 'Slug and display name are required.']); exit;
        }
        $result = my_api_post('/admin/federated-services', [
            'slug'         => $slug,
            'display_name' => $name,
            'description'  => $input['description'] ?: null,
            'base_url'     => $input['base_url'] ?: null,
        ]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => "'{$slug}' registered."]
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing service ID']); exit; }

        $result = my_api_put("/admin/federated-services/{$id}", [
            'display_name' => trim($input['display_name'] ?? ''),
            'description'  => $input['description'] ?: null,
            'base_url'     => $input['base_url'] ?: null,
            'active'       => (bool)($input['active'] ?? true),
        ]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Service updated.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'delete') {
        $id            = (int)($input['id'] ?? 0);
        $remove_grants = !empty($input['remove_grants']);
        if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing service ID']); exit; }

        $result = my_api_delete("/admin/federated-services/{$id}" . ($remove_grants ? '?remove_grants=1' : ''));
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Service removed.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}

// Fetch all registered services
$result   = my_api_get('/admin/federated-services');
$services = $result['ok'] ? ($result['data']['services'] ?? []) : [];
$api_ok   = $result['ok'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Federated Services</h1>
    <p class="page-subtitle">Companion services that keys can be granted access to</p>
  </div>
  <div class="actions">
    <button class="btn btn--primary btn--sm" onclick="openServiceModal()">+ Register Service</button>
  </div>
</div>

<div class="alert alert--info" style="margin-bottom:24px;">
  Registering a service here just makes it selectable when granting a key
  access on that key's detail page — it doesn't create, deploy, or connect
  to anything. See <code>DOCS/ARCHITECTURE.md</code>, "Federated service
  access," for how a companion service actually checks these grants.
</div>

<?php if (!$api_ok): ?>
  <div class="alert alert--warning">
    Could not load services — API returned status <?= $result['status'] ?>.
    <?= htmlspecialchars($result['data']['error'] ?? '') ?>
  </div>
<?php elseif (empty($services)): ?>
  <div class="alert alert--info">
    No federated services registered yet. Click "Register Service" to add one.
  </div>
<?php else: ?>
  <div class="card">
    <div class="card__head">
      <span class="card__title">Services</span>
      <span class="count-label"><?= count($services) ?> service<?= count($services) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Slug</th>
            <th>Name</th>
            <th>Description</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($services as $svc): ?>
            <tr id="service-row-<?= (int)$svc['id'] ?>">
              <td class="mono" style="font-size:12.5px;"><?= htmlspecialchars($svc['slug']) ?></td>
              <td><strong><?= htmlspecialchars($svc['display_name']) ?></strong></td>
              <td style="color:var(--ink-light);font-size:13px;max-width:320px;">
                <?= htmlspecialchars($svc['description'] ?? '—') ?>
              </td>
              <td>
                <span class="<?= status_badge(!empty($svc['active']) ? 'active' : 'disabled') ?>"
                      id="service-status-<?= (int)$svc['id'] ?>">
                  <?= !empty($svc['active']) ? 'active' : 'inactive' ?>
                </span>
              </td>
              <td style="white-space:nowrap;">
                <button class="btn btn--ghost btn--sm"
                  onclick="openServiceModal(<?= htmlspecialchars(json_encode($svc), ENT_QUOTES) ?>)">
                  Edit
                </button>
                <button class="btn btn--ghost btn--sm"
                  onclick="deleteService(<?= (int)$svc['id'] ?>, <?= htmlspecialchars(json_encode($svc['slug'])) ?>)">
                  Remove
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Service add/edit modal -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal">
    <div class="modal__head">
      <span class="modal__title" id="service-modal-title">Register Service</span>
      <button class="modal__close" onclick="closeModal('serviceModal')">×</button>
    </div>
    <div class="modal__body">
      <input type="hidden" id="service-id">

      <div class="form-group" style="margin-bottom:16px;">
        <label for="service-slug">Slug</label>
        <input type="text" id="service-slug" maxlength="32" placeholder="my-companion-app">
        <span class="form-hint">Stable identifier, max 32 characters. Cannot be changed after registering — remove and re-register instead.</span>
      </div>

      <div class="form-group" style="margin-bottom:16px;">
        <label for="service-name">Display name</label>
        <input type="text" id="service-name" placeholder="My Companion App">
      </div>

      <div class="form-group" style="margin-bottom:16px;">
        <label for="service-description">Description</label>
        <input type="text" id="service-description" placeholder="Optional">
      </div>

      <div class="form-group" style="margin-bottom:16px;">
        <label for="service-base-url">Base URL</label>
        <input type="text" id="service-base-url" placeholder="https://example.com (optional, informational only)">
      </div>

      <div class="form-group" id="service-active-row" style="display:none; margin-bottom:8px;">
        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
          <input type="checkbox" id="service-active" style="width:auto;">
          Active (shown as grantable in key detail pages)
        </label>
      </div>

      <p id="service-modal-status" style="display:none; font-size:13px; margin-top:12px;"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--ghost" onclick="closeModal('serviceModal')">Cancel</button>
      <button class="btn btn--primary" id="service-save-btn" onclick="saveService()">Save</button>
    </div>
  </div>
</div>

<script>
function openServiceModal(svc) {
  document.getElementById('service-id').value = svc ? svc.id : '';
  document.getElementById('service-slug').value = svc ? svc.slug : '';
  document.getElementById('service-slug').disabled = !!svc;
  document.getElementById('service-name').value = svc ? svc.display_name : '';
  document.getElementById('service-description').value = svc ? (svc.description || '') : '';
  document.getElementById('service-base-url').value = svc ? (svc.base_url || '') : '';
  document.getElementById('service-active').checked = svc ? !!svc.active : true;
  document.getElementById('service-active-row').style.display = svc ? 'block' : 'none';
  document.getElementById('service-modal-title').textContent = svc ? 'Edit Service' : 'Register Service';
  document.getElementById('service-modal-status').style.display = 'none';
  document.getElementById('serviceModal').classList.add('is-open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.is-open')
    .forEach(m => m.classList.remove('is-open'));
});

async function saveService() {
  const id     = document.getElementById('service-id').value;
  const slug   = document.getElementById('service-slug').value.trim();
  const name   = document.getElementById('service-name').value.trim();
  const desc   = document.getElementById('service-description').value.trim();
  const url    = document.getElementById('service-base-url').value.trim();
  const active = document.getElementById('service-active').checked;
  const status = document.getElementById('service-modal-status');
  const btn    = document.getElementById('service-save-btn');

  if (!id && !slug) {
    status.textContent = 'Slug is required.';
    status.style.color = 'var(--error)';
    status.style.display = 'block';
    return;
  }
  if (!name) {
    status.textContent = 'Display name is required.';
    status.style.color = 'var(--error)';
    status.style.display = 'block';
    return;
  }

  btn.disabled = true;
  status.style.display = 'none';

  const body = id
    ? { action: 'update', id: parseInt(id), display_name: name, description: desc, base_url: url, active: active }
    : { action: 'create', slug: slug, display_name: name, description: desc, base_url: url };

  try {
    const res  = await fetch('/federated-services.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    const data = await res.json();

    if (data.ok) {
      window.location.reload();
    } else {
      status.textContent = data.error || 'Save failed.';
      status.style.color = 'var(--error)';
      status.style.display = 'block';
      btn.disabled = false;
    }
  } catch (e) {
    status.textContent = 'Network error — please try again.';
    status.style.color = 'var(--error)';
    status.style.display = 'block';
    btn.disabled = false;
  }
}

async function deleteService(id, slug) {
  const confirmed = confirm(
    `Remove '${slug}' from the registry?\n\nExisting key grants for it are left as-is — this only removes it from the "grant access" list on key detail pages.`
  );
  if (!confirmed) return;

  try {
    const res = await fetch('/federated-services.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'delete', id: id, remove_grants: false }),
    });
    const data = await res.json();
    if (data.ok) {
      window.location.reload();
    } else {
      alert(data.error || 'Remove failed.');
    }
  } catch (e) {
    alert('Network error — please try again.');
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>