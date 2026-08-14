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
// forgot-password.php — Request a password reset link
//
// Public page — no login required.
// Calls POST /password/forgot on the API; always shows the same confirmation
// message regardless of whether the email is registered.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in — no need to be here
if (auth_check()) {
    header('Location: /login.php');
    exit;
}

$submitted = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $result = api_post('/password/forgot', ['email' => $email], false);

        if ($result['ok'] || $result['status'] === 200) {
            $submitted = true;
        } else {
            $error = $result['data']['error'] ?? 'Something went wrong. Please try again.';
        }
    }
}

$site_name = defined('SITE_NAME') ? SITE_NAME : 'ephemeralREST';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= htmlspecialchars($site_name) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:     #e4e1da;
      --border:  #2e2e2c;
      --gold:    #c8a84b;
      --accent:  #2563ab;
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

    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(1px 1px at 20% 30%, rgba(255,255,255,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 70% 20%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 50% 70%, rgba(200,168,75,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 85% 60%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 10% 80%, rgba(255,255,255,.35) 0%, transparent 100%);
      pointer-events: none;
    }

    body::after {
      content: '';
      position: absolute;
      width: 160vw;
      height: 160vh;
      top: 50%; left: 50%;
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
      background: #1f1f1d;
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
      background: #252523;
    }

    .login-box__title {
      font-family: 'Instrument Serif', serif;
      font-size: 24px;
      font-weight: 400;
      color: var(--ink);
      margin-bottom: 6px;
    }

    .login-box__desc {
      font-size: 13.5px;
      color: #8a8a84;
      line-height: 1.55;
    }

    .login-box__body { padding: 28px 32px 32px; }

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
      background: #eef8ef;
      border: 1px solid #b9e3bf;
      border-radius: 6px;
      padding: 14px 16px;
      font-size: 13.5px;
      color: #2f7d3a;
      line-height: 1.55;
      margin-bottom: 20px;
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
      color: var(--ink);
    }

    input[type="email"] {
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
      box-shadow: 0 0 0 3px rgba(37,99,171,.1);
    }

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

    .login-links {
      display: flex;
      flex-direction: column;
      gap: 8px;
      padding-top: 16px;
      border-top: 1px solid #2e2e2c;
    }

    .login-link {
      font-size: 13px;
      color: #8a8a84;
      text-decoration: none;
      display: flex;
      justify-content: space-between;
      padding: 7px 0;
    }

    .login-link:hover { color: var(--accent); }
    .login-link__arrow { opacity: .4; }

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
  <span class="login-brand__name"><?= htmlspecialchars($site_name) ?></span>
</a>

<div class="login-box">
  <div class="login-box__head">
    <h1 class="login-box__title"><?= $submitted ? 'Check your email' : 'Forgot password?' ?></h1>
    <p class="login-box__desc">
      <?php if ($submitted): ?>
        If that email address is registered, a reset link is on its way.
      <?php else: ?>
        Enter your email address and we'll send you a link to reset your password.
      <?php endif; ?>
    </p>
  </div>

  <div class="login-box__body">

    <?php if ($submitted): ?>

      <div class="success-box">
        Check your inbox for a password reset link. It expires in 24 hours.
      </div>
      <div class="login-links">
        <a href="/login.php" class="login-link">
          <span>Back to sign in</span>
          <span class="login-link__arrow">→</span>
        </a>
      </div>

    <?php else: ?>

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com"
                 autocomplete="username"
                 autofocus required>
        </div>

        <button type="submit" class="btn-submit">Send Reset Link →</button>

        <div class="login-links">
          <a href="/login.php" class="login-link">
            <span>Back to sign in</span>
            <span class="login-link__arrow">→</span>
          </a>
        </div>
      </form>

    <?php endif; ?>

  </div>
</div>

<a href="/login.php" class="back-link">← Back to sign in</a>

</body>
</html>