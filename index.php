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

$page_title = 'Dashboard';

$health = api_get('/health');
$stats  = api_get('/cache/stats');

$server_ok  = $health['ok'];
$health_data = $health['data'] ?? [];
$stats_data  = $stats['data'] ?? [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Server status and usage overview</p>
  </div>
  <div class="actions">
    <a href="/registrations.php" class="btn btn--primary">Registrations</a>
  </div>
</div>

<!-- Health status -->
<div class="card" style="margin-bottom:32px;">
  <div class="card__head">
    <span class="card__title">API Server</span>
    <span>
      <span class="health-dot health-dot--<?= $server_ok ? 'ok' : 'error' ?>"></span>
      <span style="font-size:13px; color:var(--ink-light)">
        <?= $server_ok ? 'Healthy' : 'Unreachable' ?>
      </span>
    </span>
  </div>
  <div class="card__body">
    <?php if ($server_ok): ?>
      <div class="detail-list">
        <dt>Endpoint</dt>
        <dd class="mono"><?= htmlspecialchars(API_BASE) ?></dd>

        <?php if (!empty($health_data['timestamp'])): ?>
          <dt>Server time</dt>
          <dd><?= htmlspecialchars($health_data['timestamp']) ?> UTC</dd>
        <?php endif; ?>

        <?php if (!empty($health_data['registered_users'])): ?>
          <dt>Active clients</dt>
          <dd><?= implode(', ', array_map('htmlspecialchars', $health_data['registered_users'])) ?></dd>
        <?php endif; ?>

        <?php if (!empty($health_data['supported_house_systems'])): ?>
          <dt>House systems</dt>
          <dd><?= count($health_data['supported_house_systems']) ?> supported</dd>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="alert alert--warning" style="margin:0;">
        Cannot connect to <strong><?= htmlspecialchars(API_BASE) ?></strong>.
        Check the server is running and <code>API_BASE</code> in <code>config.php</code> is correct.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- Cache stats -->
<?php if ($server_ok && !empty($stats_data)): ?>
  <div class="stats">
    <?php
    $stat_map = [
      'charts_cached'      => 'Charts',
      'derived_charts'     => 'Derived Charts',
      'canonical_places'   => 'Places',
      'place_aliases'      => 'Aliases',
      'place_cache_active' => 'Cache Active',
      'place_cache_expired'=> 'Cache Expired',
    ];
    foreach ($stat_map as $key => $label):
      if (!isset($stats_data[$key])) continue;
    ?>
      <div class="stat">
        <div class="stat__value"><?= number_format($stats_data[$key]) ?></div>
        <div class="stat__label"><?= $label ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- API usage -->
<?php if (!empty($health_data['api_usage'])): ?>
  <div class="card">
    <div class="card__head">
      <span class="card__title">Google API Usage</span>
    </div>
    <div class="card__body">
      <?php $usage = $health_data['api_usage']; ?>
      <div class="detail-list">
        <dt>Requests used</dt>
        <dd><?= number_format($usage['requests_used'] ?? 0) ?></dd>
        <dt>Remaining</dt>
        <dd><?= number_format($usage['requests_remaining'] ?? 0) ?></dd>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Quick links -->
<div class="two-col" style="margin-top:32px;">
  <div class="card">
    <div class="card__head"><span class="card__title">Domain Registrations</span></div>
    <div class="card__body">
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:16px;">
        Review and approve pending domain API key requests.
      </p>
      <a href="/registrations.php?status=pending" class="btn btn--primary btn--sm">
        View Pending
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><span class="card__title">Key Management</span></div>
    <div class="card__body">
      <p style="font-size:14px; color:var(--ink-light); margin-bottom:16px;">
        View, rotate, disable, and configure rate limits for all API keys.
      </p>
      <a href="/keys.php" class="btn btn--secondary btn--sm">Manage Keys</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
