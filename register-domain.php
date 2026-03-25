<!--
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
-->

<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';

$page_title = 'Register Domain';

$success = false;
$errors  = [];
$result  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain        = trim($_POST['domain']        ?? '');
    $name          = trim($_POST['name']          ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $reason        = trim($_POST['reason']        ?? '');

    // Basic client-side validation
    if (!$domain) $errors['domain'] = 'Domain is required.';
    if (!$name)   $errors['name']   = 'Contact name is required.';
    if (!$contact_email) $errors['contact_email'] = 'Contact email is required.';
    elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL))
        $errors['contact_email'] = 'Please enter a valid email address.';

    if (empty($errors)) {
        $result = api_post('/register/domain', [
            'domain'        => $domain,
            'name'          => $name,
            'contact_email' => $contact_email,
            'reason'        => $reason ?: null,
        ], false); // no admin auth — public endpoint

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
    <h1 class="page-title">Register a Domain</h1>
    <p class="page-subtitle">Request an API key for your application or service</p>
  </div>
</div>

<?php if ($success): ?>

  <div class="card" style="max-width:560px;">
    <div class="card__body" style="text-align:center; padding:40px 32px;">
      <div style="font-size:36px; margin-bottom:16px;">✦</div>
      <h2 style="font-family:var(--font-serif); font-size:24px; font-weight:400; margin-bottom:12px;">
        Request Received
      </h2>
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:8px;">
        Your registration request for
        <strong><?= htmlspecialchars($_POST['domain'] ?? '') ?></strong>
        has been submitted.
      </p>
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:24px;">
        We'll send a confirmation to
        <strong><?= htmlspecialchars($_POST['contact_email'] ?? '') ?></strong>
        once it's been reviewed. This usually takes 1–2 business days.
      </p>
      <a href="/register-domain.php" class="btn btn--secondary">Register Another</a>
    </div>
  </div>

<?php else: ?>

  <div class="two-col" style="align-items:start; gap:48px;">

    <div class="card">
      <div class="card__head"><span class="card__title">Registration Details</span></div>
      <div class="card__body">

        <?php if (!empty($errors['_global'])): ?>
          <div class="alert alert--warning" style="margin-bottom:20px;">
            <?= htmlspecialchars($errors['_global']) ?>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="form-grid form-grid--full" style="gap:18px;">

            <div class="form-group">
              <label for="domain">Domain <span style="color:var(--error)">*</span></label>
              <input type="text" id="domain" name="domain"
                     value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>"
                     placeholder="myapp.com"
                     autocomplete="off" required>
              <?php if (!empty($errors['domain'])): ?>
                <span class="form-error"><?= htmlspecialchars($errors['domain']) ?></span>
              <?php else: ?>
                <span class="form-hint">The domain or hostname your application runs on.</span>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="name">Contact Name <span style="color:var(--error)">*</span></label>
              <input type="text" id="name" name="name"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                     placeholder="Jane Smith" required>
              <?php if (!empty($errors['name'])): ?>
                <span class="form-error"><?= htmlspecialchars($errors['name']) ?></span>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="contact_email">Contact Email <span style="color:var(--error)">*</span></label>
              <input type="email" id="contact_email" name="contact_email"
                     value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>"
                     placeholder="jane@myapp.com" required>
              <?php if (!empty($errors['contact_email'])): ?>
                <span class="form-error"><?= htmlspecialchars($errors['contact_email']) ?></span>
              <?php else: ?>
                <span class="form-hint">Your API key will be emailed here once approved.</span>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label for="reason">Intended Use <span class="label-hint">optional</span></label>
              <textarea id="reason" name="reason"
                        placeholder="Briefly describe what you're building and how you'll use the API…"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            </div>

          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Submit Request</button>
            <span style="font-size:13px; color:var(--ink-faint);">
              Requests are reviewed within 1–2 business days.
            </span>
          </div>
        </form>
      </div>
    </div>

    <!-- Info sidebar -->
    <div>
      <div class="card">
        <div class="card__head"><span class="card__title">What Happens Next</span></div>
        <div class="card__body">
          <ol style="font-size:14px; color:var(--ink-light); line-height:1.8; padding-left:18px; margin:0;">
            <li>Your request is reviewed by our team.</li>
            <li>You'll receive a confirmation email immediately.</li>
            <li>Once approved, your API key is emailed to you — it's shown only once, so keep it safe.</li>
            <li>Include it in every API request as the <code>X-API-Key</code> header.</li>
          </ol>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <div class="card__head"><span class="card__title">Individual Access</span></div>
        <div class="card__body">
          <p style="font-size:14px; color:var(--ink-light); margin-bottom:14px;">
            Need a personal API key for desktop or direct access?
          </p>
          <a href="/register-user.php" class="btn btn--ghost btn--sm">Register as a User →</a>
        </div>
      </div>
    </div>

  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
