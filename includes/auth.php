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

// ─────────────────────────────────────────────────────────────────────────────
// Astro API Admin — Session Auth Helpers
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
