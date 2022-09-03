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
 */
function is_backend() {
	if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
		// "Install" process, count as backend
		return true;
	}

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		// AJAX request, check if the referrer is from wp-admin
		if (isset($_SERVER['HTTP_REFERER'])) {
			return strpos( $_SERVER['HTTP_REFERER'], admin_url() ) === 0;
		} else {
			return null;
		}
	}

	// Check if in the admin or otherwise the login/register page
	return is_admin() || in_array( basename( $_SERVER['SCRIPT_NAME'] ), array( 'wp-login.php', 'wp-register.php' ) );
}

/**
 * Check if the request came from the admin or network admin.
 *
 * @internal
 *
 * @since 1.0.0
 */
function from_network_admin() {
	global $current_blog;

	$referer = '';
	if ( isset( $_POST['_wp_http_referer'] ) ) {
		$referer = $_POST['_wp_http_referer'];
	} else if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
		$referer = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_PATH );
	}

	return strpos( $referer, $current_blog->path . 'wp-admin/network/admin.php' ) === 0;
}

// =========================
// ! Helper Utilities
// =========================

/**
 * Return the current domain+path of a site.
 *
 * @internal
 *
 * @since 1.0.0
 *
 * @global \WP_Site $current_blog The current site object.
 *
 * @param int $blog_id Optional. The specific site to fetch.
 *
 * @return string The domain + path, unslashed.
 */
function get_current_url( $blog_id = null ) {
	global $current_blog;

	$blog = $blog_id ? \WP_Site::get_instance( $blog_id ) : $current_blog;

	if ( property_exists( $blog, 'domain_id' ) && $domain = Registry::get_domain( $blog->domain_id ) ) {
		return $domain->fullname();
	}

	return trim( $blog->domain . $blog->path, '/' );
}

/**
 * Return the true domain+path of a site.
 *
 * @internal
 *
 * @since 1.0.0
 *
 * @global \WP_Site $current_blog The current site object.
 *
 * @param int $blog_id Optional. The specific site to fetch.
 *
 * @return string The domain + path, unslashed.
 */
function get_true_url( $blog_id = null ) {
	global $current_blog;

	$blog = $blog_id ? WP_Site::get_instance( $blog_id ) : $current_blog;

	$domain = $blog->domain;
	$path = $blog->path;

	if ( property_exists( $blog, 'true_domain' ) ) {
		$domain = $blog->true_domain;
	}

	if ( property_exists( $blog, 'true_path' ) ) {
		$path = $blog->true_path;
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
