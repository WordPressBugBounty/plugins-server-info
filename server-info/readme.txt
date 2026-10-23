=== Server Info - System Health & Diagnostics Suite ===
Contributors: usmanaliqureshi
Tags: server info, server status, php info, system health, diagnostics
Requires at least: 5.5
Tested up to: 7.1
Stable tag: 1.1.1
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inspect WordPress, PHP, database, caching, SSL, and server health from one administrator-only dashboard.

== Description ==

Server Info provides a practical dashboard for inspecting the hosting environment behind a WordPress site. It brings important WordPress, PHP, database, caching, certificate, and diagnostic details together without changing the public site.

All diagnostic features are free. The optional Support area offers one-time voluntary payments through Freemius; paying does not unlock or restrict plugin functionality.

= Overview and diagnostics =

* PHP security warning across the dashboard when the server's PHP version is affected by the PHP2Shell vulnerabilities (CVE-2026-109618 and CVE-2026-109619).
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

The optional domain-expiry setting is disabled by default. When enabled and an administrator opens the dashboard, the plugin downloads IANA's public RDAP bootstrap file (`data.iana.org`, cached for a week) and sends only the site's registrable domain, never the full hostname, to the registry's HTTPS RDAP service, or to `rdap.org` when IANA lists no RDAP service for the extension. If RDAP cannot provide a result, it may query the registry's public WHOIS server over port 43. To find that server for less common extensions, it sends only the domain extension (for example `de`) to `whois.iana.org`. These services use the domain name to return public registration data. See the [RDAP bootstrap service](https://data.iana.org/rdap/dns.json), [IANA WHOIS](https://www.iana.org/whois), [RDAP.org](https://rdap.org/), and the applicable registry's terms and privacy policy.

The SSL/TLS certificate check connects from the WordPress server to the site's own hostname on port 443 to read the public certificate. No visitor data is sent.

When the server reports an internal address, the plugin resolves the site's own hostname through the server's DNS resolver to show its public IP address. The PHP security warning is calculated locally from the PHP version and links to the [PHP project's advisory](https://www.php.net/archive/2026.php#2026-10-21-5); no data is sent.

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

= 1.1.1 =
* Added a dashboard-wide warning when the server's PHP version is affected by the PHP2Shell vulnerabilities (CVE-2026-109618 and CVE-2026-109619), with a link to the PHP project's advisory. Administrators can hide it for the current PHP version once their host confirms a patched build.
* Fixed PHP version status: affected or outdated versions are no longer labelled "Latest" or "Optimal", and they now lower the health score.
* Redesigned the admin bar HUD: a status dot shows at a glance whether the PHP, SSL and domain checks pass, and the dropdown groups site, server, software and memory details.
* The Support tab now explains that payments go through Freemius's secure checkout and card details never touch your site.
* After activation, a dismissible pointer next to the Settings menu shows where to find Server Info.
* Improved domain expiry lookups: the registry's own RDAP server is used, WHOIS covers more extensions, and the Overview explains when a registry does not publish expiry dates or when a parent domain is shown.
* The SSL/TLS issuer now shows the certificate authority name, such as Let's Encrypt or Google Trust Services.
* The Overview now explains why SSL or domain details are unavailable.
* Memory and disk usage now show used and total amounts, and CPU load is rated against the number of CPU cores.
* Server IP also shows the site's public address when the server reports an internal one.
* The floating Support button no longer covers page content and shrinks to an icon while scrolling, and long lists now wrap neatly.
* Added more plugins from the author to the More Plugins view.
* Updated Freemius SDK to the latest version.
* Tested with WordPress 7.1.

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
