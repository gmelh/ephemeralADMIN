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

$page_title = 'Key Detail';

$key_id = (int)($_GET['id'] ?? 0);
if (!$key_id) {
    header('Location: /keys.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set-limits') {
        $rpm = strlen($_POST['rpm'] ?? '') ? (int)$_POST['rpm'] : null;
        $rph = strlen($_POST['rph'] ?? '') ? (int)$_POST['rph'] : null;
        $rpd = strlen($_POST['rpd'] ?? '') ? (int)$_POST['rpd'] : null;

        $result = my_api_post("/admin/keys/{$key_id}/limits", [
            'rate_per_minute' => $rpm,
            'rate_per_hour'   => $rph,
            'rate_per_day'    => $rpd,
        ]);

        flash_set($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Rate limits updated.' : ($result['data']['error'] ?? 'Update failed.'));
    }

    if ($action === 'set-output') {
        $json = trim($_POST['output_config'] ?? '');
        $cfg  = $json ? json_decode($json, true) : null;

        if ($json && $cfg === null) {
            flash_set('error', 'Invalid JSON in output config.');
        } else {
            $result = my_api_post("/admin/keys/{$key_id}/output", ['output_config' => $cfg]);
            flash_set($result['ok'] ? 'success' : 'error',
                $result['ok'] ? 'Output config updated.' : ($result['data']['error'] ?? 'Update failed.'));
        }
    }

    if ($action === 'rotate') {
        $result = my_api_post("/admin/keys/{$key_id}/rotate");
        if ($result['ok']) {
            $new_key = $result['data']['api_key'] ?? null;
            flash_set('success', 'Key rotated successfully.' . ($new_key ? ' New key shown below.' : ''));
        } else {
            flash_set('error', $result['data']['error'] ?? 'Rotation failed.');
        }
    }

    if ($action === 'grant-service') {
        // Checkboxes from the registered-services list on this page —
        // each checked value is already a known, registered slug.
        $services = array_values(array_filter(array_map('trim', $_POST['grant_slugs'] ?? [])));

        if (!$services) {
            flash_set('error', 'Select at least one service to grant.');
        } else {
            $result = my_api_post("/admin/keys/{$key_id}/services", ['services' => $services]);
            flash_set($result['ok'] ? 'success' : 'error', $result['ok']
                ? 'Granted access to: ' . implode(', ', $services) . '.'
                : ($result['data']['error'] ?? 'Grant failed.'));
        }
    }

    if ($action === 'revoke-service') {
        $service = trim($_POST['service'] ?? '');
        if ($service === '') {
            flash_set('error', 'Service name is required.');
        } else {
            $result = my_api_post("/admin/keys/{$key_id}/services/revoke", ['services' => [$service]]);
            flash_set($result['ok'] ? 'success' : 'error', $result['ok']
                ? "Revoked access to '{$service}'."
                : ($result['data']['error'] ?? 'Revoke failed.'));
        }
    }

    header("Location: /key-detail.php?id={$key_id}");
    exit;
}

// Fetch key details
$result   = my_api_get("/admin/keys/{$key_id}");
$key      = $result['ok'] ? ($result['data'] ?? []) : [];
$services = $key['services'] ?? [];

// Registered federated services this key doesn't already have — the
// checkbox list below only offers granting from the curated registry;
// free-text/ad-hoc grants are still possible via key_manager.py or the
// API directly, just not surfaced in the portal.
$registry_result     = my_api_get('/admin/federated-services?active=1');
$all_registered       = $registry_result['ok'] ? ($registry_result['data']['services'] ?? []) : [];
$available_services   = array_values(array_filter(
    $all_registered,
    fn($s) => !in_array($s['slug'], $services, true)
));

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= htmlspecialchars($key['identifier'] ?? 'Key #' . $key_id) ?>
    </h1>
    <p class="page-subtitle">
      <span class="<?= status_badge($key['active'] ? 'active' : 'disabled') ?>">
        <?= ($key['active'] ?? false) ? 'active' : 'disabled' ?>
      </span>
    </p>
  </div>
  <div class="actions">
    <a href="/keys.php" class="btn btn--ghost">← Back to Keys</a>
      <a href="/key-output.php?key_id=<?= $key_id ?>" class="btn btn--secondary">Output Config</a>
  </div>
</div>

<?php if (empty($key)): ?>
  <div class="alert alert--warning">
    Key #<?= $key_id ?> not found, or the <code>/admin/keys/{id}</code> endpoint is not yet implemented.
    Use <code>key_manager.py show</code> in the terminal.
  </div>
<?php else: ?>

  <div class="two-col">

    <!-- Key details -->
    <div class="card">
      <div class="card__head"><span class="card__title">Details</span></div>
      <div class="card__body">
        <dl class="detail-list">
          <dt>ID</dt>         <dd class="mono"><?= (int)($key['id'] ?? 0) ?></dd>
          <dt>Name</dt>       <dd><?= htmlspecialchars($key['name'] ?? '—') ?></dd>
          <dt>Identifier</dt> <dd><?= htmlspecialchars($key['identifier'] ?? '—') ?></dd>
          <dt>Admin</dt>      <dd><?= !empty($key['admin']) ? 'Yes' : 'No' ?></dd>
          <dt>Prefix</dt>     <dd class="mono"><?= htmlspecialchars($key['key_prefix'] ?? '—') ?></dd>
          <dt>Created</dt>    <dd><?= htmlspecialchars(substr($key['created_at'] ?? '', 0, 10)) ?></dd>
          <dt>Updated</dt>    <dd><?= htmlspecialchars(substr($key['updated_at'] ?? '', 0, 10)) ?></dd>
        </dl>
      </div>
    </div>

    <!-- Dangerous actions -->
    <div>
      <div class="card" style="margin-bottom:16px;">
        <div class="card__head"><span class="card__title">Rotate Key</span></div>
        <div class="card__body">
          <p style="font-size:13px; color:var(--ink-light); margin-bottom:14px;">
            Generate a new API key. The current key stops working immediately.
            The new key is shown once.
          </p>
          <form method="POST">
            <input type="hidden" name="action" value="rotate">
            <button type="submit" class="btn btn--secondary"
                    data-confirm="Rotate key for '<?= htmlspecialchars($key['identifier'] ?? '') ?>'? The current key will stop working.">
              Rotate Key
            </button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card__head"><span class="card__title">Enable / Disable</span></div>
        <div class="card__body">
          <?php if (!empty($key['active'])): ?>
            <p style="font-size:13px; color:var(--ink-light); margin-bottom:14px;">
              Disabling this key will immediately reject all requests using it.
            </p>
            <form method="POST" action="/keys.php">
              <input type="hidden" name="action"     value="disable">
              <input type="hidden" name="key_id"     value="<?= $key_id ?>">
              <input type="hidden" name="identifier" value="<?= htmlspecialchars($key['identifier'] ?? '') ?>">
              <button type="submit" class="btn btn--danger"
                      data-confirm="Disable key for '<?= htmlspecialchars($key['identifier'] ?? '') ?>'?">
                Disable Key
              </button>
            </form>
          <?php else: ?>
            <p style="font-size:13px; color:var(--ink-light); margin-bottom:14px;">
              Re-enabling this key will allow requests to be authenticated again.
            </p>
            <form method="POST" action="/keys.php">
              <input type="hidden" name="action"     value="enable">
              <input type="hidden" name="key_id"     value="<?= $key_id ?>">
              <input type="hidden" name="identifier" value="<?= htmlspecialchars($key['identifier'] ?? '') ?>">
              <button type="submit" class="btn btn--success">Enable Key</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /.two-col -->

  <hr class="divider">

  <!-- Rate limits -->
  <div class="card" style="max-width:520px;">
    <div class="card__head"><span class="card__title">Rate Limits</span></div>
    <div class="card__body">
      <p style="font-size:13px; color:var(--ink-light); margin-bottom:16px;">
        Leave blank to use the class default for this key type.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="set-limits">
        <div class="form-grid">
          <div class="form-group">
            <label for="rpm">Per minute</label>
            <input type="number" id="rpm" name="rpm" min="0"
                   value="<?= htmlspecialchars($key['rate_per_minute'] ?? '') ?>"
                   placeholder="class default">
          </div>
          <div class="form-group">
            <label for="rph">Per hour</label>
            <input type="number" id="rph" name="rph" min="0"
                   value="<?= htmlspecialchars($key['rate_per_hour'] ?? '') ?>"
                   placeholder="class default">
          </div>
          <div class="form-group">
            <label for="rpd">Per day</label>
            <input type="number" id="rpd" name="rpd" min="0"
                   value="<?= htmlspecialchars($key['rate_per_day'] ?? '') ?>"
                   placeholder="class default">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Save Limits</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Output config -->
  <div class="card" style="margin-top:16px; max-width:520px;">
    <div class="card__head"><span class="card__title">Output Configuration</span></div>
    <div class="card__body">
      <p style="font-size:13px; color:var(--ink-light); margin-bottom:16px;">
        JSON output config overrides for this key. Leave empty to use server defaults.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="set-output">
        <div class="form-group">
          <label for="output_config">Output config JSON</label>
          <textarea id="output_config" name="output_config" rows="8"
                    placeholder='{"heliocentric": false, "bodies": {"asteroids": false}}'
                    style="font-family:var(--font-mono); font-size:13px;"><?php
            $cfg = $key['output_config'] ?? null;
            if ($cfg) echo htmlspecialchars(json_encode($cfg, JSON_PRETTY_PRINT));
          ?></textarea>
          <span class="form-hint">Must be valid JSON or left empty.</span>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Save Config</button>
          <?php if (!empty($key['output_config'])): ?>
            <button type="submit" name="output_config" value=""
                    class="btn btn--ghost"
                    data-confirm="Clear output config and revert to server defaults?">
              Clear
            </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Federated service access -->
  <div class="card" style="margin-top:16px; max-width:520px;">
    <div class="card__head"><span class="card__title">Federated Service Access</span></div>
    <div class="card__body">
      <p style="font-size:13px; color:var(--ink-light); margin-bottom:16px;">
        Grant this key access to registered companion services that check
        ephemeral.rest's shared authentication data directly. Manage the
        list of available services on the
        <a href="/federated-services.php">Federated Services</a> page.
      </p>

      <?php if ($services): ?>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
          <?php foreach ($services as $svc): ?>
            <span class="badge badge--blue" style="display:inline-flex; align-items:center; gap:6px; padding:4px 6px 4px 10px;">
              <?= htmlspecialchars($svc) ?>
              <form method="POST" style="display:inline; line-height:0;">
                <input type="hidden" name="action"  value="revoke-service">
                <input type="hidden" name="service" value="<?= htmlspecialchars($svc) ?>">
                <button type="submit"
                        title="Revoke '<?= htmlspecialchars($svc) ?>'"
                        data-confirm="Revoke '<?= htmlspecialchars($svc) ?>' access for '<?= htmlspecialchars($key['identifier'] ?? '') ?>'?"
                        style="background:none; border:none; cursor:pointer; color:inherit; font-size:14px; line-height:1; padding:0;">
                  &times;
                </button>
              </form>
            </span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="font-size:13px; color:var(--ink-faint); margin-bottom:16px;">No service access granted.</p>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="grant-service">
        <?php if ($available_services): ?>
          <div class="form-group">
            <label>Grant access to</label>
            <div style="display:flex; flex-direction:column; gap:8px; margin-top:4px;">
              <?php foreach ($available_services as $svc): ?>
                <label style="display:flex; align-items:center; gap:8px; font-weight:400; font-size:13.5px;">
                  <input type="checkbox" name="grant_slugs[]" value="<?= htmlspecialchars($svc['slug']) ?>" style="width:auto;">
                  <span><strong><?= htmlspecialchars($svc['display_name']) ?></strong>
                    <span style="color:var(--ink-light);"> — <?= htmlspecialchars($svc['slug']) ?></span>
                    <?php if (!empty($svc['description'])): ?>
                      <br><span style="color:var(--ink-light); font-size:12.5px;"><?= htmlspecialchars($svc['description']) ?></span>
                    <?php endif; ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--sm">Grant Selected</button>
          </div>
        <?php else: ?>
          <p style="font-size:13px; color:var(--ink-faint);">
            <?= $all_registered
                ? 'This key already has access to every registered service.'
                : 'No services are registered yet.' ?>
            Manage the registry on the
            <a href="/federated-services.php">Federated Services</a> page.
          </p>
        <?php endif; ?>
      </form>
    </div>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>