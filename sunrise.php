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

		// Flag that Sunrise has been loaded
		if ( ! defined( 'SUNRISE_LOADED' ) ) {
			define( 'SUNRISE_LOADED', true );
		}

		// Error out if the cookie domain is already set.
		if ( defined( 'COOKIE_DOMAIN' ) ) {
			trigger_error( 'The constant "COOKIE_DOMAIN" should not be defined yet. Please remove/comment out the define() line (likely in wp-config.php', E_USER_ERROR );
		}

		// Setup the registry
		Registry::load();

		// Sanitize the HOST value, save it
		$domain = strtolower( $_SERVER['HTTP_HOST'] );
		$_SERVER['HTTP_HOST'] = $domain;

		// See if a matching site ID can be found for the provided HOST name
		if ( $match = Registry::get_domain( $domain ) ) {
			// Ensure a matching site is found
			if ( $current_blog = WP_Site::get_instance( $match->blog_id ) ) {
				// Rewrite the site's domain/path
				$current_blog->domain = $domain;
				$current_blog->path = '/';

				// Populate the site's Network object
				$current_site = WP_Network::get_instance( $current_blog->site_id );

				// Populate the site/network ID globals
				$blog_id = $current_blog->blog_id;
				$site_id = $blog->site_id;

				// Set the cookie domain constant
				define( 'COOKIE_DOMAIN', $domain );
			}
		}
	}
}

Sunrise::run();
