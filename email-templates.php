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

$page_title = 'Email Templates';

// Handle AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'save';
    $name   = preg_replace('/[^a-z0-9_-]/', '', $input['name'] ?? 'test');

    $all_template_names = ['test', 'registration-verification', 'key-rotated', 'user-activated', 'set-password', 'password-reset-required', '2fa-code'];

    if ($action === 'save') {
        $width       = max(320, min(800, (int)($input['content_width'] ?? 600)));
        $header_align = in_array($input['header_align'] ?? '', ['left','center','right'])
            ? $input['header_align'] : 'left';

        $appearance = [
            'bg_color'      => $input['bg_color']    ?? '#f4f4f4',
            'panel_color'   => $input['panel_color']  ?? '#ffffff',
            'text_color'    => $input['text_color']   ?? '#1a1a1a',
            'content_width' => $width,
            'header_align'  => $header_align,
        ];

        // Save this template (appearance + content)
        $result = my_api_post("/admin/email-templates/{$name}", array_merge($appearance, [
            'subject'     => trim($input['subject']     ?? '') ?: null,
            'header_text' => trim($input['header_text'] ?? '') ?: null,
            'body_text'   => trim($input['body_text']   ?? '') ?: null,
            'footer_text' => trim($input['footer_text'] ?? '') ?: null,
        ]));

        if (!$result['ok']) {
            echo json_encode(['ok' => false, 'error' => extractApiError($result)]);
            exit;
        }

        // If the test template was saved, propagate its appearance to all other templates
        // (content fields are left untouched on those templates)
        if ($name === 'test') {
            foreach ($all_template_names as $other) {
                if ($other === 'test') continue;
                my_api_post("/admin/email-templates/{$other}", $appearance);
            }
            echo json_encode(['ok' => true, 'message' => 'Test template saved. Appearance applied to all templates.']);
        } else {
            echo json_encode(['ok' => true, 'message' => 'Template saved.']);
        }
        exit;
    }

    if ($action === 'reset') {
        $result = my_api_post("/admin/email-templates/{$name}/reset");
        echo json_encode($result['ok']
            ? ['ok' => true,  'message' => 'Template reset to defaults.']
            : ['ok' => false, 'error'   => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

function extractApiError(array $result): string {
    $d   = $result['data'] ?? [];
    $msg = $d['error'] ?? $d['message'] ?? null;
    if (!$msg) $msg = 'HTTP ' . ($result['status'] ?? '?');
    if (($result['status'] ?? 0) === 429) $msg = 'Rate limit exceeded (429).';
    return $msg;
}

// ─── Template definitions ────────────────────────────────────────────────────
$templates_meta = [
    'test' => [
        'label'    => 'Test Email',
        'desc'     => 'Sent manually from SMTP Settings to verify your mail configuration.',
        'vars'     => [],
        'defaults' => [
            'subject'     => 'Test email from ephemeralREST',
            'header_text' => 'ephemeralREST',
            'body_text'   => "This is a test email from ephemeralREST.\n\nYour SMTP configuration is working correctly. You can safely discard this message.",
            'footer_text' => 'You are receiving this email because an administrator triggered a test.',
        ],
    ],
    'registration-verification' => [
        'label'    => 'Email Verification',
        'desc'     => 'Sent when a user registers. Contains a link to verify their email address.',
        'vars'     => ['{name}' => 'User name', '{verify_url}' => 'One-time verification link (expires in 24 hours)'],
        'defaults' => [
            'subject'     => 'Verify your email address',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nThank you for registering. Click the link below to verify your email address:\n\n{verify_url}\n\nThis link expires in 24 hours.\n\nIf you did not request this, you can safely ignore this email.",
            'footer_text' => 'ephemeralREST',
        ],
    ],
    'user-activated' => [
        'label'    => 'Account Activated',
        'desc'     => 'Sent after a user sets their password and their account becomes fully active. Contains the API key.',
        'vars'     => ['{name}' => 'User name', '{api_key}' => 'The issued API key'],
        'defaults' => [
            'subject'     => 'Your API key is ready',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nYour account is now active.\n\nYour API key is:\n\n{api_key}\n\nThis key will not be shown again — please store it securely.",
            'footer_text' => 'You are receiving this email because your ephemeralREST account was activated.',
        ],
    ],
    'set-password' => [
        'label'    => 'Set Password',
        'desc'     => 'Sent after email verification. Contains a link to the set-password page.',
        'vars'     => ['{name}' => 'User name', '{set_password_url}' => 'One-time link to set a password (expires in 24 hours)'],
        'defaults' => [
            'subject'     => 'Set your password',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nYour email has been verified. Click the link below to set a password for your account:\n\n{set_password_url}\n\nThis link expires in 24 hours.",
            'footer_text' => 'You are receiving this email because you registered at ephemeralREST.',
        ],
    ],
    'password-reset-required' => [
        'label'    => 'Password Reset Required',
        'desc'     => 'Sent when an admin requires a user to set a new password.',
        'vars'     => ['{name}' => 'User name', '{set_password_url}' => 'One-time link to set a new password (expires in 24 hours)'],
        'defaults' => [
            'subject'     => 'Please reset your password',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nAn administrator has required that you set a new password.\n\nClick the link below to set a new password:\n\n{set_password_url}\n\nThis link expires in 24 hours.",
            'footer_text' => 'You are receiving this email because an administrator reset your ephemeralREST password.',
        ],
    ],
    '2fa-code' => [
        'label'    => '2FA Verification Code',
        'desc'     => 'Sent during login when a trusted-device cookie is not present.',
        'vars'     => ['{name}' => 'User name', '{code}' => '6-digit verification code', '{expiry_minutes}' => 'Minutes until the code expires'],
        'defaults' => [
            'subject'     => 'Your login verification code',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nYour verification code is:\n\n{code}\n\nThis code expires in {expiry_minutes} minutes. If you did not attempt to log in, you can safely ignore this email.",
            'footer_text' => 'You are receiving this email because a login was attempted at ephemeralREST.',
        ],
    ],
    'key-rotated' => [
        'label'    => 'Key Rotated',
        'desc'     => 'Sent when an API key is rotated, either by the user or an admin.',
        'vars'     => ['{name}' => 'Key holder name', '{identifier}' => 'Domain or email', '{api_key}' => 'The new API key'],
        'defaults' => [
            'subject'     => 'Your API key has been rotated',
            'header_text' => 'ephemeralREST',
            'body_text'   => "Hi {name},\n\nYour ephemeralREST API key for {identifier} has been rotated.\n\nYour new API key is:\n\n{api_key}\n\nThis key will not be shown again — please store it securely now. Your previous key is no longer valid.\n\nIf you did not request this rotation, please contact us immediately by replying to this email.",
            'footer_text' => 'You are receiving this email because your ephemeralREST API key was rotated.',
        ],
    ],
];

// Shared style defaults applied to every template
$style_defaults = [
    'bg_color'      => '#f4f4f4',
    'panel_color'   => '#ffffff',
    'text_color'    => '#1a1a1a',
    'content_width' => 600,
    'header_align'  => 'left',
];

// Validate and select current template
$template_name = $_GET['template'] ?? 'test';
if (!array_key_exists($template_name, $templates_meta)) $template_name = 'test';
$meta     = $templates_meta[$template_name];
$defaults = array_merge($style_defaults, $meta['defaults']);

// Fetch saved template — falls back to defaults if API call fails
$result   = my_api_get("/admin/email-templates/{$template_name}");
$api_ok   = $result['ok'];
$api_error = $api_ok ? '' : extractApiError($result);
// API returns the template fields flat — merge onto PHP-side defaults so
// fields not yet stored in DB still have sensible values
$template = $api_ok ? array_merge($defaults, $result['data'] ?? []) : $defaults;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Email Templates</h1>
    <p class="page-subtitle">Customise the style and content of transactional emails</p>
  </div>
  <a href="/smtp.php" class="btn btn--ghost">← SMTP Settings</a>
</div>

<?php if (!$api_ok): ?>
  <div class="alert alert--warning" style="margin-bottom:24px;">
    Could not load saved template — showing defaults.
    <?php if ($api_error): ?>
      API error: <strong><?= htmlspecialchars($api_error) ?></strong>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Template tabs -->
<div class="tabs" style="margin-bottom:8px;">
  <?php foreach ($templates_meta as $slug => $m): ?>
    <a href="?template=<?= $slug ?>"
       class="tab <?= $template_name === $slug ? 'tab--active' : '' ?>">
      <?= htmlspecialchars($m['label']) ?>
    </a>
  <?php endforeach; ?>
</div>
<p style="font-size:13px; color:var(--ink-light); margin-bottom:24px;">
  <?= htmlspecialchars($meta['desc']) ?>
</p>

<div class="two-col" style="align-items:start; gap:32px;">

  <!-- ── LEFT: settings form ─────────────────────────────── -->
  <div>

    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Appearance</span></div>
      <div class="card__body">
        <div class="form-grid form-grid--full" style="gap:20px;">

          <!-- Background colour -->
          <div class="form-group">
            <label for="bg-text">Background Colour</label>
            <div class="color-field">
              <input type="color"  id="bg-picker" value="<?= htmlspecialchars($template['bg_color']) ?>">
              <input type="text"   id="bg-text"   value="<?= htmlspecialchars($template['bg_color']) ?>"
                     maxlength="7" placeholder="#f4f4f4" spellcheck="false">
            </div>
            <span class="form-hint">Page background outside the email content area.</span>
          </div>

          <!-- Text colour -->
          <div class="form-group">
            <label for="text-text">Text Colour</label>
            <div class="color-field">
              <input type="color"  id="text-picker" value="<?= htmlspecialchars($template['text_color']) ?>">
              <input type="text"   id="text-text"   value="<?= htmlspecialchars($template['text_color']) ?>"
                     maxlength="7" placeholder="#1a1a1a" spellcheck="false">
            </div>
            <span class="form-hint">Applies to header text and body copy.</span>
          </div>

          <!-- Panel colour -->
          <div class="form-group">
            <label for="panel-text">Panel Background</label>
            <div class="color-field">
              <input type="color"  id="panel-picker" value="<?= htmlspecialchars($template['panel_color'] ?? '#ffffff') ?>">
              <input type="text"   id="panel-text"   value="<?= htmlspecialchars($template['panel_color'] ?? '#ffffff') ?>"
                     maxlength="7" placeholder="#ffffff" spellcheck="false">
            </div>
            <span class="form-hint">Background of the white content card inside the email.</span>
          </div>

          <!-- Content width -->
          <div class="form-group">
            <label for="width-number">Content Width <span class="label-hint">320 – 800 px</span></label>
            <div style="display:flex; align-items:center; gap:12px;">
              <input type="range"  id="width-range"  min="320" max="800" step="10"
                     value="<?= (int)$template['content_width'] ?>"
                     style="flex:1; accent-color:var(--gold);">
              <input type="number" id="width-number" min="320" max="800" step="10"
                     value="<?= (int)$template['content_width'] ?>"
                     style="width:74px;">
            </div>
            <span class="form-hint">Width of the white email content block.</span>
          </div>

        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><span class="card__title">Content</span></div>
      <div class="card__body">
        <div class="form-grid form-grid--full" style="gap:20px;">

          <div class="form-group">
            <label for="subject-text">Subject Line</label>
            <input type="text" id="subject-text"
                   value="<?= htmlspecialchars($template['subject'] ?? '') ?>"
                   placeholder="<?= htmlspecialchars($meta['defaults']['subject']) ?>">
            <span class="form-hint">The email subject line shown in the recipient's inbox.</span>
          </div>

          <div class="form-group">
            <label for="header-text">Header Text</label>
            <input type="text" id="header-text"
                   value="<?= htmlspecialchars($template['header_text'] ?? '') ?>"
                   placeholder="ephemeralREST">
            <span class="form-hint">Displayed at the top of the email. Defaults to the site name if left blank.</span>
          </div>

          <div class="form-group">
            <label>Header Alignment</label>
            <div class="seg-control">
              <?php foreach (['left' => '⬅ Left', 'center' => '↔ Centre', 'right' => 'Right ➡'] as $val => $lbl): ?>
                <label class="seg-control__opt">
                  <input type="radio" name="header-align" value="<?= $val ?>"
                         <?= ($template['header_align'] ?? 'left') === $val ? 'checked' : '' ?>
                         onchange="updatePreview()">
                  <span><?= $lbl ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label for="body-text">Body Text</label>
            <textarea id="body-text" rows="5"
                      placeholder="Email body content…"><?= htmlspecialchars($template['body_text'] ?? '') ?></textarea>
            <span class="form-hint">Main content of the email. Each blank line becomes a new paragraph.</span>
            <?php if (!empty($meta['vars'])): ?>
              <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                <span style="font-size:11.5px; color:var(--ink-light); margin-right:2px;">Variables:</span>
                <?php foreach ($meta['vars'] as $var => $desc): ?>
                  <span class="var-tag" title="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($var) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="footer-text">Footer Text</label>
            <textarea id="footer-text" rows="3"
                      placeholder="You are receiving this email because you registered with ephemeralREST."><?= htmlspecialchars($template['footer_text'] ?? '') ?></textarea>
            <span class="form-hint">Small-print shown at the bottom of every email.</span>
          </div>

        </div>

        <p id="tpl-error"   style="color:var(--error);  font-size:13px; margin-top:14px; display:none;"></p>
        <p id="tpl-success" style="color:var(--success); font-size:13px; margin-top:14px; display:none;"></p>

        <div class="form-actions" style="margin-top:20px;">
          <button class="btn btn--primary" onclick="saveTemplate()" id="btn-save">Save Template</button>
          <button class="btn btn--ghost"   onclick="resetTemplate()"
                  data-confirm="Reset this template to system defaults?">Reset</button>
          <span id="tpl-saving" style="font-size:13px; color:var(--ink-faint); display:none;">Saving…</span>
        </div>
      </div>
    </div>

  </div><!-- /.left -->

  <!-- ── RIGHT: live preview ─────────────────────────────── -->
  <div>
    <div class="card">
      <div class="card__head">
        <span class="card__title">Live Preview</span>
        <span style="font-size:12px; color:var(--ink-faint);">Updates as you type</span>
      </div>
      <div class="card__body" style="padding:0;">

        <!-- Mock email client chrome -->
        <div style="background:var(--bg-alt); border-bottom:1px solid var(--border); padding:12px 16px;">
          <div style="display:flex; gap:8px; margin-bottom:10px;">
            <span style="width:10px;height:10px;border-radius:50%;background:#ff5f57;display:inline-block;"></span>
            <span style="width:10px;height:10px;border-radius:50%;background:#febc2e;display:inline-block;"></span>
            <span style="width:10px;height:10px;border-radius:50%;background:#28c840;display:inline-block;"></span>
          </div>
          <div style="font-size:12px; color:var(--ink-light); display:flex; flex-direction:column; gap:3px;">
            <div><strong style="color:var(--ink-light);">From:</strong> ephemeralREST &lt;no-reply@example.com&gt;</div>
            <div><strong style="color:var(--ink-light);">Subject:</strong> <span id="preview-subject"><?= htmlspecialchars($template['subject'] ?? $meta['defaults']['subject']) ?></span></div>
          </div>
        </div>

        <!-- Email preview iframe -->
        <iframe id="email-preview"
                style="width:100%; border:none; display:block; height:360px;"
                sandbox="allow-scripts"></iframe>

      </div>
    </div>

  </div><!-- /.right -->

</div><!-- /.two-col -->

<style>
.color-field {
  display: flex;
  align-items: center;
  gap: 10px;
}

.color-field input[type="color"] {
  width: 40px;
  height: 38px;
  padding: 2px 3px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius);
  background: var(--bg-alt);
  cursor: pointer;
  flex-shrink: 0;
}

.color-field input[type="text"] {
  flex: 1;
  font-family: var(--font-mono);
  font-size: 13.5px;
  letter-spacing: .04em;
}

.seg-control {
  display: flex;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius);
  overflow: hidden;
}

.seg-control__opt {
  flex: 1;
  display: flex;
}

.seg-control__opt input[type="radio"] {
  display: none;
}

.seg-control__opt span {
  flex: 1;
  text-align: center;
  padding: 8px 12px;
  font-size: 13px;
  color: var(--ink-light);
  background: var(--bg-alt);
  border-right: 1px solid var(--border-strong);
  cursor: pointer;
  transition: background .15s, color .15s;
  user-select: none;
}

.seg-control__opt:last-child span {
  border-right: none;
}

.seg-control__opt input:checked + span {
  background: var(--gold);
  color: #0e0e0d;
  font-weight: 600;
}

.var-tag {
  display: inline-block;
  padding: 2px 8px;
  background: var(--bg-alt);
  border: 1px solid var(--border-strong);
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 11.5px;
  color: var(--ink-light);
  cursor: default;
}
</style>

<script>
// ── Template name for this page ──
const TEMPLATE_NAME = '<?= $template_name ?>';
let previewSubject = document.getElementById('preview-subject');

// ── Sync colour picker ↔ text inputs ──
function bindColor(pickerId, textId) {
  const picker = document.getElementById(pickerId);
  const text   = document.getElementById(textId);

  picker.addEventListener('input', () => {
    text.value = picker.value;
    updatePreview();
  });

  text.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
      picker.value = text.value;
      updatePreview();
    }
  });

  text.addEventListener('blur', () => {
    if (!/^#[0-9a-fA-F]{6}$/.test(text.value)) {
      text.value   = picker.value; // revert invalid input
    }
  });
}

// ── Sync width range ↔ number ──
const widthRange  = document.getElementById('width-range');
const widthNumber = document.getElementById('width-number');

widthRange.addEventListener('input', () => {
  widthNumber.value = widthRange.value;
  updatePreview();
});

widthNumber.addEventListener('input', () => {
  const v = Math.max(320, Math.min(800, parseInt(widthNumber.value) || 600));
  widthRange.value  = v;
  widthNumber.value = v;
  updatePreview();
});

bindColor('bg-picker',    'bg-text');
bindColor('text-picker',  'text-text');
bindColor('panel-picker', 'panel-text');

document.getElementById('subject-text').addEventListener('input', () => {
  document.getElementById('preview-subject').textContent =
    document.getElementById('subject-text').value || '';
});
document.getElementById('header-text').addEventListener('input', updatePreview);
document.getElementById('body-text').addEventListener('input', updatePreview);
document.getElementById('footer-text').addEventListener('input', updatePreview);

// ── Build preview HTML ──
function buildPreviewHtml() {
  const bg      = document.getElementById('bg-text').value    || '#f4f4f4';
  const panel   = document.getElementById('panel-text').value || '#ffffff';
  const textCol = document.getElementById('text-text').value  || '#1a1a1a';
  const width   = parseInt(widthRange.value) || 600;
  const align   = document.querySelector('input[name="header-align"]:checked')?.value || 'left';
  const header  = escHtml(document.getElementById('header-text').value || 'ephemeralREST');
  const rawBody = document.getElementById('body-text').value ||
    "This is a test email from ephemeralREST.\n\nYour SMTP configuration is working correctly. You can safely discard this message.";
  const bodyHtml = rawBody.split(/\n\n+/).map(p => `<p>${escHtml(p.replace(/\n/g, '<br>'))}</p>`).join('\n      ');
  const footer  = escHtml(document.getElementById('footer-text').value ||
    'You are receiving this email because you registered with ephemeralREST.');

  return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: ${bg};
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    padding: 24px 12px;
  }
  .content {
    max-width: ${width}px;
    margin: 0 auto;
    background: ${panel};
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
  }
  .email-header {
    padding: 24px 32px;
    border-bottom: 1px solid rgba(128,128,128,0.2);
    text-align: ${align};
    background: ${panel};
  }
  .email-header__name {
    font-size: 18px;
    font-weight: 700;
    color: ${textCol};
    letter-spacing: -.01em;
  }
  .email-body {
    padding: 32px;
    background: ${panel};
    color: ${textCol};
    font-size: 15px;
    line-height: 1.65;
  }
  .email-body p { margin-bottom: 14px; }
  .email-body p:last-child { margin-bottom: 0; }
  .email-footer {
    padding: 18px 32px;
    background: ${panel};
    border-top: 1px solid rgba(128,128,128,0.2);
    font-size: 12px;
    color: ${textCol};
    line-height: 1.6;
  }
</style>
</head>
<body>
  <div class="content">
    <div class="email-header">
      <span class="email-header__name">${header}</span>
    </div>
    <div class="email-body">
      ${bodyHtml}
    </div>
    <div class="email-footer">${footer}</div>
  </div>
<script>window.addEventListener('load',function(){parent.postMessage({type:'previewHeight',height:document.documentElement.scrollHeight},'*');});<\/script>
</body>
</html>`;
}

function escHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Collect form values ──
function getValues() {
  return {
    name:          TEMPLATE_NAME,
    bg_color:      document.getElementById('bg-text').value,
    panel_color:   document.getElementById('panel-text').value,
    text_color:    document.getElementById('text-text').value,
    content_width: parseInt(widthRange.value) || 600,
    header_align:  document.querySelector('input[name="header-align"]:checked')?.value || 'left',
    subject:       document.getElementById('subject-text').value,
    header_text:   document.getElementById('header-text').value,
    body_text:     document.getElementById('body-text').value,
    footer_text:   document.getElementById('footer-text').value,
  };
}

// ── Save ──
async function saveTemplate() {
  const errEl  = document.getElementById('tpl-error');
  const succEl = document.getElementById('tpl-success');
  const saving = document.getElementById('tpl-saving');
  const saveBtn = document.getElementById('btn-save');

  errEl.style.display = succEl.style.display = 'none';
  saving.style.display = 'inline';
  saveBtn.disabled     = true;

  try {
    const res  = await fetch('/email-templates.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    JSON.stringify({ action: 'save', ...getValues() }),
    });
    const data = await res.json();
    data.status = data.status ?? res.status;

    if (data.ok) {
      succEl.textContent   = data.message;
      succEl.style.display = 'block';
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

// ── Reset to defaults ──
async function resetTemplate() {
  if (!confirm('Reset this template to system defaults?')) return;

  const errEl  = document.getElementById('tpl-error');
  const succEl = document.getElementById('tpl-success');
  errEl.style.display = succEl.style.display = 'none';

  try {
    const res  = await fetch('/email-templates.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    JSON.stringify({ action: 'reset', name: TEMPLATE_NAME }),
    });
    const data = await res.json();
    data.status = data.status ?? res.status;

    if (data.ok) {
      showFlash('success', data.message);
      setTimeout(() => location.reload(), 600);
    } else {
      errEl.textContent   = apiError(data);
      errEl.style.display = 'block';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
    errEl.style.display = 'block';
  }
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

// ── Initial render ──
// ── Preview: always rebuild srcdoc — no contentDocument access needed ──
function updatePreview() {
  const iframe = document.getElementById('email-preview');
  iframe.srcdoc = buildPreviewHtml();
}

// Receive height reports from the sandboxed iframe via postMessage
window.addEventListener('message', function(e) {
  if (e.data && e.data.type === 'previewHeight') {
    const iframe = document.getElementById('email-preview');
    iframe.style.height = Math.max(360, e.data.height) + 'px';
  }
});

updatePreview();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>