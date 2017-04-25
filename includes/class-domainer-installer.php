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
	 * @uses NL_PLUGIN_FILE to identify the plugin file.
	 * @uses Installer::plugin_activate() as the activation hook.
	 * @uses Installer::plugin_deactivate() as the deactivation hook.
	 */
	public static function register_hooks() {
		// Plugin hooks
		register_activation_hook( NL_PLUGIN_FILE, array( __CLASS__, 'plugin_activate' ) );
		register_deactivation_hook( NL_PLUGIN_FILE, array( __CLASS__, 'plugin_deactivate' ) );

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
	protected static function install() {
		// Default options
		$default_options = Registry::get_defaults();
		add_option( 'domainer_options', $default_options );
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
		// to be written
	}
}
