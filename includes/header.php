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
// ephemeralADMIN — Shared Header
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
    'smtp'            => ['label' => 'SMTP Settings',  'icon' => '✉'],
    'email-templates' => ['label' => 'Email Templates','icon' => '✎'],
    'api-tester'      => ['label' => 'API Tester',     'icon' => '⌁'],
];

$domain_nav = [
    'portal-domain'   => ['label' => 'My Portal',   'icon' => '◈'],
    'register-key'    => ['label' => 'Register Key', 'icon' => '◇'],
    'api-tester'      => ['label' => 'API Tester',   'icon' => '⌁'],
];

$user_nav = [
    'portal-user'     => ['label' => 'My Portal',   'icon' => '◈'],
    'api-tester'      => ['label' => 'API Tester',   'icon' => '⌁'],
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
        <?php foreach (['portal-admin','registrations','keys','class-limits','smtp','email-templates','api-tester'] as $page): ?>
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