<?php
/**
 * ephemeralADMIN — Administration portal for ephemeralREST
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * MIT License — see LICENSE for full text.
 */

// ─────────────────────────────────────────────────────────────────────────────
// ephemeralADMIN — Federation Test Harness helpers
// ─────────────────────────────────────────────────────────────────────────────
//
// Unlike includes/api.php's api_request() (always talks to this portal's own
// ephemeralREST instance at API_BASE, always uses the logged-in user's own
// session key), these helpers talk to an ARBITRARY federated service's base
// URL, with an ARBITRARY API key pasted in by the admin for this one test —
// there's no way to look up a key's plaintext value after creation (by
// design — see key_crypto.py), so this can never be a "pick from your keys"
// dropdown, only a paste-in field.
//
// Extended (searchREST integration) to support a raw JSON request body and a
// per-endpoint timeout: marketrest.json's endpoints were all GET or bodyless
// POST, so the original version never needed either. searchREST's /search is
// both — a JSON body AND a long-running SSE response — so both gaps needed
// closing rather than working around per-service.

const FEDERATION_CONFIG_DIR = '/data/federation';

// Endpoints with no explicit "timeout_seconds" in their config fall back to
// this. Kept short deliberately — most endpoints are quick, and a slow
// default makes every accidental infinite-hang endpoint annoying to notice.
// Long-running endpoints (like searchREST's /search) should set their own
// timeout_seconds explicitly rather than relying on a raised default here.
const FEDERATION_DEFAULT_TIMEOUT_SECONDS = 15;

/**
 * Discover all federated-service test configs.
 * Returns an array of ['slug' => ..., 'display_name' => ..., 'description' => ...],
 * sorted by display_name. Malformed JSON files are skipped, not fatal —
 * one bad config shouldn't break the whole nav menu.
 *
 * @return array<int, array{slug: string, display_name: string, description: string}>
 */
function federation_list_services(): array
{
    if (!is_dir(FEDERATION_CONFIG_DIR)) {
        return [];
    }

    $services = [];
    foreach (glob(FEDERATION_CONFIG_DIR . '/*.json') ?: [] as $path) {
        $config = federation_load_config_file($path);
        if ($config === null) {
            continue;
        }
        $services[] = [
            'slug'         => $config['service']      ?? basename($path, '.json'),
            'display_name' => $config['display_name'] ?? $config['service'] ?? basename($path, '.json'),
            'description'  => $config['description']  ?? '',
        ];
    }

    usort($services, fn($a, $b) => strcasecmp($a['display_name'], $b['display_name']));
    return $services;
}

/**
 * Load one service's full config by slug (matches the filename, not
 * necessarily the "service" field inside it, though they should agree).
 * Returns null if the file doesn't exist or fails to parse — caller is
 * responsible for showing a clear error, not assuming a config exists.
 */
function federation_load_service(string $slug): ?array
{
    // Reject anything that isn't a bare filename-safe slug before it ever
    // touches the filesystem — this comes from $_GET, so path traversal
    // (../../etc/passwd) attempts must be refused outright, not merely
    // "probably fine because glob() elsewhere only lists .json files".
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
        return null;
    }

    return federation_load_config_file(FEDERATION_CONFIG_DIR . '/' . $slug . '.json');
}

function federation_load_config_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['service'], $data['base_url'], $data['endpoints'])) {
        return null;
    }
    return $data;
}

/**
 * Fire a request at a federated service using an admin-supplied key.
 * Path params are already substituted into $path by the caller — this
 * function just sends the request and reports back raw timing/status/body.
 *
 * $requestBody, when non-null, is sent as-is as the request body with
 * Content-Type: application/json — the caller (federation-test.php) is
 * responsible for validating it's actually valid JSON first, so a malformed
 * paste-in doesn't get sent upstream only to fail there instead.
 *
 * @return array{ok: bool, status: int, headers: array, body: string, body_json: ?array, time_ms: float, error: ?string}
 */
function federation_send_request(
    string $method,
    string $baseUrl,
    string $path,
    ?string $apiKey,
    array $queryParams = [],
    ?string $requestBody = null,
    ?int $timeoutSeconds = null
): array {
    $url = rtrim($baseUrl, '/') . $path;
    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $headers = ['Accept: application/json'];
    if ($apiKey !== null && $apiKey !== '') {
        $headers[] = 'X-API-Key: ' . $apiKey;
    }
    if ($requestBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => $timeoutSeconds ?? FEDERATION_DEFAULT_TIMEOUT_SECONDS,
        CURLOPT_HEADER         => true,
    ];
    if ($requestBody !== null) {
        $opts[CURLOPT_POSTFIELDS] = $requestBody;
    }
    curl_setopt_array($ch, $opts);

    $start    = microtime(true);
    $raw      = curl_exec($ch);
    $timeMs   = (microtime(true) - $start) * 1000;
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSz = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'ok' => false, 'status' => 0, 'headers' => [], 'body' => '',
            'body_json' => null, 'time_ms' => $timeMs,
            'error' => 'Connection failed: ' . $error,
        ];
    }

    $rawHeaders = substr($raw, 0, $headerSz);
    $body       = substr($raw, $headerSz);
    $bodyJson   = json_decode($body, true);

    return [
        'ok'        => $status >= 200 && $status < 300,
        'status'    => $status,
        'headers'   => federation_parse_headers($rawHeaders),
        // Not JSON for an SSE endpoint like searchREST's POST /search —
        // body_json will correctly come back null for those, and the caller
        // falls back to showing this raw text (the full concatenated event
        // stream) instead. No special-casing needed: curl blocks until the
        // connection closes either way, and SSE responses do close once the
        // server sends its final event, so the full stream is captured here
        // same as any other response — just not shown as it arrives.
        'body'      => $body,
        'body_json' => is_array($bodyJson) ? $bodyJson : null,
        'time_ms'   => $timeMs,
        'error'     => null,
    ];
}

function federation_parse_headers(string $raw): array
{
    $lines   = explode("\r\n", trim($raw));
    $headers = [];
    foreach (array_slice($lines, 1) as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $headers[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $headers;
}

/**
 * Substitute {param} placeholders in an endpoint path template with
 * user-supplied values, URL-encoding each value individually so a ticker
 * or folder name containing e.g. a space or slash can't corrupt the path.
 */
function federation_build_path(string $template, array $values): string
{
    return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($values) {
        return rawurlencode($values[$m[1]] ?? '');
    }, $template);
}