# ephemeralADMIN — User Guide

This guide covers everything you can do in the ephemeralADMIN portal — for administrators, domain key holders, and personal user key holders.

---

## Overview

The portal has three access levels, each with its own set of pages. Which portal you land on depends on your API key type.

| Key Type | Access Level | Landing Page |
|----------|-------------|--------------|
| Admin key | Full platform management | Dashboard |
| Domain key | Manage your own domain key | Domain Portal |
| User key | Manage your own personal key | User Portal |

---

## Signing In

The portal uses your API key as your login credential. There is no separate username and password.

1. Open the portal URL in your browser
2. Click **Sign In** (top right of the landing page) or navigate directly to `/login.php`
3. Paste your API key into the field and click **Sign In →**
4. You will be redirected to the portal appropriate for your key type

Your session stays active for 30 minutes of inactivity. After that you will need to sign in again.

If you see "Your key doesn't have permission to access that area", your key type does not match the page you are trying to reach. Use the link appropriate for your key type.

---

## Registering a Key

If you do not yet have an API key, you can request one from the landing page or the registration pages.

### Domain Key

A domain key is intended for server-to-server use — a web application, service, or backend making API calls on behalf of your platform.

1. Navigate to **Register a Domain** (`/register-domain.php`)
2. Enter your domain name (e.g. `myapp.com`), your contact name, and your email address
3. Add a brief description of your intended use (optional but recommended)
4. Click **Submit Request**

A confirmation email is sent immediately. Your request is reviewed by an administrator, typically within 1–2 business days. Once approved, your API key is emailed to you. The key is shown only once — store it securely immediately.

**Wildcard domain:** If you do not have a domain (personal use, desktop application, direct access), enter `*` as the domain. A lower rate limit class applies automatically.

### User Key

A user key is tied to your email address and is intended for personal or desktop access.

1. Navigate to **Register as a User** (`/register-user.php`)
2. Enter your name and email address
3. Click **Send Verification Email**
4. Click the verification link in the email you receive
5. Your API key is displayed once on the verification page — copy it and store it securely

User keys are issued immediately on verification without admin approval.

---

## Domain Key Portal

After signing in with a domain key, you land on the Domain Portal. From here you can view your key details, check your registration status, rotate your key, and configure output options.

### Key Details

The Key Details card shows your domain, key type, current status (active or disabled), and your rate limits (requests per minute, per hour, and per day). These limits are set by the administrator and cannot be changed by the key holder.

### Registration Status

If a registration request was submitted for your domain, its current status (pending, approved, or rejected) is shown here, along with any note left by the administrator.

### Rotating Your Key

Rotating generates a new API key and immediately invalidates the old one. Use this if your key has been compromised or as a routine security practice.

1. Click **Rotate My Key**
2. Confirm the action in the prompt — the current key stops working immediately
3. Your new key is displayed once on this page — copy it immediately
4. Update your application or service with the new key

There is no way to recover the new key after you leave this page. If you lose it, you will need to rotate again.

### Output Configuration

The output configuration lets you override the server's default response fields for your key. This is useful if your application only needs a subset of the data returned by the API.

1. Click **Output Config** (top right of the portal) or use the Output Configuration card
2. Adjust the toggles for the coordinate systems, bodies, angles, and metadata you need
3. Click **Save Configuration**

Fields shown in gold are overriding the server default. Fields at their default value are inherited from the server. Click **Reset to Defaults** to remove all per-key overrides.

---

## User Key Portal

After signing in with a personal user key, you land on the User Portal. The functionality is the same as the Domain Portal — key details, rotate, and output config — but the identity shown is your email address rather than a domain name.

---

## Admin Portal

Administrators have access to all portal pages. The sidebar shows the full navigation.

### Dashboard

The dashboard shows the health status of the connected ephemeralREST API, including whether it is reachable, its server time, and supported house systems. Cache statistics (chart count, place cache) are shown when available.

A prominent button appears on the dashboard when there are pending registration requests waiting for review.

### Registrations

The Registrations page lists all domain key requests. Use the tabs to filter by status: All, Pending, Approved, or Rejected.

**Quick approve or reject:** Pending requests have Approve and Reject buttons directly in the table row. Clicking either submits immediately with no admin note.

**Edit modal:** Click **Edit** on any request to open the detail modal. Here you can review the requester's domain, name, email, and reason for requesting. You can add an admin note — this text is included in the approval or rejection email sent to the registrant. Click **Approve** or **Reject** in the modal footer.

When a request is approved, ephemeralREST generates an API key, stores it, and emails it to the contact address. The key is not shown in the portal.

When a request is rejected, the registrant receives an email with your admin note explaining the decision.

### Keys

The Keys page lists all API keys in the system. Use the type tabs (All, Domain, User) and the Show/Hide disabled toggle to filter the list.

**Edit modal:** Click **Edit** on any key row to open the management modal. From here you can:

- **Adjust rate limits** — set per-minute, per-hour, and per-day limits for this key individually. Leave blank to use the class default for the key type.
- **Rotate the key** — generates a new key, immediately invalidates the old one, and displays the new key once in the modal. The new key is also emailed to the registrant.
- **Enable or disable** — disabling a key immediately rejects all requests using it. The key record is preserved and can be re-enabled at any time.
- **Grant or revoke admin access** — see the Admin Access section below.

Click **Output** in the table row (not the modal) to go to the full output configuration editor for that key.

### Key Detail Page

Click any key's identifier in the Keys table or navigate to `/key-detail.php?id={n}` for a dedicated page with the full set of management options for a single key. This page is useful for keys that need detailed attention — it shows all fields, a rotate section with a displayed new key, rate limit editing, output config editing, and the admin access section.

### Class Limits

Class limits are the default rate limits applied to each key type when no per-key override is set. There are three classes:

| Class | Applied to |
|-------|-----------|
| `domain` | Keys registered with a specific domain name |
| `user` | Email-based personal keys |
| `wildcard` | Keys registered with `*` as the domain |

Click **Edit** on any class row to open the edit modal. Enter the desired requests per minute, per hour, and per day, then click **Save Limits**. Changes take effect immediately for all keys of that class that do not have individual overrides.

Individual key overrides always take precedence over class defaults.

### SMTP Settings

SMTP settings control the outgoing mail server used for all transactional email. Until SMTP is configured, emails are logged by ephemeralREST but not delivered.

**Connection Settings fields:**

| Field | Description |
|-------|-------------|
| SMTP Host | Mail server hostname (e.g. `smtp.gmail.com`) |
| Port | Usually 587 for STARTTLS, 465 for SSL/TLS |
| Username | Your SMTP account username |
| Password | Your SMTP password or app password. Leave blank to keep the existing password when saving |
| From Email Address | The sender address shown in outgoing emails. Defaults to the username if left blank |
| Admin Email | Receives new registration notification emails |
| API Base URL | Used in email links (verification URLs, etc.) — should be the public URL of your ephemeralREST instance |
| Encryption | STARTTLS (recommended for port 587), SSL/TLS (port 465), or None |

The **Common Provider Settings** panel on the right lists host and port details for popular mail services. Click any hostname to auto-fill the SMTP Host and Port fields.

Click **Save Settings** to store the configuration. Click **Send Test Email** to send a test message to any address and verify the settings are working. Settings must be saved before testing.

Click **Clear** to remove all SMTP settings from the database. Email sending will stop until settings are reconfigured.

### Email Templates

The Email Templates page lets you customise the appearance and content of all six transactional emails without modifying any code. Changes are stored in the database and take effect immediately for all subsequent emails of that type.

Use the tabs at the top to switch between templates:

| Template | When it is sent |
|----------|----------------|
| Test Email | Sent manually from SMTP Settings |
| Domain Registration | Sent immediately when a domain key request is submitted |
| Registration Approved | Sent when an admin approves a request — contains the API key |
| Registration Rejected | Sent when an admin rejects a request |
| Email Verification | Sent when a user registers — contains the one-time verification link |
| Key Rotated | Sent when any key is rotated |

**Appearance settings** (shared visual style):

- **Background Colour** — the page background surrounding the email panel
- **Panel Background** — the colour of the content card itself
- **Text Colour** — applies to all text: header, body, and footer
- **Content Width** — width of the panel in pixels (320–800). Use the slider or type a value

**Content settings** (per-template text):

- **Subject Line** — the email subject shown in the recipient's inbox
- **Header Text** — displayed prominently at the top of the email panel
- **Header Alignment** — Left, Centre, or Right
- **Body Text** — the main message. Blank lines become paragraph breaks. Single line breaks within a paragraph become line breaks
- **Footer Text** — small-print at the bottom

**Substitution variables:** Templates that include dynamic content (domain name, API key, etc.) show a row of variable tags below the body text field. These tags represent values that ephemeralREST substitutes at send time. Include them in your body text exactly as shown, including the curly braces.

| Variable | Value |
|----------|-------|
| `{name}` | Recipient's name |
| `{domain}` | The registered domain |
| `{api_key}` | The issued or rotated API key |
| `{admin_note}` | Note entered by the admin when approving or rejecting |
| `{verification_url}` | One-time email verification link |
| `{identifier}` | Domain or email address for the key |

The **Live Preview** panel updates as you type, showing exactly how the email will appear. The mock email chrome at the top (From and Subject) also updates live.

Click **Save Template** to store your changes. Click **Reset** to revert the template to its built-in defaults.

### Admin Access

When `ALLOW_ADMIN_PROMOTION` is enabled in `config.php`, you can grant or revoke admin access on any key other than your own.

**Granting admin access** gives a key the ability to:
- Access all admin-only API endpoints
- Sign in to the admin portal
- Manage all keys, registrations, class limits, SMTP settings, and email templates

**Revoking admin access** removes these privileges immediately. The key continues to function as a regular domain or user key.

To change admin access:

- From the **Keys** page: click **Edit** on a key row, scroll to the Admin Access section in the modal
- From the **Key Detail** page: find the Admin Access card in the right column

Both Grant and Revoke buttons are shown in red as this is a consequential action. A confirmation prompt appears before any change is made. You cannot change admin access on your own key.

To permanently disable the admin promotion feature, set `ALLOW_ADMIN_PROMOTION = false` in `config.php`. The Admin Access section will not appear anywhere in the portal.

---

## Output Configuration Reference

The output configuration editor (accessible from the Domain Portal, User Portal, or any Key Detail page) controls which fields ephemeralREST includes in API responses for that key.

### Coordinate Systems and Fields

| Field | Description |
|-------|-------------|
| Geocentric | Geocentric ecliptic positions for all bodies |
| Heliocentric | Heliocentric ecliptic positions for all planets |
| Right Ascension | Equatorial right ascension in degrees |
| Declination | Equatorial declination in degrees |
| Longitude Speed | Daily motion in ecliptic longitude |
| Latitude Speed | Daily motion in ecliptic latitude |
| Declination Speed | Daily motion in declination |
| Retrograde Flag | Boolean retrograde indicator (geocentric only) |

### Angles

ASC, MC, Vertex, East Point, and ARMC can each be individually enabled or disabled.

### Planets

Each of the ten standard planets plus Earth (heliocentric only) can be individually enabled or disabled.

### Asteroids and Special Points

The Asteroids toggle is a master switch — disabling it suppresses all asteroid output regardless of individual settings. Individual toggles are available for Ceres, Pallas, Juno, Vesta, Chiron, Mean Node, True Node, South Node, Mean Lilith, True Lilith, and Part of Fortune.

### Response Metadata

- **API Usage** — includes Google API request counts in `/calculate` responses
- **From Cache** — includes a cache status flag in responses

### Default House System

Sets the house system used when a request does not specify one. If left at None, no house cusps are returned unless the request includes `house_system`.

Fields shown in **gold** are overriding the server default for this key. All other fields are inherited from the server defaults. Use **Reset to Defaults** to remove all per-key overrides and return to server behaviour.

---

## Frequently Asked Questions

**I lost my API key. How do I get a new one?**
Sign in to the portal with your key. If you cannot sign in because you no longer have the key, contact your administrator. The administrator can rotate your key from the Keys page — a new key will be emailed to your registered address.

**I need a higher rate limit.**
Domain and user key rate limits are set by the administrator. Contact your administrator and explain your usage requirements. Administrators can set per-key limits above the class default from the Key Detail page.

**Can I register more than one domain key?**
Yes. Submit a separate registration request for each domain. Each request is reviewed individually.

**What is the difference between a domain key and a wildcard key?**
A domain key is tied to a specific domain name and receives the full domain rate limit class. A wildcard key (domain entered as `*`) has no domain restriction and receives the lower wildcard rate limit class. Wildcards are intended for personal use, desktop applications, and local development.

**My key was disabled. What do I do?**
Contact your administrator. Only an admin can re-enable a key.

**How do I know what rate limits apply to my key?**
Sign in to your portal. Rate limits are shown in the Account or Key Details card.