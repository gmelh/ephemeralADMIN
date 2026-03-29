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

$page_title = 'SMTP Settings';

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'save';

    if ($action === 'save') {
        // Don't send blank password — omit so existing stored value is preserved
        if (isset($input['password']) && $input['password'] === '') {
            unset($input['password']);
        }
        unset($input['action']);
        $result = my_api_post('/admin/smtp', $input);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'SMTP settings saved.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'test') {
        $to     = trim($input['to'] ?? '');
        $result = my_api_post('/admin/smtp/test', ['to' => $to]);
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Test email sent to ' . htmlspecialchars($to) . '.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    if ($action === 'clear') {
        $result = my_api_request('DELETE', '/admin/smtp');
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'SMTP configuration cleared.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

function extractApiError(array $result): string {
    $d = $result['data'] ?? [];
    $msg = $d['error'] ?? $d['message'] ?? null;
    if (!$msg) $msg = 'HTTP ' . ($result['status'] ?? '?');
    if (($result['status'] ?? 0) === 429) $msg = 'Rate limit exceeded (429).';
    return $msg;
}

// Need a DELETE method wrapper
function my_api_request(string $method, string $endpoint): array {
    $url     = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json', 'X-API-Key: ' . auth_key()];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => json_decode($raw, true) ?? []];
}

// Fetch current config
$result  = my_api_get('/admin/smtp');
$cfg     = $result['ok'] ? ($result['data']['config'] ?? []) : [];
$configured = $result['ok'] ? ($result['data']['configured'] ?? false) : false;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">SMTP Settings</h1>
    <p class="page-subtitle">Configure the outgoing mail server for all transactional email</p>
  </div>
  <div style="display:flex; align-items:center; gap:10px;">
    <span class="health-dot health-dot--<?= $configured ? 'ok' : 'warning' ?>"></span>
    <span style="font-size:13px; color:var(--ink-light);">
      <?= $configured ? 'Configured' : 'Not configured' ?>
    </span>
  </div>
</div>

<?php if (!$configured): ?>
  <div class="alert alert--warning" style="max-width:720px; margin-bottom:24px;">
    SMTP is not yet configured. Email sending is disabled — registration approvals and
    verification emails will be logged but not delivered until settings are saved.
  </div>
<?php endif; ?>

<div class="two-col" style="align-items:start;">

  <!-- Settings form -->
  <div class="card">
    <div class="card__head">
      <span class="card__title">Connection Settings</span>
    </div>
    <div class="card__body">

      <div class="form-grid" style="gap:16px;" id="smtp-form">

        <div class="form-group">
          <label for="smtp-host">SMTP Host</label>
          <input type="text" id="smtp-host" placeholder="smtp.example.com"
                 value="<?= htmlspecialchars($cfg['host'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="smtp-port">Port</label>
          <input type="number" id="smtp-port" placeholder="587"
                 value="<?= htmlspecialchars($cfg['port'] ?? '587') ?>">
        </div>

        <div class="form-group">
          <label for="smtp-user">Username</label>
          <input type="text" id="smtp-user" placeholder="user@example.com"
                 autocomplete="off"
                 value="<?= htmlspecialchars($cfg['user'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="smtp-password">
            Password
            <?php if (!empty($cfg['password_set'])): ?>
              <span class="label-hint">— currently set, leave blank to keep</span>
            <?php endif; ?>
          </label>
          <input type="password" id="smtp-password" placeholder="<?= !empty($cfg['password_set']) ? '••••••••' : 'Enter password' ?>"
                 autocomplete="new-password">
        </div>

        <div class="form-group">
          <label for="smtp-from">From Address <span class="label-hint">optional — defaults to username</span></label>
          <input type="text" id="smtp-from"
                 placeholder="ephemeralREST &lt;no-reply@example.com&gt;"
                 value="<?= htmlspecialchars($cfg['from_addr'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="smtp-admin">Admin Email <span class="label-hint">receives new registration notifications</span></label>
          <input type="email" id="smtp-admin" placeholder="admin@example.com"
                 value="<?= htmlspecialchars($cfg['admin_email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="smtp-base-url">API Base URL <span class="label-hint">used in email links</span></label>
          <input type="url" id="smtp-base-url" placeholder="https://api.example.com"
                 value="<?= htmlspecialchars($cfg['base_url'] ?? 'http://localhost:5000') ?>">
        </div>

        <!-- TLS / SSL toggles -->
        <div class="form-group" style="grid-column:1/-1;">
          <label style="margin-bottom:10px; display:block;">Encryption</label>
          <div style="display:flex; gap:24px; flex-wrap:wrap;">
            <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer;">
              <input type="radio" name="enc" id="enc-tls" value="tls"
                <?= (!empty($cfg['use_tls']) && $cfg['use_tls'] !== 'false' && empty($cfg['use_ssl'])) || (!isset($cfg['use_tls']) && !isset($cfg['use_ssl'])) ? 'checked' : '' ?>>
              STARTTLS <span class="label-hint">— port 587 (recommended)</span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer;">
              <input type="radio" name="enc" id="enc-ssl" value="ssl"
                <?= (!empty($cfg['use_ssl']) && $cfg['use_ssl'] !== 'false') ? 'checked' : '' ?>>
              SSL/TLS <span class="label-hint">— port 465</span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer;">
              <input type="radio" name="enc" id="enc-none" value="none"
                <?= (isset($cfg['use_tls']) && $cfg['use_tls'] === 'false' && (empty($cfg['use_ssl']) || $cfg['use_ssl'] === 'false')) ? 'checked' : '' ?>>
              None <span class="label-hint">— not recommended</span>
            </label>
          </div>
        </div>

      </div>

      <p id="form-error"   style="color:var(--error);font-size:13px;margin-top:12px;display:none;"></p>
      <p id="form-success" style="color:var(--success);font-size:13px;margin-top:12px;display:none;"></p>

      <div class="form-actions">
        <button class="btn btn--primary" onclick="saveSmtp()" id="btn-save">Save Settings</button>
        <button class="btn btn--ghost"   onclick="clearSmtp()"
                data-confirm="Clear all SMTP settings from the database?">Clear</button>
        <span id="form-saving" style="font-size:13px;color:var(--ink-faint);display:none;">Saving…</span>
      </div>

    </div>
  </div>

  <!-- Right column: test + common providers -->
  <div>

    <!-- Test email -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Send Test Email</span></div>
      <div class="card__body">
        <p style="font-size:13.5px; color:var(--ink-light); margin-bottom:14px;">
          Send a test message to verify the connection is working.
          Settings must be saved before testing.
        </p>
        <div class="form-group" style="margin-bottom:14px;">
          <label for="test-to">Send test to</label>
          <input type="email" id="test-to"
                 placeholder="your@email.com"
                 value="<?= htmlspecialchars($cfg['admin_email'] ?? '') ?>">
        </div>
        <p id="test-error"   style="color:var(--error);font-size:13px;margin-bottom:10px;display:none;"></p>
        <p id="test-success" style="color:var(--success);font-size:13px;margin-bottom:10px;display:none;"></p>
        <button class="btn btn--secondary" onclick="testSmtp()" id="btn-test">
          Send Test Email
        </button>
      </div>
    </div>

    <!-- Common provider reference -->
    <div class="card">
      <div class="card__head"><span class="card__title">Common Provider Settings</span></div>
      <div class="card__body">
        <?php
        $providers = [
            ['Gmail',     'smtp.gmail.com',       587, 'STARTTLS', 'Use an App Password, not your account password.'],
            ['Outlook',   'smtp.office365.com',   587, 'STARTTLS', 'Use your full email address as username.'],
            ['Mailgun',   'smtp.mailgun.org',      587, 'STARTTLS', 'Use your Mailgun SMTP credentials from the dashboard.'],
            ['Postmark',  'smtp.postmarkapp.com',  587, 'STARTTLS', 'Use your Server API Token as both username and password.'],
            ['SendGrid',  'smtp.sendgrid.net',     587, 'STARTTLS', "Username is always 'apikey', password is your API key."],
            ['Amazon SES','email-smtp.us-east-1.amazonaws.com', 587, 'STARTTLS', 'Generate SMTP credentials from the SES console.'],
        ];
        ?>
        <table>
          <thead>
            <tr>
              <th>Provider</th>
              <th>Host</th>
              <th>Port</th>
              <th>Enc</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($providers as [$name, $host, $port, $enc, $note]): ?>
              <tr>
                <td style="white-space:nowrap; font-weight:500;"><?= $name ?></td>
                <td class="mono" style="font-size:12px; color:var(--ink-light);">
                  <button class="btn btn--ghost btn--sm"
                          style="font-family:var(--font-mono);font-size:11px;padding:2px 8px;"
                          onclick="fillProvider('<?= $host ?>', <?= $port ?>, '<?= strtolower($enc) === 'starttls' ? 'tls' : 'ssl' ?>')">
                    <?= $host ?>
                  </button>
                </td>
                <td class="mono"><?= $port ?></td>
                <td style="font-size:12px; color:var(--ink-light);"><?= $enc ?></td>
              </tr>
              <tr>
                <td colspan="4" style="font-size:11.5px; color:var(--ink-light); padding-top:0; padding-bottom:10px;">
                  <?= $note ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
function getFormValues() {
  const enc = document.querySelector('input[name="enc"]:checked')?.value ?? 'tls';
  return {
    host:        document.getElementById('smtp-host').value.trim(),
    port:        document.getElementById('smtp-port').value.trim(),
    user:        document.getElementById('smtp-user').value.trim(),
    password:    document.getElementById('smtp-password').value,
    from_addr:   document.getElementById('smtp-from').value.trim(),
    admin_email: document.getElementById('smtp-admin').value.trim(),
    base_url:    document.getElementById('smtp-base-url').value.trim(),
    use_tls:     enc === 'tls'  ? 'true' : 'false',
    use_ssl:     enc === 'ssl'  ? 'true' : 'false',
  };
}

async function saveSmtp() {
  const errEl  = document.getElementById('form-error');
  const succEl = document.getElementById('form-success');
  const saving = document.getElementById('form-saving');
  const saveBtn = document.getElementById('btn-save');

  errEl.style.display = succEl.style.display = 'none';
  saving.style.display = 'inline';
  saveBtn.disabled = true;

  try {
    const payload = { action: 'save', ...getFormValues() };
    // Don't send empty password — server keeps existing
    if (!payload.password) delete payload.password;

    const res  = await fetch('/smtp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    data.status = data.status ?? res.status;

    if (data.ok) {
      succEl.textContent   = data.message;
      succEl.style.display = 'block';
      // Clear password field so it shows placeholder again
      document.getElementById('smtp-password').value = '';
      document.getElementById('smtp-password').placeholder = '••••••••';
      setTimeout(() => succEl.style.display = 'none', 4000);
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

async function testSmtp() {
  const to      = document.getElementById('test-to').value.trim();
  const errEl   = document.getElementById('test-error');
  const succEl  = document.getElementById('test-success');
  const testBtn = document.getElementById('btn-test');

  errEl.style.display = succEl.style.display = 'none';

  if (!to) {
    errEl.textContent   = 'Enter an email address to send the test to.';
    errEl.style.display = 'block';
    return;
  }

  testBtn.disabled     = true;
  testBtn.textContent  = 'Sending…';

  try {
    const res  = await fetch('/smtp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ action: 'test', to })
    });
    const data = await res.json();
    data.status = data.status ?? res.status;

    if (data.ok) {
      succEl.textContent   = data.message;
      succEl.style.display = 'block';
    } else {
      errEl.textContent   = apiError(data);
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
    errEl.style.display = 'block';
  } finally {
    testBtn.disabled    = false;
    testBtn.textContent = 'Send Test Email';
  }
}

async function clearSmtp() {
  if (!confirm('Clear all SMTP settings from the database? Email will stop working.')) return;

  const res  = await fetch('/smtp.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ action: 'clear' })
  });
  const data = await res.json();
  if (data.ok) {
    showFlash('success', data.message);
    // Clear all fields
    ['smtp-host','smtp-user','smtp-password','smtp-from','smtp-admin','smtp-base-url'].forEach(
      id => document.getElementById(id).value = ''
    );
    document.getElementById('smtp-port').value = '587';
    document.getElementById('enc-tls').checked = true;
  } else {
    showFlash('error', apiError(data));
  }
}

function fillProvider(host, port, enc) {
  document.getElementById('smtp-host').value = host;
  document.getElementById('smtp-port').value = port;
  document.getElementById('enc-' + enc).checked = true;
  document.getElementById('smtp-host').focus();
}

function apiError(data) {
  if (data.status === 429) return 'Rate limit exceeded (429) — please wait and try again.';
  return data.error || data.message || (data.status ? 'HTTP ' + data.status : 'Unknown error');
}

function showFlash(type, message) {
  const existing = document.querySelector('.flash');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className   = 'flash flash--' + type;
  el.textContent = message;
  document.querySelector('.main__inner').prepend(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 4000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>