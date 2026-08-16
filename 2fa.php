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

// ─────────────────────────────────────────────────────────────────────────────
// 2fa.php — Step 2 of login: email verification code
//
// Reached after login.php receives {"2fa_required": true} from /login.
// Requires $_SESSION['pending_email'] to be set by auth_attempt_login().
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';

function portal_url(array $user): string
{
    return !empty($user['admin']) ? '/portal-admin.php' : '/portal-user.php';
}

// Already logged in — nothing to do here
if (auth_check()) {
    header('Location: ' . portal_url(auth_user()));
    exit;
}

$email = auth_pending_email();
if (!$email) {
    // No pending login — start over
    header('Location: /login.php');
    exit;
}

$error = '';
$next  = $_GET['next'] ?? $_POST['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code            = trim($_POST['code'] ?? '');
    $remember_device = !empty($_POST['remember_device']);

    if (!$code) {
        $error = 'Please enter the verification code from your email.';
    } else {
        $result = auth_verify_2fa($email, $code, $remember_device);

        if ($result['state'] === 'logged_in') {
            $redirect = $next ?: portal_url($result['user']);
            header('Location: ' . $redirect);
            exit;
        }

        $error = $result['message'] ?? 'Invalid or expired verification code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify — <?= htmlspecialchars(site_name_public()) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:     #e2e5ea;
      --border:  #2e2e2c;
      --dark:    #0e0e0d;
      --gold:    #ffd700;
      --accent:  #0066cc;
      --accent-text:#70b8ff;
      --error:   #b42424;
      --error-bg:#fef0f0;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 15px; }

    body {
      font-family: 'Jost', system-ui, sans-serif;
      background: #0e0e0d;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
      -webkit-font-smoothing: antialiased;
      position: relative;
      overflow: hidden;
    }

    body::after {
      content: '';
      position: absolute;
      width: 160vw;
      height: 160vh;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: radial-gradient(ellipse at center,
        rgba(0,102,204, .55)  0%,
        rgba(0,102,204, .28) 18%,
        rgba(0,102,204, .08) 32%,
        transparent             44%
      );
      pointer-events: none;
    }

    .login-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
      text-decoration: none;
      position: relative;
      z-index: 1;
    }

    .login-brand__star { font-size: 22px; color: var(--gold); }

    .login-brand__name {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 20px;
      color: #f0ede8;
    }

    .login-box {
      background: var(--surface, #1f1f1d);
      border-radius: 12px;
      width: 100%;
      max-width: 400px;
      overflow: hidden;
      position: relative;
      z-index: 1;
    }

    .login-box__head {
      padding: 32px 32px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--surface-alt, #252523);
    }

    .login-box__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 24px;
      font-weight: 400;
      color: var(--ink, #e2e5ea);
      margin-bottom: 6px;
    }

    .login-box__desc {
      font-size: 13.5px;
      color: #8b95a8;
      line-height: 1.5;
    }

    .login-box__desc strong { color: var(--ink, #e2e5ea); }

    .login-box__body {
      padding: 28px 32px 32px;
    }

    .error-box {
      background: var(--error-bg);
      border: 1px solid #f5c0c0;
      border-radius: 6px;
      padding: 11px 14px;
      font-size: 13.5px;
      color: var(--error);
      margin-bottom: 18px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 20px;
    }

    label {
      font-size: 13px;
      font-weight: 500;
      color: var(--ink, #e2e5ea);
    }

    input[type="text"] {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #3d3d3a;
      border-radius: 6px;
      font-family: 'DM Mono', monospace;
      font-size: 22px;
      letter-spacing: .3em;
      text-align: center;
      color: var(--ink, #e2e5ea);
      background: var(--surface, #1f1f1d);
      transition: border-color .15s, box-shadow .15s;
    }

    input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(0,102,204,.1);
    }

    .checkbox-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 20px;
    }

    .checkbox-row input[type="checkbox"] {
      width: 16px;
      height: 16px;
      accent-color: var(--accent);
    }

    .checkbox-row label {
      font-size: 13px;
      font-weight: 400;
      color: #8b95a8;
      margin: 0;
    }

    .btn-submit {
      width: 100%;
      padding: 11px 24px;
      background: #0066cc;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-family: 'Jost', sans-serif;
      font-size: 14.5px;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s;
      margin-bottom: 16px;
    }

    .btn-submit:hover { background: #3a7bbf; }

    .back-link {
      margin-top: 32px;
      font-size: 13px;
      color: rgba(255,255,255,.35);
      text-decoration: none;
      position: relative;
      z-index: 1;
      transition: color .2s;
    }

    .back-link:hover { color: rgba(255,255,255,.65); }
  </style>
</head>
<body>

<a href="/landing.php" class="login-brand">
  <span class="login-brand__star">✦</span>
  <span class="login-brand__name"><?= htmlspecialchars(site_name_public()) ?></span>
</a>

<div class="login-box">
  <div class="login-box__head">
    <h1 class="login-box__title">Check your email</h1>
    <p class="login-box__desc">
      We sent a verification code to <strong><?= htmlspecialchars($email) ?></strong>.
      Enter it below to continue.
    </p>
  </div>

  <div class="login-box__body">

    <?php if ($error): ?>
      <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php if ($next): ?>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="code">Verification code</label>
        <input type="text" id="code" name="code"
               inputmode="numeric" pattern="[0-9]*" maxlength="6"
               placeholder="000000"
               autocomplete="one-time-code"
               autofocus required>
      </div>

      <div class="checkbox-row">
        <input type="checkbox" id="remember_device" name="remember_device" value="1">
        <label for="remember_device">Remember this device for <?= trusted_device_days_public() ?> days</label>
      </div>

      <button type="submit" class="btn-submit">Verify →</button>
    </form>
  </div>
</div>

<a href="/login.php" class="back-link">← Back to sign in</a>

</body>
</html>