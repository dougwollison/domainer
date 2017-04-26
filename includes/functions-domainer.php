<?php
/**
 * Domainer Internal Functions
 *
 * @package Domainer
 * @subpackage Utilities
 *
 * @internal
 *
 * @since 1.0.0
 */

namespace Domainer;

// =========================
// ! Conditional Tags
// =========================

/**
 * Check if we're in the backend of the site (excluding frontend AJAX requests)
 *
 * @internal
 *
 * @since 1.0.0
 *
 * @global string $pagenow The current page slug.
 */
function is_backend() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		// AJAX request, check if the referrer is from wp-admin
		return strpos( $_SERVER['HTTP_REFERER'], admin_url() ) === 0;
	} else {
		// Check if in the admin or otherwise the login/register page
		return is_admin() || in_array( basename( $_SERVER['SCRIPT_NAME'] ), array( 'wp-login.php', 'wp-register.php' ) );
	}
}

// =========================
// ! Helper Utilities
// =========================

/**
 * Return the true domain+path of a site.
 *
 * @internal
 *
 * @since 1.0.0
 *
 * @global WP_Site $current_blog The current site object.
 *
 * @param int $blog_id Optional. The specific site to fetch.
 *
 * @return string The domain + path, unslashed.
 */
function get_true_url( $blog_id = null ) {
	if ( is_null( $blog_id ) ) {
		global $current_blog;

		$domain = $current_blog->true_domain ?: $current_blog->domain;
		$path = $current_blog->true_path ?: $current_blog->path;
	} else {
		$blog = WP_Site::get_instance( $blog_id );

		$domain = $blog->domain;
		$path = $blog->path;
	}

	return trim( $domain . $path, '/' );
}

// =========================
// ! Misc. Utilities
// =========================

/**
 * Triggers the standard "Cheatin’ uh?" wp_die message.
 *
 * @internal
 *
 * @since 1.0.0
 */
function cheatin() {
	wp_die( __( 'Cheatin&#8217; uh?' ), 403 );
}
