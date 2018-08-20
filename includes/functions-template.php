<?php
/**
 * Domainer Template Functions
 *
 * @package Domainer
 * @subpackage Utilities
 *
 * @api
 *
 * @since 1.0.0
 */

/**
 * Filter the content to replace the domain name.
 *
 * @since 1.0.0
 *
 * @global \WP_Site $current_blog The current site object.
 *
 * @param string       $content    The content to filter.
 * @param string|array $old_domain Optional. The original domain(s) to replace
 *                                 (defaults to blog's original).
 * @param string       $new_domain Optional. The new domain to replace with
 *                                 (defaults to blog's primary).
 */
function domainer_rewrite_url( $content, $old_domain = null, $new_domain = null ) {
	if ( is_null( $old_domain ) ) {
		$old_domain = Domainer\get_true_url();
	}

	if ( is_null( $new_domain ) ) {
		global $current_blog;
		$new_domain = rtrim( $current_blog->domain . $current_blog->path, '/' );

		if ( property_exists( $current_blog, 'domain_id' ) && $domain = Domainer\Registry::get_domain( $current_blog->domain_id ) ) {
			$new_domain = $domain->fullname();
		}
	}

	$content = str_replace( (array) $old_domain, $new_domain, $content );

	return $content;
}

/**
 * Get the primary domain for the current blog.
 *
 * @since 1.1.0
 *
 * @global int      $blog_id      The current blog ID.
 * @global \WP_Site $current_blog The current site object.
 *
 * @param int $blog_id Optional The ID of the current blog.
 *
 * @return string The primary/only domain name for the blog, falling back to the original.
 */
function domainer_get_primary_domain( $blog_id = null ) {
	if ( is_null( $blog_id ) ) {
		global $blog_id;
	}

	$domain = Domainer\Registry::get_primary_domain( $blog_id );

	if ( ! $domain ) {
		global $current_blog;

		return $current_blog->domain;
	}

	return $domain->fullname();
}
