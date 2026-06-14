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
//  ephemeralADMIN — Session Auth Helpers
// ─────────────────────────────────────────────────────────────────────────────
//
// Login flow:
//   1. POST /login with email + password (+ trusted-device cookie if present)
//        - {"must_change_password": true}  → redirect to set-password.php
//        - {"2fa_required": true}          → redirect to 2fa.php
//        - {identity..., "api_key": "..."} → logged in immediately (trusted device)
//   2. POST /login/2fa with email + code (+ remember_device)
//        - {identity..., "api_key": "...", "device_token": "..."} → logged in,
//          device_token stored as a cookie for future trusted-device logins
//
// The decrypted API key is stored server-side in $_SESSION['user']['api_key']
// and used for all subsequent my_api_* calls. It is never sent to the browser.

if (session_status() === PHP_SESSION_NONE) session_start();

// Cookie name for the trusted-device token
const TRUSTED_DEVICE_COOKIE = 'epht_device';

/**
 * Read the device-token map from the cookie.
 * Returns an array of [ email => token, ... ].
 */
function device_token_map(): array
{
    $raw = $_COOKIE[TRUSTED_DEVICE_COOKIE] ?? '';
    if (!$raw) return [];
    $map = json_decode($raw, true);
    return is_array($map) ? $map : [];
}

/**
 * Write an updated device-token map back to the cookie.
 */
function device_token_map_save(array $map): void
{
    $days = (int)portal_setting('trusted_device_days', 28);
    setcookie(TRUSTED_DEVICE_COOKIE, json_encode($map), [
        'expires'  => time() + ($days * 86400),
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Get the device token for a specific email address, or null if not present.
 */
function device_token_for(string $email): ?string
{
    $map = device_token_map();
    return $map[strtolower($email)] ?? null;
}

/**
 * Step 1 of login: email + password.
 *
 * Returns an array describing what happened:
 *   ['state' => 'must_change_password', 'email' => ...]
 *   ['state' => '2fa_required',         'email' => ...]
 *   ['state' => 'logged_in',            'user' => [...]]
 *   ['state' => 'error',                'message' => ...]
 */
function auth_attempt_login(string $email, string $password): array
{
    $body = ['email' => $email, 'password' => $password];

    $device_token = device_token_for($email);
    if ($device_token) {
        $body['device_token'] = $device_token;
    }

    $result = api_post('/login', $body, false);

    if (!$result['ok']) {
        $message = $result['data']['error'] ?? 'Invalid email or password.';
        return ['state' => 'error', 'message' => $message];
    }

    $data = $result['data'];

    if (!empty($data['must_change_password'])) {
        // Stash the email temporarily so set-password.php knows who this is
        // for the "current password" flow (token-based resets don't need this).
        $_SESSION['pending_email'] = $email;
        return ['state' => 'must_change_password', 'email' => $email];
    }

    if (!empty($data['2fa_required'])) {
        $_SESSION['pending_email'] = $email;
        return ['state' => '2fa_required', 'email' => $email];
    }

    // Trusted device — logged in directly
    auth_set_session($data);
    unset($_SESSION['pending_email']);
    return ['state' => 'logged_in', 'user' => $data];
}

/**
 * Step 2 of login: email + 2FA code.
 *
 * Returns:
 *   ['state' => 'logged_in', 'user' => [...]]
 *   ['state' => 'error',     'message' => ...]
 *
 * On success, if the API issued a device_token (remember_device was set),
 * stores it as a long-lived cookie so future logins skip 2FA.
 */
function auth_verify_2fa(string $email, string $code, bool $remember_device = false): array
{
    $result = api_post('/login/2fa', [
        'email'           => $email,
        'code'            => $code,
        'remember_device' => $remember_device,
    ], false);

    if (!$result['ok']) {
        $message = $result['data']['error'] ?? 'Invalid or expired verification code.';
        return ['state' => 'error', 'message' => $message];
    }

    $data = $result['data'];

    auth_set_session($data);
    unset($_SESSION['pending_email']);

    if (!empty($data['device_token'])) {
        $map = device_token_map();
        $map[strtolower($email)] = $data['device_token'];
        device_token_map_save($map);
    }

    return ['state' => 'logged_in', 'user' => $data];
}

/**
 * Store the identity + decrypted API key returned by /login or /login/2fa
 * into the session.
 */
function auth_set_session(array $data): void
{
    $_SESSION['user']       = $data;
    $_SESSION['logged_in']  = true;
    $_SESSION['login_time'] = time();
}

/**
 * Check if the current session is authenticated.
 */
function auth_check(): bool
{
    if (empty($_SESSION['logged_in'])) return false;

    // Timeout check
    if (time() - ($_SESSION['login_time'] ?? 0) > (int)portal_setting('session_timeout', 1800)) {
        auth_logout();
        return false;
    }

    // Refresh user data from the API on every request so that role/admin
    // changes (e.g. admin promotion, key disable) take effect immediately
    // without requiring a re-login. /me is authenticated with the API key
    // already stored in the session.
    $result = my_api_get('/me');

    if ($result['ok']) {
        $data = $result['data'];
        if (!empty($data['identifier'])) {
            // Preserve the decrypted api_key — /me does not return it
            $data['api_key'] = $_SESSION['user']['api_key'] ?? null;
            $_SESSION['user'] = $data;
        }
    } elseif (in_array($result['status'], [401, 403], true)) {
        // Key has been disabled, deleted, or rotated elsewhere — force logout
        auth_logout();
        return false;
    }

    $_SESSION['login_time'] = time(); // rolling timeout
    return true;
}

/**
 * Require authentication — redirect to login if not logged in.
 * Optionally restrict to a role: 'admin'.
 * Also redirects to /setup.php if the database is empty.
 */
function auth_require(string $role = null): void
{
    // Check setup before anything else
    $setup_check = api_get('/setup/status', false);
    if ($setup_check['ok'] && !empty($setup_check['data']['setup_required'])) {
        header('Location: /setup.php');
        exit;
    }

    if (!auth_check()) {
        header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    if ($role === 'admin' && !auth_is_admin()) {
        header('Location: /login.php?error=access_denied');
        exit;
    }
}

/**
 * Log out: clear session and forget this device (revoke the trusted-device
 * token both locally and on the API, and remove the cookie).
 */
function auth_logout(): void
{
    // Intentionally do NOT revoke or clear the trusted-device cookie on
    // normal logout. The cookie identifies "this machine has previously
    // authenticated here" — that fact remains true after logging out.
    // The cookie (and the server-side token) will expire naturally after
    // TRUSTED_DEVICE_COOKIE_DAYS days.
    //
    // To explicitly forget a device (e.g. "sign out everywhere"), call
    // auth_forget_device() separately before auth_logout().

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Explicitly revoke the trusted-device token for this browser.
 * Called when the user actively chooses "forget this device".
 */
function auth_forget_device(): void
{
    $email = strtolower($_SESSION['user']['identifier'] ?? '');
    if (!$email) return;

    $device_token = device_token_for($email);

    if ($device_token && !empty($_SESSION['user']['api_key'])) {
        my_api_post('/me/forget-device', ['device_token' => $device_token]);
    }

    // Remove just this email's token from the map, leaving others intact
    $map = device_token_map();
    unset($map[$email]);

    if (empty($map)) {
        // No tokens left — clear the cookie entirely
        setcookie(TRUSTED_DEVICE_COOKIE, '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        device_token_map_save($map);
    }
}

function auth_user(): ?array  { return $_SESSION['user'] ?? null; }
function auth_key(): string   { return $_SESSION['user']['api_key'] ?? ''; }
function auth_is_admin(): bool { return !empty($_SESSION['user']['admin']); }

/**
 * The email address pending verification during the must-change-password
 * or 2FA steps (set by auth_attempt_login, cleared on success/failure).
 */
function auth_pending_email(): ?string
{
    return $_SESSION['pending_email'] ?? null;
}

/**
 * Make an API call using the logged-in user's key.
 */
function my_api_get(string $endpoint): array
{
    $url     = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json', 'X-API-Key: ' . auth_key()];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => json_decode($raw, true) ?? []];
}

function my_api_delete(string $endpoint): array
{
    $url     = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json', 'X-API-Key: ' . auth_key()];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_TIMEOUT => 10]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => json_decode($raw, true) ?? []];
}

function my_api_post(string $endpoint, array $body = []): array
{
    $url     = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json', 'X-API-Key: ' . auth_key()];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_TIMEOUT => 10]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => json_decode($raw, true) ?? []];
}
