# ephemeralADMIN — User Guide

This guide covers everything you can do in the ephemeralADMIN portal — for administrators and standard users.

---

## Overview

The portal has two access levels.

| Access Level | Who | Landing Page |
|---|---|---|
| Administrator | Accounts with `admin: true` | Dashboard |
| Standard user | All other accounts | My Account |

Admin access is granted by an existing admin via the Keys page. All accounts use the same flat key structure — there is no domain/user distinction.

---

## Signing In

Login uses your email address and password. There is no API key entry field on the login page.

1. Navigate to the portal URL
2. Enter your email address and password
3. Click **Sign In →**

### Two-factor authentication

Unless you are on a remembered device, a 6-digit code is emailed to you after entering your credentials. Enter the code on the verification page to complete login.

Tick **Remember this device** to skip 2FA on this browser for the next 28 days (or however many days the operator has configured). Multiple email addresses on the same browser are each remembered independently.

### Trusted devices and logout

Logging out does **not** revoke the trusted-device status of your browser — the next login from the same browser will still skip 2FA. This is intentional: the device trust is about "this machine has previously authenticated here", which remains true after signing out.

To explicitly forget a device (for example, on a shared computer), your administrator can force a password reset which clears all trusted devices for your account.

---

## Registering an account

1. Navigate to `/register-user.php`
2. Enter your name and email address
3. Click **Send Verification Email**
4. Click the verification link in the email — the portal shows a confirmation page
5. Check your inbox again for a **set your password** email and click the link
6. Choose a password (minimum 8 characters)
7. A final email delivers your API key — **save it**, it is shown once only
8. Sign in at `/login.php` with your email and password

Your account is active immediately on setting a password — no admin approval required.

---

## Forgot password

If you cannot log in:

1. Click **Forgot your password?** on the sign-in page
2. Enter your email address and click **Send Reset Link →**
3. Check your inbox for a password reset email and click the link
4. Choose a new password

The confirmation message is the same whether or not your email is registered (to prevent account enumeration). If no email arrives within a few minutes, check your spam folder or contact your administrator.

---

## My Account (standard users)

After signing in, standard users land on the My Account page.

### Key details

Shows your name, email address, active status, and rate limits (per minute, per hour, per day). Rate limits are set by the administrator.

### Rotating your key

Rotating generates a new API key and immediately invalidates the old one. A new key is emailed to your address.

1. Click **Rotate My Key**
2. Confirm the prompt — your current key stops working immediately
3. Update your application or script with the new key from the email

### Output configuration

Click **Output Config** (in the page header or sidebar) to open the output configuration editor. This lets you override the server's default response fields for your key — for example, to disable bodies you never use or to enable heliocentric positions. See [Output Configuration Reference](#output-configuration-reference) below.

---

## Admin Portal

Administrators see the full sidebar navigation.

### Dashboard

Shows the health status of the connected ephemeralREST API, cache statistics, and server time.

### Keys

Lists all API keys.

Toggle **Show disabled** to include inactive keys. Use the **Edit** modal (click a key row or the Edit button) to manage a key.

#### Edit modal sections

| Section | Description |
|---|---|
| **Rate Limits** | Per-minute, per-hour, per-day overrides. Leave blank to use class defaults |
| **Rotate Key** | Generate a new key — new key is emailed to the account's email address |
| **Admin Access** | Grant or revoke admin privileges (if admin promotion is enabled in portal settings) |
| **Status** | Enable or disable the key |
| **Force Password Reset** | Sets `must_change_password`, clears trusted devices, emails a reset link |

Click **Output** in the key row to open the output configuration editor for that key.

### Key Detail

Click any key row to open the key detail page. Shows all key metadata plus a full audit trail of rate-limit changes, and access to the output configuration editor. Also provides the **Force Password Reset** action as a button.

### Rate Limits

Default rate limits applied to all keys when no per-key override is set. There is a single default class — individual keys can be overridden above or below this baseline.

Click **Save Limits** to apply.

### SMTP Settings

Controls the outgoing mail server for all transactional email.

| Field | Description |
|---|---|
| SMTP Host | Mail server hostname |
| Port | 587 for STARTTLS, 465 for SSL/TLS |
| Username | SMTP account username |
| Password | SMTP password or app password. Leave blank to retain the existing password |
| From Email Address | Sender address shown in outgoing emails |
| Admin Email | Receives admin notification emails |
| API Base URL | Public URL of the ephemeralREST API — used in API-side email links |
| Portal URL | Public URL of this admin portal — used in verification and password links. **Must be set for registration to work.** |
| Encryption | STARTTLS (recommended for port 587) or SSL/TLS (port 465) |

Click **Save Settings**, then **Send Test Email** to verify delivery. Settings must be saved before testing.

> **Portal URL** is the most important field. Verification emails and password-reset emails both link to pages on the portal. Without this, email links point to the API and show raw JSON.

### Email Templates

Customise the content and appearance of all transactional emails. Changes take effect immediately.

| Template | When sent |
|---|---|
| **Email Verification** | User registers — contains `{verify_url}` |
| **Set Password** | Email verified — contains `{set_password_url}` |
| **Password Reset Required** | Admin forces reset or user requests forgot-password — contains `{set_password_url}` |
| **Account Activated** | User sets password for first time — contains `{api_key}` |
| **2FA Verification Code** | Login 2FA step — contains `{code}` and `{expiry_minutes}` |
| **Key Rotated** | Any key rotation — contains `{api_key}` |
| **Test Email** | Sent manually from SMTP Settings |

**Appearance settings** (background colour, panel colour, text colour, content width) control the visual style. Saving the **Test Email** template propagates its appearance to all other templates.

**Content settings** are per-template: subject line, header text, header alignment, body text, and footer text.

**Substitution variables** in body text are replaced at send time:

| Variable | Value |
|---|---|
| `{name}` | Recipient's name |
| `{api_key}` | Issued or rotated API key |
| `{verify_url}` | Email verification link |
| `{set_password_url}` | Set/reset password link |
| `{code}` | 6-digit 2FA code |
| `{expiry_minutes}` | Minutes until the 2FA code expires |
| `{identifier}` | Account email address |

Bare URLs in body text are automatically converted to clickable links in HTML email clients.

Click **Save Template** to save. Click **Reset** to revert to built-in defaults.

### Portal Settings

Controls portal behaviour. All values are stored in the database and take effect immediately — no file changes or restarts required.

| Setting | Default | Description |
|---|---|---|
| Site Name | `ephemeralREST` | Shown in browser title and sidebar header |
| Version | `1.0` | Shown in sidebar footer |
| Session Timeout | `1800` | Seconds of inactivity before a session expires |
| Trusted Device Days | `28` | How long a "remember this device" cookie skips 2FA |
| Allow Admin Promotion | On | Whether admins can grant/revoke admin access via the portal |
| Portal URL | — | Public URL of this portal — used in email links |
| Logout Redirect URL | `/login.php` | Where to send users after signing out |

> Once your admin accounts are established, consider turning off **Allow Admin Promotion** to prevent accidental privilege changes.

### API Tester

An interactive console for all ephemeralREST endpoints. Available to all users.

Endpoints are organised into tabs: Health, Calculation, Derived Charts, Astronomical Events, Self-service, and Utility.

All authenticated calls use your signed-in key. Path parameters (e.g. chart ID) appear as input fields that update the URL in real time. POST endpoints have a pre-filled JSON body editor.

---

## Output Configuration Reference

The output configuration editor controls which fields ephemeralREST includes in responses for a key. Access it from My Account or the Keys page (**Output** button).

Fields shown in **gold** override the server default for this key. Click **Reset to Defaults** to remove all per-key overrides.

### Coordinate systems

| Field | Description |
|---|---|
| Geocentric | Geocentric ecliptic positions (standard) |
| Heliocentric | Heliocentric ecliptic positions |
| Right Ascension | Equatorial right ascension |
| Declination | Equatorial declination |
| Longitude Speed | Daily motion in longitude |
| Latitude Speed | Daily motion in latitude |
| Declination Speed | Daily motion in declination |
| Retrograde Flag | Boolean retrograde indicator (geocentric only) |

### Angles

ASC, MC, Vertex, East Point, and ARMC — each individually enabled or disabled.

### Celestial bodies

Individual toggles for: Sun, Moon, Mercury, Venus, Mars, Jupiter, Saturn, Uranus, Neptune, Pluto, Earth (heliocentric), Ceres, Pallas, Juno, Vesta, Chiron, Mean Node, True Node, South Node, Mean Lilith, True Lilith, Part of Fortune. The **Asteroids** toggle is a master switch for Ceres, Pallas, Juno, Vesta, and Chiron.

### Response metadata

- **API Usage** — Google API request counts in `/calculate` responses
- **From Cache** — cache status flag in responses

### Default house system

Sets the fallback house system when a request doesn't specify one. Leave at None to suppress house cusps by default.

---

## Frequently asked questions

**I lost my API key. How do I get a new one?**
Go to My Account → click **Rotate My Key**. A new key will be emailed to your address. The old key is immediately invalidated.

**I need a higher rate limit.**
Rate limits are set by the administrator per key or globally. Contact your admin.

**My key was disabled. What do I do?**
Contact your administrator. Only an admin can re-enable a key.

**I received a verification email but the link isn't working.**
The link expires after 24 hours and can only be used once. Register again at `/register-user.php` to receive a new link.

**I haven't received any emails.**
The portal's SMTP may not be configured. Contact your administrator. They can check the SMTP settings and send a test email.

**The API tester shows "Unauthorized".**
Your key may have been rotated or disabled since you signed in. Sign out and sign in again.

**I can't remember my password.**
Use the **Forgot your password?** link on the sign-in page to receive a reset link by email.

**I'm always asked for a 2FA code even after ticking "Remember this device".**
The trusted-device cookie may have been cleared (browser data wipe, private browsing, different browser). Tick the box again after completing 2FA to re-establish trust for this browser.

**How do I revoke trusted-device status from a shared computer?**
Ask your administrator to use **Force Password Reset** on your account. This clears all trusted devices for your account, requiring fresh 2FA on all browsers on next login.
