<?php
/**
 * Domainer Frontend Functionality
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Frontend Functionality
 *
 * Handles redirection and rewriting of the frontend output.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class Frontend extends Handler {
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
	// ! Hook Registration
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		// Don't do anything if in the backend
		if ( is_backend() ) {
			return;
		}

		self::add_hook( 'plugins_loaded', 'redirect_to_primary', 10, 0 );

		// Apply filters as needed
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
			// Get the redirect status to use (301 vs 302)
			$status = Registry::get( 'redirection_permanent' ) ? 301 : 302;

			// Build the rewritten URL
			$redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $domain->name . $_SERVER['REQUEST_URI'];
			if ( wp_redirect( $redirect_url, $status ) ) {
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
