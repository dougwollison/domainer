<?php
/**
 * Domainer Installation Functionality
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Plugin Installer
 *
 * Registers activate/deactivate/uninstall hooks, and handle
 * any necessary upgrading from an existing install.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class Installer extends Handler {
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
	 * Register the plugin hooks.
	 *
	 * @uses DOMAINER_PLUGIN_FILE to identify the plugin file.
	 * @uses Installer::plugin_activate() as the activation hook.
	 * @uses Installer::plugin_deactivate() as the deactivation hook.
	 */
	public static function register_hooks() {
		// Plugin hooks
		register_activation_hook( DOMAINER_PLUGIN_FILE, array( __CLASS__, 'plugin_activate' ) );
		register_deactivation_hook( DOMAINER_PLUGIN_FILE, array( __CLASS__, 'plugin_deactivate' ) );

		// Upgrade logic
		self::add_hook( 'plugins_loaded', 'upgrade', 10, 0 );
	}

	// =========================
	// ! Utilities
	// =========================

	/**
	 * Security check logic.
	 *
	 * @since 1.0.0
	 */
	private static function plugin_security_check( $check_referer = null ) {
		// Make sure they have permisson
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		if ( $check_referer ) {
			$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
			check_admin_referer( "{$check_referer}-plugin_{$plugin}" );
		} else {
			// Check if this is the intended plugin for uninstalling
			if ( ! isset( $_REQUEST['checked'] )
			|| ! in_array( plugin_basename( DOMAINER_PLUGIN_FILE ), $_REQUEST['checked'] ) ) {
				return false;
			}
		}

		return true;
	}

	// =========================
	// ! Hook Handlers
	// =========================

	/**
	 * Create database tables and add default options.
	 *
	 * @since 1.0.0
	 *
	 * @uses Loader::plugin_security_check() to check for activation nonce.
	 *
	 * @global wpdb $wpdb The database abstraction class instance.
	 */
	public static function plugin_activate() {
		global $wpdb;

		if ( ! self::plugin_security_check( 'activate' ) ) {
			return;
		}

		// Attempt to upgrade, in case we're activating after an plugin update
		if ( ! self::upgrade() ) {
			// Otherwise just install the options/tables
			self::install();
		}
	}

	/**
	 * Empty deactivation hook for now.
	 *
	 * @since 1.0.0
	 *
	 * @uses Loader::plugin_security_check() to check for deactivation nonce.
	 *
	 * @global wpdb $wpdb The database abstraction class instance.
	 */
	public static function plugin_deactivate() {
		global $wpdb;

		if ( ! self::plugin_security_check( 'deactivate' ) ) {
			return;
		}

		// to be written
	}

	// =========================
	// ! Install Logic
	// =========================

	/**
	 * Install the default options.
	 *
	 * @since 1.0.0
	 *
	 * @uses Registry::get_defaults() to get the default option values.
	 */
	private static function install_options() {
		// Default options
		$default_options = Registry::get_defaults();
		add_option( 'domainer_options', $default_options );
	}

	/**
	 * Install/upgrade the domain table.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb The database abstraction class instance.
	 */
	private static function install_tables() {
		global $wpdb;

		// Load dbDelta utility
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		// Just install/update the translations table as normal
		$sql_domainer = "CREATE TABLE $wpdb->domainer (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(253) DEFAULT '' NOT NULL,
			blog_id bigint(20) unsigned NOT NULL,
			type enum('primary','redirect','alias') DEFAULT 'redirect' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain (name)
		) $charset_collate;";
		dbDelta( $sql_domainer );
	}

	// =========================
	// ! Upgrade Logic
	// =========================

	/**
	 * Install/Upgrade the database tables, converting them if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Wether or not an upgrade was performed.
	 */
	public static function upgrade() {
		global $wpdb;

		// Abort if the site is using the latest version
		if ( version_compare( get_option( 'domainer_database_version', '1.0.0' ), DOMAINER_DB_VERSION, '>=' ) ) {
			return false;
		}

		// Install/update the tables
		self::install_tables();

		// Add the default options
		self::install_options();

		return true;
	}
}
