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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
auth_require();

$page_title = 'API Tester';

// ── AJAX proxy ────────────────────────────────────────────────────────────────
// The browser posts here; we forward to the API and return the result with
// timing. This keeps the admin key server-side and avoids CORS issues.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $endpoint    = $input['endpoint']    ?? '';
    $method      = strtoupper($input['method']      ?? 'GET');
    $body        = $input['body']        ?? null;
    $params      = $input['params']      ?? [];
    $path_params = $input['path_params'] ?? [];
    $use_auth    = (bool)($input['auth'] ?? true);

    // Whitelist: check the template path (e.g. /chart/{id}) not the resolved one
    $allowed = array_column(_endpoint_list(), 'path');
    if (!in_array($endpoint, $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'Endpoint not in allowlist']); exit;
    }

    // Substitute path params — replace {key} placeholders with actual values
    $resolved_path = $endpoint;
    foreach ($path_params as $key => $value) {
        $resolved_path = str_replace('{' . $key . '}', rawurlencode($value), $resolved_path);
    }

    // Validate no unresolved placeholders remain
    if (preg_match('/\{[a-z_]+\}/', $resolved_path)) {
        echo json_encode(['ok' => false, 'error' => 'Missing required path parameter']); exit;
    }

    // Build URL, appending any query params
    $clean_params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    $url = API_BASE . $resolved_path . (!empty($clean_params) ? '?' . http_build_query($clean_params) : '');
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($use_auth) $headers[] = 'X-API-Key: ' . auth_key();

    $start = microtime(true);
    $ch    = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HEADER         => true,   // include response headers
    ]);
    if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw      = curl_exec($ch);
    $elapsed  = round((microtime(true) - $start) * 1000);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdr_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo json_encode(['ok' => false, 'error' => 'Connection failed: ' . $curl_err]); exit;
    }

    $raw_headers  = substr($raw, 0, $hdr_size);
    $raw_body     = substr($raw, $hdr_size);
    $decoded      = json_decode($raw_body, true);

    // Parse response headers into an associative array
    $resp_headers = [];
    foreach (explode("\r\n", $raw_headers) as $line) {
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $resp_headers[trim($k)] = trim($v);
        }
    }

    echo json_encode([
        'ok'      => true,
        'status'  => $status,
        'elapsed' => $elapsed,
        'headers' => $resp_headers,
        'body'    => $decoded ?? $raw_body,
        'raw'     => $decoded === null,  // true when body is not JSON
    ]);
    exit;
}

// ── Endpoint definitions ──────────────────────────────────────────────────────
// Each entry describes one testable endpoint. Adding more here automatically
// populates the endpoint list in the UI.
function _endpoint_list(): array {
    return [
        [
            'group'       => 'Health',
            'path'   => '/health',
            'method' => 'GET',
            'label'  => 'Health Check',
            'desc'   => 'Returns API server status, supported house systems, and server time.',
            'auth'        => false,
            'body'        => null,
            'params'      => [],
            'path_params' => [],
        ],
        [
            'group'       => 'Health',
            'path'        => '/cache/stats',
            'method'      => 'GET',
            'label'       => 'Cache Stats',
            'desc'        => 'Returns counts for cached charts, derived charts, places, and place cache activity.',
            'auth'        => false,
            'body'        => null,
            'params'      => [],
            'path_params' => [],
        ],
        [
            'group'       => 'Health',
            'path'        => '/autocomplete',
            'method'      => 'GET',
            'label'       => 'Autocomplete',
            'desc'        => 'Returns place name suggestions for a partial query string. Minimum 2 characters.',
            'auth'        => false,
            'body'        => null,
            'path_params' => [],
            'params'      => [
                [
                    'key'         => 'q',
                    'label'       => 'Search query',
                    'placeholder' => 'e.g. London, UK',
                    'required'    => true,
                    'type'        => 'text',
                    'autorun'     => true,
                    'minlength'   => 3,
                ],
            ],
        ],
        [
            'group'       => 'Calculation',
            'path'        => '/calculate',
            'method'      => 'POST',
            'label'       => 'Calculate Chart',
            'desc'        => 'Calculate a natal or event chart. Returns planetary positions, house cusps, and angles.',
            'auth'        => true,
            'params'      => [],
            'path_params' => [],
            'body'        => [
                'chart_name'   => 'Albert Einstein',
                'datetime'     => '1879-03-14 11:30:00',
                'location'     => 'Ulm, Germany',
                'house_system' => 'placidus',
            ],
        ],
        [
            'group'       => 'Calculation',
            'path'        => '/calculate',
            'method'      => 'POST',
            'label'       => 'Recalculate Chart',
            'desc'        => 'Force-recalculate an existing chart in place, preserving its chart_id. Paste a chart_id from a previous Calculate response into the body.',
            'auth'        => true,
            'params'      => [],
            'path_params' => [],
            'body'        => [
                'chart_name'   => 'Albert Einstein',
                'datetime'     => '1879-03-14 11:30:00',
                'location'     => 'Ulm, Germany',
                'house_system' => 'placidus',
                'recalc'       => true,
                'chart_id'     => '',
            ],
        ],
        [
            'group'       => 'Calculation',
            'path'        => '/chart/{id}',
            'method'      => 'GET',
            'label'       => 'Get Chart',
            'desc'        => 'Retrieve a previously calculated chart by UUID. Paste a chart_id from a Calculate response.',
            'auth'        => true,
            'params'      => [],
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'body'        => null,
        ],
        [
            'path'        => '/chart/{id}/progressions',
            'method'      => 'POST',
            'group'       => 'Calculation',
            'label'       => 'Secondary Progressions',
            'desc'        => 'Calculate secondary progressions (day-for-a-year) for a natal chart to a given date.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params' => [],
            'body'   => [
                'progression_date' => '2026-01-01',
                'house_system'     => 'placidus',
            ],
        ],
        [
            'path'        => '/chart/{id}/solar-arc',
            'method'      => 'POST',
            'group'       => 'Calculation',
            'label'       => 'Solar Arc Directions',
            'desc'        => 'Calculate solar arc directions for a natal chart. All planets are advanced by the Sun\'s progressed arc.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params' => [],
            'body'   => [
                'progression_date' => '2026-01-01',
                'house_system'     => 'placidus',
            ],
        ],
        [
            'path'        => '/chart/{id}/solar-return',
            'method'      => 'POST',
            'group'       => 'Calculation',
            'label'       => 'Solar Return',
            'desc'        => 'Find the exact solar return moment for a given year and cast the return chart. Location defaults to natal if not supplied.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params' => [],
            'body'   => [
                'return_year'  => 2026,
                'house_system' => 'placidus',
            ],
        ],
        [
            'path'        => '/chart/{id}/lunar-return',
            'method'      => 'POST',
            'group'       => 'Calculation',
            'label'       => 'Lunar Return',
            'desc'        => 'Find the exact lunar return moment for a given month and cast the return chart.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params' => [],
            'body'   => [
                'return_year'  => 2026,
                'return_month' => 1,
                'house_system' => 'placidus',
            ],
        ],
        [
            'path'        => '/chart/{id}/derived',
            'method'      => 'GET',
            'group'       => 'Calculation',
            'label'       => 'List Derived Charts',
            'desc'        => 'List all derived charts for a radix chart. Optionally filter by type: progressions, solar_return, lunar_return, solar_arc.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Chart ID',
                    'placeholder' => 'Paste a chart_id UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params' => [
                [
                    'key'         => 'type',
                    'label'       => 'Filter by type',
                    'placeholder' => 'e.g. solar_return',
                    'required'    => false,
                    'type'        => 'text',
                ],
            ],
            'body'        => null,
        ],

        // ── Derived Charts ────────────────────────────────────────────────
        [
            'group'       => 'Derived Charts',
            'path'        => '/derived/{id}',
            'method'      => 'GET',
            'label'       => 'Get Derived Chart',
            'desc'        => 'Retrieve a specific derived chart (progression, return, solar arc) by its UUID.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Derived Chart ID',
                    'placeholder' => 'Paste a derived chart UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params'      => [],
            'body'        => null,
        ],
        [
            'group'       => 'Derived Charts',
            'path'        => '/derived/{id}',
            'method'      => 'DELETE',
            'label'       => 'Delete Derived Chart',
            'desc'        => 'Permanently delete a derived chart by UUID. This cannot be undone.',
            'auth'        => true,
            'path_params' => [
                [
                    'key'         => 'id',
                    'label'       => 'Derived Chart ID',
                    'placeholder' => 'Paste a derived chart UUID',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'params'      => [],
            'body'        => null,
        ],

        // ── Astronomical Events ───────────────────────────────────────────
        [
            'group'       => 'Astronomical Events',
            'path'        => '/apsides',
            'method'      => 'POST',
            'label'       => 'Apsides',
            'desc'        => 'Lunar and planetary apside positions (perigee/apogee, perihelion/aphelion) for a given datetime.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'datetime' => '2026-03-29 12:00:00',
            ],
        ],
        [
            'group'       => 'Astronomical Events',
            'path'        => '/apsides/next',
            'method'      => 'POST',
            'label'       => 'Next Apsides',
            'desc'        => 'Find the next perigee/apogee and perihelion/aphelion events for each body after a reference date.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'reference_date'   => '2026-03-29',
                'max_search_years' => 2,
            ],
        ],
        [
            'group'       => 'Astronomical Events',
            'path'        => '/lunations',
            'method'      => 'POST',
            'label'       => 'Lunations',
            'desc'        => 'Find New Moon, Full Moon, and Quarter events. Use direction (next/previous/both) or supply start_date and end_date for range mode.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'reference_date' => '2026-03-29',
                'direction'      => 'next',
            ],
        ],
        [
            'group'       => 'Astronomical Events',
            'path'        => '/eclipses',
            'method'      => 'POST',
            'label'       => 'Eclipses',
            'desc'        => 'Find solar and lunar eclipses within a time window. Returns type, disc obscuration, magnitude, and Saros series data for each eclipse.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'reference_date' => '2026-03-29',
                'years_ahead'    => 5,
            ],
        ],
        [
            'group'       => 'Astronomical Events',
            'path'        => '/ephemeris',
            'method'      => 'POST',
            'label'       => 'Ephemeris',
            'desc'        => 'Planetary positions at noon UT for every day of a given month. No location required — house cusps not included.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'year'  => 2026,
                'month' => 3,
            ],
        ],

        // ── Self-service ──────────────────────────────────────────────────
        [
            'group'       => 'Self-service',
            'path'        => '/me',
            'method'      => 'GET',
            'label'       => 'My Identity',
            'desc'        => 'Returns the identity, key type, admin flag, and rate limits for the authenticated key.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => null,
        ],
        [
            'group'       => 'Self-service',
            'path'        => '/me/output',
            'method'      => 'GET',
            'label'       => 'My Output Config',
            'desc'        => 'Returns the stored output config overrides for this key and the full effective (merged) configuration.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => null,
        ],
        [
            'group'       => 'Self-service',
            'path'        => '/me/output',
            'method'      => 'POST',
            'label'       => 'Update My Output Config',
            'desc'        => 'Save per-key output config overrides. Pass null to reset to server defaults.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'output_config' => [
                    'heliocentric' => false,
                    'bodies'       => ['asteroids' => false],
                ],
            ],
        ],

        // ── Utility ───────────────────────────────────────────────────────
        [
            'group'       => 'Utility',
            'path'        => '/locations/resolve',
            'method'      => 'POST',
            'label'       => 'Resolve Location',
            'desc'        => 'Resolve a place name to its canonical record with coordinates and timezone.',
            'auth'        => false,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'place_name' => 'Ulm, Germany',
            ],
        ],

        // ── Views ─────────────────────────────────────────────────────────
        [
            'group'       => 'Views',
            'path'        => '/views',
            'method'      => 'POST',
            'label'       => 'Save View',
            'desc'        => 'Save a new opaque JSON blob. Always generates a fresh UUID. Returns the view_id.',
            'auth'        => true,
            'path_params' => [],
            'params'      => [],
            'body'        => [
                'data' => new stdClass(),
            ],
        ],
        [
            'group'       => 'Views',
            'path'        => '/views/{view_id}',
            'method'      => 'PUT',
            'label'       => 'Update View',
            'desc'        => 'Update an existing view blob in place by UUID. Returns 404 if the view does not exist.',
            'auth'        => true,
            'path_params' => [
                ['key' => 'view_id', 'label' => 'View UUID', 'placeholder' => 'e.g. a1b2c3d4-...', 'required' => true, 'type' => 'text'],
            ],
            'params'      => [],
            'body'        => [
                'data' => new stdClass(),
            ],
        ],
        [
            'group'       => 'Views',
            'path'        => '/views',
            'method'      => 'GET',
            'label'       => 'Get View',
            'desc'        => 'Retrieve a saved view by UUID. No authentication required.',
            'auth'        => false,
            'path_params' => [],
            'params'      => [
                [
                    'key'         => 'v',
                    'label'       => 'View UUID',
                    'placeholder' => 'e.g. a1b2c3d4-...',
                    'required'    => true,
                    'type'        => 'text',
                ],
            ],
            'body'        => null,
        ],
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">API Tester</h1>
    <p class="page-subtitle">Run ephemeralREST endpoints and inspect responses</p>
  </div>
  <span class="mono" style="font-size:13px; color:var(--ink-faint);"><?= htmlspecialchars(API_BASE) ?></span>
</div>

<div class="two-col" style="align-items:start; gap:24px;">

  <!-- ── Left: endpoint list with tabs ───────────────────────── -->
  <div style="min-width:0;">
    <?php
    // Build ordered unique group list
    $all_groups = [];
    foreach (_endpoint_list() as $ep) {
        $g = $ep['group'] ?? '';
        if ($g && !in_array($g, $all_groups)) $all_groups[] = $g;
    }
    ?>

    <!-- Group tabs -->
    <div class="tabs" style="margin-bottom:14px; flex-wrap:wrap; gap:4px;">
      <?php foreach ($all_groups as $j => $g): ?>
        <button class="tab <?= $j === 0 ? 'tab--active' : '' ?>"
                data-group="<?= htmlspecialchars($g) ?>"
                onclick="switchTab('<?= htmlspecialchars($g) ?>')">
          <?= htmlspecialchars($g) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- One div per group — only the active one is visible -->
    <?php foreach ($all_groups as $j => $g):
          $group_id = 'group-' . preg_replace('/[^a-zA-Z0-9]/', '-', $g);
    ?>
      <div id="<?= $group_id ?>"
           class="endpoint-group"
           <?= $j > 0 ? 'style="display:none"' : '' ?>>
        <?php foreach (_endpoint_list() as $i => $ep): ?>
          <?php if (($ep['group'] ?? '') === $g): ?>
            <div class="endpoint-card <?= $i === 0 ? 'endpoint-card--active' : '' ?>"
                 id="ep-card-<?= $i ?>"
                 onclick="selectEndpoint(<?= $i ?>)">
              <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <span class="method-badge method-badge--<?= strtolower($ep['method']) ?>">
                  <?= $ep['method'] ?>
                </span>
                <span class="mono" style="font-size:13px; color:var(--ink);">
                  <?= htmlspecialchars($ep['path']) ?>
                </span>
                <?php if (!$ep['auth']): ?>
                  <span style="font-size:11px; color:var(--ink-light); margin-left:auto;">public</span>
                <?php endif; ?>
              </div>
              <div style="font-size:12.5px; color:var(--ink-light); padding-left:54px;">
                <?= htmlspecialchars($ep['desc']) ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

  </div>

  <!-- ── Right: request + response ────────────────────────── -->
  <div style="min-width:0; flex:1;">

    <!-- Request panel -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head">
        <span class="card__title">Request</span>
        <div style="display:flex; align-items:center; gap:10px;">
          <span class="mono" id="req-method-badge" style="font-size:12px; color:var(--ink-light);"></span>
          <span class="mono" id="req-url" style="font-size:12px; color:var(--ink-light);"></span>
        </div>
      </div>
      <div class="card__body">
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
          <div style="font-size:13px; color:var(--ink-light);" id="req-desc"></div>
          <button class="btn btn--primary" id="btn-run" onclick="runEndpoint()" style="margin-left:auto;">
            ▶ Run
          </button>
        </div>

        <!-- Auth indicator — shown when endpoint requires a key -->
        <div id="req-auth-indicator" style="display:none; margin-top:12px;
             font-size:12px; color:var(--ink-light); display:none; align-items:center; gap:6px;">
          <span style="opacity:.5;">🔑</span>
          <span>Authenticated as <strong><?= htmlspecialchars(auth_user()['identifier'] ?? '') ?></strong>
            &nbsp;<span class="mono" style="color:var(--ink-faint); font-size:11px;">
              (<?= htmlspecialchars(substr(auth_key(), 0, 8)) ?>…)
            </span>
          </span>
        </div>

        <!-- Path param inputs — shown for endpoints with {placeholders} in the path -->
        <div id="req-path-params" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);"></div>

        <!-- Query param inputs — shown only for endpoints that define params -->
        <div id="req-params" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);"></div>

        <!-- Body editor — shown for POST/PUT endpoints with a body -->
        <div id="req-body-wrap" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
            <label style="font-size:12px; color:var(--ink-light); font-weight:500;">Request Body</label>
            <span style="font-size:11px; color:var(--ink-faint);">JSON</span>
            <span id="body-valid-indicator" style="font-size:11px; margin-left:auto;"></span>
          </div>
          <textarea id="req-body-editor"
                    rows="10"
                    spellcheck="false"
                    style="width:100%; font-family:var(--font-mono); font-size:12.5px;
                           line-height:1.6; resize:vertical;"
                    oninput="validateBodyEditor()"></textarea>
        </div>
      </div>
    </div>

    <!-- Response panel — hidden until first run -->
    <div class="card" id="response-panel" style="display:none;">
      <div class="card__head">
        <span class="card__title">Response</span>
        <div style="display:flex; align-items:center; gap:14px;">
          <span id="resp-status-badge"></span>
          <span id="resp-time" style="font-size:12px; color:var(--ink-light);"></span>
        </div>
      </div>
      <div class="card__body" style="padding:0;">

        <!-- Response headers (collapsible) -->
        <div id="resp-headers-wrap" style="border-bottom:1px solid var(--border);">
          <button onclick="toggleHeaders()"
                  style="width:100%; text-align:left; padding:10px 20px;
                         background:none; border:none; cursor:pointer;
                         font-size:12px; color:var(--ink-light); display:flex; align-items:center; gap:6px;">
            <span id="headers-chevron">▶</span>
            <span>Response Headers</span>
            <span id="headers-count" style="color:var(--ink-faint);"></span>
          </button>
          <div id="resp-headers" style="display:none; padding:0 20px 14px; overflow-x:auto;">
            <table style="width:100%; font-size:12px; border-collapse:collapse;">
              <tbody id="resp-headers-body"></tbody>
            </table>
          </div>
        </div>

        <!-- Response body -->
        <pre id="resp-body"
             style="margin:0; padding:20px; overflow:auto; font-family:var(--font-mono);
                    font-size:12.5px; line-height:1.7; max-height:600px;
                    background:var(--bg-alt); border-radius:0 0 var(--radius) var(--radius);"></pre>
      </div>
    </div>

    <!-- Running indicator -->
    <div id="running-indicator" style="display:none; text-align:center; padding:32px; color:var(--ink-faint); font-size:13px;">
      Calling <?= htmlspecialchars(API_BASE) ?><span id="running-path"></span>…
    </div>

  </div>
</div>

<style>
.endpoint-card {
  padding: 14px 16px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  margin-bottom: 10px;
  cursor: pointer;
  transition: border-color .15s, background .15s;
  background: var(--bg);
}

.endpoint-card:hover {
  border-color: var(--border-strong);
  background: var(--bg-alt);
}

.endpoint-card--active {
  border-color: var(--gold);
  background: var(--bg-alt);
}

.method-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 9px;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  min-width: 44px;
  flex-shrink: 0;
}

.method-badge--get    { background: #edfaf3; color: #1e7b4b; }
.method-badge--post   { background: #fef8ed; color: #92560a; }
.method-badge--delete { background: #fef0f0; color: #b42424; }
.method-badge--put    { background: #edf2fb; color: #2563ab; }

.endpoint-group {
  display: block;
}

/* Make tab buttons look like the existing portal .tab links */
.tabs button.tab {
  background: none;
  border: none;
  cursor: pointer;
  font-family: var(--font-sans);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 4px;
  font-family: var(--font-mono);
  font-size: 12px;
  font-weight: 600;
}

.status-badge--2xx { background: #edfaf3; color: #1e7b4b; }
.status-badge--4xx { background: #fef8ed; color: #92560a; }
.status-badge--5xx { background: #fef0f0; color: #b42424; }
.status-badge--err { background: #f0f0f0; color: #666;    }

/* JSON syntax highlighting */
.json-key  { color: #2563ab; }
.json-str  { color: #1e7b4b; }
.json-num  { color: #c8a84b; }
.json-bool { color: #7c3aed; }
.json-null { color: #9ca3af; }
</style>

<script>
// ── Endpoint definitions mirrored from PHP ────────────────────────────────────
const ENDPOINTS = <?= json_encode(array_values(_endpoint_list())) ?>;
let activeIndex   = 0;
let activeGroup   = ENDPOINTS[0]?.group || '';
let debounceTimer = null;

function groupId(group) {
  return 'group-' + group.replace(/[^a-zA-Z0-9]/g, '-');
}

function switchTab(group, clearResponse = true) {
  activeGroup = group;

  // Show/hide group panels
  document.querySelectorAll('.endpoint-group').forEach(el => {
    el.style.display = el.id === groupId(group) ? 'block' : 'none';
  });

  // Update tab active state
  document.querySelectorAll('.tabs button.tab').forEach(btn => {
    btn.classList.toggle('tab--active', btn.dataset.group === group);
  });

  if (clearResponse) {
    document.getElementById('response-panel').style.display    = 'none';
    document.getElementById('running-indicator').style.display = 'none';
  }
}

function selectEndpoint(i) {
  const ep = ENDPOINTS[i];

  // Switch tab if this endpoint is in a different group
  if (ep.group && ep.group !== activeGroup) {
    switchTab(ep.group, false);
  }

  // Update active card highlight
  document.querySelectorAll('.endpoint-card').forEach((c, idx) => {
    c.classList.toggle('endpoint-card--active', idx === i);
  });
  activeIndex = i;

  document.getElementById('req-method-badge').textContent = ep.method;
  document.getElementById('req-desc').textContent         = ep.desc;

  // Render param inputs
  const paramsWrap = document.getElementById('req-params');
  paramsWrap.innerHTML = '';
  if (ep.params && ep.params.length > 0) {
    paramsWrap.style.display = 'block';
    ep.params.forEach((p, idx) => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex; align-items:center; gap:10px; margin-bottom:10px;';

      const hint = p.minlength
        ? `<span id="param-hint-${escHtml(p.key)}" style="font-size:11px;color:var(--ink-faint);margin-left:4px;">min ${p.minlength} chars</span>`
        : '';

      row.innerHTML = `
        <label style="font-size:12px; color:var(--ink-light); min-width:90px; flex-shrink:0;">
          ${escHtml(p.label)}${p.required ? ' <span style="color:var(--error)">*</span>' : ''}${hint}
        </label>
        <input type="${p.type || 'text'}"
               id="param-${escHtml(p.key)}"
               data-key="${escHtml(p.key)}"
               data-param-idx="${idx}"
               placeholder="${escHtml(p.placeholder || '')}"
               style="flex:1; font-family:var(--font-mono); font-size:13px;">`;
      paramsWrap.appendChild(row);

      // Attach the right input handler after the element exists
      document.getElementById('param-' + p.key).addEventListener('input', () => {
        updateUrlDisplay();
        handleParamInput(p);
      });
    });
  } else {
    paramsWrap.style.display = 'none';
  }

  updateUrlDisplay();

  // Auth indicator
  const authEl = document.getElementById('req-auth-indicator');
  authEl.style.display = ep.auth ? 'flex' : 'none';

  // Path param inputs
  const pathParamsWrap = document.getElementById('req-path-params');
  pathParamsWrap.innerHTML = '';
  if (ep.path_params && ep.path_params.length > 0) {
    pathParamsWrap.style.display = 'block';
    const heading = document.createElement('div');
    heading.style.cssText = 'font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-light); margin-bottom:10px;';
    heading.textContent = 'Path Parameters';
    pathParamsWrap.appendChild(heading);
    for (const p of ep.path_params) {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex; align-items:center; gap:10px; margin-bottom:10px;';
      row.innerHTML = `
        <label style="font-size:12px; color:var(--ink-light); min-width:90px; flex-shrink:0;">
          ${escHtml(p.label)}${p.required ? ' <span style="color:var(--error)">*</span>' : ''}
        </label>
        <input type="${p.type || 'text'}"
               id="path-param-${escHtml(p.key)}"
               data-key="${escHtml(p.key)}"
               placeholder="${escHtml(p.placeholder || '')}"
               style="flex:1; font-family:var(--font-mono); font-size:13px;"
               oninput="updateUrlDisplay()">`;
      pathParamsWrap.appendChild(row);
    }
  } else {
    pathParamsWrap.style.display = 'none';
  }

  // Body editor
  const bodyWrap   = document.getElementById('req-body-wrap');
  const bodyEditor = document.getElementById('req-body-editor');
  if (ep.body) {
    bodyEditor.value = JSON.stringify(ep.body, null, 2);
    bodyWrap.style.display = 'block';
    validateBodyEditor();
  } else {
    bodyWrap.style.display = 'none';
    bodyEditor.value = '';
  }

  // Hide previous response
  document.getElementById('response-panel').style.display    = 'none';
  document.getElementById('running-indicator').style.display = 'none';
}

function updateUrlDisplay() {
  const ep         = ENDPOINTS[activeIndex];
  const pathParams = collectPathParams();
  const queryParams = collectParams();

  // Substitute path params into the template
  let resolvedPath = ep.path;
  for (const [k, v] of Object.entries(pathParams)) {
    resolvedPath = resolvedPath.replace(`{${k}}`, encodeURIComponent(v));
  }

  const qs = Object.keys(queryParams).length
    ? '?' + Object.entries(queryParams).map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')
    : '';
  document.getElementById('req-url').textContent = '<?= htmlspecialchars(API_BASE) ?>' + resolvedPath + qs;
}

function collectPathParams() {
  const ep     = ENDPOINTS[activeIndex];
  const result = {};
  for (const p of (ep.path_params || [])) {
    const el = document.getElementById('path-param-' + p.key);
    if (el && el.value.trim() !== '') result[p.key] = el.value.trim();
  }
  return result;
}

function collectParams() {
  const ep     = ENDPOINTS[activeIndex];
  const result = {};
  for (const p of (ep.params || [])) {
    const el = document.getElementById('param-' + p.key);
    if (el && el.value.trim() !== '') result[p.key] = el.value.trim();
  }
  return result;
}

function handleParamInput(paramDef) {
  if (!paramDef.autorun) return;

  clearTimeout(debounceTimer);

  const el  = document.getElementById('param-' + paramDef.key);
  const val = el ? el.value.trim() : '';
  const min = paramDef.minlength || 1;

  if (val.length < min) return;  // wait until minimum length is met

  debounceTimer = setTimeout(runEndpoint, 500);
}

function validateBodyEditor() {
  const indicator = document.getElementById('body-valid-indicator');
  const val       = document.getElementById('req-body-editor').value.trim();
  if (!val) { indicator.textContent = ''; return true; }
  try {
    JSON.parse(val);
    indicator.textContent = '✓ valid JSON';
    indicator.style.color = 'var(--success)';
    return true;
  } catch(e) {
    indicator.textContent = '✗ ' + e.message;
    indicator.style.color = 'var(--error)';
    return false;
  }
}

async function runEndpoint() {
  const ep      = ENDPOINTS[activeIndex];
  const runBtn  = document.getElementById('btn-run');
  const panel   = document.getElementById('response-panel');
  const running = document.getElementById('running-indicator');

  // Validate required path params
  const pathParams = collectPathParams();
  for (const p of (ep.path_params || [])) {
    if (p.required && !pathParams[p.key]) {
      showError(`'${p.label}' is required.`);
      document.getElementById('path-param-' + p.key)?.focus();
      return;
    }
  }

  // Validate required query params and minlength
  const params = collectParams();
  for (const p of (ep.params || [])) {
    if (p.required && !params[p.key]) {
      showError(`'${p.label}' is required.`);
      document.getElementById('param-' + p.key)?.focus();
      return;
    }
    if (p.minlength && params[p.key] && params[p.key].length < p.minlength) {
      showError(`'${p.label}' must be at least ${p.minlength} characters.`);
      document.getElementById('param-' + p.key)?.focus();
      return;
    }
  }

  clearTimeout(debounceTimer);

  // Parse body editor if present
  let requestBody = ep.body || null;
  const bodyEditor = document.getElementById('req-body-editor');
  if (ep.body && bodyEditor.value.trim()) {
    if (!validateBodyEditor()) {
      showError('Fix the JSON in the request body before running.');
      bodyEditor.focus();
      return;
    }
    try {
      requestBody = JSON.parse(bodyEditor.value.trim());
    } catch(e) {
      showError('Invalid JSON in request body: ' + e.message);
      return;
    }
  }

  document.getElementById('running-path').textContent = ep.path;
  panel.style.display   = 'none';
  running.style.display = 'block';
  runBtn.disabled       = true;
  runBtn.textContent    = '…';

  try {
    const res  = await fetch('/api-tester.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    JSON.stringify({
        endpoint:    ep.path,
        method:      ep.method,
        auth:        ep.auth !== false,
        path_params: pathParams,
        params,
        body:        requestBody,
      }),
    });
    const data = await res.json();

    running.style.display = 'none';

    if (!data.ok) {
      showError(data.error || 'Request failed');
      return;
    }

    renderResponse(data);
    panel.style.display = 'block';

  } catch(e) {
    running.style.display = 'none';
    showError('Network error: ' + e.message);
  } finally {
    runBtn.disabled     = false;
    runBtn.textContent  = '▶ Run';
  }
}

function renderResponse(data) {
  // Status badge
  const statusEl  = document.getElementById('resp-status-badge');
  const statusCls = data.status >= 500 ? '5xx'
                  : data.status >= 400 ? '4xx'
                  : data.status >= 200 ? '2xx' : 'err';
  statusEl.className   = `status-badge status-badge--${statusCls}`;
  statusEl.textContent = data.status + ' ' + httpStatusText(data.status);

  // Time
  document.getElementById('resp-time').textContent = data.elapsed + ' ms';

  // Headers table
  const tbody = document.getElementById('resp-headers-body');
  tbody.innerHTML = '';
  const hdrs = data.headers || {};
  const hdrKeys = Object.keys(hdrs);
  document.getElementById('headers-count').textContent = `(${hdrKeys.length})`;
  for (const k of hdrKeys) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="padding:3px 12px 3px 0; color:var(--ink-light); white-space:nowrap; vertical-align:top;">${escHtml(k)}</td>
      <td style="padding:3px 0; color:var(--ink); word-break:break-all;">${escHtml(hdrs[k])}</td>`;
    tbody.appendChild(tr);
  }

  // Body
  const bodyEl = document.getElementById('resp-body');
  if (data.raw) {
    bodyEl.textContent = data.body;
  } else {
    bodyEl.innerHTML = syntaxHighlight(JSON.stringify(data.body, null, 2));
  }
}

function showError(msg) {
  const panel = document.getElementById('response-panel');
  document.getElementById('resp-status-badge').className   = 'status-badge status-badge--err';
  document.getElementById('resp-status-badge').textContent = 'Error';
  document.getElementById('resp-time').textContent         = '';
  document.getElementById('resp-headers-body').innerHTML   = '';
  document.getElementById('headers-count').textContent     = '';
  document.getElementById('resp-body').textContent         = msg;
  panel.style.display = 'block';
}

function toggleHeaders() {
  const el  = document.getElementById('resp-headers');
  const chv = document.getElementById('headers-chevron');
  const vis = el.style.display === 'block';
  el.style.display  = vis ? 'none' : 'block';
  chv.textContent   = vis ? '▶' : '▼';
}

// ── JSON syntax highlighter ───────────────────────────────────────────────────
function syntaxHighlight(json) {
  return json.replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
    match => {
      let cls = 'json-num';
      if (/^"/.test(match)) {
        cls = /:$/.test(match) ? 'json-key' : 'json-str';
      } else if (/true|false/.test(match)) {
        cls = 'json-bool';
      } else if (/null/.test(match)) {
        cls = 'json-null';
      }
      return `<span class="${cls}">${escHtml(match)}</span>`;
    }
  );
}

function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function httpStatusText(code) {
  const map = {
    200:'OK', 201:'Created', 204:'No Content',
    400:'Bad Request', 401:'Unauthorized', 403:'Forbidden',
    404:'Not Found', 409:'Conflict', 429:'Too Many Requests',
    500:'Internal Server Error', 502:'Bad Gateway', 503:'Service Unavailable',
  };
  return map[code] || '';
}

// ── Init ──────────────────────────────────────────────────────────────────────
switchTab(ENDPOINTS[0]?.group || '', false);
selectEndpoint(0);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
