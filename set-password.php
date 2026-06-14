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
// set-password.php — Set or reset a password
//
// Reached via two paths:
//   1. ?t=TOKEN     — link from a "set your password" or admin-forced
//                      password-reset email (POST /password/set with token)
//   2. (no token)   — reached from login.php after /login returned
//                      {"must_change_password": true}. Uses
//                      $_SESSION['pending_email'] (POST /password/set with
//                      email + current_password)
//
// On success, redirects to login.php so the user logs in fresh (with the
// new password, and 2FA as normal).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');
$email = auth_pending_email();

// Token flow doesn't need a session email — it's identified by the token.
// must_change_password flow requires a pending email from login.php.
if (!$token && !$email) {
    header('Location: /login.php');
    exit;
}

$is_must_change = !$token; // true when reached via the must_change_password path

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm      = trim($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $body = ['new_password' => $new_password];

        if ($token) {
            $body['token'] = $token;
        } else {
            // must_change_password flow — re-supply current credentials
            $current_password = trim($_POST['current_password'] ?? '');
            if (!$current_password) {
                $error = 'Please enter your current password.';
            } else {
                $body['email']            = $email;
                $body['current_password'] = $current_password;
            }
        }

        if (!$error) {
            $result = api_post('/password/set', $body, false);

            if ($result['ok']) {
                $success = true;
                unset($_SESSION['pending_email']);
            } else {
                $error = $result['data']['error'] ?? 'Could not set password. The link may be invalid or expired.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set Password — ephemeralREST</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:     #e4e1da;
      --border:  #2e2e2c;
      --dark:    #0e0e0d;
      --gold:    #c8a84b;
      --accent:  #2563ab;
      --error:   #b42424;
      --error-bg:#fef0f0;
      --success: #2f7d3a;
      --success-bg: #eef8ef;
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
        rgba(79, 143, 212, .55)  0%,
        rgba(79, 143, 212, .28) 18%,
        rgba(79, 143, 212, .08) 32%,
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
      font-family: 'Instrument Serif', serif;
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
      font-family: 'Instrument Serif', serif;
      font-size: 24px;
      font-weight: 400;
      color: var(--ink, #e4e1da);
      margin-bottom: 6px;
    }

    .login-box__desc {
      font-size: 13.5px;
      color: #8a8a84;
      line-height: 1.5;
    }

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

    .success-box {
      background: var(--success-bg);
      border: 1px solid #b9e3bf;
      border-radius: 6px;
      padding: 11px 14px;
      font-size: 13.5px;
      color: var(--success);
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
      color: var(--ink, #e4e1da);
    }

    input[type="password"] {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #3d3d3a;
      border-radius: 6px;
      font-family: 'DM Mono', monospace;
      font-size: 13px;
      color: var(--ink, #e4e1da);
      background: var(--surface, #1f1f1d);
      transition: border-color .15s, box-shadow .15s;
      letter-spacing: .02em;
    }

    input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37,99,171,.1);
    }

    .hint { font-size: 12px; color: #525250; }

    .btn-submit {
      width: 100%;
      padding: 11px 24px;
      background: #4f8fd4;
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
  <span class="login-brand__name">ephemeralREST</span>
</a>

<div class="login-box">
  <div class="login-box__head">
    <h1 class="login-box__title"><?= $success ? 'Password set' : 'Set your password' ?></h1>
    <p class="login-box__desc">
      <?php if ($success): ?>
        Your password has been updated. You can now sign in.
      <?php elseif ($is_must_change): ?>
        Your account requires a new password before you can continue.
      <?php else: ?>
        Choose a password for your account. It must be at least 8 characters.
      <?php endif; ?>
    </p>
  </div>

  <div class="login-box__body">

    <?php if ($success): ?>

      <a href="/login.php" class="btn-submit" style="display:block; text-align:center; text-decoration:none; box-sizing:border-box;">
        Continue to sign in →
      </a>

    <?php else: ?>

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <?php if ($token): ?>
          <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
        <?php endif; ?>

        <?php if ($is_must_change): ?>
          <div class="form-group">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="new_password">New password</label>
          <input type="password" id="new_password" name="new_password"
                 autocomplete="new-password" minlength="8" required>
          <span class="hint">At least 8 characters.</span>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm new password</label>
          <input type="password" id="confirm_password" name="confirm_password"
                 autocomplete="new-password" minlength="8" required>
        </div>

        <button type="submit" class="btn-submit">Set Password →</button>
      </form>

    <?php endif; ?>

  </div>
</div>

<a href="/login.php" class="back-link">← Back to sign in</a>

</body>
</html>