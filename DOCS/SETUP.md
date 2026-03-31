# ephemeralADMIN — Setup Guide

Administration portal for [ephemeralREST](https://github.com/gmelh/ephemeralREST).

---

## Prerequisites

- **ephemeralREST** running and reachable (default: `http://localhost:5000`)
- **PHP 8.1** or later with extensions: `curl`, `session`, `json`, `mbstring`
- A web server — **nginx** (recommended) or Apache
- An admin API key for ephemeralREST (created via `key_manager.py`)

---

## Directory Structure

```
ephemeralADMIN/
├── assets/
│   └── style.css
├── includes/
│   ├── api.php
│   ├── auth.php
│   ├── header.php
│   └── footer.php
├── config.php
├── index.php
├── landing.php
├── login.php
├── logout.php
├── verify.php
├── api-tester.php
├── class-limits.php
├── email-templates.php
├── key-output.php
├── keys.php
├── portal-admin.php
├── portal-domain.php
├── portal-user.php
├── register-domain.php
├── register-key.php
├── register-user.php
├── registrations.php
├── smtp.php
└── SETUP.md
```

> **Note:** `key-detail.php` is no longer part of the active portal. All key management is handled via the modal on `keys.php`.

---

## Installation

### 1. Deploy the files

```bash
cp -r ephemeralADMIN/ /var/www/ephemeral-admin/
chown -R www-data:www-data /var/www/ephemeral-admin/
```

### 2. Configure `config.php`

```php
// URL of the running ephemeralREST instance
define('API_BASE', 'http://localhost:5000');

// Admin API key created via key_manager.py on the ephemeralREST side
// Used for portal-level API calls (static admin operations)
define('ADMIN_API_KEY', 'your-admin-api-key-here');

// Display name shown in the portal UI and emails
define('SITE_NAME', 'ephemeralREST');

// Version string shown in the sidebar footer
define('SITE_VERSION', '1.0');

// Session timeout in seconds (default: 30 minutes)
define('SESSION_TIMEOUT', 1800);

// Allow administrators to grant or revoke admin access on other keys via the portal.
// Set to false once your admin keys are established.
define('ALLOW_ADMIN_PROMOTION', true);

// URL to redirect to after logout.
// Use a relative path to stay on this server, or a full URL to redirect elsewhere.
define('LOGOUT_REDIRECT_URL', '/landing.php');
```

> **Security note:** `ADMIN_API_KEY` is the master admin key. Do not commit `config.php` to version control. Add it to `.gitignore` and use a `config.example.php` template instead.

### 3. Configure the web server

#### nginx

```nginx
server {
    listen 80;
    server_name admin.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name admin.yourdomain.com;

    root /var/www/ephemeral-admin;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/admin.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.yourdomain.com/privkey.pem;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    # Block direct access to includes
    location /includes/ {
        deny all;
    }
}
```

#### Apache

```apache
<VirtualHost *:443>
    ServerName admin.yourdomain.com
    DocumentRoot /var/www/ephemeral-admin

    <Directory /var/www/ephemeral-admin>
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/ephemeral-admin/includes>
        Require all denied
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/admin.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.yourdomain.com/privkey.pem
</VirtualHost>
```

#### PHP built-in server (development only)

```bash
php -S localhost:8080
```

---

## ephemeralREST Requirements

### Database migration

The `email_templates` table is created automatically when ephemeralREST starts (via `CREATE TABLE IF NOT EXISTS`). No manual migration is needed if you are running the current version of `database.py`.

The six template names used by the portal are:

| Name | Trigger |
|------|---------|
| `test` | Sent manually from SMTP Settings |
| `register-domain` | Sent on domain key request submission |
| `register-approved` | Sent when a registration is approved |
| `register-rejected` | Sent when a registration is rejected |
| `user-verify` | Sent when a user registers (verification link) |
| `key-rotated` | Sent when any key is rotated |

### Required API endpoints

The following endpoints must be present in ephemeralREST. All require admin authentication via `X-API-Key` unless marked public.

**Email templates**
```
GET  /admin/email-templates/{name}
POST /admin/email-templates/{name}
POST /admin/email-templates/{name}/reset
```

**Admin promotion**
```
POST /admin/keys/{id}/set-admin
Body: { "admin": true | false }
```

**Key type toggle**
```
POST /admin/keys/{id}/set-type
Body: { "key_type": "domain" | "user" }
```

**Email verification** (public)
```
GET /register/verify?t={token}
```

**Eclipses** (authenticated)
```
POST /eclipses
Body: { "reference_date": "YYYY-MM-DD", "years_ahead": 5 }
```

---

## SMTP Configuration

SMTP settings are stored in the ephemeralREST database, not in config files. Configure them through the portal UI:

1. Sign in → **SMTP Settings** in the sidebar
2. Fill in all connection fields
3. Set **Portal URL** to the public URL of this admin portal (e.g. `https://admin.example.com`) — this is used for verification email links
4. Save, then use **Send Test Email** to verify

Until SMTP is configured, all transactional emails are logged but not delivered.

### Portal URL

The **Portal URL** field is distinct from **API Base URL**:

| Field | Purpose |
|-------|---------|
| API Base URL | Public URL of the ephemeralREST API — used in admin notification links |
| Portal URL | Public URL of this admin portal — used in user verification email links (`/verify.php?t=...`) |

If Portal URL is left blank it falls back to API Base URL, which will cause verification links to 404.

---

## User Registration Flow

User keys (email-based) follow this flow:

1. User submits name + email on `/register-user.php`
2. ephemeralREST creates an inactive key and sends a verification email to the address with a link to `{portal_url}/verify.php?t={token}`
3. User clicks the link → `verify.php` calls `GET /register/verify?t={token}` on the API
4. API activates the key, emails the plaintext key to the user, and returns JSON
5. `verify.php` shows a success page: "Check your inbox for your API key"

The key is delivered by email only — it is never shown in the browser.

---

## Initial Admin Setup

The portal does not create admin keys. Create one on the ephemeralREST server:

```bash
python key_manager.py create --admin --name "Your Name" --identifier admin@yourdomain.com
```

The key is printed to the terminal once. Verify it works:

```bash
curl -H "X-API-Key: your-key" http://localhost:5000/me
# Should return { "admin": true, ... }
```

---

## Session Behaviour

The portal refreshes the authenticated user's data from `/me` on every page load. This means:

- Admin privileges granted to a key take effect on the next page load — no re-login required
- If a key is disabled by an admin, the session is terminated immediately on the next request
- The session rolling timeout resets with each page load; idle sessions expire after `SESSION_TIMEOUT` seconds

---

## Verifying the Installation

1. Open the portal URL — you should see the landing page
2. Click **Sign In** and enter your admin API key
3. You should land on the **Dashboard**
4. A green health indicator confirms the API is reachable
5. Navigate to **SMTP Settings** → fill in and save → **Send Test Email**

---

## Security Recommendations

- Serve over HTTPS only
- Set `ALLOW_ADMIN_PROMOTION = false` in `config.php` once your admin keys are established
- The `/includes/` directory must not be web-accessible
- `config.php` must not be in version control
- Consider restricting the portal to a VPN or internal network

---

## Troubleshooting

**Blank page or PHP error**
```bash
php -m | grep -E 'curl|session'
```

**Dashboard shows API as unreachable**
Check `API_BASE` in `config.php` matches the address ephemeralREST is listening on.

**Login fails with a valid key**
```bash
python key_manager.py show --identifier admin@yourdomain.com
```
Check the key is active and has `admin: true`.

**Verification emails link to the API instead of the portal**
Set the **Portal URL** field in SMTP Settings to the public URL of this admin portal.

**Emails not delivered**
Use Send Test Email in SMTP Settings. Check ephemeralREST logs for SMTP errors. Common causes: wrong port, TLS/SSL mismatch, app password required.

**Session expires too quickly**
Increase `SESSION_TIMEOUT` in `config.php` (seconds; default 1800).

**Key type changed but portal still shows old type**
The portal refreshes user data on every page load. If you changed your own key type, sign out and back in to see the change reflected in your session.