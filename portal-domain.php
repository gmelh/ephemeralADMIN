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
auth_require();
if (!auth_is_domain()) { header('Location: /portal-user.php'); exit; }

$page_title = 'My Portal';
$user       = auth_user();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set-output') {
        $json = trim($_POST['output_config'] ?? '');
        $cfg  = $json ? json_decode($json, true) : null;
        if ($json && $cfg === null) {
            flash_set('error', 'Invalid JSON — output config not saved.');
        } else {
            $result = my_api_post('/me/output', ['output_config' => $cfg]);
            flash_set($result['ok'] ? 'success' : 'error',
                $result['ok'] ? 'Output config updated.' : ($result['data']['error'] ?? 'Update failed.'));
        }
    }

    if ($action === 'rotate') {
        $result = my_api_post('/me/rotate');
        if ($result['ok']) {
            $new_key = $result['data']['api_key'] ?? null;
            if ($new_key) {
                $_SESSION['_new_key'] = $new_key;
                auth_logout();
                header('Location: /portal-domain.php?rotated=1&key=' . urlencode($new_key));
            } else {
                flash_set('success', 'Key rotated. Check your email for the new key.');
            }
        } else {
            flash_set('error', $result['data']['error'] ?? 'Rotation failed.');
        }
    }

    header('Location: /portal-domain.php');
    exit;
}

// Fetch registration status
$regs_result = api_get('/admin/registrations');
$my_reg = null;
foreach ($regs_result['data']['requests'] ?? [] as $req) {
    if (strtolower($req['domain'] ?? '') === strtolower($user['identifier'] ?? '')) {
        $my_reg = $req;
        break;
    }
}

// Check for rotated key in URL (shown once)
$rotated_key = isset($_GET['rotated']) ? ($_GET['key'] ?? '') : '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($user['identifier'] ?? '') ?></h1>
    <p class="page-subtitle">Domain API key portal</p>
  </div>
  <a href="/key-output.php" class="btn btn--secondary">Output Config</a>
</div>

<?php if ($rotated_key): ?>
  <div class="card" style="margin-bottom:24px; border-color:var(--success);">
    <div class="card__head" style="background:var(--success-bg);">
      <span class="card__title" style="color:var(--success);">New API Key — Save This Now</span>
    </div>
    <div class="card__body">
      <p class="key-reveal__warning">⚠ This key will not be shown again. Copy it and store it securely.</p>
      <div class="key-reveal">
        <?= htmlspecialchars($rotated_key) ?>
        <button class="btn btn--ghost btn--sm" style="margin-top:12px;"
                data-copy="<?= htmlspecialchars($rotated_key) ?>">Copy</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="two-col">
  <!-- Key details -->
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Key Details</span></div>
      <div class="card__body">
        <dl class="detail-list">
          <dt>Type</dt>     <dd><span class="badge badge--blue">domain</span></dd>
          <dt>Status</dt>   <dd><span class="<?= status_badge($user['active'] ? 'active' : 'disabled') ?>"><?= $user['active'] ? 'active' : 'disabled' ?></span></dd>
          <dt>Name</dt>     <dd><?= htmlspecialchars($user['name'] ?? '—') ?></dd>
          <dt>Domain</dt>   <dd><?= htmlspecialchars($user['identifier'] ?? '—') ?></dd>
          <dt>Rate / min</dt><dd class="mono"><?= $user['rate_limits']['per_minute'] ?? 'default' ?></dd>
          <dt>Rate / hr</dt> <dd class="mono"><?= $user['rate_limits']['per_hour']   ?? 'default' ?></dd>
          <dt>Rate / day</dt><dd class="mono"><?= $user['rate_limits']['per_day']    ?? 'default' ?></dd>
        </dl>
      </div>
    </div>

    <!-- Registration status -->
    <?php if ($my_reg): ?>
      <div class="card">
        <div class="card__head"><span class="card__title">Registration Status</span></div>
        <div class="card__body">
          <dl class="detail-list">
            <dt>Status</dt>     <dd><span class="<?= status_badge($my_reg['status']) ?>"><?= htmlspecialchars($my_reg['status']) ?></span></dd>
            <dt>Submitted</dt>  <dd><?= htmlspecialchars(substr($my_reg['created_at'] ?? '', 0, 10)) ?></dd>
            <?php if (!empty($my_reg['admin_note'])): ?>
              <dt>Note</dt><dd><?= htmlspecialchars($my_reg['admin_note']) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Rotate Key</span></div>
      <div class="card__body">
        <p style="font-size:13.5px;color:var(--ink-light);margin-bottom:16px;">
          Generate a new API key. Your current key stops working immediately.
          The new key is shown once on this page.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="rotate">
          <button type="submit" class="btn btn--secondary"
                  data-confirm="Rotate your API key? Your current key will stop working immediately.">
            Rotate My Key
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><span class="card__title">Output Configuration</span></div>
      <div class="card__body">
        <p style="font-size:13.5px;color:var(--ink-light);margin-bottom:16px;">
          Override server defaults for your key. Leave empty to use server defaults.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="set-output">
          <div class="form-group">
            <label for="output_config">Config JSON <span class="label-hint">optional</span></label>
            <textarea id="output_config" name="output_config" rows="7"
                      style="font-family:var(--font-mono);font-size:12.5px;"
                      placeholder='{"heliocentric": false, "bodies": {"asteroids": false}}'><?php
              $cfg = $user['output'] ?? null;
              if ($cfg) echo htmlspecialchars(json_encode($cfg, JSON_PRETTY_PRINT));
            ?></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Save Config</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
