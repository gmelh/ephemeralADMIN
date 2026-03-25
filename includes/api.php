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
// Astro API Admin — API Client
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Make a request to the Astro API.
 *
 * @param  string      $method   HTTP method (GET, POST, DELETE)
 * @param  string      $endpoint Path including leading slash e.g. '/calculate'
 * @param  array|null  $body     Request body (will be JSON-encoded)
 * @param  bool        $auth     Whether to include the admin API key header
 * @return array       ['ok' => bool, 'status' => int, 'data' => array]
 */
function api_request(string $method, string $endpoint, array $body = null, bool $auth = true): array
{
    $url = API_BASE . $endpoint;

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($auth) {
        $headers[] = 'X-API-Key: ' . ADMIN_API_KEY;
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

/**
 * Return a CSS class based on a status string.
 */
function status_badge(string $status): string
{
    return match ($status) {
        'pending'  => 'badge badge--warning',
        'approved' => 'badge badge--success',
        'rejected' => 'badge badge--error',
        'active'   => 'badge badge--success',
        'disabled' => 'badge badge--error',
        'domain'   => 'badge badge--blue',
        'user'     => 'badge badge--purple',
        default    => 'badge',
    };
}

/**
 * Format a UTC datetime string for display.
 */
function fmt_date(string $dt): string
{
    try {
        return (new DateTime($dt))->format('d M Y, H:i') . ' UTC';
    } catch (Exception $e) {
        return $dt;
    }
}

/**
 * Truncate a string and add ellipsis.
 */
function truncate(string $str, int $len = 20): string
{
    return strlen($str) > $len ? substr($str, 0, $len) . '…' : $str;
}

/**
 * Flash message helpers using session.
 */
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
