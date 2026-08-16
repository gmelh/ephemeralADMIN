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
$cfg        = public_portal_config();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Portal Settings</h1>
    <p class="page-subtitle">Current configuration — read-only</p>
  </div>
</div>

<div class="alert alert--info" style="max-width:720px; margin-bottom:24px;">
  These are set via config files, not through the portal — deployment-time
  values and security locks aren't things anyone with portal access should
  be able to change through the portal itself. To change any of these,
  edit <code>ephemeralADMIN/config.php</code> or ephemeralREST's
  <code>.env</code> (noted per row below) and restart the relevant service.
</div>

<!-- Appearance -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">Appearance</span></div>
  <div class="card__body">
    <dl class="settings-list">
      <dt>Site Name</dt>
      <dd><?= htmlspecialchars($cfg['site_name']) ?>
        <span class="hint">ephemeralREST's <code>.env</code> — <code>SITE_NAME</code></span></dd>

      <dt>Version</dt>
      <dd><?= htmlspecialchars(SITE_VERSION) ?>
        <span class="hint"><code>ephemeralADMIN/config.php</code> — <code>SITE_VERSION</code></span></dd>
    </dl>
  </div>
</div>

<!-- Session & Security -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">Session &amp; Security</span></div>
  <div class="card__body">
    <dl class="settings-list">
      <dt>Session Timeout</dt>
      <dd><?= (int)SESSION_TIMEOUT ?> seconds (<?= round(SESSION_TIMEOUT / 60) ?> min)
        <span class="hint"><code>ephemeralADMIN/config.php</code> — <code>SESSION_TIMEOUT</code></span></dd>

      <dt>Trusted Device Days</dt>
      <dd><?= (int)$cfg['trusted_device_days'] ?> days
        <span class="hint">ephemeralREST's <code>.env</code> — <code>TRUSTED_DEVICE_DAYS</code>. Governs both the portal's "remember this device" cookie and the API's own token expiry — a single value, not two that can drift.</span></dd>

      <dt>Allow Admin Promotion</dt>
      <dd>
        <?php $portal_allows = ALLOW_ADMIN_PROMOTION; $api_allows = (bool)$cfg['allow_admin_promotion']; ?>
        <span class="<?= status_badge($portal_allows ? 'active' : 'disabled') ?>">portal: <?= $portal_allows ? 'on' : 'off' ?></span>
        <span class="<?= status_badge($api_allows ? 'active' : 'disabled') ?>">API: <?= $api_allows ? 'on' : 'off' ?></span>
        <?php if ($portal_allows !== $api_allows): ?>
          <span class="hint" style="color:var(--warning);">
            ⚠ These disagree — the portal UI and the API's own enforcement should normally match.
            Portal: <code>ephemeralADMIN/config.php</code>'s <code>ALLOW_ADMIN_PROMOTION</code>.
            API: ephemeralREST's <code>.env</code>'s <code>ALLOW_ADMIN_PROMOTION</code>.
          </span>
        <?php else: ?>
          <span class="hint">
            The portal only controls whether the Keys page offers the option; the API independently
            enforces the same lock regardless of whether the portal UI is bypassed.
            Portal: <code>ephemeralADMIN/config.php</code>. API: ephemeralREST's <code>.env</code>.
          </span>
        <?php endif; ?>
      </dd>
    </dl>
  </div>
</div>

<!-- URLs -->
<div class="card" style="margin-bottom:20px;">
  <div class="card__head"><span class="card__title">URLs</span></div>
  <div class="card__body">
    <dl class="settings-list">
      <dt>Portal URL</dt>
      <dd><?= $cfg['portal_url'] !== '' ? htmlspecialchars($cfg['portal_url']) : '<span class="hint" style="color:var(--warning);">not set — password-reset emails will link to the API instead of the portal</span>' ?>
        <span class="hint">ephemeralREST's <code>.env</code> — <code>PORTAL_URL</code></span></dd>

      <dt>Logout Redirect URL</dt>
      <dd><?= htmlspecialchars(LOGOUT_REDIRECT_URL) ?>
        <span class="hint"><code>ephemeralADMIN/config.php</code> — <code>LOGOUT_REDIRECT_URL</code></span></dd>
    </dl>
  </div>
</div>

<style>
.settings-list { display: grid; grid-template-columns: 200px 1fr; gap: 14px 20px; }
.settings-list dt { font-weight: 600; color: var(--ink-light); font-size: 13px; padding-top: 2px; }
.settings-list dd { font-size: 14px; }
.settings-list .hint { display: block; font-size: 12px; color: var(--ink-faint); margin-top: 4px; }
.settings-list .hint code { font-size: 11.5px; }
@media (max-width: 640px) {
  .settings-list { grid-template-columns: 1fr; gap: 4px; }
  .settings-list dt { padding-top: 10px; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>