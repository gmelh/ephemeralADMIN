# ephemeralADMIN — Setup Guide

Administration portal for [ephemeralREST](https://github.com/gmelh/ephemeralREST).

---

## Prerequisites

- **ephemeralREST** running and reachable (default: `http://localhost:5000`)
- **PHP 8.1** or later with the following extensions: `curl`, `session`, `json`, `mbstring`
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
├── class-limits.php
├── email-templates.php
├── key-detail.php
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

---

## Installation

### 1. Deploy the files

Copy the ephemeralADMIN directory to your server. The portal is a flat PHP application with no Composer dependencies — no build step is required.

```bash
cp -r ephemeralADMIN/ /var/www/ephemeral-admin/
chown -R www-data:www-data /var/www/ephemeral-admin/
```

### 2. Configure `config.php`

Open `config.php` and set the following constants:

```php
// URL of the running ephemeralREST instance
define('API_BASE', 'http://localhost:5000');

// Admin API key created via key_manager.py on the ephemeralREST side
// This key is used for all admin-authenticated API calls made by the portal itself
define('ADMIN_API_KEY', 'your-admin-api-key-here');

// Display name shown in the portal UI and emails
define('SITE_NAME', 'ephemeralREST');

// Version string shown in the sidebar footer
define('SITE_VERSION', '1.0');

// Session timeout in seconds (default: 30 minutes)
define('SESSION_TIMEOUT', 1800);

// Allow administrators to grant or revoke admin access on other keys via the portal.
// Set to false once your admin keys are established and you want to lock this down.
define('ALLOW_ADMIN_PROMOTION', true);
```

> **Security note:** `ADMIN_API_KEY` is the master admin key. Treat it like a root password.
> Do not commit `config.php` to version control. Add it to `.gitignore`.

### 3. Configure the web server

#### nginx

Create a server block pointing to the ephemeralADMIN directory:

```nginx
server {
    listen 80;
    server_name admin.yourdomain.com;

    root /var/www/ephemeral-admin;
    index index.php;

    # Redirect HTTP to HTTPS (recommended)
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

Reload nginx after saving:

```bash
nginx -t && systemctl reload nginx
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

    # Block direct access to includes
    <Directory /var/www/ephemeral-admin/includes>
        Require all denied
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/admin.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.yourdomain.com/privkey.pem
</VirtualHost>
```

#### PHP built-in server (development only)

For local development and testing, from the ephemeralADMIN directory:

```bash
php -S localhost:8080
```

Or to allow connections from other devices on your network:

```bash
php -S 0.0.0.0:8080
```

> Do not use the built-in server in production.

---

## Database Migration

The email template editor requires an `email_templates` table in the ephemeralREST database. Run the following migration on the ephemeralREST SQLite database:

```sql
CREATE TABLE IF NOT EXISTS email_templates (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          VARCHAR(64) UNIQUE NOT NULL,
    bg_color      VARCHAR(16)  NOT NULL DEFAULT '#f4f4f4',
    panel_color   VARCHAR(16)  NOT NULL DEFAULT '#ffffff',
    text_color    VARCHAR(16)  NOT NULL DEFAULT '#1a1a1a',
    content_width INTEGER      NOT NULL DEFAULT 600,
    header_align  VARCHAR(8)   NOT NULL DEFAULT 'left',
    subject       TEXT,
    header_text   TEXT,
    body_text     TEXT,
    footer_text   TEXT,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

The six template names used by the portal are:

| Name | Trigger |
|------|---------|
| `test` | Sent manually from SMTP Settings |
| `register-domain` | Sent on domain key request submission |
| `register-approved` | Sent when a registration is approved |
| `register-rejected` | Sent when a registration is rejected |
| `user-verify` | Sent when a user registers (verification link) |
| `key-rotated` | Sent when a key is rotated |

If a template has no row in the database, ephemeralREST falls back to the hardcoded defaults defined in the API. You only need rows for templates you have customised.

---

## ephemeralREST API Endpoints Required

The following endpoints must be present in ephemeralREST for full portal functionality. All require admin authentication via `X-API-Key`.

### Email Templates

```
GET  /admin/email-templates/{name}
POST /admin/email-templates/{name}
POST /admin/email-templates/{name}/reset
```

`GET` response shape:

```json
{
  "template": {
    "name": "test",
    "bg_color": "#f4f4f4",
    "panel_color": "#ffffff",
    "text_color": "#1a1a1a",
    "content_width": 600,
    "header_align": "left",
    "subject": "Test email from ephemeralREST",
    "header_text": "ephemeralREST",
    "body_text": "This is a test email...",
    "footer_text": "You are receiving this email because..."
  }
}
```

### Admin Promotion

```
POST /admin/keys/{id}/set-admin
Body: { "admin": true | false }
```

This endpoint should refuse to modify a key if it is the only admin key remaining, and should refuse if the requesting key is modifying itself.

---

## Initial Admin Setup

The portal does not create admin keys — that is done on the ephemeralREST side using `key_manager.py`.

```bash
# On the ephemeralREST server
python key_manager.py create --admin --name "Your Name" --identifier admin@yourdomain.com
```

The key is printed to the terminal once. Store it securely — it is your login credential for the admin portal.

To verify the key works before opening a browser:

```bash
curl -H "X-API-Key: your-key" http://localhost:5000/me
```

You should receive a JSON response with `"admin": true`.

---

## SMTP Configuration

SMTP is configured through the portal UI rather than a config file, so that credentials are stored in the ephemeralREST database and do not need to be in any PHP file.

1. Sign in to the admin portal
2. Navigate to **SMTP Settings** in the sidebar
3. Enter your mail server details and save
4. Use **Send Test Email** to verify the configuration

Until SMTP is configured, all transactional emails (registration confirmations, approvals, key delivery, verification links) are logged by ephemeralREST but not delivered.

---

## Email Template Customisation

The appearance and content of all six transactional emails can be customised without touching code.

1. Sign in to the admin portal
2. Navigate to **Email Templates** in the sidebar
3. Select a template from the tabs
4. Adjust colours, width, and text
5. Use the live preview to check the result before saving

Templates support substitution variables (shown as `{variable}` tags in the body field for templates that use them). These are replaced by ephemeralREST at send time with the actual values for each recipient.

---

## Verifying the Installation

Once the web server is configured:

1. Open the portal URL in a browser — you should see the ephemeralREST landing page
2. Click **Sign In** and enter your admin API key
3. You should land on the **Dashboard** (portal-admin.php)
4. The dashboard shows a green health indicator if the API at `API_BASE` is reachable
5. Navigate to **SMTP Settings** and configure your mail server
6. Send a test email to confirm end-to-end delivery

If the health indicator is red, check that `API_BASE` in `config.php` is correct and that ephemeralREST is running.

---

## Security Recommendations

- Serve the portal over HTTPS only — the API key is the only credential and must not travel in plaintext
- Set a strong, unique value for `ADMIN_API_KEY` in `config.php`
- Once your admin keys are established, set `ALLOW_ADMIN_PROMOTION = false` in `config.php` to prevent the portal from being used to create new admin keys
- The `/includes/` directory should not be web-accessible (the nginx and Apache configs above both block it)
- Consider restricting the admin portal to a VPN or internal network using firewall rules or nginx `allow`/`deny` directives
- `config.php` must not be committed to version control — add it to `.gitignore` and use a template (`config.example.php`) instead

---

## Troubleshooting

**Portal shows a blank page or PHP error**
Ensure PHP 8.1+ is installed and the `curl` and `session` extensions are enabled:
```bash
php -m | grep -E 'curl|session'
```

**Dashboard shows API as unreachable**
Check that `API_BASE` in `config.php` matches the address ephemeralREST is actually listening on, and that no firewall is blocking the connection between the web server process and the API.

**Login fails with a valid key**
Confirm the key has not been disabled. On the ephemeralREST server:
```bash
python key_manager.py show --identifier admin@yourdomain.com
```

**Emails are not being delivered**
Navigate to SMTP Settings and use Send Test Email. Check the ephemeralREST log for any SMTP errors. Common issues: wrong port, TLS/SSL mismatch, app password required (Gmail, Outlook).

**Session expires too quickly**
Increase `SESSION_TIMEOUT` in `config.php`. The value is in seconds; the default is 1800 (30 minutes).