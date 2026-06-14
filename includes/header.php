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

// ── Navigation ────────────────────────────────────────────────────────────────
// Flat structure: admin or standard user. No domain/user distinction.

$admin_nav = [
    'portal-admin'    => ['label' => 'Dashboard',      'icon' => '◈', 'section' => 'Admin'],
    'keys'            => ['label' => 'Keys',            'icon' => '◆', 'section' => null],
    'class-limits'    => ['label' => 'Rate Limits',     'icon' => '⊟', 'section' => null],
    'smtp'             => ['label' => 'SMTP Settings',    'icon' => '✉', 'section' => null],
    'email-templates'  => ['label' => 'Email Templates',  'icon' => '✎', 'section' => null],
    'portal-settings'  => ['label' => 'Portal Settings',  'icon' => '⚙', 'section' => null],
    'api-tester'      => ['label' => 'API Tester',      'icon' => '⌁', 'section' => 'Tools'],
];

$user_nav = [
    'portal-user' => ['label' => 'My Account',    'icon' => '◈', 'section' => 'Account'],
    'key-output'  => ['label' => 'Output Config',  'icon' => '⊟', 'section' => null],
    'api-tester'  => ['label' => 'API Tester',     'icon' => '⌁', 'section' => 'Tools'],
];

$nav = $is_admin ? $admin_nav : $user_nav;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? portal_setting('site_name')) ?> — <?= portal_setting('site_name') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@300;400;500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css">
  <style>
    /* ── Mobile nav ─────────────────────────────────────── */
    .mobile-bar {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 52px;
      background: #1a1a18;
      border-bottom: 1px solid #2e2e2c;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px;
      z-index: 200;
    }

    .mobile-bar__brand {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .mobile-bar__star { font-size: 18px; color: #c8a84b; }

    .mobile-bar__name {
      font-family: 'Instrument Serif', serif;
      font-size: 17px;
      color: #f0ede8;
    }

    .hamburger {
      background: none;
      border: none;
      cursor: pointer;
      padding: 6px;
      display: flex;
      flex-direction: column;
      gap: 5px;
      border-radius: 4px;
      transition: background .15s;
    }

    .hamburger:hover { background: rgba(255,255,255,.07); }

    .hamburger span {
      display: block;
      width: 22px;
      height: 2px;
      background: #e4e1da;
      border-radius: 2px;
      transition: transform .25s, opacity .25s;
      transform-origin: center;
    }

    .hamburger.is-open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.is-open span:nth-child(2) { opacity: 0; }
    .hamburger.is-open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    .sidebar-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 149;
      backdrop-filter: blur(1px);
    }

    @media (max-width: 800px) {
      .mobile-bar { display: flex; }
      .main { margin-left: 0; padding-top: 52px; }

      /* Override style.css display:none so the drawer can slide in */
      .sidebar {
        display: flex !important;
        position: fixed;
        top: 0; left: 0;
        height: 100dvh;
        transform: translateX(-100%);
        transition: transform .28s cubic-bezier(.4,0,.2,1);
        z-index: 150;
        box-shadow: 4px 0 24px rgba(0,0,0,.5);
      }

      .sidebar.is-open { transform: translateX(0); }
      .sidebar-backdrop.is-open { display: block; }
    }
  </style>
</head>
<body>

<!-- Mobile top bar -->
<div class="mobile-bar">
  <a href="/landing.php" class="mobile-bar__brand">
    <span class="mobile-bar__star">✦</span>
    <span class="mobile-bar__name"><?= portal_setting('site_name') ?></span>
  </a>
  <button class="hamburger" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</div>

<!-- Backdrop -->
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <a href="/landing.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
        <span class="sidebar__logo">✦</span>
        <span class="sidebar__name"><?= portal_setting('site_name') ?></span>
      </a>
    </div>

    <?php if ($current_user): ?>
    <div class="sidebar__user">
      <div class="sidebar__user-name"><?= htmlspecialchars($current_user['name'] ?? '') ?></div>
      <div class="sidebar__user-id"><?= htmlspecialchars($current_user['identifier'] ?? '') ?></div>
      <span class="sidebar__user-badge <?= $is_admin ? 'sidebar__user-badge--admin' : '' ?>">
        <?= $is_admin ? 'admin' : 'user' ?>
      </span>
    </div>
    <?php endif; ?>

    <nav class="sidebar__nav">
      <?php
      $current_section = null;
      foreach ($nav as $page => $item):
          if ($item['section'] !== null && $item['section'] !== $current_section):
              $current_section = $item['section'];
      ?>
        <div class="sidebar__section<?= $current_section !== array_values($nav)[0]['section'] ? ' sidebar__section--mt' : '' ?>">
          <?= htmlspecialchars($current_section) ?>
        </div>
      <?php endif; ?>
        <a href="/<?= $page ?>.php"
           class="sidebar__link <?= $current_page === $page ? 'sidebar__link--active' : '' ?>">
          <span class="sidebar__icon"><?= $item['icon'] ?></span>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
      <?php if ($current_user): ?>
      <a href="/logout.php" class="sidebar__signout"
         onclick="return confirm('Sign out?')">
        ⎋ &nbsp;Sign Out
      </a>
      <?php endif; ?>
      <span class="sidebar__version">v<?= portal_setting('site_version', '1.0') ?></span>
    </div>
  </aside>

  <script>
    (function () {
      var toggle   = document.getElementById('nav-toggle');
      var sidebar  = document.querySelector('.sidebar');
      var backdrop = document.getElementById('sidebar-backdrop');

      function open() {
        sidebar.classList.add('is-open');
        backdrop.classList.add('is-open');
        toggle.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
      }

      function close() {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        toggle.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }

      toggle.addEventListener('click', function () {
        sidebar.classList.contains('is-open') ? close() : open();
      });

      backdrop.addEventListener('click', close);

      // Close on nav link tap (single-page feel)
      sidebar.querySelectorAll('.sidebar__link').forEach(function (link) {
        link.addEventListener('click', close);
      });
    })();
  </script>

  <!-- Main content -->
  <main class="main">
    <div class="main__inner">

      <!-- Flash message -->
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="flash flash--<?= htmlspecialchars($flash['type']) ?>">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>
      