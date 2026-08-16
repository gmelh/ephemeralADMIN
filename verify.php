<?php
/**
 * verify.php — Email verification landing page
 *
 * Reached via redirect from the API's GET /register/verify endpoint.
 * The API does the actual verification work and redirects here with:
 *
 *   ?status=verified  &email=...   &message=...  — success
 *   ?status=error     &message=...               — failure
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

$token  = trim($_GET['t']      ?? '');
$status = trim($_GET['status'] ?? '');

if ($token !== '') {
    // Arrived directly from the verification email — call the API
    $result = api_get('/register/verify?t=' . urlencode($token), false);

    if ($result['ok']) {
        $success = true;
        $email   = htmlspecialchars($result['data']['email'] ?? '');
    } else {
        $error = htmlspecialchars(
            $result['data']['error'] ?? 'Verification failed. The link may be invalid or expired.'
        );
    }

} elseif ($status === 'verified') {
    // Redirected here by the API (legacy path / fallback)
    $success = true;
    $email   = htmlspecialchars(trim($_GET['email'] ?? ''));

} elseif ($status === 'error') {
    $error = htmlspecialchars(trim($_GET['message'] ?? 'Verification failed. The link may be invalid or expired.'));

} else {
    $error = 'No verification token was provided. Please use the link from your email.';
}

$site_name = site_name_public();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $success ? 'Email Verified' : 'Verification Failed' ?> — <?= htmlspecialchars($site_name) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold:    #ffd700;
      --accent:  #0066cc;
      --accent-text:#70b8ff;
      --error:   #b42424;
      --success: #2f7d3a;
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
        <?= $success ? 'rgba(47,125,58,.35) 0%, rgba(47,125,58,.12) 18%, rgba(47,125,58,.03)' : 'rgba(180,36,36,.25) 0%, rgba(180,36,36,.08) 18%, rgba(180,36,36,.02)' ?> 32%,
        transparent 44%
      );
      pointer-events: none;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 36px;
      text-decoration: none;
      position: relative;
      z-index: 1;
    }

    .brand__star { font-size: 22px; color: var(--gold); }
    .brand__name { font-family: 'Instrument Sans', sans-serif; font-size: 20px; color: #f0ede8; }

    .card {
      background: #1f1f1d;
      border-radius: 12px;
      width: 100%;
      max-width: 420px;
      overflow: hidden;
      position: relative;
      z-index: 1;
    }

    .card__head {
      padding: 32px 32px 24px;
      border-bottom: 1px solid #2e2e2c;
      background: #252523;
      text-align: center;
    }

    .card__icon {
      font-size: 40px;
      margin-bottom: 14px;
      display: block;
      line-height: 1;
    }

    .card__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 24px;
      font-weight: 400;
      color: #e2e5ea;
      margin-bottom: 8px;
    }

    .card__desc {
      font-size: 13.5px;
      color: #8b95a8;
      line-height: 1.55;
    }

    .card__desc strong { color: #e2e5ea; }

    .card__body { padding: 28px 32px 32px; }

    .info-row {
      display: flex;
      flex-direction: column;
      gap: 6px;
      background: #141412;
      border: 1px solid #2e2e2c;
      border-radius: 6px;
      padding: 14px 16px;
      margin-bottom: 20px;
      font-size: 13px;
    }

    .info-row__label { color: #565f70; }
    .info-row__value { color: #e2e5ea; font-family: 'DM Mono', monospace; font-size: 12.5px; }

    .btn {
      display: block;
      width: 100%;
      padding: 11px 24px;
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

    .btn--primary { background: #0066cc; color: #fff; }
    .btn--primary:hover { background: #3a7bbf; }

    .step-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 24px;
    }

    .step-list li {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      font-size: 13.5px;
      color: #8b95a8;
      line-height: 1.5;
    }

    .step-num {
      flex-shrink: 0;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: #2e2e2c;
      color: var(--gold);
      font-size: 11px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 1px;
    }

    .error-box {
      background: #1e0a0a;
      border: 1px solid #5c1a1a;
      border-radius: 6px;
      padding: 14px 16px;
      font-size: 13.5px;
      color: #f5a0a0;
      line-height: 1.55;
      margin-bottom: 20px;
    }

    .back-link {
      margin-top: 28px;
      font-size: 13px;
      color: rgba(255,255,255,.3);
      text-decoration: none;
      position: relative;
      z-index: 1;
      transition: color .2s;
    }

    .back-link:hover { color: rgba(255,255,255,.6); }
  </style>
</head>
<body>

<a href="/landing.php" class="brand">
  <span class="brand__star">✦</span>
  <span class="brand__name"><?= htmlspecialchars($site_name) ?></span>
</a>

<div class="card">

  <?php if ($success): ?>

    <div class="card__head">
      <span class="card__icon">✓</span>
      <h1 class="card__title">Email verified</h1>
      <p class="card__desc">
        <?php if ($email): ?>
          <strong><?= $email ?></strong> has been confirmed.
        <?php else: ?>
          Your email address has been confirmed.
        <?php endif; ?>
      </p>
    </div>

    <div class="card__body">
      <ul class="step-list">
        <li>
          <span class="step-num">✓</span>
          <span>Email verified</span>
        </li>
        <li>
          <span class="step-num">2</span>
          <span>Check your inbox — we've sent a link to set your password</span>
        </li>
        <li>
          <span class="step-num">3</span>
          <span>Once your password is set, sign in to your account</span>
        </li>
      </ul>

      <a href="/login.php" class="btn btn--primary">Go to Sign In →</a>
    </div>

  <?php else: ?>

    <div class="card__head">
      <span class="card__icon">✗</span>
      <h1 class="card__title">Verification failed</h1>
      <p class="card__desc">We couldn't verify your email address.</p>
    </div>

    <div class="card__body">
      <div class="error-box"><?= $error ?></div>
      <p style="font-size:13px; color:#565f70; margin-bottom:20px; line-height:1.55;">
        Verification links expire after 24 hours and can only be used once.
        If your link has expired, you can register again to receive a new one.
      </p>
      <a href="/register-user.php" class="btn btn--primary">Register Again →</a>
    </div>

  <?php endif; ?>

</div>

<a href="/login.php" class="back-link">← Back to sign in</a>

</body>
</html>