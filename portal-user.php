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
auth_require();
if (!auth_is_user() && !auth_is_admin()) { header('Location: /portal-domain.php'); exit; }

$page_title = 'My Portal';
$user       = auth_user();

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
                auth_logout();
                header('Location: /portal-user.php?rotated=1&key=' . urlencode($new_key));
            } else {
                flash_set('success', 'Key rotated. Check your email for the new key.');
            }
        } else {
            flash_set('error', $result['data']['error'] ?? 'Rotation failed.');
        }
    }

    header('Location: /portal-user.php');
    exit;
}

$rotated_key = isset($_GET['rotated']) ? ($_GET['key'] ?? '') : '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($user['name'] ?? '') ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($user['identifier'] ?? '') ?></p>
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
  <div class="card">
    <div class="card__head"><span class="card__title">Account</span></div>
    <div class="card__body">
      <dl class="detail-list">
        <dt>Name</dt>       <dd><?= htmlspecialchars($user['name'] ?? '—') ?></dd>
        <dt>Email</dt>      <dd><?= htmlspecialchars($user['identifier'] ?? '—') ?></dd>
        <dt>Type</dt>       <dd><span class="badge badge--purple">user</span></dd>
        <dt>Status</dt>     <dd><span class="<?= status_badge($user['active'] ? 'active' : 'disabled') ?>"><?= $user['active'] ? 'active' : 'disabled' ?></span></dd>
        <dt>Rate / min</dt> <dd class="mono"><?= $user['rate_limits']['per_minute'] ?? 'default' ?></dd>
        <dt>Rate / hr</dt>  <dd class="mono"><?= $user['rate_limits']['per_hour']   ?? 'default' ?></dd>
        <dt>Rate / day</dt> <dd class="mono"><?= $user['rate_limits']['per_day']    ?? 'default' ?></dd>
      </dl>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Rotate Key</span></div>
      <div class="card__body">
        <p style="font-size:13.5px;color:var(--ink-light);margin-bottom:16px;">
          Generate a new API key. Your current key stops working immediately.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="rotate">
          <button type="submit" class="btn btn--secondary"
                  data-confirm="Rotate your key? Your current key will stop working immediately.">
            Rotate My Key
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><span class="card__title">Output Configuration</span></div>
      <div class="card__body">
        <p style="font-size:13.5px;color:var(--ink-light);margin-bottom:16px;">
          Customise which bodies and fields are returned for your key.
        </p>
        <form method="POST">
          <input type="hidden" name="action" value="set-output">
          <div class="form-group">
            <label for="output_config">Config JSON <span class="label-hint">optional</span></label>
            <textarea id="output_config" name="output_config" rows="7"
                      style="font-family:var(--font-mono);font-size:12.5px;"
                      placeholder='{"heliocentric": false}'><?php
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
