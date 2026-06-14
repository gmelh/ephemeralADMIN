# ephemeralADMIN — Setup Guide

Administration portal for [ephemeralREST](https://github.com/your-org/ephemeralREST).

---

## Prerequisites

- **ephemeralREST** running and reachable (default: `http://localhost:5000`)
- **PHP 8.2** or later with extensions: `curl`, `session`, `json`, `mbstring`
- A web server — **nginx** (recommended) or Apache

No admin API key is required before setup — the first admin account is created through the portal's setup page.

---

## Directory structure

```
ephemeralADMIN/
├── assets/
│   └── style.css
├── includes/
│   ├── api.php             HTTP client + portal settings helpers
│   ├── auth.php            Session auth, 2FA, trusted-device cookie
│   ├── header.php          Sidebar nav, HTML head, flash messages
│   └── footer.php          Closing HTML, shared JS
├── config.php              Two lines only: API_BASE and SITE_NAME
│
├── setup.php               First-run admin account creation
├── login.php               Email + password sign-in
├── logout.php              Clear session
├── 2fa.php                 Two-factor authentication code entry
├── forgot-password.php     Request a password reset link
├── set-password.php        Set or reset a password
├── verify.php              Email verification landing page
├── register-user.php       Self-service account registration
│
├── index.php               Admin dashboard
├── portal-admin.php        Admin home page
├── portal-user.php         Standard user self-service
├── keys.php                All API keys (admin)
├── key-detail.php          Key detail and management (admin)
├── key-output.php          Output configuration editor (admin + user)
├── class-limits.php        Default rate limit settings (admin)
├── smtp.php                SMTP configuration (admin)
├── email-templates.php     Email template editor (admin)
├── portal-settings.php     Portal behaviour settings (admin)
├── api-tester.php          Interactive API explorer
└── landing.php             Public home page
```

---

## Installation

### 1. Deploy the files

```bash
cp -r ephemeralADMIN/ /var/www/ephemeral-admin/
chown -R www-data:www-data /var/www/ephemeral-admin/
```

### 2. Edit config.php

`config.php` now contains only two values:

```php
define('API_BASE',  'https://api.yourdomain.com');
define('SITE_NAME', 'ephemeralREST');
```

Everything else — session timeout, trusted device expiry, admin promotion flag, logout redirect, portal URL, site version — is configured through the portal's **Settings → Portal Settings** page after first login and stored in the ephemeralREST database.

### 3. Configure the web server

#### nginx

```nginx
server {
    listen 80;
    server_name admin.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admin.yourdomain.com;

    root  /var/www/ephemeral-admin;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/admin.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.yourdomain.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;

    add_header X-Frame-Options        "SAMEORIGIN"  always;
    add_header X-Content-Type-Options "nosniff"     always;

    # Block direct access to includes
    location ~ ^/includes/ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include        snippets/fastcgi-php.conf;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    access_log /var/log/nginx/ephemeral-admin-access.log;
    error_log  /var/log/nginx/ephemeral-admin-error.log;
}
```

#### Apache

```apache
<VirtualHost *:443>
    ServerName admin.yourdomain.com
    DocumentRoot /var/www/ephemeral-admin

    DirectoryIndex index.php

    <Directory /var/www/ephemeral-admin>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    <Directory /var/www/ephemeral-admin/includes>
        Require all denied
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/admin.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.yourdomain.com/privkey.pem
</VirtualHost>
```

An `.htaccess` file is included in the repository with `Options -Indexes` and `/includes/` protection for Apache deployments.

#### PHP built-in server (development only)

```bash
php -S localhost:3000
```

---

## First-run setup

When the ephemeralREST database is empty, the portal automatically redirects all visitors to `/setup.php`.

1. Navigate to the portal URL — you are redirected to the setup page
2. Enter your name, email address, and a password (minimum 8 characters)
3. Click **Create Administrator →**
4. The setup page shows your API key **once** — note it, though you will rarely need it directly since you log in with email and password
5. Click **Continue to Dashboard**
6. Go to **Settings → SMTP Settings** and configure your mail server
7. Go to **Settings → Portal Settings** and set **Portal URL** to your portal's public address (e.g. `https://admin.yourdomain.com`)
8. Send a test email to confirm delivery

After setup completes, `/setup.php` is permanently disabled — `POST /setup` returns `403` once any key exists.

---

## Portal URL — critical setting

The **Portal URL** must be set before user registration works correctly. It is used to build links in verification emails and password-reset emails:

- Email verification: `{portal_url}/verify.php?t={token}`
- Set password: `{portal_url}/set-password.php?t={token}`
- Password reset: `{portal_url}/set-password.php?t={token}`

If not set, email links point to the API server and show raw JSON instead of a portal page.

Set it via **Settings → Portal Settings → Portal URL**, or add `PORTAL_URL=https://admin.yourdomain.com` to the ephemeralREST `.env` file.

---

## Email template names

The following template names are used by ephemeralREST for transactional emails. All are editable via **Settings → Email Templates**.

| Name | When sent |
|---|---|
| `registration-verification` | User registers — contains `{verify_url}` |
| `set-password` | Email verified — contains `{set_password_url}` |
| `password-reset-required` | Admin forces reset or user requests forgot-password — contains `{set_password_url}` |
| `user-activated` | User sets password first time — contains `{api_key}` |
| `2fa-code` | Login 2FA step — contains `{code}` and `{expiry_minutes}` |
| `key-rotated` | Key rotated — contains `{api_key}` |
| `test` | Sent manually from SMTP Settings |

---

## Session and authentication behaviour

- Sessions expire after the configured idle timeout (default 30 minutes, adjustable via Portal Settings)
- The session rolling timeout resets on every page load
- If an admin disables a key, the corresponding session is terminated on the next page request
- Admin privileges granted to a key take effect immediately on the next page load — no re-login required

### Two-factor authentication

All logins require a 2FA code sent by email **unless**:
- The browser presents a valid trusted-device token (set when the user previously ticked "Remember this device")
- The account is an administrator and SMTP is not yet configured (prevents a catch-22 on fresh installs)

Trusted-device tokens are stored per-email-address in a cookie (`epht_device`), allowing multiple accounts on the same browser. Tokens expire after `trusted_device_days` days (default 28). Logging out does **not** revoke the trusted-device token — the device remains trusted for future logins.

---

## Adding a new portal page

1. Create `my-page.php` in the portal root following the pattern of an existing simple page:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/auth.php';
auth_require('admin');   // omit 'admin' to allow all authenticated users

$page_title = 'My Page';

// Handle POST at top
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    // ... handle, echo json_encode([...])
    exit;
}

// Fetch data
$result = my_api_get('/some/endpoint');

require_once __DIR__ . '/includes/header.php';
?>

<!-- HTML here -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

2. Add it to the appropriate nav array in `includes/header.php`:

```php
$admin_nav['my-page'] = ['label' => 'My Page', 'icon' => '◎', 'section' => null];
```

3. Add any new API endpoints it uses to `routes.py` and `app.py` in ephemeralREST.

### API client helpers

| Function | Usage |
|---|---|
| `my_api_get($endpoint)` | Authenticated GET using session key |
| `my_api_post($endpoint, $body)` | Authenticated POST using session key |
| `my_api_delete($endpoint)` | Authenticated DELETE using session key |
| `api_get($endpoint, $auth=true)` | GET with optional auth |
| `api_post($endpoint, $body, $auth=true)` | POST with optional auth |
| `portal_setting($key, $default)` | Read a portal setting from cache |

Use `$auth=false` for public endpoints (login, register, setup, etc.).

---

## Upgrading from an older version

### From a version using ADMIN_API_KEY and config constants

The following `config.php` constants are **no longer used** and can be removed:

- `ADMIN_API_KEY` — eliminated. The portal uses the logged-in user's session key for all API calls
- `SESSION_TIMEOUT` — moved to Portal Settings in the database
- `ALLOW_ADMIN_PROMOTION` — moved to Portal Settings
- `LOGOUT_REDIRECT_URL` — moved to Portal Settings
- `TRUSTED_DEVICE_COOKIE_DAYS` — moved to Portal Settings
- `SITE_VERSION` — moved to Portal Settings

Replace the full `config.php` with the two-line version:

```php
define('API_BASE',  'https://api.yourdomain.com');
define('SITE_NAME', 'ephemeralREST');
```

After deploying, sign in and configure the equivalent settings under **Settings → Portal Settings**.

### Database migration

The ephemeralREST API runs all schema migrations automatically on startup. No manual SQL is required. New tables added in recent versions:

- `login_2fa_codes` — 2FA login codes
- `trusted_devices` — remember-me device tokens
- `portal_settings` — portal behaviour settings

The `api_keys` table gains `password_hash` and `must_change_password` columns. Existing key records get `must_change_password = 1` automatically — they will be prompted to set a password on first login.

---

## Verifying the installation

```bash
# Check the API is reachable
curl https://api.yourdomain.com/ping
# → { "status": "ok" }

# Check setup is required (new install)
curl https://api.yourdomain.com/setup/status
# → { "setup_required": true }
```

Then open the portal URL in a browser — you should be redirected to `/setup.php`.

---

## Security recommendations

- Serve over HTTPS only
- Disable admin promotion via Portal Settings once your admin accounts are established
- The `/includes/` directory must not be web-accessible
- `config.php` contains no secrets (API_BASE and SITE_NAME only) but should still be excluded from version control
- Consider restricting the portal to a VPN or internal network for sensitive deployments

---

## Troubleshooting

**Blank page or PHP fatal error**
```bash
php -m | grep -E 'curl|session|json|mbstring'
```
Ensure all required extensions are installed.

**"Call to undefined function auth_is_domain()"**
Old portal files are deployed. Replace all `.php` files with the current version.

**Dashboard shows API as unreachable**
Check `API_BASE` in `config.php` matches the address ephemeralREST is listening on. Test with `curl {API_BASE}/ping`.

**Login always requires 2FA even after ticking "Remember this device"**
The trusted-device cookie (`epht_device`) is not being set or sent. Check the browser is not blocking cookies for the portal domain. On HTTP (development), ensure the cookie is not marked `Secure` — the portal sets `Secure` only when `$_SERVER['HTTPS']` is set.

**Verification emails link to the API (raw JSON) instead of the portal**
Set **Portal URL** in **Settings → Portal Settings**, or add `PORTAL_URL=https://admin.yourdomain.com` to the ephemeralREST `.env` file and restart the API.

**Emails not delivered**
Use **Send Test Email** in SMTP Settings. Check ephemeralREST logs for SMTP errors. Common causes: wrong port, TLS/SSL mismatch, app password required instead of account password.

**"NOT NULL constraint failed: api_keys.key_type"**
Old `database.py` is deployed on the ephemeralREST side. Deploy the current version — it detects and repairs this schema issue automatically on startup.

**Session expires too quickly**
Increase **Session Timeout** via **Settings → Portal Settings** (value in seconds; default 1800).

**2FA code never arrives**
SMTP is not configured. Admins can still log in without 2FA when SMTP is absent (intentional bypass). Configure SMTP via the portal's SMTP Settings page.
