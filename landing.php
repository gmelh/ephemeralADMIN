<!--
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
-->
<?php
if (!defined('API_BASE')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
$site_name = site_name_public();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($site_name) ?> — Swiss Ephemeris REST API</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Mono:wght@300;400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink:        #1a1a18;
      --ink-light:  #6b6b66;
      --ink-faint:  #a8a8a0;
      --paper:      #eeebe4;
      --border:     #e4e4dc;
      --accent:     #0066cc;
      --accent-text:#70b8ff;
      --accent-mid: #1e4f8c;
      --gold:       #ffd700;
      --dark:       #0f0f0e;
      --dark-mid:   #1a1a18;
      --dark-surface:#202632;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; scroll-behavior: smooth; }

    body {
      font-family: 'Jost', system-ui, sans-serif;
      color: var(--ink);
      background: #eeebe4;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }

    a { color: inherit; text-decoration: none; }

    /* ── NAV ── */
    .nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 48px;
      height: 64px;
      background: rgba(15,15,14,.92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255,255,255,.07);
    }

    .nav__brand {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #f0ede8;
    }

    .nav__star  { font-size: 18px; color: var(--gold); }
    .nav__name  { font-family: 'Instrument Sans', sans-serif; font-size: 18px; letter-spacing: .01em; }

    .nav__links { display: flex; gap: 8px; align-items: center; }

    .nav__link {
      padding: 7px 14px;
      border-radius: 6px;
      font-size: 13.5px;
      font-weight: 500;
      color: rgba(255,255,255,.65);
      transition: color .2s, background .2s;
      cursor: pointer;
    }

    .nav__link:hover { color: #fff; background: rgba(255,255,255,.08); }

    .nav__cta {
      padding: 7px 18px;
      border-radius: 6px;
      background: var(--accent);
      color: #fff;
      font-size: 13.5px;
      font-weight: 600;
      transition: opacity .2s;
    }

    .nav__cta:hover { opacity: .88; }

    /* ── HERO ── */
    .hero {
      min-height: 100vh;
      background: var(--dark);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 120px 24px 80px;
      position: relative;
      overflow: hidden;
    }

    /* Star field effect */
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(1px 1px at 15% 25%, rgba(255,255,255,.6) 0%, transparent 100%),
        radial-gradient(1px 1px at 35% 55%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 60% 20%, rgba(255,255,255,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 80% 40%, rgba(255,255,255,.35) 0%, transparent 100%),
        radial-gradient(1px 1px at 45% 80%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1px 1px at 90% 70%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 10% 70%, rgba(255,255,255,.25) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 70% 85%, rgba(255,255,255,.45) 0%, transparent 100%),
        radial-gradient(1px 1px at 25% 45%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(2px 2px at 55% 60%, rgba(255,215,0,.6) 0%, transparent 100%);
      pointer-events: none;
    }

    /* Subtle gradient orb */
    .hero::after {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(0,102,204,.12) 0%, transparent 70%);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }

    .hero__eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 11.5px;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 28px;
      position: relative;
      z-index: 1;
    }

    .hero__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: clamp(44px, 7vw, 84px);
      font-weight: 400;
      color: #f5f2ec;
      line-height: 1.05;
      letter-spacing: -.02em;
      max-width: 820px;
      margin-bottom: 28px;
      position: relative;
      z-index: 1;
    }

    .hero__title em {
      font-style: italic;
      color: var(--gold);
    }

    .hero__sub {
      font-size: 18px;
      font-weight: 300;
      color: rgba(255,255,255,.5);
      max-width: 540px;
      line-height: 1.65;
      margin-bottom: 52px;
      position: relative;
      z-index: 1;
    }

    .hero__actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
      position: relative;
      z-index: 1;
    }

    .btn-hero-primary {
      padding: 14px 32px;
      background: var(--accent);
      color: #fff;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      transition: opacity .2s, transform .2s;
    }

    .btn-hero-primary:hover { opacity: .9; transform: translateY(-1px); }

    .btn-hero-secondary {
      padding: 14px 32px;
      border: 1px solid rgba(255,255,255,.2);
      color: rgba(255,255,255,.75);
      border-radius: 8px;
      font-size: 15px;
      font-weight: 400;
      transition: border-color .2s, color .2s, background .2s;
    }

    .btn-hero-secondary:hover { border-color: rgba(255,255,255,.5); color: #fff; background: rgba(255,255,255,.06); }

    /* ── SCROLL HINT ── */
    .scroll-hint {
      position: absolute;
      bottom: 36px;
      left: 50%;
      transform: translateX(-50%);
      color: rgba(255,255,255,.25);
      font-size: 12px;
      letter-spacing: .1em;
      text-transform: uppercase;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      animation: fadeUp 2s ease infinite;
    }

    @keyframes fadeUp {
      0%, 100% { opacity: .25; transform: translateX(-50%) translateY(0); }
      50%       { opacity: .6;  transform: translateX(-50%) translateY(-6px); }
    }

    /* ── FEATURES STRIP ── */
    .features {
      background: #f5f3ef;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }

    .features__inner {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 48px;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      divide-x: 1px solid var(--border);
    }

    .feature {
      padding: 40px 36px;
      border-right: 1px solid var(--border);
    }

    .feature:last-child { border-right: none; }

    .feature__icon {
      font-size: 20px;
      margin-bottom: 16px;
      display: block;
      color: var(--accent-text);
    }

    .feature__title {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--ink);
    }

    .feature__desc {
      font-size: 13.5px;
      color: var(--ink-light);
      line-height: 1.6;
    }

    /* ── SECTION ── */
    .section {
      max-width: 1100px;
      margin: 0 auto;
      padding: 96px 48px;
    }

    .section--alt { background: #eeebe4; }

    .section__label {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: .15em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 16px;
    }

    .section__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: clamp(30px, 4vw, 46px);
      font-weight: 400;
      line-height: 1.15;
      color: var(--ink);
      margin-bottom: 20px;
    }

    .section__desc {
      font-size: 16px;
      color: var(--ink-light);
      line-height: 1.7;
      max-width: 560px;
    }

    /* ── CODE BLOCK ── */
    .code-block {
      background: var(--dark);
      border-radius: 10px;
      overflow: hidden;
      font-family: 'DM Mono', monospace;
      font-size: 13px;
    }

    .code-block__bar {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 12px 16px;
      background: var(--dark-surface);
      border-bottom: 1px solid rgba(255,255,255,.07);
    }

    .code-block__dot {
      width: 10px; height: 10px;
      border-radius: 50%;
    }

    .dot--red    { background: #ff5f57; }
    .dot--yellow { background: #febc2e; }
    .dot--green  { background: #28c840; }

    .code-block__label {
      font-size: 11px;
      color: rgba(255,255,255,.3);
      margin-left: 6px;
    }

    .code-block__body {
      padding: 24px;
      color: rgba(255,255,255,.8);
      line-height: 1.8;
      overflow-x: auto;
    }

    .code-block__body .c-comment { color: rgba(255,255,255,.3); }
    .code-block__body .c-key     { color: #79b8ff; }
    .code-block__body .c-str     { color: #f8bb66; }
    .code-block__body .c-num     { color: #79dcaa; }
    .code-block__body .c-method  { color: var(--gold); }

    /* ── TWO COL ── */
    .two-up {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 72px;
      align-items: center;
    }

    .two-up--flip { direction: rtl; }
    .two-up--flip > * { direction: ltr; }

    /* ── ENDPOINT LIST ── */
    .endpoint-list {
      display: flex;
      flex-direction: column;
      gap: 1px;
      border: 1px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
    }

    .endpoint {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 13px 18px;
      background: #f5f3ef;
      border-bottom: 1px solid var(--border);
      font-size: 13.5px;
    }

    .endpoint:last-child { border-bottom: none; }

    .method {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      font-weight: 500;
      padding: 3px 8px;
      border-radius: 4px;
      min-width: 48px;
      text-align: center;
    }

    .method--get  { background: #edfaf3; color: #1e7b4b; }
    .method--post { background: #fef8ed; color: #92560a; }

    .endpoint__path {
      font-family: 'DM Mono', monospace;
      color: var(--ink);
    }

    .endpoint__desc {
      color: var(--ink-light);
      margin-left: auto;
      font-size: 13px;
    }

    /* ── PORTAL CARDS ── */
    .portal-section {
      background: var(--dark);
      padding: 96px 48px;
    }

    .portal-section__inner {
      max-width: 1100px;
      margin: 0 auto;
    }

    .portal-section__head {
      text-align: center;
      margin-bottom: 56px;
    }

    .portal-section__label {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: .15em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 16px;
    }

    .portal-section__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 40px;
      font-weight: 400;
      color: #f5f2ec;
      margin-bottom: 14px;
    }

    .portal-section__desc {
      font-size: 16px;
      color: rgba(255,255,255,.45);
    }

    .portal-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }

    .portal-card {
      background: var(--dark-surface);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px;
      padding: 36px 32px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      transition: border-color .25s, transform .25s;
    }

    .portal-card:hover {
      border-color: var(--gold);
      transform: translateY(-3px);
    }

    .portal-card__icon {
      font-size: 28px;
      line-height: 1;
    }

    .portal-card__role {
      font-family: 'DM Mono', monospace;
      font-size: 10.5px;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--gold);
    }

    .portal-card__title {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 22px;
      color: #f0ede8;
    }

    .portal-card__desc {
      font-size: 14px;
      color: rgba(255,255,255,.45);
      line-height: 1.65;
      flex: 1;
    }

    .portal-card__perms {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .portal-card__perms li {
      font-size: 13px;
      color: rgba(255,255,255,.55);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .portal-card__perms li::before {
      content: '✓';
      color: var(--gold);
      font-size: 11px;
      flex-shrink: 0;
    }

    .portal-card__btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 24px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      background: var(--accent);
      color: #fff;
      transition: opacity .2s;
      text-align: center;
    }

    .portal-card__btn:hover { opacity: .85; }

    .portal-card__btn--outline {
      background: transparent;
      border: 1px solid rgba(255,255,255,.2);
      color: rgba(255,255,255,.75);
    }

    .portal-card__btn--outline:hover {
      border-color: rgba(255,255,255,.5);
      color: #fff;
      background: rgba(255,255,255,.06);
    }

    /* ── TECH STRIP ── */
    .tech-strip {
      background: #e8e4dc;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }

    .tech-strip__inner {
      max-width: 1100px;
      margin: 0 auto;
      padding: 40px 48px;
      display: flex;
      align-items: center;
      gap: 48px;
      flex-wrap: wrap;
    }

    .tech-strip__label {
      font-size: 12px;
      color: var(--ink-faint);
      letter-spacing: .08em;
      text-transform: uppercase;
      flex-shrink: 0;
    }

    .tech-strip__items {
      display: flex;
      gap: 40px;
      flex-wrap: wrap;
    }

    .tech-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .tech-item__name {
      font-size: 14px;
      font-weight: 600;
      color: var(--ink);
    }

    .tech-item__detail {
      font-size: 12px;
      color: var(--ink-faint);
    }

    /* ── FOOTER ── */
    footer {
      background: var(--dark);
      padding: 56px 48px 40px;
    }

    .footer__inner {
      max-width: 1100px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 48px;
      flex-wrap: wrap;
    }

    .footer__brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }

    .footer__brand-name {
      font-family: 'Instrument Sans', sans-serif;
      font-size: 18px;
      color: #f0ede8;
    }

    .footer__tagline {
      font-size: 13px;
      color: rgba(255,255,255,.35);
      max-width: 260px;
      line-height: 1.6;
    }

    .footer__nav {
      display: flex;
      gap: 64px;
    }

    .footer__col-title {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: rgba(255,255,255,.35);
      margin-bottom: 16px;
    }

    .footer__links {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .footer__link {
      font-size: 14px;
      color: rgba(255,255,255,.5);
      transition: color .2s;
    }

    .footer__link:hover { color: rgba(255,255,255,.85); }

    .footer__bottom {
      max-width: 1100px;
      margin: 40px auto 0;
      padding-top: 24px;
      border-top: 1px solid rgba(255,255,255,.07);
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12.5px;
      color: rgba(255,255,255,.25);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .features__inner { grid-template-columns: 1fr 1fr; }
      .two-up { grid-template-columns: 1fr; gap: 40px; }
      .two-up--flip { direction: ltr; }
      .portal-cards { grid-template-columns: 1fr; }
      .nav { padding: 0 24px; }
      .section { padding: 64px 24px; }
      .portal-section { padding: 64px 24px; }
    }

    @media (max-width: 600px) {
      .features__inner { grid-template-columns: 1fr; }
      .footer__nav { gap: 32px; }
      .footer__inner { flex-direction: column; }
      .hero__actions { flex-direction: column; align-items: stretch; text-align: center; }
      .endpoint__desc { display: none; }
    }
  </style>
</head>
<body>

<!-- Navigation -->
<nav class="nav">
  <div class="nav__brand">
    <span class="nav__star">✦</span>
    <span class="nav__name"><?= htmlspecialchars($site_name) ?></span>
  </div>
  <div class="nav__links">
    <a href="#features" class="nav__link">Features</a>
    <a href="#endpoints" class="nav__link">Endpoints</a>
    <a href="#get-key" class="nav__link">Get a Key</a>
    <a href="/login.php" class="nav__cta">Sign In</a>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <p class="hero__eyebrow">Swiss Ephemeris · Open Source · AGPL v3 Licensed · Free to Use</p>
  <h1 class="hero__title">
    Precise astronomical<br>
    calculations for<br>
    <em>every application</em>
  </h1>
  <p class="hero__sub">
    A production-grade ephemeris API powered by the Swiss Ephemeris.
    Free for development and low-volume use. Open source — run your own instance.
  </p>
  <div class="hero__actions">
    <a href="#get-key" class="btn-hero-primary">Request a Key</a>
    <a href="#endpoints" class="btn-hero-secondary">View Endpoints</a>
  </div>
  <div class="scroll-hint">
    <span>Explore</span>
    <span>↓</span>
  </div>
</section>

<!-- Feature strip -->
<div class="features">
  <div class="features__inner">
    <div class="feature">
      <span class="feature__icon">⊕</span>
      <div class="feature__title">Geocentric &amp; Heliocentric</div>
      <p class="feature__desc">Full dual-frame position data for all major bodies, asteroids, and special points.</p>
    </div>
    <div class="feature">
      <span class="feature__icon">◎</span>
      <div class="feature__title">15 House Systems</div>
      <p class="feature__desc">Placidus, Koch, Whole Sign, Regiomontanus, Equal, and ten more — switchable per request.</p>
    </div>
    <div class="feature">
      <span class="feature__icon">◈</span>
      <div class="feature__title">Predictive Techniques</div>
      <p class="feature__desc">Secondary progressions, solar arc directions, solar and lunar returns all in one API.</p>
    </div>
    <div class="feature">
      <span class="feature__icon">◆</span>
      <div class="feature__title">Ephemeris &amp; Events</div>
      <p class="feature__desc">Monthly ephemeris, lunations, apsides, and next-event finders for any date range.</p>
    </div>
  </div>
</div>

<!-- API section -->
<section class="section" id="endpoints">
  <div class="two-up">
    <div>
      <p class="section__label">Simple by Design</p>
      <h2 class="section__title">One request.<br>Complete data.</h2>
      <p class="section__desc">
        A single POST to <code>/calculate</code> returns geocentric and
        heliocentric positions, house cusps, angles, and all configured
        celestial bodies. Every field is configurable per-request or set
        once in your key's output configuration.
      </p>
    </div>
    <div class="code-block">
      <div class="code-block__bar">
        <span class="code-block__dot dot--red"></span>
        <span class="code-block__dot dot--yellow"></span>
        <span class="code-block__dot dot--green"></span>
        <span class="code-block__label">POST /calculate</span>
      </div>
      <div class="code-block__body">
<pre><span class="c-comment">// Request</span>
{
  <span class="c-key">"chart_name"</span>: <span class="c-str">"Albert Einstein"</span>,
  <span class="c-key">"datetime"</span>: <span class="c-str">"1879-03-14 11:30:00"</span>,
  <span class="c-key">"location"</span>: <span class="c-str">"Ulm, Germany"</span>,
  <span class="c-key">"house_system"</span>: <span class="c-str">"placidus"</span>
}

<span class="c-comment">// Response (excerpt)</span>
{
  <span class="c-key">"chart_id"</span>: <span class="c-str">"a3f2c1d4-..."</span>,
  <span class="c-key">"planetary_positions"</span>: {
    <span class="c-key">"sun"</span>: {
      <span class="c-key">"longitude"</span>: <span class="c-num">354.098</span>,
      <span class="c-key">"retrograde"</span>: <span class="c-num">false</span>
    }
  }
}</pre>
      </div>
    </div>
  </div>
</section>

<!-- Endpoint list -->
<div style="background:#fff; border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:80px 0;">
  <div class="section" style="padding-top:0; padding-bottom:0;">
    <div class="two-up two-up--flip">
      <div class="endpoint-list">
        <?php
        $endpoints = [
            ['POST', '/calculate',                   'Calculate natal or event chart'],
            ['POST', '/chart/{id}/solar-return',     'Solar return for a given year'],
            ['POST', '/chart/{id}/lunar-return',     'Lunar return for a given month'],
            ['POST', '/chart/{id}/progressions',     'Secondary progressions'],
            ['POST', '/chart/{id}/solar-arc',        'Solar arc directions'],
            ['POST', '/ephemeris',                   'Full month ephemeris at noon UT'],
            ['POST', '/apsides',                     'Lunar and planetary apside positions'],
            ['POST', '/apsides/next',                'Next apside events over any window'],
            ['POST', '/lunations',                   'New/Full Moon and quarter events'],
            ['GET',  '/chart/{id}/derived',          'List derived charts for a radix'],
        ];
        foreach ($endpoints as [$m, $p, $d]):
        ?>
          <div class="endpoint">
            <span class="method method--<?= strtolower($m) ?>"><?= $m ?></span>
            <span class="endpoint__path"><?= htmlspecialchars($p) ?></span>
            <span class="endpoint__desc"><?= htmlspecialchars($d) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div>
        <p class="section__label">Full Coverage</p>
        <h2 class="section__title">19 endpoints.<br>Everything you need.</h2>
        <p class="section__desc">
          From basic natal chart calculation through to complex predictive techniques,
          lunation finding, and ephemeris generation. All responses are JSON with
          consistent error shapes and HTTP status codes.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- Tech strip -->
<div class="tech-strip">
  <div class="tech-strip__inner">
    <span class="tech-strip__label">Built on</span>
    <div class="tech-strip__items">
      <div class="tech-item">
        <span class="tech-item__name">Swiss Ephemeris</span>
        <span class="tech-item__detail">Astrodienst AG — industry standard</span>
      </div>
      <div class="tech-item">
        <span class="tech-item__name">15 House Systems</span>
        <span class="tech-item__detail">Placidus to Gauquelin</span>
      </div>
      <div class="tech-item">
        <span class="tech-item__name">Geocentric &amp; Heliocentric</span>
        <span class="tech-item__detail">Dual reference frame</span>
      </div>
      <div class="tech-item">
        <span class="tech-item__name">Newton's Method</span>
        <span class="tech-item__detail">Precise event finding</span>
      </div>
      <div class="tech-item">
        <span class="tech-item__name">Fernet Encryption</span>
        <span class="tech-item__detail">AES-128 key storage</span>
      </div>
    </div>
  </div>
</div>

<!-- Open Source section -->
<div style="background:#e8e4dc; border-top:1px solid #d4cfc5; border-bottom:1px solid #d4cfc5; padding:72px 0;">
  <div class="section" style="padding-top:0; padding-bottom:0;">
    <div class="two-up">
      <div>
        <p class="section__label">Free &amp; Open Source</p>
        <h2 class="section__title">AGPL v3 licensed.<br>Run your own instance.</h2>
        <p class="section__desc" style="margin-bottom:24px;">
          ephemeralREST is released under the <strong>GNU Affero General Public License v3 (AGPL v3)</strong>
          to maintain licensing compatibility with the Swiss Ephemeris, which is itself AGPL v3.
          This live instance is free for all to use with no registration required for public endpoints.
        </p>
        <p class="section__desc" style="margin-bottom:32px;">
          Full source code, documentation, and setup instructions for running your
          own instance are available on GitHub — including the Flask API backend,
          this admin portal, and the Swiss Ephemeris data files.
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <a href="https://github.com/gmelh/ephemeralREST" target="_blank" class="btn-hero-primary"
             style="display:inline-flex;align-items:center;gap:8px;">
            <span>⬡</span> View on GitHub
          </a>
          <a href="#endpoints" class="btn-hero-secondary"
             style="display:inline-flex;align-items:center;gap:8px; color:var(--ink)!important; border-color:var(--ink-light)!important;">
            API Documentation
          </a>
        </div>
      </div>
      <div style="display:flex; flex-direction:column; gap:16px;">
        <div style="background:#fff7f0; border:1px solid #d4cfc5; border-radius:8px; padding:20px 24px;">
          <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#8a7a5a; margin-bottom:8px; font-weight:600;">License</div>
          <div style="font-size:16px; font-weight:600; color:var(--ink); margin-bottom:4px;">GNU Affero General Public License v3</div>
          <div style="font-size:13px; color:#7a7060; line-height:1.6;">ephemeralREST uses the AGPL v3 to maintain compatibility with the Swiss Ephemeris. The AGPL v3 requires that source code be made available to anyone who uses the software over a network.</div>
        </div>
        <div style="background:#fff7f0; border:1px solid #d4cfc5; border-radius:8px; padding:20px 24px;">
          <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#8a7a5a; margin-bottom:8px; font-weight:600;">This Instance</div>
          <div style="font-size:16px; font-weight:600; color:var(--ink); margin-bottom:4px;">Live &amp; Free to Use</div>
          <div style="font-size:13px; color:#7a7060; line-height:1.6;">No cost for public endpoints. Register a domain or user key to access authenticated endpoints and predictive techniques.</div>
        </div>
        <div style="background:#fff7f0; border:1px solid #d4cfc5; border-radius:8px; padding:20px 24px;">
          <div style="font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:#8a7a5a; margin-bottom:8px; font-weight:600;">Backend</div>
          <div style="font-size:16px; font-weight:600; color:var(--ink); margin-bottom:4px;">Flask · SQLite · Python</div>
          <div style="font-size:13px; color:#7a7060; line-height:1.6;">Lightweight, self-hosted. Runs on any Linux server with Python 3.10+. Swiss Ephemeris data files included.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Request a Key section -->
<div class="portal-section" id="get-key">
  <div class="portal-section__inner">

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:64px; align-items:start;">

      <!-- Left: copy -->
      <div>
        <p class="portal-section__label" style="margin-bottom:16px;">API Access</p>
        <h2 style="font-family:'Instrument Sans', sans-serif; font-size:clamp(28px,4vw,44px); font-weight:400; color:#f5f2ec; line-height:1.15; margin-bottom:24px;">
          One key.<br>Everything unlocked.
        </h2>
        <p style="font-size:16px; color:rgba(255,255,255,.5); line-height:1.7; margin-bottom:20px;">
          A single API key gives you full access to all endpoints — chart calculation,
          predictive techniques, ephemeris data, lunations, and apsides.
        </p>
        <p style="font-size:16px; color:rgba(255,255,255,.5); line-height:1.7; margin-bottom:32px;">
          Keys are <strong style="color:rgba(255,255,255,.7);">free for development and low-volume personal use</strong>.
          No tiers, no paywalls, no credit card. If you need higher limits for production traffic,
          just mention it in your request.
        </p>

        <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:40px;">
          <?php
          $uses = [
            ['◈', 'Building an application',  'Chart calculation, progressions, returns — everything you need for an astrology app.'],
            ['◎', 'Research and data work',    'Monthly ephemeris, event finders, and bulk access to planetary position data.'],
            ['◆', 'Personal projects',         'Use * as your domain for direct access without needing a website or server.'],
            ['○', 'Evaluating the API',        'Request a key and start building immediately — your key arrives by email.'],
          ];
          foreach ($uses as [$icon, $title, $desc]):
          ?>
            <div style="display:flex; gap:14px; align-items:flex-start; padding:13px 16px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); border-radius:8px;">
              <span style="font-size:13px; color:var(--gold); margin-top:2px; flex-shrink:0;"><?= $icon ?></span>
              <div>
                <div style="font-size:13.5px; font-weight:500; color:rgba(255,255,255,.8); margin-bottom:2px;"><?= $title ?></div>
                <div style="font-size:12.5px; color:rgba(255,255,255,.38); line-height:1.55;"><?= $desc ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <a href="/login.php" class="btn-hero-secondary" style="font-size:13.5px; padding:10px 20px;">
          Already have a key? Sign in →
        </a>
      </div>

      <!-- Right: inline form -->
      <div>
        <div style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1); border-radius:12px; overflow:hidden;">
          <div style="padding:18px 24px; border-bottom:1px solid rgba(255,255,255,.07); background:rgba(255,255,255,.03);">
            <p style="font-family:'DM Mono', monospace; font-size:10px; letter-spacing:.14em; text-transform:uppercase; color:var(--gold); margin-bottom:4px;">Free Access</p>
            <p style="font-size:15px; color:#f0ede8; font-weight:500;">Request your API key</p>
          </div>
          <div style="padding:22px;">
            <?php
            $req_success = false;
            $req_errors  = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['req_submit'])) {
                if (!function_exists('api_post')) require_once __DIR__ . '/includes/api.php';
                $domain        = trim($_POST['req_domain'] ?? '');
                $name          = trim($_POST['req_name']   ?? '');
                $contact_email = trim($_POST['req_email']  ?? '');
                $reason        = trim($_POST['req_reason'] ?? '');
                if (!$domain) $req_errors['domain'] = 'Required.';
                if (!$name)   $req_errors['name']   = 'Required.';
                if (!$contact_email || !filter_var($contact_email, FILTER_VALIDATE_EMAIL))
                    $req_errors['email'] = 'Valid email required.';
                if (empty($req_errors)) {
                    $result = api_post('/register/domain', [
                        'domain'        => $domain,
                        'name'          => $name,
                        'contact_email' => $contact_email,
                        'reason'        => $reason ?: null,
                    ], false);
                    if ($result['ok']) {
                        $req_success = true;
                    } else {
                        $req_errors['_global'] = $result['data']['error'] ?? 'Submission failed.';
                    }
                }
            }
            ?>

            <?php if ($req_success): ?>
              <div style="text-align:center; padding:28px 0;">
                <div style="font-size:32px; margin-bottom:14px;">✦</div>
                <p style="font-size:16px; color:#f0ede8; font-weight:500; margin-bottom:10px;">Request received</p>
                <p style="font-size:13px; color:rgba(255,255,255,.45); line-height:1.65;">
                  We'll review your request and email your API key to<br>
                  <strong style="color:rgba(255,255,255,.65);"><?= htmlspecialchars($_POST['req_email'] ?? '') ?></strong>.
                </p>
              </div>
            <?php else: ?>

              <?php if (!empty($req_errors['_global'])): ?>
                <div style="background:rgba(212,96,96,.12); border:1px solid rgba(212,96,96,.25); border-radius:6px; padding:10px 14px; font-size:13px; color:#d46060; margin-bottom:14px;">
                  <?= htmlspecialchars($req_errors['_global']) ?>
                </div>
              <?php endif; ?>

              <form method="POST" novalidate style="display:flex; flex-direction:column; gap:12px;">
                <input type="hidden" name="req_submit" value="1">

                <div>
                  <label style="display:block; font-size:12px; font-weight:500; color:rgba(255,255,255,.5); margin-bottom:5px;">Domain <span style="opacity:.6; font-weight:400;">— or * for personal use</span></label>
                  <input type="text" name="req_domain"
                         value="<?= htmlspecialchars($_POST['req_domain'] ?? '') ?>"
                         placeholder="myapp.com  or  *"
                         style="width:100%; padding:9px 12px; background:rgba(255,255,255,.06); border:1px solid <?= !empty($req_errors['domain']) ? 'rgba(212,96,96,.5)' : 'rgba(255,255,255,.12)' ?>; border-radius:6px; color:#e2e5ea; font-family:inherit; font-size:13.5px; outline:none;">
                  <?php if (!empty($req_errors['domain'])): ?>
                    <span style="font-size:11.5px; color:#d46060; display:block; margin-top:2px;"><?= $req_errors['domain'] ?></span>
                  <?php endif; ?>
                </div>

                <div>
                  <label style="display:block; font-size:12px; font-weight:500; color:rgba(255,255,255,.5); margin-bottom:5px;">Your name</label>
                  <input type="text" name="req_name"
                         value="<?= htmlspecialchars($_POST['req_name'] ?? '') ?>"
                         placeholder="Jane Smith"
                         style="width:100%; padding:9px 12px; background:rgba(255,255,255,.06); border:1px solid <?= !empty($req_errors['name']) ? 'rgba(212,96,96,.5)' : 'rgba(255,255,255,.12)' ?>; border-radius:6px; color:#e2e5ea; font-family:inherit; font-size:13.5px; outline:none;">
                </div>

                <div>
                  <label style="display:block; font-size:12px; font-weight:500; color:rgba(255,255,255,.5); margin-bottom:5px;">Email address</label>
                  <input type="email" name="req_email"
                         value="<?= htmlspecialchars($_POST['req_email'] ?? '') ?>"
                         placeholder="jane@example.com"
                         style="width:100%; padding:9px 12px; background:rgba(255,255,255,.06); border:1px solid <?= !empty($req_errors['email']) ? 'rgba(212,96,96,.5)' : 'rgba(255,255,255,.12)' ?>; border-radius:6px; color:#e2e5ea; font-family:inherit; font-size:13.5px; outline:none;">
                  <?php if (!empty($req_errors['email'])): ?>
                    <span style="font-size:11.5px; color:#d46060; display:block; margin-top:2px;"><?= $req_errors['email'] ?></span>
                  <?php endif; ?>
                  <span style="font-size:11.5px; color:rgba(255,255,255,.25); display:block; margin-top:3px;">Your key will be emailed here once approved.</span>
                </div>

                <div>
                  <label style="display:block; font-size:12px; font-weight:500; color:rgba(255,255,255,.5); margin-bottom:5px;">
                    What are you building? <span style="font-weight:400; opacity:.6;">(optional)</span>
                  </label>
                  <textarea name="req_reason" rows="2"
                            placeholder="Brief description…"
                            style="width:100%; padding:9px 12px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:6px; color:#e2e5ea; font-family:inherit; font-size:13.5px; resize:vertical; outline:none;"><?= htmlspecialchars($_POST['req_reason'] ?? '') ?></textarea>
                </div>

                <button type="submit"
                        style="width:100%; padding:11px; background:var(--accent); color:#fff; border:none; border-radius:6px; font-family:inherit; font-size:14px; font-weight:600; cursor:pointer; transition:opacity .2s; margin-top:2px;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                  Request Key →
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <p style="font-size:12px; color:rgba(255,255,255,.2); text-align:center; margin-top:12px;">
          Already have a key? <a href="/login.php" style="color:rgba(255,255,255,.35);">Sign in →</a>
        </p>
      </div>

    </div>

    <style>
      @media (max-width: 800px) {
        #get-key .portal-section__inner > div[style*="grid-template-columns"] {
          grid-template-columns: 1fr !important;
        }
      }
    </style>

  </div>
</div>

<!-- Footer -->
<footer>
  <div class="footer__inner">
    <div>
      <div class="footer__brand">
        <span style="color:var(--gold); font-size:18px;">✦</span>
        <span class="footer__brand-name"><?= htmlspecialchars($site_name) ?></span>
      </div>
      <p class="footer__tagline">
        Open source astronomical calculation API. AGPL v3 licensed, Swiss Ephemeris powered, free to use.
      </p>
    </div>
    <div class="footer__nav">
      <div>
        <p class="footer__col-title">Access</p>
        <div class="footer__links">
          <a href="/register-key.php" class="footer__link">Register Domain</a>
          <a href="/register-key.php" class="footer__link">Register User</a>
          <a href="/login.php" class="footer__link">Sign In</a>
        </div>
      </div>
      <div>
        <p class="footer__col-title">API</p>
        <div class="footer__links">
          <a href="<?= defined('API_BASE') ? API_BASE : 'http://localhost:5000' ?>/health" target="_blank" class="footer__link">Health</a>
          <a href="#endpoints" class="footer__link">Endpoints</a>
        </div>
      </div>
    </div>
  </div>
  <div class="footer__bottom">
    <span>AGPL v3 Licensed · Swiss Ephemeris © Astrodienst AG · <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" style="color:rgba(255,255,255,.4)">Licence</a> · <a href="https://github.com/gmelh/ephemeralREST" target="_blank" style="color:rgba(255,255,255,.4)">GitHub</a></span>
    <span>ephemeralREST v<?= htmlspecialchars(SITE_VERSION) ?></span>
  </div>
</footer>

</body>
</html>
<?php
// Include config if available for footer version number
if (!defined('API_BASE')) require_once __DIR__ . '/config.php';
?>