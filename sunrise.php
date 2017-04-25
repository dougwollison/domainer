<?php
/**
 * Domainer Sunrise Drop-In
 *
 * @package Domainer
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Sunrise Drop-In
 *
 * Populates the $current_site and $current_blog objects.
 *
 * @internal Automatically called within this file.
 *
 * @since 1.0.0
 */
final class Sunrise {
	/**
	 * Populate $current_site and $current_blog if applicable.
	 *
	 * @global \wpdb      $wpdb         The database abstraction class instance.
	 * @global WP_Network $current_site The current network object.
	 * @global WP_Site    $current_blog The current site object.
	 * @global int        $site_id      The ID of the current network.
	 * @global int        $blog_id      The ID of the current site.
	 *
	 * @since 1.0.0
	 */
	public static function run() {
		global $wpdb, $current_site, $current_blog, $site_id, $blog_id;

		// Setup the domainer table alias
		$wpdb->domainer = $wpdb->base_prefix . 'domainer';

		// Flag that Sunrise has been loaded
		if ( ! defined( 'SUNRISE_LOADED' ) ) {
			define( 'SUNRISE_LOADED', true );
		}

		// Error out if the cookie domain is already set.
		if ( defined( 'COOKIE_DOMAIN' ) ) {
			trigger_error( 'The constant "COOKIE_DOMAIN" should not be defined yet. Please remove/comment out the define() line (likely in wp-config.php', E_USER_ERROR );
		}

		// Sanitize the HOST value, save it
		$domain = strtolower( $_SERVER['HTTP_HOST'] );
		$_SERVER['HTTP_HOST'] = $domain;

		// All domains are stored without www
		$find = preg_replace( '/^www\./', '', $domain );

		// See if a matching site ID can be found for the provided HOST name
		$match = $wpdb->get_row( $wpdb->prepare( "SELECT id, blog_id FROM $wpdb->domainer WHERE name = %s LIMIT 1", $find ) );
		if ( $match ) {
			// Ensure a matching site is found
			if ( $current_blog = \WP_Site::get_instance( $match->blog_id ) ) {
				// Amend the REQUEST_URI to remove the site's original path
				$path = rtrim( $current_blog->path, '/' );
				if ( $path && strpos( $_SERVER['REQUEST_URI'], $path ) === 0 ) {
					$_SERVER['REQUEST_URI'] = substr( $_SERVER['REQUEST_URI'], strlen( $path ) );
				}

				// Store the true domain/path, along with the requested domain's ID
				$current_blog->true_domain = $current_blog->domain;
				$current_blog->true_path = $current_blog->path;
				$current_blog->domain_id = $match->id;

				// Rewrite the domain/path
				$current_blog->domain = $domain;
				$current_blog->path = '/';

				// Populate the site's Network object
				$current_site = \WP_Network::get_instance( $current_blog->site_id );

				// Populate the site/network ID globals
				$blog_id = $current_blog->blog_id;
				$site_id = $current_blog->site_id;

				// Flag that Domainer rewrote the domain
				define( 'DOMAINER_REWRITTEN', true );

				// Set the cookie domain constant
				define( 'COOKIE_DOMAIN', $domain );
			}
		}
	}
}

Sunrise::run();
