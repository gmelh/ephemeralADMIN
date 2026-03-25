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
// ephemeralREST Admin — Shared Header
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_user = auth_user();
$is_admin     = auth_is_admin();

// Build nav based on role
$admin_nav = [
    'portal-admin'    => ['label' => 'Dashboard',     'icon' => '◈'],
    'registrations'   => ['label' => 'Registrations', 'icon' => '◎'],
    'keys'            => ['label' => 'Keys',           'icon' => '◆'],
    'register-key'    => ['label' => 'Register Key',   'icon' => '◇'],
    'class-limits'    => ['label' => 'Class Limits',   'icon' => '⊟'],
    'smtp'            => ['label' => 'SMTP Settings',   'icon' => '✉'],
];

$domain_nav = [
    'portal-domain'   => ['label' => 'My Portal',     'icon' => '◈'],
    'register-key'    => ['label' => 'Register Key',  'icon' => '◇'],
];

$user_nav = [
    'portal-user'     => ['label' => 'My Portal',     'icon' => '◈'],
];

$nav = $is_admin ? $admin_nav : (auth_is_domain() ? $domain_nav : $user_nav);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? SITE_NAME) ?> — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@300;400;500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <a href="/landing.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
        <span class="sidebar__logo">✦</span>
        <span class="sidebar__name"><?= SITE_NAME ?></span>
      </a>
    </div>

    <?php if ($current_user): ?>
    <div class="sidebar__user">
      <div class="sidebar__user-name"><?= htmlspecialchars($current_user['name'] ?? '') ?></div>
      <div class="sidebar__user-id"><?= htmlspecialchars($current_user['identifier'] ?? '') ?></div>
      <span class="sidebar__user-badge <?= $is_admin ? 'sidebar__user-badge--admin' : '' ?>">
        <?= $is_admin ? 'admin' : htmlspecialchars($current_user['key_type'] ?? '') ?>
      </span>
    </div>
    <?php endif; ?>

    <nav class="sidebar__nav">
      <?php if ($is_admin): ?>
        <div class="sidebar__section">Admin</div>
        <?php foreach (['portal-admin','registrations','keys','class-limits','smtp'] as $page): ?>
          <a href="/<?= $page ?>.php"
             class="sidebar__link <?= $current_page === $page ? 'sidebar__link--active' : '' ?>">
            <span class="sidebar__icon"><?= $nav[$page]['icon'] ?></span>
            <?= $nav[$page]['label'] ?>
          </a>
        <?php endforeach; ?>
        <div class="sidebar__section sidebar__section--mt">Public</div>
        <?php foreach (['register-key'] as $page): ?>
          <a href="/<?= $page ?>.php"
             class="sidebar__link <?= $current_page === $page ? 'sidebar__link--active' : '' ?>">
            <span class="sidebar__icon"><?= $nav[$page]['icon'] ?></span>
            <?= $nav[$page]['label'] ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="sidebar__section">My Account</div>
        <?php foreach ($nav as $page => $item): ?>
          <a href="/<?= $page ?>.php"
             class="sidebar__link <?= $current_page === $page ? 'sidebar__link--active' : '' ?>">
            <span class="sidebar__icon"><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>

    <div class="sidebar__footer">
      <a href="/logout.php" class="sidebar__signout"
         onclick="return confirm('Sign out?')">
        ⎋ &nbsp;Sign Out
      </a>
      <span class="sidebar__version">v<?= SITE_VERSION ?></span>
    </div>
  </aside>

  <!-- Main content -->
  <main class="main">
    <div class="main__inner">

      <!-- Flash message -->
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="flash flash--<?= htmlspecialchars($flash['type']) ?>">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>
