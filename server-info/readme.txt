=== Server Info - System Health & Diagnostics Suite ===
Contributors: usmanaliqureshi
Tags: server info, server status, php info, system health, diagnostics
Requires at least: 5.5
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inspect WordPress, PHP, database, caching, SSL, and server health from one administrator-only dashboard.

== Description ==

Server Info provides a practical dashboard for inspecting the hosting environment behind a WordPress site. It brings important WordPress, PHP, database, caching, certificate, and diagnostic details together without changing the public site.

All diagnostic features are free. The optional Support area offers one-time voluntary payments through Freemius; paying does not unlock or restrict plugin functionality.

= Overview and diagnostics =

* Server hostname, IP address, protocol, operating system, web server, and resource usage.
* SSL/TLS issuer, hostname, expiry date, and remaining days, with graceful unavailable states.
* Optional domain registration expiry and registrar lookup, disabled by default.
* PHP version, limits, extensions, configuration summary, and a detailed phpinfo view.
* Database version, size, charset, collation, limits, and largest tables.
* WordPress version, memory, debugging, permalinks, theme, and plugin status.
* OPcache, Redis, Memcached, object cache, and output buffering detection.
* Configuration, file permission, cron, and error-log diagnostics.

= Administrator controls =

* Optional admin bar environment HUD.
* Compact optional admin footer summary.
* Display schemes and custom background/text colors.
* Settings for external domain-expiry lookup and admin display elements.

The dashboard and HUD are available only to users with the `manage_options` capability.

== External Services ==

Server Info includes the Freemius SDK for optional usage opt-in and voluntary one-time supporter payments. Freemius communication is subject to user consent where requested by the SDK. The checkout script is loaded from `checkout.freemius.com` only after an administrator selects a support amount. See the [Freemius privacy policy](https://freemius.com/privacy/) and [terms](https://freemius.com/terms/).

The optional domain-expiry setting is disabled by default. When enabled and an administrator opens the dashboard, the plugin sends the site's registrable domain to an HTTPS RDAP service operated by the relevant registry or `rdap.org`. If RDAP cannot provide a result, it may query the relevant public WHOIS server over port 43. These services use the domain name to return public registration data. See the [RDAP bootstrap service](https://data.iana.org/rdap/dns.json), [RDAP.org](https://rdap.org/), and the applicable registry's terms and privacy policy.

The SSL/TLS certificate check connects from the WordPress server to the site's own hostname on port 443 to read the public certificate. No visitor data is sent.

== Installation ==

1. Install and activate Server Info from Plugins -> Add New.
2. Open Settings -> Server Info.
3. Review Settings before enabling the optional domain-expiry lookup.

== Frequently Asked Questions ==

= Does it change my frontend? =

No. Server Info is an administrator diagnostic tool. The optional admin bar HUD can appear while an administrator views the frontend, but it is not shown to visitors.

= Does the domain-expiry lookup run automatically? =

No. It is disabled by default and can be enabled from the plugin's Settings tab.

= Does supporting the plugin unlock features? =

No. Support payments are voluntary, one-time contributions. Every plugin feature remains available without payment.

= Who can view Server Info? =

Only users with the `manage_options` capability can access the dashboard and its server details.

= Does it detect caching? =

Yes. It checks for common signals from OPcache, Redis, Memcached, persistent object caching, and output buffering.

== Screenshots ==

1. Overview with server health, hosting, SSL/TLS, and optional domain-expiry details.
2. Database information, limits, and largest-table summary.
3. WordPress configuration and plugin status.
4. PHP configuration, limits, and extension summary.
5. Diagnostics with actionable health checks.
6. Settings for the admin HUD, footer, external lookup, and appearance.
7. Voluntary one-time support plans, with every feature remaining free.
8. Additional lightweight WordPress plugins from the author.

== Changelog ==

= 1.1.0 =
* Added SSL/TLS certificate issuer, hostname, expiry, and remaining-day information.
* Added an optional domain registrar and expiry lookup, disabled by default.
* Added display settings for the admin bar HUD and compact admin footer.
* Added multiple appearance schemes and custom color controls.
* Added more useful PHP configuration details and improved unavailable states.
* Added voluntary one-time support plans through Freemius; all features remain free.
* Added a More Plugins view and refreshed the administrator interface.
* Improved privacy disclosures, checkout loading, responsive layout, and failure handling.

= 1.0.0 =
* Initial release with the system dashboard, admin bar HUD, footer summary, and environment badges.
