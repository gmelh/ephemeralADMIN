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
