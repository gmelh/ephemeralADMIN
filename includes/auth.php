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

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Validate an API key against the /me endpoint and store identity in session.
 * Returns the user array on success, or null on failure.
 */
function auth_login(string $api_key): ?array
{
    $url     = API_BASE . '/me';
    $headers = [
        'Content-Type: application/json',
        'X-API-Key: ' . $api_key,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) return null;

    $data = json_decode($raw, true);
    if (!$data || empty($data['identifier'])) return null;

    // Store in session
    $_SESSION['api_key']    = $api_key;
    $_SESSION['user']       = $data;
    $_SESSION['logged_in']  = true;
    $_SESSION['login_time'] = time();

    return $data;
}

/**
 * Check if the current session is authenticated.
 */
function auth_check(): bool
{
    if (empty($_SESSION['logged_in'])) return false;

    // Timeout check
    if (time() - ($_SESSION['login_time'] ?? 0) > SESSION_TIMEOUT) {
        auth_logout();
        return false;
    }

    $_SESSION['login_time'] = time(); // rolling timeout
    return true;
}

/**
 * Require authentication — redirect to login if not logged in.
 * Optionally restrict to a role: 'admin', 'domain', 'user'
 */
function auth_require(string $role = null): void
{
    if (!auth_check()) {
        header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    if ($role === 'admin' && !auth_is_admin()) {
        header('Location: /login.php?error=access_denied');
        exit;
    }
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_user(): ?array  { return $_SESSION['user'] ?? null; }
function auth_key(): string   { return $_SESSION['api_key'] ?? ''; }
function auth_is_admin(): bool { return !empty($_SESSION['user']['admin']); }
function auth_is_domain(): bool { return ($_SESSION['user']['key_type'] ?? '') === 'domain'; }
function auth_is_user(): bool  { return ($_SESSION['user']['key_type'] ?? '') === 'user'; }

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
