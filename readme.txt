=== Machh ===
Contributors: machh
Tags: tracking, analytics, server-side, forms
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server-side tracking for Machh ingestion API.

== Description ==

Machh WordPress Plugin enables server-side tracking and data collection for the Machh analytics platform.

**Features:**

* Server-side event tracking
* Form integrations: Contact Form 7, WPForms, MetForm
* Cookie management
* Secure data transmission

== Installation ==

1. Upload the `machh-wp-plugin` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Machh API settings

== Changelog ==

= 1.1.1 =
* Fixed pageview tracking failure ("Invalid nonce") on cached pages
* Removed nonce verification from public pageview endpoint (cache-compatible)

= 1.1.0 =
* Added WPForms integration
* Added MetForm integration
* Improved form provider architecture

= 1.0.9 =
* Fix

= 1.0.8 =
* Fix

= 1.0.7 =
* Improved settings page UI/UX
* Added GitHub token management for update checks
* Better tracking status indicator

= 1.0.6 =
* GitHub API error handling

= 1.0.5 =
* Bug fixes

= 1.0.4 =
* Bug fixes

= 1.0.3 =
* Bug fixes and improvements

= 1.0.2 =
* Bug fixes and improvements

= 1.0.1 =
* Bug fixes and improvements

= 1.0.0 =
* Initial release
* Server-side tracking implementation
* Contact Form 7 support
* Cookie management system

== Upgrade Notice ==

= 1.1.1 =
Fixes pageview tracking on sites with page caching enabled.

= 1.1.0 =
New form integrations: WPForms and MetForm support added.

= 1.0.9 =
Fix url ingest api

