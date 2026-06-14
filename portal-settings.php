<?php
/**
 * ephemeralADMIN — Administration portal for ephemeralREST
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * MIT License — see LICENSE for full text.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
auth_require('admin');

$page_title = 'Portal Settings';

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $result = api_post('/admin/portal-settings', $input);

    if ($result['ok']) {
        // Bust the session settings cache so changes take effect immediately
        unset($_SESSION['portal_settings']);
        echo json_encode(['ok' => true, 'message' => 'Settings saved.']);
    } else {
        echo json_encode(['ok' => false, 'error' => $result['data']['error'] ?? 'Save failed.']);
    }
    exit;
}

// Handle single setting reset (DELETE-style via POST with _method)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_key'])) {
    $key    = preg_replace('/[^a-z_]/', '', $_POST['reset_key']);
    $result = api_request('DELETE', "/admin/portal-settings/{$key}", null);
    if ($result['ok']) {
        unset($_SESSION['portal_settings']);
        flash_set('success', "Setting '{$key}' reset to default.");
    } else {
        flash_set('error', $result['data']['error'] ?? 'Reset failed.');
    }
    header('Location: /portal-settings.php');
    exit;
}

$r        = api_get('/admin/portal-settings');
$settings = $r['ok'] ? ($r['data']['settings'] ?? []) : [];
$defaults = $r['ok'] ? ($r['data']['defaults'] ?? []) : [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Portal Settings</h1>
    <p class="page-subtitle">Configure the behaviour of this admin portal. Changes take effect immediately.</p>
  </div>
</div>

<p id="save-status" style="display:none; font-size:13px; margin-bottom:16px;"></p>

<!-- Appearance -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">Appearance</span></div>
  <div class="card__body">
    <div class="form-grid">
      <div class="form-group">
        <label for="site_name">Site Name</label>
        <input type="text" id="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'ephemeralREST') ?>"
               placeholder="ephemeralREST">
        <span class="hint">Shown in the browser title and portal header. Default: <code>ephemeralREST</code></span>
      </div>
      <div class="form-group">
        <label for="site_version">Version</label>
        <input type="text" id="site_version" value="<?= htmlspecialchars($settings['site_version'] ?? '1.0') ?>"
               placeholder="1.0">
        <span class="hint">Shown in the sidebar footer. Default: <code>1.0</code></span>
      </div>
    </div>
  </div>
</div>

<!-- Session & Security -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">Session &amp; Security</span></div>
  <div class="card__body">
    <div class="form-grid">
      <div class="form-group">
        <label for="session_timeout">Session Timeout <span class="label-hint">seconds</span></label>
        <input type="number" id="session_timeout"
               value="<?= (int)($settings['session_timeout'] ?? 1800) ?>"
               min="60" max="86400">
        <span class="hint">How long before an inactive session is expired. Default: <code>1800</code> (30 min)</span>
      </div>
      <div class="form-group">
        <label for="trusted_device_days">Trusted Device Days</label>
        <input type="number" id="trusted_device_days"
               value="<?= (int)($settings['trusted_device_days'] ?? 28) ?>"
               min="1" max="365">
        <span class="hint">How long a "remember this device" cookie skips 2FA. Default: <code>28</code></span>
      </div>
    </div>
    <div class="form-group" style="margin-top:16px;">
      <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:400;">
        <input type="checkbox" id="allow_admin_promotion"
               <?= !empty($settings['allow_admin_promotion']) ? 'checked' : '' ?>
               style="width:16px; height:16px; accent-color:var(--accent);">
        <span>Allow admin promotion via the portal</span>
      </label>
      <span class="hint" style="margin-top:6px; display:block;">
        When off, no admin can grant or revoke admin status for other keys through this portal.
        Useful once your admin accounts are established. Default: <strong>on</strong>
      </span>
    </div>
  </div>
</div>

<!-- URLs -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">URLs</span></div>
  <div class="card__body">
    <div class="form-grid">
      <div class="form-group">
        <label for="portal_url">Portal URL <span class="label-hint">used in password-reset emails</span></label>
        <input type="url" id="portal_url"
               value="<?= htmlspecialchars($settings['portal_url'] ?? '') ?>"
               placeholder="https://admin.example.com">
        <span class="hint">The public URL of this portal. Set-password and password-reset email links point here.</span>
      </div>
      <div class="form-group">
        <label for="logout_redirect_url">Logout Redirect URL</label>
        <input type="text" id="logout_redirect_url"
               value="<?= htmlspecialchars($settings['logout_redirect_url'] ?? '/login.php') ?>"
               placeholder="/login.php">
        <span class="hint">Where to redirect after signing out. Default: <code>/login.php</code></span>
      </div>
    </div>
  </div>
</div>

<div class="form-actions">
  <button class="btn btn--primary" onclick="saveSettings()">Save Settings</button>
</div>

<script>
async function saveSettings() {
  const status = document.getElementById('save-status');
  status.style.display = 'none';

  const payload = {
    site_name:             document.getElementById('site_name').value.trim(),
    site_version:          document.getElementById('site_version').value.trim(),
    session_timeout:       parseInt(document.getElementById('session_timeout').value),
    trusted_device_days:   parseInt(document.getElementById('trusted_device_days').value),
    allow_admin_promotion: document.getElementById('allow_admin_promotion').checked,
    portal_url:            document.getElementById('portal_url').value.trim(),
    logout_redirect_url:   document.getElementById('logout_redirect_url').value.trim(),
  };

  try {
    const res  = await fetch('/portal-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    status.style.display = 'block';
    status.textContent   = data.ok ? '✓ ' + data.message : (data.error || 'Save failed.');
    status.style.color   = data.ok ? 'var(--success)' : 'var(--error)';
  } catch(e) {
    status.textContent   = 'Network error — please try again.';
    status.style.color   = 'var(--error)';
    status.style.display = 'block';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
