# Machh WP Plugin

Server-side pageview tracking for WordPress, forwarding events to the Machh ingestion API.

## Features

- **First-party device ID cookie** (`machh_did`) - persistent 365-day cookie for device tracking
- **First-touch UTM capture** (`machh_utm`) - captures UTM parameters and click IDs on first visit
- **Server-side forwarding** - pageview events are sent server-side with proper headers
- **No client-side API key exposure** - the `X-MACHH-KEY` is only used server-side
- **No jQuery dependency** - vanilla JavaScript collector

## Installation

1. Copy the `machh-wp-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin: **Plugins > Installed Plugins > Machh WP Plugin > Activate**
3. Configure the plugin: **Settings > Machh**

## Configuration

Navigate to **Settings > Machh** in the WordPress admin and configure:

| Setting | Description | Example |
|---------|-------------|---------|
| **Enable Tracking** | Checkbox to enable/disable pageview tracking | ✓ Checked |
| **Ingestion API Base URL** | The base URL for your Machh ingestion API (no trailing slash) | `https://machh-ingestion-api.example.com` |
| **Client API Key** | Your Machh client API key (sent as `X-MACHH-KEY` header) | `sk_live_abc123xyz` |

## How It Works

### Cookie Management

1. **Device ID (`machh_did`)**: Generated on first visit using `bin2hex(random_bytes(16))`, stored for 365 days
2. **UTM Cookie (`machh_utm`)**: First-touch only - captures UTM parameters if present on first visit:
   - `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`
   - Click IDs: `gclid`, `fbclid`, `msclkid`, `ttclid`, `wbraid`, `dclid`, `twclid`, `li_fat_id`

### Pageview Flow

1. Frontend JavaScript (`machh-collector.js`) fires on `DOMContentLoaded`
2. Sends AJAX request to `admin-ajax.php` with:
   - `action`: `machh_pageview`
   - `nonce`: Security nonce
   - `url`: Current page URL
   - `referrer`: Document referrer
3. Server-side handler:
   - Verifies nonce
   - Skips admin users and ignored paths (`/wp-admin`, `/wp-json`, etc.)
   - Builds payload with device_id, UTM data, user agent, IP, timestamp
   - Forwards to `{INGEST_BASE_URL}/pageview` with `X-MACHH-KEY` header

### Payload Format

```json
{
  "device_id": "a1b2c3d4e5f6...",
  "url": "https://example.com/page",
  "referrer": "https://google.com",
  "site_domain": "example.com",
  "utm": {
    "utm_source": "google",
    "utm_medium": "cpc",
    "gclid": "abc123"
  },
  "user_agent": "Mozilla/5.0...",
  "ip": "192.168.1.1",
  "ts": 1737100000
}
```

## Testing

### 1. Enable Debug Logging

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 2. Configure the Plugin

1. Go to **Settings > Machh**
2. Enter your ingestion API URL and client key
3. Check "Enable Tracking"
4. Save changes

### 3. Test Pageview Tracking

1. Open your site in a browser (logged out or in incognito)
2. Open browser DevTools > Network tab
3. Filter by "admin-ajax"
4. You should see a POST request to `admin-ajax.php?action=machh_pageview`
5. Response should be: `{"success":true,"data":{"ok":true,"status":200}}`

### 4. Check Cookies

In DevTools > Application > Cookies, verify:
- `machh_did` - 32-character hex device ID
- `machh_utm` - JSON object (only if UTM params were present)

### 5. Check Debug Log

View `/wp-content/debug.log` for Machh log entries:

```
[Machh][INFO] Sending request to https://api.example.com/pageview: {...}
[Machh][INFO] Response from /pageview: status=200, body={...}
```

## Ignored Paths

The following paths are automatically excluded from tracking:
- `/wp-admin/*`
- `/wp-json/*`
- `/sitemap.xml`
- `/robots.txt`
- `/favicon.ico`
- `/wp-login.php`
- `/wp-cron.php`

## Security

- Nonce verification on all AJAX requests
- Input sanitization using WordPress functions
- API key never exposed to client-side JavaScript
- Admin users are excluded from tracking

## File Structure

```
machh-wp-plugin/
├── machh-wp-plugin.php          # Main plugin file
├── README.md                    # This file
├── assets/
│   └── machh-collector.js       # Frontend collector (no jQuery)
└── includes/
    ├── class-machh-plugin.php   # Bootstrap & hooks
    ├── class-machh-cookies.php  # Cookie management
    ├── class-machh-ajax.php     # AJAX handlers
    └── class-machh-http.php     # HTTP client
```

## Future Enhancements

- [ ] Form submission tracking (`/form-submitted` endpoint)
- [ ] E-commerce event tracking
- [ ] Custom event API
- [ ] SPA/AJAX navigation support

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

GPL v2 or later


