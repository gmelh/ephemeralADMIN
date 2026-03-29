<?php
/**
 * verify.php — Email verification landing page
 *
 * Handles two URL formats:
 *   ?t=TOKEN      — direct link from email (calls API to verify, then shows result)
 *   ?success=1    — redirected here by API after successful verification
 *   ?error=...    — redirected here by API after failed verification
 *
 * Public page — no login required.
 *
 * ephemeralADMIN — MIT License
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';

$success = false;
$email   = '';
$error   = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    // API redirect — success
    $success = true;
    $email   = htmlspecialchars(trim($_GET['email'] ?? ''));

} elseif (isset($_GET['error']) && $_GET['error'] !== '') {
    // API redirect — error
    $error = htmlspecialchars(trim($_GET['error']));

} elseif (isset($_GET['t']) && $_GET['t'] !== '') {
    // Direct link from email — call the API to verify the token
    $token  = trim($_GET['t']);
    $result = api_get('/register/verify?t=' . urlencode($token), false);

    if ($result['ok']) {
        $success = true;
        $email   = htmlspecialchars($result['body']['email'] ?? '');
    } else {
        $body  = $result['body'] ?? [];
        $error = htmlspecialchars(
            is_array($body) && isset($body['error'])
                ? $body['error']
                : 'Verification failed. The link may be invalid or already used.'
        );
    }

} else {
    $error = 'No verification token was provided. Please use the link from your email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $success ? 'Email Verified' : 'Verification Failed' ?> — ephemeralREST</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
  <style>
    body {
      background: var(--bg-alt);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .verify-card {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 48px 40px;
      width: 100%;
      max-width: 460px;
      text-align: center;
      box-shadow: 0 4px 24px rgba(0,0,0,.06);
    }

    .verify-icon { font-size: 48px; line-height: 1; margin-bottom: 20px; }

    .verify-card h1 {
      font-size: 22px;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 12px;
    }

    .verify-card p {
      font-size: 14px;
      color: var(--ink-light);
      line-height: 1.6;
      margin: 0 0 24px;
    }

    .verify-detail {
      background: var(--bg-alt);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 18px;
      font-size: 13px;
      color: var(--ink-light);
      margin-bottom: 24px;
      text-align: left;
    }

    .verify-detail strong { color: var(--ink); }
  </style>
</head>
<body>
<div class="verify-card">

  <?php if ($success): ?>

    <div class="verify-icon" style="color:var(--success);">&#x2713;</div>
    <h1>Email verified</h1>
    <p>
      Your email address has been confirmed and your API key is now active.
      The key has been sent to your email address — please check your inbox.
    </p>
    <?php if ($email): ?>
      <div class="verify-detail">
        <strong>Sent to:</strong> <?= $email ?>
      </div>
    <?php endif; ?>
    <p style="font-size:13px; color:var(--ink-faint);">
      Save your API key securely — it will not be shown again.
    </p>

  <?php else: ?>

    <div class="verify-icon" style="color:var(--error);">&#x2715;</div>
    <h1>Verification failed</h1>
    <p><?= $error ?></p>
    <a href="/register-user.php" class="btn btn--primary" style="display:inline-block;">
      Register again
    </a>

  <?php endif; ?>

</div>
</body>
</html>