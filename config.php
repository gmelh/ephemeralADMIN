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
// ephemeralREST Admin — Configuration
// ─────────────────────────────────────────────────────────────────────────────

define('API_BASE',      'http://localhost:5000');
define('ADMIN_API_KEY', 'your-admin-api-key-here');
define('SITE_NAME',     'ephemeralREST');
define('SITE_VERSION',  '1.0');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Timezone for displaying dates
date_default_timezone_set('UTC');
