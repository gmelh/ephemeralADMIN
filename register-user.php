<?php
/**
 * ephemeralREST — Swiss Ephemeris REST API
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * AGPL v3 is used to maintain licensing compatibility with the Swiss Ephemeris
 * library by Astrodienst AG, which is itself licensed under the AGPL v3.
 * See https://www.astro.com/swisseph/ for details.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';

$page_title = 'Register User';

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
        $result = api_post('/register/user', [
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

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Register as a User</h1>
    <p class="page-subtitle">Get a personal API key for direct or desktop access</p>
  </div>
</div>

<?php if ($success): ?>

  <div class="card" style="max-width:520px;">
    <div class="card__body" style="text-align:center; padding:40px 32px;">
      <div style="font-size:36px; margin-bottom:16px;">✉</div>
      <h2 style="font-family:var(--font-serif); font-size:24px; font-weight:400; margin-bottom:12px;">
        Check Your Email
      </h2>
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:8px;">
        We've sent a verification link to
        <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong>.
      </p>
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:24px;">
        Click the link to activate your API key. It will be shown once — please save it securely.
        The link expires in 24 hours.
      </p>
      <p style="font-size:13px; color:var(--ink-faint);">
        Didn't receive it? Check your spam folder, or
        <a href="/register-user.php">try again</a>.
      </p>
    </div>
  </div>

<?php else: ?>

  <div class="two-col" style="align-items:start; gap:48px;">

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
                  A verification link will be sent here. Your email becomes your API key identifier.
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
            <li>Your API key is shown once on that page — save it securely.</li>
            <li>Use it in the <code>X-API-Key</code> header on every request.</li>
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
          <a href="/register-domain.php" class="btn btn--ghost btn--sm">Register a Domain →</a>
        </div>
      </div>
    </div>

  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
