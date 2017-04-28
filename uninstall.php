<?php
/**
 * Domainer Uninstall Logic
 *
 * @package Domainer
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * Uninstall Domainer.
 *
 * Handles removal of tables and options created by the plugin.
 *
 * @internal Automatically called within this file.
 *
 * @since 1.0.0
 */
function uninstall() {
	global $wpdb;

	// Abort if not running in the WordPress context.
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		die( 'Must be run within WordPress context.' );
	}

	// Also abort if (somehow) it's some other plugin being uninstalled
	if ( WP_UNINSTALL_PLUGIN != basename( __DIR__ ) . '/domainer.php' ) {
		die( sprintf( 'Illegal attempt to uninstall [Plugin Name] while uninstalling %s.', WP_UNINSTALL_PLUGIN ) );
	}

	// Run through each blog and perform the uninstall on each one
	$sites = get_sites( array(
		'fields' => 'ids',
	) );

	foreach ( $sites as $site ) {
		delete_blog_option( $site, 'domainer_option_overrides' );
	}

	delete_site_option( 'domainer_options' );

	// Delete the object and string translation tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}domainer" );

	delete_site_option( 'domainer_database_version' );
}

uninstall();
