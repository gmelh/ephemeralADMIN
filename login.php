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

// Already logged in — redirect to appropriate portal
if (auth_check()) {
    header('Location: ' . portal_url(auth_user()));
    exit;
}

$error      = '';
$role_hint  = $_GET['role'] ?? '';
$next       = $_GET['next'] ?? '';
$access_denied = isset($_GET['error']) && $_GET['error'] === 'access_denied';

function portal_url(array $user): string
{
    if (!empty($user['admin']))                return '/portal-admin.php';
    if (($user['key_type'] ?? '') === 'domain') return '/portal-domain.php';
    return '/portal-user.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = trim($_POST['api_key'] ?? '');

    if (!$api_key) {
        $error = 'Please enter your API key.';
    } else {
        $user = auth_login($api_key);

        if ($user) {
            $redirect = $next ?: portal_url($user);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid API key. Please check and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — ephemeralREST</title>
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
        radial-gradient(1px 1px at 10% 80%, rgba(255,255,255,.35) 0%, transparent 100%),
        radial-gradient(2px 2px at 40% 40%, rgba(255,255,255,.2) 0%, transparent 100%);
      pointer-events: none;
    }

    .login-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
      text-decoration: none;
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

    .role-hint {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      background: var(--surface-alt, #252523);
      border: 1px solid #2e2e2c;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    .role-hint__dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--gold);
      flex-shrink: 0;
    }

    .role-hint__text { font-size: 13px; color: #8a8a84; }

    .error-box {
      background: var(--error-bg);
      border: 1px solid #f5c0c0;
      border-radius: 6px;
      padding: 11px 14px;
      font-size: 13.5px;
      color: var(--error);
      margin-bottom: 18px;
    }

    .access-denied {
      background: #fef8ed;
      border: 1px solid #f5dca8;
      border-radius: 6px;
      padding: 11px 14px;
      font-size: 13.5px;
      color: #92560a;
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

    input[type="text"],
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
  <span class="login-brand__name">ephemeralREST</span>
</a>

<div class="login-box">
  <div class="login-box__head">
    <h1 class="login-box__title">Sign in</h1>
    <p class="login-box__desc">Enter your API key to access your portal.</p>
  </div>

  <div class="login-box__body">

    <?php if ($access_denied): ?>
      <div class="access-denied">Your key doesn't have permission to access that area.</div>
    <?php endif; ?>

    <?php if ($role_hint): ?>
      <div class="role-hint">
        <span class="role-hint__dot"></span>
        <span class="role-hint__text">
          Signing in as: <strong>
          <?= match($role_hint) {
              'admin'  => 'Administrator',
              'domain' => 'Domain Owner',
              'user'   => 'User',
              default  => ucfirst($role_hint)
          } ?>
          </strong>
        </span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php if ($next): ?>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="api_key">API Key</label>
        <input type="text" id="api_key" name="api_key"
               placeholder="Paste your API key"
               autocomplete="off" autocorrect="off" spellcheck="false"
               autofocus required>
        <span class="hint">Your key was issued by email when your account was activated.</span>
      </div>

      <button type="submit" class="btn-submit">Sign In →</button>

      <div class="login-links">
        <a href="/register-domain.php" class="login-link">
          <span>Register a domain key</span>
          <span class="login-link__arrow">→</span>
        </a>
        <a href="/register-user.php" class="login-link">
          <span>Register a personal user key</span>
          <span class="login-link__arrow">→</span>
        </a>
      </div>
    </form>
  </div>
</div>

<a href="/landing.php" class="back-link">← Back to home</a>

</body>
</html>
