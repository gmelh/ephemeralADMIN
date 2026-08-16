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
auth_require('admin');

$page_title = 'Rate Limits';

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rpm   = isset($input['rate_per_minute']) ? (int)$input['rate_per_minute'] : null;
    $rph   = isset($input['rate_per_hour'])   ? (int)$input['rate_per_hour']   : null;
    $rpd   = isset($input['rate_per_day'])    ? (int)$input['rate_per_day']    : null;

    if ($rpm === null || $rph === null || $rpd === null) {
        echo json_encode(['ok' => false, 'error' => 'All three rate values are required.']); exit;
    }

    $result = my_api_post('/admin/class-limits', [
        'rate_per_minute' => $rpm,
        'rate_per_hour'   => $rph,
        'rate_per_day'    => $rpd,
    ]);

    echo json_encode($result['ok']
        ? ['ok' => true,  'message' => 'Default rate limits updated.']
        : ['ok' => false, 'error'   => $result['data']['error'] ?? ('HTTP ' . $result['status'])]);
    exit;
}

// Fetch current defaults
$r      = my_api_get('/admin/class-limits');
$limits = $r['ok'] ? $r['data'] : ['rate_per_minute' => 10, 'rate_per_hour' => 100, 'rate_per_day' => 500];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Default Rate Limits</h1>
    <p class="page-subtitle">Applied to all keys when no per-key override is set</p>
  </div>
</div>

<div class="alert alert--info" style="margin-bottom:24px;">
  These are the fallback limits for every key. Individual keys can override any value on their detail page.
</div>

<div class="card">
  <div class="card__head"><span class="card__title">Current Defaults</span></div>
  <div class="card__body">

    <p id="save-status" style="display:none; font-size:13px; margin-bottom:16px;"></p>

    <div class="form-grid" style="gap:20px; margin-bottom:24px;">
      <div class="form-group">
        <label for="rpm">Requests per minute</label>
        <input type="number" id="rpm" value="<?= (int)($limits['rate_per_minute'] ?? 10) ?>"
               min="0" max="10000">
      </div>
      <div class="form-group">
        <label for="rph">Requests per hour</label>
        <input type="number" id="rph" value="<?= (int)($limits['rate_per_hour'] ?? 100) ?>"
               min="0" max="100000">
      </div>
      <div class="form-group">
        <label for="rpd">Requests per day</label>
        <input type="number" id="rpd" value="<?= (int)($limits['rate_per_day'] ?? 500) ?>"
               min="0" max="1000000">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn--primary" id="save-btn" onclick="saveLimits()">Save Limits</button>
    </div>
  </div>
</div>

<script>
async function saveLimits() {
  const rpm    = parseInt(document.getElementById('rpm').value);
  const rph    = parseInt(document.getElementById('rph').value);
  const rpd    = parseInt(document.getElementById('rpd').value);
  const status = document.getElementById('save-status');
  const btn    = document.getElementById('save-btn');

  if (isNaN(rpm) || isNaN(rph) || isNaN(rpd)) {
    status.textContent    = 'Please enter valid numbers for all three fields.';
    status.style.color    = 'var(--error)';
    status.style.display  = 'block';
    return;
  }

  btn.disabled = true;
  status.style.display = 'none';

  try {
    const res  = await fetch('/class-limits.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ rate_per_minute: rpm, rate_per_hour: rph, rate_per_day: rpd }),
    });
    const data = await res.json();

    status.style.display = 'block';
    if (data.ok) {
      status.textContent = '✓ ' + (data.message || 'Saved.');
      status.style.color = 'var(--success)';
    } else {
      status.textContent = data.error || 'Save failed.';
      status.style.color = 'var(--error)';
    }
  } catch(e) {
    status.textContent   = 'Network error — please try again.';
    status.style.color   = 'var(--error)';
    status.style.display = 'block';
  } finally {
    btn.disabled = false;
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>