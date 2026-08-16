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
// setup.php — First-run administrator account creation
//
// Only reachable when the database is empty (/setup/status returns
// setup_required: true). Once any key exists, /setup returns 403 and
// this page redirects to login.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Confirm setup is actually required — redirect to login if not
$status = api_get('/setup/status', false);
$setup_required = $status['ok'] && !empty($status['data']['setup_required']);

if (!$setup_required) {
    header('Location: /login.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name) {
        $error = 'Please enter your name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = api_post('/setup', [
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ], false);

        if ($result['ok']) {
            // Log the admin in immediately using the returned identity + key
            $data = $result['data'];
            auth_set_session($data);

            // Store the API key in session so we can display it once on the success screen.
            $_SESSION['setup_api_key'] = $data['api_key'] ?? '';

            $success = true;
        } else {
            $error = $result['data']['error'] ?? 'Setup failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup — <?= htmlspecialchars(site_name_public()) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:      #e2e5ea;
      --border:   #2e2e2c;
      --gold:     #ffd700;
      --accent:   #0066cc;
      --accent-text:#70b8ff;
      --error:    #b42424;
      --error-bg: #fef0f0;
      --success:  #2f7d3a;
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

    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(1px 1px at 20% 30%, rgba(255,255,255,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 70% 20%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 50% 70%, rgba(255,215,0,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 85% 60%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 10% 80%, rgba(255,255,255,.35) 0%, transparent 100%),
        radial-gradient(2px 2px at 40% 40%, rgba(255,255,255,.2) 0%, transparent 100%);
      pointer-events: none;
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
        rgba(255,215,0, .35)  0%,
        rgba(255,215,0, .12) 18%,
        rgba(255,215,0, .03) 32%,
        transparent             44%
      );
      pointer-events: none;
    }

    .setup-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
      position: relative;
      z-index: 1;
    }

    .setup-brand__star { font-size: 22px; color: var(--gold); }

    .setup-brand__name {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 20px;
      color: #f0ede8;
    }

    .setup-step {
      font-size: 12px;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 28px;
      position: relative;
      z-index: 1;
    }

    .setup-box {
      background: #1f1f1d;
      border-radius: 12px;
      width: 100%;
      max-width: 440px;
      overflow: hidden;
      position: relative;
      z-index: 1;
    }

    .setup-box__head {
      padding: 32px 32px 24px;
      border-bottom: 1px solid var(--border);
      background: #252523;
    }

    .setup-box__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 24px;
      font-weight: 400;
      color: var(--ink);
      margin-bottom: 6px;
    }

    .setup-box__desc {
      font-size: 13.5px;
      color: #8b95a8;
      line-height: 1.55;
    }

    .setup-box__body { padding: 28px 32px 32px; }

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
      margin-bottom: 18px;
    }

    label {
      font-size: 13px;
      font-weight: 500;
      color: var(--ink);
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #3d3d3a;
      border-radius: 6px;
      font-family: 'DM Mono', monospace;
      font-size: 13px;
      color: var(--ink);
      background: #1f1f1d;
      transition: border-color .15s, box-shadow .15s;
    }

    input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(0,102,204,.1);
    }

    .hint { font-size: 12px; color: #565f70; }

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
      margin-top: 4px;
    }

    .btn-submit:hover { background: #3a7bbf; }

    /* Success state */
    .key-display {
      background: #141412;
      border: 1px solid #3d3d3a;
      border-radius: 6px;
      padding: 14px 16px;
      font-family: 'DM Mono', monospace;
      font-size: 12px;
      color: var(--gold);
      word-break: break-all;
      line-height: 1.6;
      margin: 16px 0;
    }

    .warning-box {
      background: #1e1800;
      border: 1px solid #5c4a00;
      border-radius: 6px;
      padding: 12px 14px;
      font-size: 13px;
      color: #f5d78e;
      line-height: 1.55;
      margin-bottom: 20px;
    }

    .config-snippet {
      background: #141412;
      border: 1px solid #2e2e2c;
      border-radius: 6px;
      padding: 12px 16px;
      font-family: 'DM Mono', monospace;
      font-size: 12px;
      color: #8b95a8;
      word-break: break-all;
      line-height: 1.7;
      margin-bottom: 20px;
    }

    .config-snippet .hl { color: var(--gold); }

    .btn-continue {
      display: block;
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
      text-align: center;
      text-decoration: none;
      transition: background .15s;
    }

    .btn-continue:hover { background: #3a7bbf; }
  </style>
</head>
<body>

<div class="setup-brand">
  <span class="setup-brand__star">✦</span>
  <span class="setup-brand__name"><?= htmlspecialchars(site_name_public()) ?></span>
</div>

<div class="setup-step">First-time setup</div>

<div class="setup-box">

  <?php if ($success): ?>

    <?php $api_key = $_SESSION['setup_api_key'] ?? ''; ?>

    <div class="setup-box__head">
      <h1 class="setup-box__title">Setup complete ✓</h1>
      <p class="setup-box__desc">
        Your administrator account has been created and you are now signed in.
      </p>
    </div>

    <div class="setup-box__body">

      <div class="warning-box">
        ⚠ Your API key is shown below — this is the only time it will be
        displayed. No email has been sent (SMTP is not yet configured).
        Save it somewhere secure before continuing.
      </div>

      <p style="font-size:13px; color:#8b95a8; margin-bottom:8px;">Your API key:</p>
      <div class="key-display"><?= htmlspecialchars($api_key) ?></div>

      <p style="font-size:13px; color:#8b95a8; margin-bottom:16px;">
        Once you continue, configure SMTP from the portal settings so that
        future emails (2FA codes, user registration, password resets) are
        delivered automatically.
      </p>

      <a href="/portal-admin.php" class="btn-continue">Continue to Dashboard →</a>

    </div>

  <?php else: ?>

    <div class="setup-box__head">
      <h1 class="setup-box__title">Create administrator</h1>
      <p class="setup-box__desc">
        Set up your administrator account. This is the only time you will be
        able to do this — once created, this page is permanently disabled.
      </p>
    </div>

    <div class="setup-box__body">

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">

        <div class="form-group">
          <label for="name">Your name</label>
          <input type="text" id="name" name="name"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                 placeholder="Jane Smith"
                 autocomplete="name"
                 autofocus required>
        </div>

        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="you@example.com"
                 autocomplete="username"
                 required>
          <span class="hint">This will be your login email.</span>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 autocomplete="new-password"
                 minlength="8" required>
          <span class="hint">At least 8 characters.</span>
        </div>

        <div class="form-group">
          <label for="confirm">Confirm password</label>
          <input type="password" id="confirm" name="confirm"
                 autocomplete="new-password"
                 minlength="8" required>
        </div>

        <button type="submit" class="btn-submit">Create Administrator →</button>

      </form>

    </div>

  <?php endif; ?>

</div>

</body>
</html>