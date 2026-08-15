<?php
/**
 * register-user.php — Public user registration page
 *
 * Standalone page — no portal auth required. Intentionally does not
 * include header.php so authenticated users do not see the portal nav.
 *
 * ephemeralADMIN — MIT License
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $name  = trim($_POST['name']  ?? '');

    if (!$email) $errors['email'] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Please enter a valid email address.';
    if (!$name) $errors['name'] = 'Name is required.';

    if (empty($errors)) {
        $result = api_post('/register', [
            'email' => $email,
            'name'  => $name,
        ], false); // public endpoint — no admin key

        if ($result['ok']) {
            $success = true;
        } else {
            $errors['_global'] = $result['data']['error'] ?? 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — <?= htmlspecialchars(site_name_public()) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
  <style>
    body {
      background: var(--bg-alt);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 48px 24px;
    }

    .reg-wrap {
      width: 100%;
      max-width: 860px;
    }

    .reg-brand {
      text-align: center;
      margin-bottom: 36px;
    }

    .reg-brand h1 {
      font-family: var(--font-heading);
      font-size: 26px;
      font-weight: 400;
      color: var(--ink);
      margin-bottom: 6px;
    }

    .reg-brand p {
      font-size: 14px;
      color: var(--ink-light);
    }

    .reg-back {
      display: inline-block;
      font-size: 13px;
      color: var(--ink-light);
      text-decoration: none;
      margin-top: 24px;
    }

    .reg-back:hover { color: var(--ink); }
  </style>
</head>
<body>

<div class="reg-wrap">

  <div class="reg-brand">
    <h1><?= htmlspecialchars(site_name_public()) ?></h1>
    <p>Create your account</p>
  </div>

  <?php if ($success): ?>

    <div class="card" style="max-width:520px; margin:0 auto; text-align:center;">
      <div class="card__body" style="padding:40px 32px;">
        <div style="font-size:40px; margin-bottom:16px;">&#x2709;</div>
        <h2 style="font-family:var(--font-heading); font-size:24px; font-weight:400; margin-bottom:12px;">
          Check Your Email
        </h2>
        <p style="font-size:14px; color:var(--ink-light); margin-bottom:8px;">
          We've sent a verification link to
          <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong>.
        </p>
        <p style="font-size:14px; color:var(--ink-light); margin-bottom:24px;">
          Click the link to verify your address. You'll then be sent a link
          to set your password and activate your account. The link expires
          in 24 hours.
        </p>
        <p style="font-size:13px; color:var(--ink-faint);">
          Didn't receive it? Check your spam folder, or
          <a href="/register-user.php">try again</a>.
        </p>
      </div>
    </div>

  <?php else: ?>

    <div style="display:grid; grid-template-columns:1fr 320px; gap:32px; align-items:start;">

      <div class="card">
        <div class="card__head"><span class="card__title">Your Details</span></div>
        <div class="card__body">

          <?php if (!empty($errors['_global'])): ?>
            <div class="alert alert--warning" style="margin-bottom:20px;">
              <?= htmlspecialchars($errors['_global']) ?>
            </div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <div class="form-grid form-grid--full" style="gap:18px;">

              <div class="form-group">
                <label for="name">Name <span style="color:var(--error)">*</span></label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       placeholder="Your name" required>
                <?php if (!empty($errors['name'])): ?>
                  <span class="form-error"><?= htmlspecialchars($errors['name']) ?></span>
                <?php endif; ?>
              </div>

              <div class="form-group">
                <label for="email">Email Address <span style="color:var(--error)">*</span></label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" required>
                <?php if (!empty($errors['email'])): ?>
                  <span class="form-error"><?= htmlspecialchars($errors['email']) ?></span>
                <?php else: ?>
                  <span class="form-hint">
                    A verification link will be sent here. This will be your login email address.
                  </span>
                <?php endif; ?>
              </div>

            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn--primary">Send Verification Email</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Info sidebar -->
      <div>
        <div class="card">
          <div class="card__head"><span class="card__title">How It Works</span></div>
          <div class="card__body">
            <ol style="font-size:14px; color:var(--ink-light); line-height:1.8; padding-left:18px; margin:0;">
              <li>Enter your name and email address.</li>
              <li>Click the verification link in your email.</li>
              <li>Set a password for your account.</li>
              <li>Sign in with your email and password.</li>
            </ol>
          </div>
        </div>

        <div class="card" style="margin-top:16px;">
          <div class="card__head"><span class="card__title">For Applications</span></div>
          <div class="card__body">
            <p style="font-size:14px; color:var(--ink-light); margin-bottom:14px;">
              Building a web app or service? Register a domain key instead — it's better suited
              for server-to-server use.
            </p>
            <a href="/register-domain.php" class="btn btn--ghost btn--sm">Register a Domain &rarr;</a>
          </div>
        </div>
      </div>

    </div>

  <?php endif; ?>

  <div style="text-align:center;">
    <a href="/login.php" class="reg-back">&larr; Back to Sign In</a>
  </div>

</div>

</body>
</html>