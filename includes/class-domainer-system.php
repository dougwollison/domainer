<?php
/**
 * Domainer System
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Main System
 *
 * Sets up the database table aliases, the Registry,
 * and all the Handler classes.
 *
 * @api
 *
 * @since 1.0.0
 */
final class System extends Handler {
	// =========================
	// ! Properties
	// =========================

	/**
	 * Record of added hooks.
	 *
	 * @internal Used by the Handler enable/disable methods.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $implemented_hooks = array();

	// =========================
	// ! Master Setup Method
	// =========================

	/**
	 * Register hooks and load options.
	 *
	 * @since 1.0.0
	 *
	 * @uses Registry::load() to load the options.
	 * @uses Loader::register_hooks() to setup plugin management.
	 * @uses System::register_hooks() to setup global functionality.
	 * @uses Backend::register_hooks() to setup backend functionality.
	 * @uses AJAX::register_hooks() to setup AJAX functionality.
	 * @uses Manager::register_hooks() to setup admin screens.
	 * @uses Documenter::register_hooks() to setup admin documentation.
	 */
	public static function setup() {
		// Setup the registry
		Registry::load();

		// Register the Installer stuff
		Installer::register_hooks();

		// Register global hooks
		self::register_hooks();

		// Register the hooks of the subsystems
		Backend::register_hooks();
		Manager::register_hooks();
		Documenter::register_hooks();
	}

	// =========================
	// ! Setup Utilities
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 */
	public static function register_hooks() {
		global $wpdb;

		// Setup the domainer table alias
		if ( ! $wpdb->domainer ) {
			$wpdb->domainer = $wpdb->base_prefix . 'domainer';
		}

		// Primary Domain redirection
		self::add_hook( 'wp', 'redirect_to_primary', 10, 0 );

		// URL Rewriting (if applicable).
		if ( defined( 'DOMAINER_REWRITTEN' ) ) {
			self::add_hook( 'option_siteurl', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'option_home', 'rewrite_domain_in_url', 0, 1 );

			self::add_hook( 'plugins_url', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'theme_root_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'stylesheet_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'stylesheet_directory_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'template_directory_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'get_the_guid', 'rewrite_domain_in_url', 0, 1 );

			self::add_hook( 'redirect_canonical', 'rewrite_domain_in_url', 10, 1 );

			self::add_hook( 'the_content', 'rewrite_domain_in_content', 0, 1 );
			self::add_hook( 'the_excerpt', 'rewrite_domain_in_content', 0, 1 );

			self::add_hook( 'upload_dir', 'rewrite_domain_in_upload_dir', 0, 1 );
		}
	}

	// =========================
	// ! Primary Domain Handling
	// =========================

	/**
	 * Redirect to the primary domain if applicable.
	 *
	 * @since 1.0.0
	 *
	 * @global WP_Site $current_blog The current site object.
	 */
	public static function redirect_to_primary() {
		global $current_blog;

		// Skip if not for a HEAD/GET request
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ) ) ) {
			return;
		}

		// Get the domain if one was matched
		if ( $current_blog->domain_id ) {
			$domain = Registry::get_domain( $current_blog->domain_id );

			// If the domain is found and is not a redirect, skip
			if ( $domain && $domain->type !== 'redirect' ) {
				return;
			}
		}

		// Find a primary domain for this site
		if ( $domain = Registry::get_primary_domain( $current_blog->blog_id ) ) {
			// Build the rewritten URL
			$redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $domain->name . $_SERVER['REQUEST_URI'];
			if ( wp_redirect( $redirect_url, 302 ) ) {
				exit;
			}
		}
	}

	// =========================
	// ! Domain Rewriting
	// =========================

	/**
	 * Filter the URL to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to rewrite.
	 *
	 * @return string The filtered URL.
	 */
	public static function rewrite_domain_in_url( $url ) {
		global $current_blog;

		$url = str_replace( get_true_url(), $current_blog->domain, $url );

		return $url;
	}

	/**
	 * Filter the content to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The content to filter.
	 *
	 * @return string The filtered content.
	 */
	public static function rewrite_domain_in_content( $content ) {
		global $current_blog;

		// Only replace instances prefixed with a double slash, to prevent it affecting email addresses
		$content = str_replace( '//' . get_true_url(), '//' . $current_blog->domain, $content );

		return $content;
	}

	/**
	 * Filter the upload_dir array to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param array $upload_dir The array to filter.
	 *
	 * @return array The filtered array.
	 */
	public static function rewrite_domain_in_upload_dir( $upload_dir ) {
		$upload_dir['baseurl'] = self::rewrite_domain_in_url( $upload_dir['baseurl'] );
		$upload_dir['url'] = self::rewrite_domain_in_url( $upload_dir['url'] );

		return $upload_dir;
	}
}
