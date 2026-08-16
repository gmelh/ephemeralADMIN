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

$page_title = 'Output Config';

// Determine which key we're editing
$is_admin  = auth_is_admin();
$key_id    = isset($_GET['key_id']) && $is_admin ? (int)$_GET['key_id'] : null;
$self_edit = !$key_id; // editing own key

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? 'save';

    if ($action === 'fetch') {
        $endpoint = $key_id ? "/admin/keys/{$key_id}/output" : '/me/output';
        $result   = my_api_get($endpoint);
        echo json_encode($result['ok']
            ? ['ok' => true, 'data' => $result['data']]
            : ['ok' => false, 'error' => extractApiError($result)]);
        exit;
    }

    if ($action === 'save') {
        $config   = buildOutputConfig($input);
        $endpoint = $key_id ? "/admin/keys/{$key_id}/output" : '/me/output';
        $result   = my_api_post($endpoint, ['output_config' => $config]);
        echo json_encode($result['ok']
            ? ['ok' => true, 'message' => 'Output configuration saved.']
            : ['ok' => false, 'error' => extractApiError($result)]);
        exit;
    }

    if ($action === 'reset') {
        $endpoint = $key_id ? "/admin/keys/{$key_id}/output" : '/me/output';
        $result   = my_api_post($endpoint, ['output_config' => null]);
        echo json_encode($result['ok']
            ? ['ok' => true, 'message' => 'Output configuration reset to server defaults.']
            : ['ok' => false, 'error' => extractApiError($result)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

function buildOutputConfig(array $input): array {
    $cfg = [];

    // Top-level booleans
    foreach (['geocentric','heliocentric','right_ascension','declination',
              'longitude_speed','latitude_speed','declination_speed','retrograde'] as $k) {
        if (isset($input[$k])) $cfg[$k] = (bool)$input[$k];
    }
    if (array_key_exists('default_house_system', $input)) {
        $cfg['default_house_system'] = $input['default_house_system'] ?: null;
    }

    // Nested sections
    foreach (['angles', 'bodies', 'meta'] as $section) {
        if (!empty($input[$section]) && is_array($input[$section])) {
            foreach ($input[$section] as $k => $v) {
                $cfg[$section][$k] = (bool)$v;
            }
        }
    }
    return $cfg;
}

function extractApiError(array $result): string {
    $d   = $result['data'] ?? [];
    $msg = $d['error'] ?? $d['message'] ?? null;
    if (!$msg) $msg = 'HTTP ' . ($result['status'] ?? '?');
    if (($result['status'] ?? 0) === 429) $msg = 'Rate limit exceeded (429).';
    return $msg;
}

// Fetch initial data for page load
$endpoint     = $key_id ? "/admin/keys/{$key_id}/output" : '/me/output';
$result       = my_api_get($endpoint);
$identifier   = '';
$effective    = [];
$stored       = [];
$defaults     = [];

if ($result['ok']) {
    $identifier = $result['data']['identifier'] ?? '';
    $effective  = $result['data']['effective']  ?? [];
    $stored     = $result['data']['stored']     ?? [];
    $defaults   = $result['data']['defaults']   ?? [];
}

// Helper: is this field overridden (differs from default)?
function is_overridden(string $path, array $stored): bool {
    $parts = explode('.', $path);
    $node  = $stored;
    foreach ($parts as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) return false;
        $node = $node[$part];
    }
    return true;
}

function eff(string $path, array $effective): mixed {
    $parts = explode('.', $path);
    $node  = $effective;
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) return null;
        $node = $node[$p];
    }
    return $node;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Output Configuration</h1>
    <p class="page-subtitle">
      <?= $key_id ? htmlspecialchars($identifier) : 'Your key — ' . htmlspecialchars(auth_user()['identifier'] ?? '') ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($key_id && $is_admin): ?>
      <a href="/key-detail.php?id=<?= $key_id ?>" class="btn btn--ghost">← Key Detail</a>
    <?php elseif (!$is_admin): ?>
      <a href="/portal-user.php" class="btn btn--ghost">← Portal</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$result['ok']): ?>
  <div class="alert alert--warning">
    Could not load output configuration — <?= htmlspecialchars($result['data']['error'] ?? 'API error') ?>
  </div>
<?php endif; ?>

<div class="alert alert--info" style="margin-bottom:24px;">
  Fields shown in <strong style="color:var(--gold);">gold</strong> are overriding the server default for this key.
  All other fields are inherited from the server defaults.
  Leave fields at their defaults unless you need a specific change.
</div>

<div id="config-loading" style="text-align:center; padding:40px; color:var(--ink-light);">Loading…</div>

<div id="config-form" style="display:none;">
  <form id="output-form">

    <!-- Coordinate systems -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Coordinate Systems &amp; Fields</span></div>
      <div class="card__body">
        <div class="cfg-grid">
          <?php
          $coord_fields = [
            ['geocentric',        'Geocentric',         'Geocentric ecliptic positions for all bodies'],
            ['heliocentric',      'Heliocentric',       'Heliocentric ecliptic positions for all planets'],
            ['right_ascension',   'Right Ascension',    'Equatorial right ascension (degrees)'],
            ['declination',       'Declination',        'Equatorial declination (degrees)'],
            ['longitude_speed',   'Longitude Speed',    'Daily motion in ecliptic longitude'],
            ['latitude_speed',    'Latitude Speed',     'Daily motion in ecliptic latitude'],
            ['declination_speed', 'Declination Speed',  'Daily motion in declination'],
            ['retrograde',        'Retrograde Flag',    'Boolean retrograde indicator (geocentric only)'],
          ];
          foreach ($coord_fields as [$key, $label, $hint]):
            $val      = eff($key, $effective);
            $override = is_overridden($key, $stored);
          ?>
            <div class="cfg-row <?= $override ? 'cfg-row--override' : '' ?>">
              <div class="cfg-info">
                <span class="cfg-label <?= $override ? 'cfg-label--override' : '' ?>"><?= $label ?></span>
                <span class="cfg-hint"><?= $hint ?></span>
              </div>
              <label class="toggle">
                <input type="checkbox" name="<?= $key ?>" value="1"
                       <?= $val ? 'checked' : '' ?>
                       onchange="markDirty()">
                <span class="toggle__track"></span>
              </label>
            </div>
          <?php endforeach; ?>

          <!-- House system -->
          <?php
          $hs_val      = eff('default_house_system', $effective);
          $hs_override = is_overridden('default_house_system', $stored);
          $house_systems = [''=>'None (no cusps)','placidus'=>'Placidus','koch'=>'Koch',
            'whole_sign'=>'Whole Sign','equal'=>'Equal','regiomontanus'=>'Regiomontanus',
            'campanus'=>'Campanus','porphyrius'=>'Porphyrius','vehlow_equal'=>'Vehlow Equal',
            'meridian'=>'Meridian','azimuthal'=>'Azimuthal','topocentric'=>'Topocentric',
            'alcabitus'=>'Alcabitus','morinus'=>'Morinus','krusinski'=>'Krusinski',
            'gauquelin'=>'Gauquelin'];
          ?>
          <div class="cfg-row <?= $hs_override ? 'cfg-row--override' : '' ?>" style="grid-column:1/-1;">
            <div class="cfg-info">
              <span class="cfg-label <?= $hs_override ? 'cfg-label--override' : '' ?>">Default House System</span>
              <span class="cfg-hint">Applied when the request does not specify a house system</span>
            </div>
            <select name="default_house_system" onchange="markDirty()"
                    style="width:200px; padding:7px 10px; background:var(--bg-alt); border:1px solid var(--border-strong); border-radius:var(--radius); color:var(--ink); font-family:var(--font-sans); font-size:13.5px;">
              <?php foreach ($house_systems as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($hs_val ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Angles -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Angles</span></div>
      <div class="card__body">
        <div class="cfg-grid">
          <?php
          $angle_fields = [
            ['angles.asc',        'ASC',        'Ascendant'],
            ['angles.mc',         'MC',         'Midheaven'],
            ['angles.vertex',     'Vertex',     'Vertex angle'],
            ['angles.east_point', 'East Point', 'Equatorial Ascendant'],
            ['angles.armc',       'ARMC',       'Sidereal time angle — niche use'],
          ];
          foreach ($angle_fields as [$path, $label, $hint]):
            $parts    = explode('.', $path);
            $val      = eff($path, $effective);
            $override = is_overridden($path, $stored);
          ?>
            <div class="cfg-row <?= $override ? 'cfg-row--override' : '' ?>">
              <div class="cfg-info">
                <span class="cfg-label <?= $override ? 'cfg-label--override' : '' ?>"><?= $label ?></span>
                <span class="cfg-hint"><?= $hint ?></span>
              </div>
              <label class="toggle">
                <input type="checkbox" name="angles[<?= $parts[1] ?>]" value="1"
                       <?= $val ? 'checked' : '' ?>
                       onchange="markDirty()">
                <span class="toggle__track"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Bodies — Standard planets -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Planets</span></div>
      <div class="card__body">
        <div class="cfg-grid">
          <?php
          $planet_fields = [
            ['bodies.sun',     'Sun',     ''],
            ['bodies.moon',    'Moon',    ''],
            ['bodies.mercury', 'Mercury', ''],
            ['bodies.venus',   'Venus',   ''],
            ['bodies.mars',    'Mars',    ''],
            ['bodies.jupiter', 'Jupiter', ''],
            ['bodies.saturn',  'Saturn',  ''],
            ['bodies.uranus',  'Uranus',  ''],
            ['bodies.neptune', 'Neptune', ''],
            ['bodies.pluto',   'Pluto',   ''],
            ['bodies.earth',   'Earth',   'Heliocentric only'],
          ];
          foreach ($planet_fields as [$path, $label, $hint]):
            $parts    = explode('.', $path);
            $val      = eff($path, $effective);
            $override = is_overridden($path, $stored);
          ?>
            <div class="cfg-row <?= $override ? 'cfg-row--override' : '' ?>">
              <div class="cfg-info">
                <span class="cfg-label <?= $override ? 'cfg-label--override' : '' ?>"><?= $label ?></span>
                <?php if ($hint): ?><span class="cfg-hint"><?= $hint ?></span><?php endif; ?>
              </div>
              <label class="toggle">
                <input type="checkbox" name="bodies[<?= $parts[1] ?>]" value="1"
                       <?= $val ? 'checked' : '' ?>
                       onchange="markDirty()">
                <span class="toggle__track"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Bodies — Asteroids & special points -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card__head"><span class="card__title">Asteroids &amp; Special Points</span></div>
      <div class="card__body">
        <div class="cfg-grid">
          <?php
          $special_fields = [
            ['bodies.asteroids',       'Asteroids',       'Master switch — disabling this overrides all individual asteroid settings'],
            ['bodies.ceres',           'Ceres',           ''],
            ['bodies.pallas',          'Pallas',          ''],
            ['bodies.juno',            'Juno',            ''],
            ['bodies.vesta',           'Vesta',           ''],
            ['bodies.chiron',          'Chiron',          ''],
            ['bodies.mean_node',       'Mean Node',       'Mean North Node'],
            ['bodies.true_node',       'True Node',       'True/Osculating North Node'],
            ['bodies.south_node',      'South Node',      'Derived: North Node + 180°'],
            ['bodies.mean_lilith',     'Mean Lilith',     'Mean Black Moon Lilith'],
            ['bodies.true_lilith',     'True Lilith',     'True/Osculating Black Moon Lilith'],
            ['bodies.part_of_fortune', 'Part of Fortune', 'ASC + Moon − Sun — requires location'],
          ];
          foreach ($special_fields as [$path, $label, $hint]):
            $parts    = explode('.', $path);
            $val      = eff($path, $effective);
            $override = is_overridden($path, $stored);
          ?>
            <div class="cfg-row <?= $override ? 'cfg-row--override' : '' ?>">
              <div class="cfg-info">
                <span class="cfg-label <?= $override ? 'cfg-label--override' : '' ?>"><?= $label ?></span>
                <?php if ($hint): ?><span class="cfg-hint"><?= $hint ?></span><?php endif; ?>
              </div>
              <label class="toggle">
                <input type="checkbox" name="bodies[<?= $parts[1] ?>]" value="1"
                       <?= $val ? 'checked' : '' ?>
                       onchange="markDirty()">
                <span class="toggle__track"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Meta -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card__head"><span class="card__title">Response Metadata</span></div>
      <div class="card__body">
        <div class="cfg-grid">
          <?php
          $meta_fields = [
            ['meta.api_usage',  'API Usage',  'Include Google API request counts in calculate responses'],
            ['meta.from_cache', 'From Cache', 'Include cache status flag in responses'],
          ];
          foreach ($meta_fields as [$path, $label, $hint]):
            $parts    = explode('.', $path);
            $val      = eff($path, $effective);
            $override = is_overridden($path, $stored);
          ?>
            <div class="cfg-row <?= $override ? 'cfg-row--override' : '' ?>">
              <div class="cfg-info">
                <span class="cfg-label <?= $override ? 'cfg-label--override' : '' ?>"><?= $label ?></span>
                <span class="cfg-hint"><?= $hint ?></span>
              </div>
              <label class="toggle">
                <input type="checkbox" name="meta[<?= $parts[1] ?>]" value="1"
                       <?= $val ? 'checked' : '' ?>
                       onchange="markDirty()">
                <span class="toggle__track"></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </form><!-- /#output-form -->

  <!-- Action bar -->
  <div style="display:flex; gap:12px; align-items:center; padding:16px 0; border-top:1px solid var(--border);">
    <button class="btn btn--primary" onclick="saveConfig()" id="btn-save">Save Configuration</button>
    <button class="btn btn--ghost"   onclick="resetConfig()"
            data-confirm="Reset to server defaults? All per-key overrides will be removed.">
      Reset to Defaults
    </button>
    <span id="cfg-saving"  style="font-size:13px; color:var(--ink-faint); display:none;">Saving…</span>
    <span id="cfg-dirty"   style="font-size:13px; color:var(--warning);   display:none;">Unsaved changes</span>
    <span id="cfg-error"   style="font-size:13px; color:var(--error);     display:none;"></span>
  </div>

</div><!-- /#config-form -->

<style>
.cfg-grid {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.cfg-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}

.cfg-row:last-child { border-bottom: none; }

.cfg-row--override {
  background: rgba(255,215,0,.05);
  margin: 0 -20px;
  padding: 10px 20px;
  border-bottom-color: rgba(255,215,0,.15);
}

.cfg-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.cfg-label {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--ink);
}

.cfg-label--override { color: var(--gold); }

.cfg-hint {
  font-size: 12px;
  color: var(--ink-light);
}

/* Toggle switch */
.toggle {
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  flex-shrink: 0;
}

.toggle input { display: none; }

.toggle__track {
  width: 38px;
  height: 20px;
  background: var(--border-strong);
  border-radius: 10px;
  position: relative;
  transition: background .2s;
}

.toggle__track::after {
  content: '';
  position: absolute;
  width: 14px;
  height: 14px;
  background: #fff;
  border-radius: 50%;
  top: 3px;
  left: 3px;
  transition: transform .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,.3);
}

.toggle input:checked ~ .toggle__track {
  background: var(--gold);
}

.toggle input:checked ~ .toggle__track::after {
  transform: translateX(18px);
}
</style>

<script>
const KEY_ID   = <?= $key_id ? (int)$key_id : 'null' ?>;
let isDirty    = false;

// Show form once page is ready (data already loaded server-side)
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('config-loading').style.display = 'none';
  document.getElementById('config-form').style.display    = 'block';
});

function markDirty() {
  isDirty = true;
  document.getElementById('cfg-dirty').style.display = 'inline';
}

function collectForm() {
  const form   = document.getElementById('output-form');
  const data   = { action: 'save' };

  // Checkbox top-level booleans
  ['geocentric','heliocentric','right_ascension','declination',
   'longitude_speed','latitude_speed','declination_speed','retrograde'].forEach(k => {
    const el = form.querySelector(`[name="${k}"]`);
    if (el) data[k] = el.checked;
  });

  // House system
  const hs = form.querySelector('[name="default_house_system"]');
  if (hs) data.default_house_system = hs.value;

  // Nested checkboxes
  ['angles', 'bodies', 'meta'].forEach(section => {
    form.querySelectorAll(`[name^="${section}["]`).forEach(el => {
      const key = el.name.match(/\[(\w+)\]/)[1];
      data[section] = data[section] || {};
      data[section][key] = el.checked;
    });
  });

  return data;
}

async function saveConfig() {
  const errEl  = document.getElementById('cfg-error');
  const saving = document.getElementById('cfg-saving');
  const saveBtn = document.getElementById('btn-save');
  const dirty  = document.getElementById('cfg-dirty');

  errEl.style.display  = 'none';
  saving.style.display = 'inline';
  saveBtn.disabled     = true;

  const url = KEY_ID ? `/key-output.php?key_id=${KEY_ID}` : '/key-output.php';

  try {
    const res  = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(collectForm())
    });
    const data = await res.json();

    if (data.ok) {
      isDirty              = false;
      dirty.style.display  = 'none';
      showFlash('success', data.message);
      // Reload to reflect new override highlights
      setTimeout(() => location.reload(), 800);
    } else {
      errEl.textContent   = apiError(data, res.status);
      errEl.style.display = 'inline';
    }
  } catch(e) {
    errEl.textContent   = 'Network error — please try again.';
    errEl.style.display = 'inline';
  } finally {
    saving.style.display = 'none';
    saveBtn.disabled     = false;
  }
}

async function resetConfig() {
  if (!confirm('Reset to server defaults? All per-key overrides will be removed.')) return;

  const url = KEY_ID ? `/key-output.php?key_id=${KEY_ID}` : '/key-output.php';

  const res  = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ action: 'reset' })
  });
  const data = await res.json();
  if (data.ok) {
    showFlash('success', data.message);
    setTimeout(() => location.reload(), 600);
  } else {
    showFlash('error', apiError(data, res.status));
  }
}

function apiError(data, status) {
  if ((data.status || status) === 429) return 'Rate limit exceeded (429) — please wait and try again.';
  return data.error || data.message || ('HTTP ' + (data.status || status));
}

function showFlash(type, message) {
  const existing = document.querySelector('.flash');
  if (existing) existing.remove();
  const el = document.createElement('div');
  el.className   = 'flash flash--' + type;
  el.textContent = message;
  document.querySelector('.main__inner').prepend(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 4000);
}

// Warn before leaving with unsaved changes
window.addEventListener('beforeunload', e => {
  if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>