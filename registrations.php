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

$page_title = 'Registrations';


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
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $action     = $input['action']     ?? '';
    $request_id = (int)($input['request_id'] ?? 0);
    $admin_note = trim($input['admin_note'] ?? '');

    if (!$request_id) {
        echo json_encode(['ok' => false, 'error' => 'Missing request ID']); exit;
    }

    if (in_array($action, ['approve', 'reject'])) {
        $result = my_api_post("/admin/registrations/{$request_id}/{$action}", [
            'admin_note' => $admin_note ?: null,
        ]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => ucfirst($action) . 'd registration #' . $request_id, 'status' => $action === 'approve' ? 'approved' : 'rejected']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}

// Handle regular POST (quick approve/reject from table buttons)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']     ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($request_id && in_array($action, ['approve', 'reject'])) {
        $result = my_api_post("/admin/registrations/{$request_id}/{$action}", [
            'admin_note' => $admin_note ?: null,
        ]);
        flash_set($result['ok'] ? 'success' : 'error',
            $result['ok']
                ? ucfirst($action) . 'd registration #' . $request_id . ' successfully.'
                : ($result['data']['error'] ?? 'Action failed.'));
    }
    header('Location: /registrations.php?status=' . ($_GET['status'] ?? 'all'));
    exit;
}

// Fetch all registrations
$result   = my_api_get('/admin/registrations');
$all_reqs = $result['data']['requests'] ?? [];

$status_filter = $_GET['status'] ?? 'all';
$requests = $status_filter !== 'all'
    ? array_values(array_filter($all_reqs, fn($r) => ($r['status'] ?? '') === $status_filter))
    : $all_reqs;
$total = count($requests);

$status_tabs = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Registrations</h1>
    <p class="page-subtitle">API key registration requests</p>
  </div>
  <a href="/register-key.php" class="btn btn--secondary">+ New Registration</a>
</div>

<div class="tabs">
  <?php foreach ($status_tabs as $val => $label): ?>
    <a href="?status=<?= $val ?>" class="tab <?= $status_filter === $val ? 'tab--active' : '' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$result['ok']): ?>
  <div class="alert alert--warning">
    Could not load registrations — API returned status <?= $result['status'] ?>.
    <?= htmlspecialchars($result['data']['error'] ?? '') ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <span class="card__title"><?= $status_tabs[$status_filter] ?? 'All' ?> Requests</span>
    <span class="count-label"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Domain</th>
          <th>Contact</th>
          <th>Name</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($requests)): ?>
          <tr class="empty-row"><td colspan="7">No requests found.</td></tr>
        <?php else: ?>
          <?php foreach ($requests as $req): ?>
            <tr id="reg-row-<?= (int)$req['id'] ?>">
              <td class="mono" style="color:var(--ink-light)"><?= (int)$req['id'] ?></td>
              <td><strong><?= htmlspecialchars($req['domain'] ?? '—') ?></strong></td>
              <td>
                <a href="mailto:<?= htmlspecialchars($req['contact_email'] ?? '') ?>">
                  <?= htmlspecialchars($req['contact_email'] ?? '—') ?>
                </a>
              </td>
              <td><?= htmlspecialchars($req['name'] ?? '—') ?></td>
              <td>
                <span class="<?= status_badge($req['status'] ?? '') ?>" id="reg-status-<?= (int)$req['id'] ?>">
                  <?= htmlspecialchars($req['status'] ?? '') ?>
                </span>
              </td>
              <td style="font-size:13px; color:var(--ink-light); white-space:nowrap;">
                <?= htmlspecialchars(substr($req['created_at'] ?? '', 0, 10)) ?>
              </td>
              <td>
                <div class="actions">
                  <button class="btn btn--ghost btn--sm"
                    onclick="openRegModal(
                      <?= (int)$req['id'] ?>,
                      <?= htmlspecialchars(json_encode($req['domain']        ?? ''), ENT_QUOTES) ?>,
                      <?= htmlspecialchars(json_encode($req['name']          ?? ''), ENT_QUOTES) ?>,
                      <?= htmlspecialchars(json_encode($req['contact_email'] ?? ''), ENT_QUOTES) ?>,
                      <?= htmlspecialchars(json_encode($req['status']        ?? ''), ENT_QUOTES) ?>,
                      <?= htmlspecialchars(json_encode($req['admin_note']    ?? ''), ENT_QUOTES) ?>,
                      <?= htmlspecialchars(json_encode($req['reason']        ?? ''), ENT_QUOTES) ?>
                    )">Edit</button>
                  <?php if (($req['status'] ?? '') === 'pending'): ?>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action"     value="approve">
                      <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                      <button type="submit" class="btn btn--success btn--sm"
                              data-confirm="Approve '<?= htmlspecialchars($req['domain'] ?? '') ?>'?">
                        Approve
                      </button>
                    </form>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action"     value="reject">
                      <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                      <button type="submit" class="btn btn--danger btn--sm"
                              data-confirm="Reject '<?= htmlspecialchars($req['domain'] ?? '') ?>'?">
                        Reject
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit / Review modal -->
<div class="modal-overlay" id="regModal">
  <div class="modal">
    <div class="modal__head">
      <span class="modal__title" id="reg-modal-title">Registration</span>
      <button class="modal__close" onclick="closeModal('regModal')">×</button>
    </div>
    <div class="modal__body">
      <input type="hidden" id="reg-id">

      <div class="detail-list" id="reg-detail" style="margin-bottom:20px;">
        <dt>Domain</dt>  <dd id="reg-detail-domain"></dd>
        <dt>Contact</dt> <dd id="reg-detail-email"></dd>
        <dt>Reason</dt>  <dd id="reg-detail-reason" style="color:var(--ink-light);font-size:13px;"></dd>
      </div>

      <div class="form-grid form-grid--full" style="gap:14px;">
        <div class="form-group">
          <label for="reg-name">Contact Name</label>
          <input type="text" id="reg-name">
        </div>
        <div class="form-group">
          <label for="reg-email-input">Contact Email</label>
          <input type="email" id="reg-email-input">
        </div>
        <div class="form-group">
          <label for="reg-admin-note">Admin Note <span class="label-hint">included in approval/rejection email</span></label>
          <textarea id="reg-admin-note" rows="3" placeholder="Optional message to the registrant…"></textarea>
        </div>
      </div>
      <p id="reg-modal-error" style="color:var(--error);font-size:13px;margin-top:10px;display:none;"></p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--success" onclick="submitReg('approve')" id="btn-approve">Approve</button>
      <button class="btn btn--danger"  onclick="submitReg('reject')"  id="btn-reject">Reject</button>
      <button class="btn btn--ghost"   onclick="closeModal('regModal')">Cancel</button>
      <span id="reg-saving" style="font-size:13px;color:var(--ink-faint);display:none;margin-left:auto;">Saving…</span>
    </div>
  </div>
</div>

<script>
const statusBadgeClass = {
  pending:  'badge badge--warning',
  approved: 'badge badge--success',
  rejected: 'badge badge--error',
};

function openRegModal(id, domain, name, email, status, adminNote, reason) {
  document.getElementById('reg-id').value               = id;
  document.getElementById('reg-modal-title').textContent = 'Registration #' + id + ' — ' + domain;
  document.getElementById('reg-detail-domain').textContent = domain;
  document.getElementById('reg-detail-email').textContent  = email;
  document.getElementById('reg-detail-reason').textContent = reason || '—';
  document.getElementById('reg-name').value              = name;
  document.getElementById('reg-email-input').value       = email;
  document.getElementById('reg-admin-note').value        = adminNote || '';
  document.getElementById('reg-modal-error').style.display = 'none';

  const isPending = status === 'pending';
  document.getElementById('btn-approve').style.display = isPending ? 'inline-flex' : 'none';
  document.getElementById('btn-reject').style.display  = isPending ? 'inline-flex' : 'none';

  document.getElementById('regModal').classList.add('is-open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
}

async function submitReg(action) {
  const id        = document.getElementById('reg-id').value;
  const adminNote = document.getElementById('reg-admin-note').value;
  const errEl     = document.getElementById('reg-modal-error');
  const saving    = document.getElementById('reg-saving');

  errEl.style.display  = 'none';
  saving.style.display = 'inline';
  document.getElementById('btn-approve').disabled = true;
  document.getElementById('btn-reject').disabled  = true;

  try {
    const res  = await fetch('/registrations.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action, request_id: parseInt(id), admin_note: adminNote })
    });
    const data = await res.json();
    if (!data.status) data.status = res.status;

    if (data.ok) {
      // Update status badge in table
      const badge = document.getElementById('reg-status-' + id);
      if (badge) {
        badge.className   = statusBadgeClass[data.status] || 'badge';
        badge.textContent = data.status;
      }
      closeModal('regModal');
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
    document.getElementById('btn-approve').disabled = false;
    document.getElementById('btn-reject').disabled  = false;
  }
}

document.getElementById('regModal').addEventListener('click', e => {
  if (e.target === document.getElementById('regModal')) closeModal('regModal');
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