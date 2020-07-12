=== Domainer ===
Contributors: dougwollison
Tags: domain mapping, domain management, multisite
Requires at least: 4.0
Tested up to: 5.4.2
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Domain mapping management for WordPress Multisite.

== Description ==

Domainer lets you route custom domain names to specific sites on your Multisite installation. That's it.

= Domain Options =

Each domain has 4 options you set:

1. The target site; pick from a list of existing sites on your network.
2. A type, choose from one of 3 flavours:
	- *Primary*: sites with a primary domain will have their URLs redirected to them.
	- *Redirect*: these domains will always redirect to the primary domain, or the original failing that.
	- *Alias*: these won't redirect to the primary, so they're bad for SEO but can be useful for certain setups.
3. A www rule, choose from one of 3 options:
	- *Always*: always redirect to the domain with www at the front.
	- *Never*: always redirect to the domain without www at the front.
	- *Auto*: allow the domain to function with or without www, usually bad for SEO.

= Content Rewriting =

In order to reduce redirects while navigating the site, Domainer will replaced all instances of the site's original domain name on the pages to that of the primary domain, or currently requested alias domain. This will not affect email addresses however; any filters on the content will only replace instances starting with a double slash so as to match URLs.

If you find instances of the domain not being replaced, such as in content filtered by 3rd party plugins, you can patch it with this function:

	add_filter( 'my_filter', 'domainer_rewrite_url' );

The function can also take a domain or array of domains to replace, as well as a specific domain to replace with.

== Installation ==

1. Upload the contents of `domainer.tar.gz` to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Assuming the system is unable to take care of it automatically:
	1. Copy the sunrise.php file to `/wp-content/`.
	2. Add `define('SUNRISE', true);` to your `wp-config.php` file, anywhere above the `ABSPATH` line.
4. Start adding domains under Network Admin > Domains.

== Changelog ==

**Details on each release can be found [on the GitHub releases page](https://github.com/dougwollison/domainer/releases) for this project.**

= 1.2.1 =
Improved UX and error handling of Sunrise install process.

= 1.2.0 =
Network Only now. Fixed session handling that was causing loopback requests to fail.

= 1.1.3.1 =
Fixed subdirectory rewrites, ironed out install process.

= 1.1.2 =
Fixed rewrite handling on subdirectory style network setups.

= 1.1.1 =
Fixed handling of alias domains, improved security of remote login/logout.

= 1.1.0 =
Remote login capabilities, blog switching and site selecting fixes.

= 1.0.1 =
Fixed bug with deleting domains and certain redirect URLs.

= 1.0.0 =
Initial public release.
