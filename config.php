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

// Minimal .env loader — PHP has no built-in equivalent to Python's
// python-dotenv, and this project has no Composer dependencies to pull one
// in, so this is a small hand-rolled one. Only fills in values that aren't
// already set by the real environment, so Docker Compose's `environment:`
// block (or Apache/PHP-FPM env config) always takes precedence — this file
// is a fallback for bare-metal installs, not an override mechanism. Safe
// to call even when .env doesn't exist (e.g. every Docker deployment,
// which sets real environment variables directly and has no use for this
// file at all) — it just does nothing in that case.
(function () {
    $path = __DIR__ . '/.env';
    if (!is_file($path) || !is_readable($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, if present
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key === '' || getenv($key) !== false) continue; // never override a real env var
        putenv("{$key}={$value}");
    }
})();

// API_BASE can be overridden via the API_BASE environment variable (used by
// the Docker Compose setup, where it points at the ephemeral-rest service
// name rather than localhost). Bare-metal deployments that edit this file
// directly, per DOCS/SETUP.md, are unaffected — the literal value below is
// simply the fallback when no environment variable is set.
define('API_BASE',      getenv('API_BASE') ?: 'http://localhost:5000');

// API_PUBLIC_URL is purely cosmetic — it's what gets *shown* to an admin
// (the Dashboard's "Endpoint" field, the API Tester's "Calling..." text),
// as distinct from API_BASE, which is what the portal actually connects
// to under the hood. These are usually the same on a bare-metal install,
// but under Docker Compose, API_BASE is deliberately the internal
// container network address (e.g. http://ephemeral-rest:5000 — fast,
// stays inside the Docker network, never touches nginx/TLS), which is
// correct for the portal's own requests but meaningless to a human
// reading the dashboard. Set API_PUBLIC_URL to your real, public API
// domain (e.g. https://api.yourdomain.com) so the portal *displays* that
// instead, without changing which address it actually calls. Falls back
// to API_BASE if unset, so nothing changes unless you set this.
define('API_PUBLIC_URL', getenv('API_PUBLIC_URL') ?: API_BASE);

define('SITE_NAME',     getenv('SITE_NAME') ?: 'ephemeralREST');

// Fallback version string shown in the sidebar footer — the API doesn't
// have a concept of "portal version" (it's about this codebase, not the
// astrology calculation service), so this stays purely local, unlike the
// settings below that mirror an API-side config value.
define('SITE_VERSION',  getenv('SITE_VERSION') ?: '1.0');

// Session timeout in seconds (30 minutes). Purely a portal concept — the
// API has no equivalent, since it's not a stateful browser session.
define('SESSION_TIMEOUT', (int)(getenv('SESSION_TIMEOUT') ?: 1800));

// Allow administrators to grant or revoke admin access on other keys via
// the portal's Keys page. Set to false to lock down admin promotion
// entirely — useful once your admin keys are established. Also enforced
// server-side by the API's own ALLOW_ADMIN_PROMOTION setting (see
// ephemeralREST's .env) — set both together; this one only controls
// whether the portal's UI offers the option at all, the API's is what
// actually stops the request if someone bypasses the UI.
$_allow_admin_promotion_env = getenv('ALLOW_ADMIN_PROMOTION');
define('ALLOW_ADMIN_PROMOTION', $_allow_admin_promotion_env === false
    ? true
    : !in_array(strtolower(trim($_allow_admin_promotion_env)), ['false', '0', 'no', ''], true));

// URL to redirect to after logout.
// Use a relative path (e.g. '/landing.php') to stay on this server,
// or a full URL (e.g. 'https://myapp.com') to redirect elsewhere.
define('LOGOUT_REDIRECT_URL', getenv('LOGOUT_REDIRECT_URL') ?: '/login.php');

// Timezone for displaying dates
date_default_timezone_set('UTC');