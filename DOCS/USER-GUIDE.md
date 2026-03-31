# ephemeralADMIN — User Guide

This guide covers everything you can do in the ephemeralADMIN portal — for administrators, domain key holders, and personal user key holders.

---

## Overview

The portal has three access levels based on key type.

| Key Type | Access Level | Landing Page |
|----------|-------------|--------------|
| Admin key | Full platform management | Dashboard |
| Domain key | Manage your own domain key | Domain Portal |
| User key | Manage your own personal key | User Portal |

Admin access can be granted to any key type (domain or user) — it is not limited to domain keys.

---

## Signing In

Your API key is your login credential. There is no separate username and password.

1. Open the portal URL in your browser
2. Click **Sign In** or navigate to `/login.php`
3. Paste your API key and click **Sign In →**
4. You are redirected to the portal appropriate for your key type

Sessions expire after 30 minutes of inactivity (configurable by the administrator). Your session is refreshed on every page load, so the timeout only applies if you stop using the portal entirely.

---

## Registering a Key

### Domain Key

For server-to-server use — a web application, service, or backend.

1. Navigate to `/register-domain.php`
2. Enter your domain, contact name, and email address
3. Optionally add a reason for the request
4. Click **Submit Request**

A confirmation email is sent immediately. Requests are reviewed by an administrator. On approval, your API key is emailed to you — it is shown only once. Store it immediately.

**Wildcard domain:** Enter `*` as the domain for personal use or desktop applications. A lower rate limit class applies automatically.

### User Key

For personal or direct access, tied to your email address.

1. Navigate to `/register-user.php`
2. Enter your name and email address
3. Click **Send Verification Email**
4. Click the verification link in the email
5. The verification page confirms success and your API key is emailed separately

User keys are issued immediately on verification — no admin approval required. Your API key is delivered by email only and is not shown in the browser.

---

## Domain Key Portal

After signing in with a domain key, you land on the Domain Portal.

### Key Details

Shows your domain, key type, status (active or disabled), and rate limits (per minute, per hour, per day). Rate limits are set by the administrator.

### Registration Status

Shows the status of your domain registration request (pending, approved, or rejected) and any note from the administrator.

### Rotating Your Key

Rotating generates a new API key and immediately invalidates the old one.

1. Click **Rotate My Key**
2. Confirm in the prompt — the current key stops working immediately
3. Your new key is emailed to your registered address
4. Update your application or service with the new key

### Output Configuration

Lets you override the server's default response fields for your key. Navigate to **Output Config** or use the Output Configuration card. Fields shown in gold are overriding the server default.

---

## User Key Portal

After signing in with a personal user key, you land on the User Portal. Functionality is the same as the Domain Portal — key details, rotate, and output configuration — with your email address shown as the identifier.

---

## Admin Portal

Administrators have access to all portal pages via the full sidebar navigation.

### Dashboard

Shows the health status of the connected ephemeralREST API, server time, supported house systems, and cache statistics. A prominent button appears when there are pending registration requests.

### Registrations

Lists all domain key requests. Filter by status using the tabs: All, Pending, Approved, Rejected.

**Quick action:** Approve and Reject buttons appear directly in the table row for pending requests with no admin note.

**Edit modal:** Click **Edit** to open the detail modal, add an admin note, and approve or reject. The note is included in the email sent to the registrant.

When approved, ephemeralREST generates an API key and emails it to the contact address. The key is not shown in the portal.

### Keys

Lists all API keys. Filter by type (All, Domain, User) and toggle inactive key visibility.

**Edit modal:** Click **Edit** on any key row to open the management modal with the following sections (in order):

| Section | Description |
|---------|-------------|
| **Rate Limits** | Per-minute, per-hour, per-day limits for this key. Blank = use class default |
| **Rotate Key** | Generate a new key; invalidates the old one immediately; new key emailed to registrant |
| **Key Type** | Toggle between `domain` and `user`. Shows current type and a switch button |
| **Admin Access** | Grant or revoke admin privileges (only shown when `ALLOW_ADMIN_PROMOTION` is enabled) |
| **Status** | Enable or disable the key |

Click **Save Settings** to persist rate limit changes. Key type and admin/status changes take effect immediately via individual API calls within the modal.

Click **Output** in the table row to open the full output configuration editor for that key.

### API Tester

An interactive test console for all ephemeralREST endpoints. Available to all key holders (not just admins). Navigate to `/api-tester.php`.

Endpoints are organised into tabs:

| Tab | Endpoints |
|-----|-----------|
| **Health** | Health Check, Cache Stats, Autocomplete |
| **Calculation** | Calculate, Recalculate, Get Chart, Secondary Progressions, Solar Arc, Solar Return, Lunar Return, List Derived Charts |
| **Derived Charts** | Get Derived Chart, Delete Derived Chart |
| **Astronomical Events** | Apsides, Next Apsides, Lunations, Eclipses, Ephemeris |
| **Self-service** | My Identity, My Output Config, Update My Output Config |
| **Utility** | Resolve Location |

**Features:**
- All authenticated calls use your signed-in key — shown in the auth indicator under the endpoint description
- Path parameters (e.g. chart ID) appear as labelled input fields that update the URL display in real time
- Query parameters (e.g. autocomplete search) support live debounce — Autocomplete auto-runs 500ms after you stop typing (minimum 3 characters)
- POST endpoints have a pre-filled JSON body editor with live validation
- Response panel shows status code, duration, headers, and pretty-printed JSON

### Class Limits

Default rate limits applied to each key type when no per-key override is set.

| Class | Applied to |
|-------|-----------|
| `domain` | Keys registered with a specific domain name |
| `user` | Email-based personal keys |
| `wildcard` | Keys registered with `*` as the domain |

Click **Edit** on any class row to adjust limits. Individual key overrides always take precedence over class defaults.

### SMTP Settings

Controls the outgoing mail server for all transactional email.

| Field | Description |
|-------|-------------|
| SMTP Host | Mail server hostname |
| Port | 587 for STARTTLS, 465 for SSL/TLS |
| Username | SMTP account username |
| Password | SMTP password or app password. Leave blank to retain the existing password |
| From Email Address | Sender address shown in outgoing emails |
| Admin Email | Receives new registration notification emails |
| API Base URL | Public URL of the ephemeralREST API — used in admin-facing email links |
| Portal URL | Public URL of this admin portal — used in user verification email links. Must be set for `/verify.php` links to work correctly |
| Encryption | STARTTLS (recommended for port 587) or SSL/TLS (port 465) |

The **Common Provider Settings** panel lists pre-filled settings for popular mail services.

Click **Save Settings**, then **Send Test Email** to verify. Settings must be saved before testing.

### Email Templates

Customise the appearance and content of all transactional emails without modifying code. Changes are stored in the database and take effect immediately.

Use the tabs at the top to switch between templates:

| Template | When it is sent |
|----------|----------------|
| Test Email | Sent manually from SMTP Settings |
| Domain Registration | Sent when a domain key request is submitted |
| Registration Approved | Sent when an admin approves a request — contains the API key |
| Registration Rejected | Sent when an admin rejects a request |
| Email Verification | Sent when a user registers — contains the one-time verification link |
| Key Rotated | Sent when any key is rotated — contains the new API key |

**Appearance settings** control the visual style of the email panel:

- **Background Colour** — outer page background
- **Panel Background** — content card background (including footer)
- **Text Colour** — applies to all text
- **Content Width** — panel width in pixels (320–800)

> Saving the **Test Email** template propagates its appearance settings (colours and width) to all other templates automatically. Individual template saves update only that template's appearance.

**Content settings** are per-template:

- **Subject Line** — email subject in the inbox
- **Header Text** — prominent text at the top of the panel
- **Header Alignment** — Left, Centre, or Right
- **Body Text** — main message (blank lines = paragraph breaks)
- **Footer Text** — small-print at the bottom

**Substitution variables** are replaced by ephemeralREST at send time:

| Variable | Value |
|----------|-------|
| `{name}` | Recipient's name |
| `{domain}` | The registered domain |
| `{api_key}` | The issued or rotated API key |
| `{admin_note}` | Note entered by the admin |
| `{verify_url}` | One-time email verification link |
| `{identifier}` | Domain or email address for the key |

The **Live Preview** panel updates as you type. Click **Save Template** to save. Click **Reset** to revert to built-in defaults.

### Key Type

From the Keys modal, the **Key Type** section shows the current type and provides a single **Switch to domain/user** button. This changes the key's classification and the rate limit class applied to it. A confirmation prompt appears before the change is made. The type badge in the keys table updates in-place.

### Admin Access

When `ALLOW_ADMIN_PROMOTION` is enabled in `config.php`, you can grant or revoke admin access on any key other than your own.

**Granting admin access** gives a key the ability to access all admin-only API endpoints and sign in to the admin portal with full management permissions.

**Revoking admin access** removes these privileges immediately. The last admin key cannot be revoked.

To change admin access: open the **Edit** modal on the Keys page and find the **Admin Access** section. Grant and Revoke buttons both require confirmation. You cannot change your own admin status.

To permanently disable this feature, set `ALLOW_ADMIN_PROMOTION = false` in `config.php`.

---

## Output Configuration Reference

The output configuration editor controls which fields ephemeralREST includes in responses for a key. Access it from the Domain Portal, User Portal, or the **Output** button on the Keys page.

### Coordinate Systems

| Field | Description |
|-------|-------------|
| Geocentric | Geocentric ecliptic positions |
| Heliocentric | Heliocentric ecliptic positions |
| Right Ascension | Equatorial right ascension |
| Declination | Equatorial declination |
| Longitude Speed | Daily motion in longitude |
| Latitude Speed | Daily motion in latitude |
| Declination Speed | Daily motion in declination |
| Retrograde Flag | Boolean retrograde indicator (geocentric only) |

### Angles

ASC, MC, Vertex, East Point, and ARMC can each be individually enabled or disabled.

### Planets and Special Points

Individual toggles for all ten standard planets, Earth (heliocentric), Ceres, Pallas, Juno, Vesta, Chiron, Mean Node, True Node, South Node, Mean Lilith, True Lilith, and Part of Fortune. The **Asteroids** toggle is a master switch.

### Response Metadata

- **API Usage** — Google API request counts in `/calculate` responses
- **From Cache** — cache status flag in responses

### Default House System

Sets the fallback house system when a request doesn't specify one. Leave at None to suppress house cusps unless the request includes `house_system`.

Fields shown in **gold** override the server default for this key. Click **Reset to Defaults** to remove all per-key overrides.

---

## Frequently Asked Questions

**I lost my API key. How do I get a new one?**
Contact your administrator. They can rotate your key from the Keys page — a new key will be emailed to your registered address.

**I need a higher rate limit.**
Rate limits are set by the administrator per key or per class. Contact your admin.

**Can I have more than one domain key?**
Yes. Submit a separate registration request for each domain.

**My key was disabled. What do I do?**
Contact your administrator. Only an admin can re-enable a key.

**I received a verification email but the link isn't working.**
The link may have expired (24 hours) or already been used. Register again at `/register-user.php`.

**The API tester shows "Unauthorized".**
Your key may have been rotated or disabled since you last signed in. Sign out and sign in again with your current key.

**Key type was changed but rate limits didn't change.**
Rate limits update to the new class defaults on the next API request. Per-key limit overrides are unaffected by type changes.