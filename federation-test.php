<?php
/**
 * ephemeralADMIN — Administration portal for ephemeralREST
 * Copyright (C) 2026  ephemeralREST contributors
 *
 * MIT License — see LICENSE for full text.
 */

// See DOCS/FEDERATION.md (this repo) for the config schema and
// includes/federation.php for the shared request/discovery logic.
//
// The key field below is a plain paste-in, not a dropdown of "your keys" —
// API keys are never retrievable in plaintext after creation (by design),
// so there is no list to build a dropdown from. Paste the same key you'd
// hand to `curl -H "X-API-Key: ..."`.
//
// UI intentionally mirrors api-tester.php's group-tabs + endpoint-cards
// pattern (same CSS classes: .tabs/.tab/.tab--active, .endpoint-card,
// .method-badge, .status-badge) rather than the plain <select> this page
// used before — this page's config is JSON-driven (arbitrary services),
// api-tester.php's is one hardcoded PHP array for ephemeralREST itself, so
// the two aren't literally shared code, but the visual/interaction pattern
// is copied deliberately so this doesn't feel like a different tool.
//
// One schema difference kept AS-IS rather than "fixed" to match
// api-tester.php: marketrest.json (already in production) uses a single
// `params` array with an `"in": "path"` field per param, not api-tester's
// separate `path_params`/`params` arrays — changing that now would break
// the already-deployed marketrest.json. Only searchREST-specific additions
// (`group`, `body`) were free to adopt api-tester.php's convention, since
// no existing config already committed to a different shape for those.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/federation.php';
auth_require();

if (!auth_is_admin()) {
    http_response_code(403);
    die('Admin access required.');
}

// ── AJAX proxy ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input       = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug        = $input['service']      ?? '';
    $endpointIdx = $input['endpoint_idx'] ?? null;
    $pathParams  = $input['path_params']  ?? [];
    $queryParams = $input['query_params'] ?? [];
    $apiKey      = $input['api_key']      ?? '';

    $config = federation_load_service($slug);
    if ($config === null) {
        echo json_encode(['ok' => false, 'error' => 'Unknown or unreadable service config.']);
        exit;
    }

    $endpoints = $config['endpoints'] ?? [];
    if (!is_int($endpointIdx) && !ctype_digit((string)$endpointIdx)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid endpoint selection.']);
        exit;
    }
    $endpoint = $endpoints[(int)$endpointIdx] ?? null;
    if ($endpoint === null) {
        echo json_encode(['ok' => false, 'error' => "Endpoint not found in this service's config."]);
        exit;
    }

    foreach ($endpoint['params'] ?? [] as $param) {
        if (($param['in'] ?? 'path') === 'path'
            && ($param['required'] ?? false)
            && trim($pathParams[$param['name']] ?? '') === '') {
            echo json_encode(['ok' => false, 'error' => "Missing required parameter: {$param['name']}"]);
            exit;
        }
    }

    // Body arrives already-decoded (it's a nested value in $input, which is
    // itself the result of json_decode()'ing the whole request) — no
    // separate JSON-syntax validation needed here the way a raw pasted
    // string would; the client's own JSON.parse() already caught that
    // before this request was even sent (see validateBodyEditor() below).
    // Still enforced server-side: an endpoint whose config declares a
    // `body` example must actually receive one, same as required path
    // params are enforced above rather than trusted to the client alone.
    $requestBody = null;
    if (array_key_exists('body', $input) && $input['body'] !== null) {
        $requestBody = json_encode($input['body']);
    }
    if (!empty($endpoint['body']) && $requestBody === null) {
        echo json_encode(['ok' => false, 'error' => 'This endpoint requires a JSON request body.']);
        exit;
    }

    $resolvedPath = federation_build_path($endpoint['path'], $pathParams);
    $cleanQuery   = array_filter($queryParams, fn($v) => $v !== '' && $v !== null);

    $result = federation_send_request(
        $endpoint['method'],
        $config['base_url'],
        $resolvedPath,
        $apiKey !== '' ? $apiKey : null,
        $cleanQuery,
        $requestBody,
        $endpoint['timeout_seconds'] ?? null
    );

    echo json_encode($result);
    exit;
}

// ── Normal page load ──────────────────────────────────────────────────────────

$services       = federation_list_services();
$selectedSlug   = $_GET['service'] ?? '';
$selectedConfig = $selectedSlug !== '' ? federation_load_service($selectedSlug) : null;

$page_title = $selectedConfig
    ? 'Test: ' . $selectedConfig['display_name']
    : 'Federation Test Harness';

require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
  <p class="page-subtitle">Send test requests to federated services using any API key</p>
</div>

<?php if (empty($services)): ?>

  <div class="empty-state">
    No federated service configs found under <code><?= htmlspecialchars(FEDERATION_CONFIG_DIR) ?></code>.
    Drop a <code>&lt;service-slug&gt;.json</code> file there to add one — see
    <code>DOCS/FEDERATION.md</code> for the config format. No rebuild or
    restart needed; it's picked up on the next page load.
  </div>

<?php elseif ($selectedConfig === null): ?>

  <div class="card-grid">
    <?php foreach ($services as $svc): ?>
      <a class="card card--link" href="/federation-test.php?service=<?= urlencode($svc['slug']) ?>">
        <h3><?= htmlspecialchars($svc['display_name']) ?></h3>
        <p><?= htmlspecialchars($svc['description']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

<?php else:
  $endpoints = $selectedConfig['endpoints'];

  // Ordered unique group list — endpoints without a "group" fall into
  // "General" rather than being dropped, so an incomplete config still
  // renders something usable instead of silently hiding endpoints.
  $all_groups = [];
  foreach ($endpoints as $ep) {
      $g = $ep['group'] ?? 'General';
      if (!in_array($g, $all_groups, true)) {
          $all_groups[] = $g;
      }
  }
?>

  <div class="two-col" style="align-items:start; gap:24px;" data-service="<?= htmlspecialchars($selectedSlug) ?>">

    <!-- ── Left: group tabs + numbered endpoint cards ─────────────── -->
    <div style="min-width:0;">

      <div class="tabs" style="margin-bottom:14px; flex-wrap:wrap; gap:4px;">
        <?php foreach ($all_groups as $j => $g): ?>
          <button class="tab <?= $j === 0 ? 'tab--active' : '' ?>"
                  data-group="<?= htmlspecialchars($g) ?>"
                  onclick="fedSwitchTab('<?= htmlspecialchars($g) ?>')">
            <?= htmlspecialchars($g) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($all_groups as $j => $g):
            $group_id = 'fed-group-' . preg_replace('/[^a-zA-Z0-9]/', '-', $g);
            $position = 0;
      ?>
        <div id="<?= $group_id ?>" class="endpoint-group" <?= $j > 0 ? 'style="display:none"' : '' ?>>
          <?php foreach ($endpoints as $i => $ep):
                if (($ep['group'] ?? 'General') !== $g) continue;
                $position++;
          ?>
            <div class="endpoint-card <?= $i === 0 ? 'endpoint-card--active' : '' ?>"
                 id="fed-ep-card-<?= $i ?>"
                 onclick="fedSelectEndpoint(<?= $i ?>)">
              <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <span class="mono" style="font-size:11px; color:var(--ink-faint); min-width:16px;"><?= $position ?>.</span>
                <span class="method-badge method-badge--<?= strtolower($ep['method']) ?>">
                  <?= htmlspecialchars($ep['method']) ?>
                </span>
                <span class="mono" style="font-size:13px; color:var(--ink);">
                  <?= htmlspecialchars($ep['path']) ?>
                </span>
                <?php if (empty($ep['auth_required'])): ?>
                  <span style="font-size:11px; color:var(--ink-light); margin-left:auto;">public</span>
                <?php endif; ?>
              </div>
              <div style="font-size:12.5px; color:var(--ink-light); padding-left:38px;">
                <?= htmlspecialchars($ep['description'] ?? '') ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

    </div>

    <!-- ── Right: request + response ────────────────────────────── -->
    <div style="min-width:0; flex:1;">

      <div class="card" style="margin-bottom:16px;">
        <div class="card__head">
          <span class="card__title">Request</span>
          <div style="display:flex; align-items:center; gap:10px;">
            <span class="mono" id="fed-req-method-badge" style="font-size:12px; color:var(--ink-light);"></span>
            <span class="mono" id="fed-req-url" style="font-size:12px; color:var(--ink-light);"></span>
          </div>
        </div>
        <div class="card__body">
          <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <div style="font-size:13px; color:var(--ink-light);" id="fed-req-desc"></div>
            <button class="btn btn--primary" id="fed-btn-run" onclick="fedRunEndpoint()" style="margin-left:auto;">
              ▶ Run
            </button>
          </div>

          <div class="form-group" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
            <label for="fed-api-key">X-API-Key</label>
            <input type="text" id="fed-api-key" placeholder="Paste a real key granted access to this service">
            <p class="form-hint">
              Not stored, not looked up — pasted fresh each time. There's no
              way for this portal to retrieve a key's plaintext value after
              creation, so this can't be a dropdown of your own keys.
            </p>
          </div>

          <div id="fed-req-params" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);"></div>

          <div id="fed-req-body-wrap" style="display:none; margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
              <label style="font-size:12px; color:var(--ink-light); font-weight:500;">Request Body</label>
              <span style="font-size:11px; color:var(--ink-faint);">JSON</span>
              <span id="fed-body-valid-indicator" style="font-size:11px; margin-left:auto;"></span>
            </div>
            <textarea id="fed-req-body-editor"
                      rows="8"
                      spellcheck="false"
                      style="width:100%; font-family:var(--font-mono); font-size:12.5px;
                             line-height:1.6; resize:vertical;"
                      oninput="fedValidateBodyEditor()"></textarea>
          </div>
        </div>
      </div>

      <div class="card" id="fed-response-panel" style="display:none;">
        <div class="card__head">
          <span class="card__title">Response</span>
          <div style="display:flex; align-items:center; gap:14px;">
            <span id="fed-resp-status-badge"></span>
            <span id="fed-resp-time" style="font-size:12px; color:var(--ink-light);"></span>
          </div>
        </div>
        <div class="card__body" style="padding:0;">
          <div id="fed-resp-headers-wrap" style="border-bottom:1px solid var(--border);">
            <button onclick="fedToggleHeaders()"
                    style="width:100%; text-align:left; padding:10px 20px;
                           background:none; border:none; cursor:pointer;
                           font-size:12px; color:var(--ink-light); display:flex; align-items:center; gap:6px;">
              <span id="fed-headers-chevron">▶</span>
              <span>Response Headers</span>
              <span id="fed-headers-count" style="color:var(--ink-faint);"></span>
            </button>
            <div id="fed-resp-headers" style="display:none; padding:0 20px 14px; overflow-x:auto;">
              <table style="width:100%; font-size:12px; border-collapse:collapse;">
                <tbody id="fed-resp-headers-body"></tbody>
              </table>
            </div>
          </div>
          <pre id="fed-resp-body"
               style="margin:0; padding:20px; overflow:auto; font-family:var(--font-mono);
                      font-size:12.5px; line-height:1.7; max-height:600px;
                      background:var(--bg-alt); border-radius:0 0 var(--radius) var(--radius);"></pre>
        </div>
      </div>

      <div id="fed-running-indicator" style="display:none; text-align:center; padding:32px; color:var(--ink-faint); font-size:13px;">
        Calling <span id="fed-running-url"></span>…
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
  .endpoint-card:hover { border-color: var(--border-strong); background: var(--bg-alt); }
  .endpoint-card--active { border-color: var(--accent); background: var(--bg-alt); }

  .method-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 3px 9px; border-radius: 4px;
    font-family: var(--font-mono); font-size: 11px; font-weight: 600;
    min-width: 44px; flex-shrink: 0;
  }
  .method-badge--get    { background: #edfaf3; color: #1e7b4b; }
  .method-badge--post   { background: #fef8ed; color: #92560a; }
  .method-badge--delete { background: #fef0f0; color: #b42424; }
  .method-badge--put    { background: #edf2fb; color: #2563ab; }

  .endpoint-group { display: block; }

  .tabs button.tab {
    background: none; border: none; cursor: pointer; font-family: var(--font-sans);
  }

  .status-badge {
    display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 4px;
    font-family: var(--font-mono); font-size: 12px; font-weight: 600;
  }
  .status-badge--2xx { background: #edfaf3; color: #1e7b4b; }
  .status-badge--4xx { background: #fef8ed; color: #92560a; }
  .status-badge--5xx { background: #fef0f0; color: #b42424; }
  .status-badge--err { background: #f0f0f0; color: #666;    }

  .json-key  { color: var(--accent-text); }
  .json-str  { color: #1e7b4b; }
  .json-num  { color: #ffd700; }
  .json-bool { color: #7c3aed; }
  .json-null { color: #9ca3af; }
  </style>

  <script>
  (function () {
    const config = <?= json_encode($selectedConfig) ?>;
    const ENDPOINTS = config.endpoints;
    let activeIndex = 0;
    let activeGroup = ENDPOINTS[0]?.group || 'General';
    let debounceTimer = null;

    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function groupId(group) { return 'fed-group-' + group.replace(/[^a-zA-Z0-9]/g, '-'); }

    window.fedSwitchTab = function (group, clearResponse = true) {
      activeGroup = group;
      document.querySelectorAll('.endpoint-group').forEach(el => {
        el.style.display = el.id === groupId(group) ? 'block' : 'none';
      });
      document.querySelectorAll('.tabs button.tab').forEach(btn => {
        btn.classList.toggle('tab--active', btn.dataset.group === group);
      });
      if (clearResponse) {
        document.getElementById('fed-response-panel').style.display = 'none';
        document.getElementById('fed-running-indicator').style.display = 'none';
      }
    };

    function collectPathParams() {
      const ep = ENDPOINTS[activeIndex];
      const result = {};
      for (const p of (ep.params || [])) {
        if ((p.in || 'path') !== 'path') continue;
        const el = document.getElementById('fed-param-' + p.name);
        if (el && el.value.trim() !== '') result[p.name] = el.value.trim();
      }
      return result;
    }

    function updateUrlDisplay() {
      const ep = ENDPOINTS[activeIndex];
      const pathParams = collectPathParams();
      let resolvedPath = ep.path;
      for (const [k, v] of Object.entries(pathParams)) {
        resolvedPath = resolvedPath.replace(`{${k}}`, encodeURIComponent(v));
      }
      document.getElementById('fed-req-url').textContent = config.base_url + resolvedPath;
    }

    window.fedSelectEndpoint = function (i) {
      const ep = ENDPOINTS[i];

      if ((ep.group || 'General') !== activeGroup) {
        window.fedSwitchTab(ep.group || 'General', false);
      }

      document.querySelectorAll('.endpoint-card').forEach((c, idx) => {
        c.classList.toggle('endpoint-card--active', idx === i);
      });
      activeIndex = i;

      document.getElementById('fed-req-method-badge').textContent = ep.method;
      document.getElementById('fed-req-desc').textContent = ep.description || '';

      const paramsWrap = document.getElementById('fed-req-params');
      paramsWrap.innerHTML = '';
      const pathParams = (ep.params || []).filter(p => (p.in || 'path') === 'path');
      if (pathParams.length > 0) {
        paramsWrap.style.display = 'block';
        const heading = document.createElement('div');
        heading.style.cssText = 'font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-light); margin-bottom:10px;';
        heading.textContent = 'Path Parameters';
        paramsWrap.appendChild(heading);
        pathParams.forEach(p => {
          const row = document.createElement('div');
          row.style.cssText = 'display:flex; align-items:center; gap:10px; margin-bottom:10px;';
          row.innerHTML = `
            <label style="font-size:12px; color:var(--ink-light); min-width:120px; flex-shrink:0;">
              ${escHtml(p.name)}${p.required ? ' <span style="color:var(--error)">*</span>' : ''}
            </label>
            <input type="text" id="fed-param-${escHtml(p.name)}" data-key="${escHtml(p.name)}"
                   placeholder="${escHtml(p.placeholder || '')}"
                   style="flex:1; font-family:var(--font-mono); font-size:13px;"
                   oninput="fedUpdateUrl()">`;
          paramsWrap.appendChild(row);
        });
      } else {
        paramsWrap.style.display = 'none';
      }

      const bodyWrap = document.getElementById('fed-req-body-wrap');
      const bodyEditor = document.getElementById('fed-req-body-editor');
      if (ep.body) {
        bodyEditor.value = JSON.stringify(ep.body, null, 2);
        bodyWrap.style.display = 'block';
        window.fedValidateBodyEditor();
      } else {
        bodyWrap.style.display = 'none';
        bodyEditor.value = '';
      }

      updateUrlDisplay();
      document.getElementById('fed-response-panel').style.display = 'none';
      document.getElementById('fed-running-indicator').style.display = 'none';
    };

    window.fedUpdateUrl = updateUrlDisplay;

    window.fedValidateBodyEditor = function () {
      const indicator = document.getElementById('fed-body-valid-indicator');
      const val = document.getElementById('fed-req-body-editor').value.trim();
      if (!val) { indicator.textContent = ''; return true; }
      try {
        JSON.parse(val);
        indicator.textContent = '✓ valid JSON';
        indicator.style.color = 'var(--success)';
        return true;
      } catch (e) {
        indicator.textContent = '✗ ' + e.message;
        indicator.style.color = 'var(--error)';
        return false;
      }
    };

    window.fedToggleHeaders = function () {
      const el = document.getElementById('fed-resp-headers');
      const chv = document.getElementById('fed-headers-chevron');
      const vis = el.style.display === 'block';
      el.style.display = vis ? 'none' : 'block';
      chv.textContent = vis ? '▶' : '▼';
    };

    function syntaxHighlight(json) {
      return json.replace(
        /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
        match => {
          let cls = 'json-num';
          if (/^"/.test(match)) cls = /:$/.test(match) ? 'json-key' : 'json-str';
          else if (/true|false/.test(match)) cls = 'json-bool';
          else if (/null/.test(match)) cls = 'json-null';
          return `<span class="${cls}">${escHtml(match)}</span>`;
        }
      );
    }

    function httpStatusText(code) {
      const map = {200:'OK',201:'Created',204:'No Content',400:'Bad Request',401:'Unauthorized',
        403:'Forbidden',404:'Not Found',409:'Conflict',422:'Unprocessable Entity',429:'Too Many Requests',
        500:'Internal Server Error',502:'Bad Gateway',503:'Service Unavailable'};
      return map[code] || '';
    }

    function showError(msg) {
      const panel = document.getElementById('fed-response-panel');
      document.getElementById('fed-resp-status-badge').className = 'status-badge status-badge--err';
      document.getElementById('fed-resp-status-badge').textContent = 'Error';
      document.getElementById('fed-resp-time').textContent = '';
      document.getElementById('fed-resp-headers-body').innerHTML = '';
      document.getElementById('fed-headers-count').textContent = '';
      document.getElementById('fed-resp-body').textContent = msg;
      panel.style.display = 'block';
    }

    function renderResponse(data) {
      const statusEl = document.getElementById('fed-resp-status-badge');
      const statusCls = data.status >= 500 ? '5xx' : data.status >= 400 ? '4xx' : data.status >= 200 ? '2xx' : 'err';
      statusEl.className = `status-badge status-badge--${statusCls}`;
      statusEl.textContent = data.status + ' ' + httpStatusText(data.status);
      document.getElementById('fed-resp-time').textContent = Math.round(data.time_ms) + ' ms';

      const tbody = document.getElementById('fed-resp-headers-body');
      tbody.innerHTML = '';
      const hdrs = data.headers || {};
      const hdrKeys = Object.keys(hdrs);
      document.getElementById('fed-headers-count').textContent = `(${hdrKeys.length})`;
      for (const k of hdrKeys) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td style="padding:3px 12px 3px 0; color:var(--ink-light); white-space:nowrap; vertical-align:top;">${escHtml(k)}</td>
                         <td style="padding:3px 0; color:var(--ink); word-break:break-all;">${escHtml(hdrs[k])}</td>`;
        tbody.appendChild(tr);
      }

      const bodyEl = document.getElementById('fed-resp-body');
      if (data.body_json) {
        bodyEl.innerHTML = syntaxHighlight(JSON.stringify(data.body_json, null, 2));
      } else {
        // Not JSON — e.g. searchREST's POST /search returns an SSE stream.
        // Shown as raw text: the harness is a blocking proxy (see
        // includes/federation.php), so this is the FULL stream, captured
        // only once the connection closes, not a live view.
        bodyEl.textContent = data.body || data.error || '(empty response)';
      }
    }

    window.fedRunEndpoint = async function () {
      const ep = ENDPOINTS[activeIndex];
      const runBtn = document.getElementById('fed-btn-run');
      const panel = document.getElementById('fed-response-panel');
      const running = document.getElementById('fed-running-indicator');

      const pathParams = collectPathParams();
      for (const p of (ep.params || [])) {
        if ((p.in || 'path') === 'path' && p.required && !pathParams[p.name]) {
          showError(`'${p.name}' is required.`);
          document.getElementById('fed-param-' + p.name)?.focus();
          return;
        }
      }

      clearTimeout(debounceTimer);

      let requestBody = ep.body || null;
      const bodyEditor = document.getElementById('fed-req-body-editor');
      if (ep.body && bodyEditor.value.trim()) {
        if (!window.fedValidateBodyEditor()) {
          showError('Fix the JSON in the request body before running.');
          bodyEditor.focus();
          return;
        }
        try {
          requestBody = JSON.parse(bodyEditor.value.trim());
        } catch (e) {
          showError('Invalid JSON in request body: ' + e.message);
          return;
        }
      }

      document.getElementById('fed-running-url').textContent = config.base_url + ep.path;
      panel.style.display = 'none';
      running.style.display = 'block';
      runBtn.disabled = true;
      runBtn.textContent = '…';

      try {
        const res = await fetch('/federation-test.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({
            service: config.service,
            endpoint_idx: activeIndex,
            path_params: pathParams,
            query_params: {},
            api_key: document.getElementById('fed-api-key').value,
            body: requestBody,
          }),
        });
        const data = await res.json();
        running.style.display = 'none';

        if (!data.ok && data.status === undefined) {
          showError(data.error || 'Request failed');
          return;
        }
        renderResponse(data);
        panel.style.display = 'block';
      } catch (e) {
        running.style.display = 'none';
        showError('Network error: ' + e.message);
      } finally {
        runBtn.disabled = false;
        runBtn.textContent = '▶ Run';
      }
    };

    // ── Init ──
    window.fedSwitchTab(ENDPOINTS[0]?.group || 'General', false);
    window.fedSelectEndpoint(0);
  })();
  </script>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>