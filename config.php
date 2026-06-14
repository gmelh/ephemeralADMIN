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
// ephemeralADMIN — Configuration
// ─────────────────────────────────────────────────────────────────────────────

define('API_BASE',      'http://localhost:5000');
define('ADMIN_API_KEY', 'ValmHbEInrg91NDiH53v9Cu2LW8xc6ILM1R73gXgggZ');
define('SITE_NAME',     'ephemeralREST');
define('SITE_VERSION',  '1.0');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Allow administrators to grant or revoke admin access on other keys.
// Set to false to lock down admin promotion entirely — useful once your
// admin keys are established and you don't want the portal to be able
// to create new admins.
define('ALLOW_ADMIN_PROMOTION', true);

// URL to redirect to after logout.
// Use a relative path (e.g. '/landing.php') to stay on this server,
// or a full URL (e.g. 'https://myapp.com') to redirect elsewhere.
define('LOGOUT_REDIRECT_URL', '/login.php');

// Number of days a "remember this device" cookie remains valid, allowing
// login to skip the 2FA email step on recognised machines. This should
// normally match the API's TRUSTED_DEVICE_DAYS setting — the API is the
// final authority on token expiry, but the cookie's Max-Age should agree
// with it so the cookie doesn't outlive (or disappear long before) the
// token it represents.
define('TRUSTED_DEVICE_COOKIE_DAYS', 28);

// Timezone for displaying dates
date_default_timezone_set('UTC');