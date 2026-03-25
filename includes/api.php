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
// ephemeralAdmin — API Client
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Make a request to the ephemeralREST API.
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
