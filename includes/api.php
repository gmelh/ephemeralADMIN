<?php
/**
 * ephemeralADMIN — Administration portal for ephemeralREST
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * MIT License — see LICENSE for full text.
 */

// ─────────────────────────────────────────────────────────────────────────────
// ephemeralADMIN — API Client
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Make a request to the ephemeralREST API.
 *
 * Authentication uses the logged-in user's API key from the session
 * (set by auth_set_session() on login). For public endpoints (login,
 * register, setup/status etc.) pass $auth = false.
 *
 * @param  string      $method   HTTP method
 * @param  string      $endpoint Path including leading slash
 * @param  array|null  $body     Request body (JSON-encoded)
 * @param  bool        $auth     Include session API key header
 * @return array       ['ok' => bool, 'status' => int, 'data' => array]
 */
function api_request(string $method, string $endpoint, ?array $body = null, bool $auth = true): array
{
    $url     = API_BASE . $endpoint;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];

    if ($auth) {
        // Use the logged-in user's key from the session.
        // auth_key() is defined in auth.php; it returns '' when not logged in,
        // in which case the API will return 401 as expected.
        $key = function_exists('auth_key') ? auth_key() : ($_SESSION['user']['api_key'] ?? '');
        if ($key) {
            $headers[] = 'X-API-Key: ' . $key;
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Connection failed: ' . $error]];
    }

    $data = json_decode($raw, true) ?? ['error' => 'Invalid JSON response'];

    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'data'   => $data,
    ];
}

function api_get(string $endpoint, bool $auth = true): array
{
    return api_request('GET', $endpoint, null, $auth);
}

function api_post(string $endpoint, array $body = [], bool $auth = true): array
{
    return api_request('POST', $endpoint, $body, $auth);
}

function api_delete(string $endpoint, bool $auth = true): array
{
    return api_request('DELETE', $endpoint, null, $auth);
}

// ─────────────────────────────────────────────────────────────────────────────
// Portal settings
// ─────────────────────────────────────────────────────────────────────────────
// Settings are fetched from GET /admin/portal-settings and cached in the
// PHP session for the duration of the session. Call portal_settings_reload()
// after updating settings to refresh the cache.

/**
 * Built-in defaults — used when the API is unreachable or the user is not
 * yet logged in (e.g. setup page, login page).
 */
function portal_settings_defaults(): array
{
    return [
        'site_name'             => defined('SITE_NAME') ? SITE_NAME : 'ephemeralREST',
        'site_version'          => '1.0',
        'session_timeout'       => 1800,
        'logout_redirect_url'   => '/login.php',
        'allow_admin_promotion' => true,
        'trusted_device_days'   => 28,
        'portal_url'            => '',
    ];
}

/**
 * Return portal settings, loading from the API if not already cached.
 * Falls back to built-in defaults if not logged in or API is unreachable.
 */
function portal_settings_get(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!empty($_SESSION['portal_settings'])) {
        return $_SESSION['portal_settings'];
    }

    return portal_settings_reload();
}

/**
 * Force a fresh fetch of portal settings from the API and update the cache.
 */
function portal_settings_reload(): array
{
    $defaults = portal_settings_defaults();

    // Only fetch from API if the user is logged in
    if (empty($_SESSION['logged_in'])) {
        return $defaults;
    }

    $result = api_get('/admin/portal-settings');

    if ($result['ok'] && !empty($result['data']['settings'])) {
        $settings = array_merge($defaults, $result['data']['settings']);
        $_SESSION['portal_settings'] = $settings;
        return $settings;
    }

    return $defaults;
}

/**
 * Get a single portal setting value.
 */
function portal_setting(string $key, mixed $default = null): mixed
{
    $settings = portal_settings_get();
    return $settings[$key] ?? $default;
}

/**
 * Site name for pre-login/public pages (landing.php) — the full
 * portal_setting() family above deliberately never calls the API before
 * login, so it can't be used here. Backed by the public GET /branding
 * endpoint instead, which exposes only the site name and nothing else
 * from portal_settings. Session-cached like portal_settings_get() is, so
 * a visitor's session doesn't re-call the API on every page load.
 */
function site_name_public(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!empty($_SESSION['public_site_name'])) {
        return $_SESSION['public_site_name'];
    }

    $default = defined('SITE_NAME') ? SITE_NAME : 'ephemeralREST';
    $result  = api_get('/branding', false);
    $name    = $result['ok'] ? trim((string)($result['data']['site_name'] ?? '')) : '';

    $_SESSION['public_site_name'] = $name !== '' ? $name : $default;
    return $_SESSION['public_site_name'];
}

// ─────────────────────────────────────────────────────────────────────────────
// Utility helpers
// ─────────────────────────────────────────────────────────────────────────────

function status_badge(string $status): string
{
    return match ($status) {
        'pending'  => 'badge badge--warning',
        'approved' => 'badge badge--success',
        'rejected' => 'badge badge--error',
        'active'   => 'badge badge--success',
        'disabled' => 'badge badge--error',
        default    => 'badge',
    };
}

function fmt_date(string $dt): string
{
    try {
        return (new DateTime($dt))->format('d M Y, H:i') . ' UTC';
    } catch (Exception $e) {
        return $dt;
    }
}

function truncate(string $str, int $len = 20): string
{
    return strlen($str) > $len ? substr($str, 0, $len) . '…' : $str;
}

function flash_set(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}