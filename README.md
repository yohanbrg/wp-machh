# Machh for WordPress

**Server-side tracking that respects privacy and survives ad blockers.**

---

## The Problem

Traditional client-side tracking (Google Analytics, Meta Pixel, etc.) faces major challenges:

- 🚫 **Blocked by ad blockers** — Up to 40% of your traffic goes untracked
- 🍪 **Third-party cookie deprecation** — Browsers are killing cross-site tracking
- ⚠️ **Client-side API keys exposed** — Security vulnerability in your frontend code
- 📉 **Inaccurate attribution** — Broken user journeys and lost conversion data

## The Solution

Machh WP Plugin sends tracking data **server-side**, making it invisible to ad blockers while keeping your API credentials secure. First-party cookies ensure accurate device identification and UTM attribution across sessions.

---

## Features

### 🎯 Pageview Tracking
Automatic server-side pageview tracking with full context: URL, referrer, device ID, and timestamps.

### 🔐 First-Party Device ID
Persistent 365-day cookie (`machh_did`) for reliable cross-session device identification — no third-party dependency.

### 📊 First-Touch UTM Attribution
Captures UTM parameters and click IDs on first visit:
- UTM: `source`, `medium`, `campaign`, `term`, `content`
- Click IDs: `gclid`, `fbclid`, `msclkid`, `ttclid`, `wbraid`, `dclid`, `twclid`, `li_fat_id`

### 📝 Form Submission Tracking
Track form conversions with automatic field capture and UTM attribution.

### 🛡️ Secure by Design
- API key never exposed to the browser
- Nonce verification on all requests
- Admin users automatically excluded

---

## Installation

1. Download the latest release from [GitHub](https://github.com/yohanbrg/wp-machh/releases)
2. In WordPress: **Plugins → Add New → Upload Plugin**
3. Upload the `.zip` file and click **Install Now**
4. Activate the plugin
5. Configure: **Settings → Machh**

Updates are delivered automatically via GitHub Releases.

---

## Configuration

Navigate to **Settings → Machh** and configure:

| Setting | Description |
|---------|-------------|
| **Enable Tracking** | Toggle pageview tracking on/off |
| **Client API Key** | Your Machh API key |

---

## Form Integrations

### Supported

| Plugin | Status | Version |
|--------|--------|---------|
| **Contact Form 7** | ✅ Ready | 1.0+ |
| **WPForms** | ✅ Ready | 1.1+ |
| **MetForm** | ✅ Ready | 1.1+ |

### Roadmap

| Plugin | Status | ETA |
|--------|--------|-----|
| **Gravity Forms** | 🔜 Planned | Q1 2026 |
| **Elementor Forms** | 🔜 Planned | Q2 2026 |

Want a specific integration? [Open an issue](https://github.com/yohanbrg/wp-machh/issues) on GitHub.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+

---

## Support

- 📖 [Documentation](https://machh.io/docs)
- 🐛 [Report an issue](https://github.com/yohanbrg/wp-machh/issues)
- 💬 [Contact us](https://machh.io/contact)

---

## License

GPL v2 or later
