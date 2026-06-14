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

$page_title  = 'Dashboard';
$health      = api_get('/health');
$stats       = api_get('/cache/stats');
$health_data = $health['data'] ?? [];
$stats_data  = $stats['data'] ?? [];
$server_ok   = $health['ok'];

// Pending registration count
$regs_result = my_api_get('/admin/registrations?status=pending');
$pending_count = $regs_result['data']['count'] ?? 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Server status and platform overview</p>
  </div>
  <?php if ($pending_count > 0): ?>
    <a href="/registrations.php?status=pending" class="btn btn--primary">
      <?= $pending_count ?> Pending Registration<?= $pending_count !== 1 ? 's' : '' ?>
    </a>
  <?php endif; ?>
</div>

<!-- Health -->
<div class="card" style="margin-bottom:28px;">
  <div class="card__head">
    <span class="card__title">API Server</span>
    <span>
      <span class="health-dot health-dot--<?= $server_ok ? 'ok' : 'error' ?>"></span>
      <span style="font-size:13px;color:var(--ink-light)"><?= $server_ok ? 'Healthy' : 'Unreachable' ?></span>
    </span>
  </div>
  <div class="card__body">
    <?php if ($server_ok): ?>
      <dl class="detail-list">
        <dt>Endpoint</dt><dd class="mono"><?= htmlspecialchars(API_BASE) ?></dd>
        <?php if (!empty($health_data['timestamp'])): ?>
          <dt>Server time</dt><dd><?= htmlspecialchars($health_data['timestamp']) ?> UTC</dd>
        <?php endif; ?>
        <?php if (!empty($health_data['supported_house_systems'])): ?>
          <dt>House systems</dt><dd><?= count($health_data['supported_house_systems']) ?> supported</dd>
        <?php endif; ?>
      </dl>
    <?php else: ?>
      <p class="alert alert--warning" style="margin:0;">Cannot connect to <strong><?= htmlspecialchars(API_BASE) ?></strong>.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<?php if ($server_ok && !empty($stats_data)): ?>
  <div class="stats">
    <?php foreach (['charts_cached'=>'Charts','derived_charts'=>'Derived','canonical_places'=>'Places','place_cache_active'=>'Cache Active'] as $k=>$l): ?>
      <?php if (isset($stats_data[$k])): ?>
        <div class="stat">
          <div class="stat__value"><?= number_format($stats_data[$k]) ?></div>
          <div class="stat__label"><?= $l ?></div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="stat <?= $pending_count > 0 ? 'stat--accent' : '' ?>">
      <div class="stat__value"><?= $pending_count ?></div>
      <div class="stat__label">Pending</div>
    </div>
  </div>
<?php endif; ?>

<div class="two-col">
  <div class="card">
    <div class="card__head"><span class="card__title">Registrations</span></div>
    <div class="card__body">
      <p style="font-size:14px;color:var(--ink-light);margin-bottom:16px;">Review and approve pending domain API key requests.</p>
      <a href="/registrations.php?status=pending" class="btn btn--primary btn--sm">View Pending</a>
      <a href="/registrations.php" class="btn btn--ghost btn--sm" style="margin-left:8px;">All Requests</a>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><span class="card__title">Keys</span></div>
    <div class="card__body">
      <p style="font-size:14px;color:var(--ink-light);margin-bottom:16px;">Manage rate limits, output config, and key status.</p>
      <a href="/keys.php" class="btn btn--secondary btn--sm">Manage Keys</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>