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
// Public portal config
// ─────────────────────────────────────────────────────────────────────────────
// site_name and trusted_device_days are config-only on the API side (see
// ephemeralREST's .env — SITE_NAME, TRUSTED_DEVICE_DAYS), never editable
// from within either portal. This fetches them from the public
// GET /public-config endpoint, which works identically whether or not the
// visitor is logged in — deliberately the single mechanism every page uses
// (pre-login pages like login.php/2fa.php included), so there's no risk of
// one code path showing a different value than another. Session-cached so
// a visitor's session doesn't re-call the API on every page load.

/**
 * Fetch and cache both public config values in one call.
 */
function public_portal_config(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!empty($_SESSION['public_config'])) {
        return $_SESSION['public_config'];
    }

    $defaults = [
        'site_name'             => defined('SITE_NAME') ? SITE_NAME : 'ephemeralREST',
        'trusted_device_days'   => 28,
        'portal_url'            => '',
        'allow_admin_promotion' => true,
    ];

    $result = api_get('/public-config', false);
    $config = $defaults;
    if ($result['ok']) {
        $name = trim((string)($result['data']['site_name'] ?? ''));
        if ($name !== '') $config['site_name'] = $name;

        $days = $result['data']['trusted_device_days'] ?? null;
        if (is_numeric($days) && (int)$days > 0) $config['trusted_device_days'] = (int)$days;

        if (isset($result['data']['portal_url'])) {
            $config['portal_url'] = trim((string)$result['data']['portal_url']);
        }
        if (isset($result['data']['allow_admin_promotion'])) {
            $config['allow_admin_promotion'] = (bool)$result['data']['allow_admin_promotion'];
        }
    }

    $_SESSION['public_config'] = $config;
    return $config;
}

/** Site name — shown in every page's title/header, logged in or not. */
function site_name_public(): string
{
    return public_portal_config()['site_name'];
}

/** Days a "remember this device" cookie/token stays valid — API-authoritative. */
function trusted_device_days_public(): int
{
    return public_portal_config()['trusted_device_days'];
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