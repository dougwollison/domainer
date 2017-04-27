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
			self::install_options();
			self::install_tables();

			// Also attempt to install/activate Sunrise
			self::install_sunrise();
			self::activate_sunrise();
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

		// Import from Domain Mapping if applicable
		$dm_to_domainer_options = array(
			'dm_301_redirect' => 'redirection_permanent',
			'dm_redirect_admin' => 'redirect_backend',
			'dm_user_settings' => 'user_domain_management',
			'dm_remote_login' => 'remote_login',
		);
		foreach ( $dm_to_domainer_options as $dm_option => $domainer_option ) {
			$value = get_site_option( $dm_option, null );
			if ( ! is_null( $value ) ) {
				$default_options[ $domainer_option ] = get_site_option( $dm_option );
			}
		}

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
			www enum('auto','always','never') DEFAULT 'auto' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain (name)
		) $charset_collate;";
		dbDelta( $sql_domainer );

		// Stop here if the table already existed and has entries
		if ( intval( $wpdb->get_var( "SELECT COUNT(id) FROM $wpdb->domainer" ) ) ) {
			return;
		}

		// Check if the domain mapping table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}domain_mapping'" ) ) {
			// Get the default domain type (primary or alias)
			$default_type = intval( get_site_option( 'dm_no_primary_domain', 0 ) ) ? 'alias' : 'primary';

			// Get the domains and import into the domainer table
			$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}domain_mapping" );
			foreach ( $domains as $domain ) {
				$wpdb->insert( $wpdb->domainer, array(
					'name' => $domain->domain,
					'blog_id' => $domain->blog_id,
					'type' => $domain->active ? $default_type : 'redirect',
				) );
			}
		}
	}

	/**
	 * Attempt to install the sunrise.php drop-in.
	 *
	 * Rather than copy over the file, code to include
	 * the plugin's copy will be included.
	 *
	 * @since 1.0.0
	 */
	public static function install_sunrise() {
		$sunrise = WP_CONTENT_DIR . '/sunrise.php';

		// Abort if Sunrise drop-in exists
		if ( file_exists( $sunrise ) ) {
			// Log the error
			set_transient( 'domainer_sunrise_install', array(
				'success' => false,
				'message' => sprintf( __( 'The <code>%s</code> file already exists. It will need to be replaced/amended manually.', 'domainer' ), 'sunrise.php' ),
			), 30 );
			return;
		}

		// Abort if unable to write to the file
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			// Log the error
			set_transient( 'domainer_sunrise_install', array(
				'success' => false,
				'message' => sprintf( __( 'The <code>%1$s</code> directory is not writable. You must install <code>%2$s</code> manually.', 'domainer' ), 'wp-content', 'sunrise.php' ),
			), 30 );
			return;
		}

		// Copy the sunrise file over
		copy( DOMAINER_PLUGIN_DIR . '/sunrise.php', $sunrise );

		// Log the result
		set_transient( 'domainer_sunrise_install', array(
			'success' => true,
			'message' => sprintf( __( 'Successfully installed the <code>%1$s</code> file.', 'domainer' ), 'sunrise.php', 'wp-content' ),
		), 30 );
	}

	/**
	 * Attempt to copy sunrise.php to /wp-content/
	 *
	 * @since 1.0.0
	 */
	public static function activate_sunrise() {
		// Abort if Sunrise is already active
		if ( defined( 'SUNRISE' ) ) {
			return;
		}

		// Find the wp-config file
		$wp_config = ABSPATH . 'wp-config.php';
		if ( ! file_exists( $wp_config ) ) {
			$wp_config = dirname( ABSPATH )  . '/wp-config.php';

			// Abort if still not found or if it belongs to another install
			if ( ! @file_exists( $wp_config ) || @file_exists( dirname( ABSPATH )  . '/wp-settings.php' ) ) {
				// Log the error
				set_transient( 'domainer_sunrise_activate', array(
					'success' => false,
					'message' => sprintf( __( 'Unable to find the <code>%s</code> file for this installation.', 'domainer' ), 'wp-config.php' )
				), 30 );
				return;
			}
		}

		// Abort if unable to write to the file
		if ( ! is_writable( $wp_config ) ) {
			// Log the error
			set_transient( 'domainer_sunrise_activate', array(
				'success' => false,
				'message' => sprintf( __( 'The <code>%s</code> file is not writable. You must edit it manually.', 'domainer' ), 'wp-config.php' ),
			), 30 );
			return;
		}

		// Get the config file contents
		$config = file_get_contents( $wp_config );

		// Attempt to find the "Stop Editing" marker
		$marker = "/* That's all, stop editing!";
		if ( strpos( $config, $marker ) !== false ) {
			// Insert the SUNRISE definition before it
			$config = str_replace( $marker, "define( 'SUNRISE', true );\r\n\r\n$marker", $config );

			// Save the changes
			file_put_contents( $wp_config, $config );

			// Log the result
			set_transient( 'domainer_sunrise_activate', array(
				'success' => true,
				'message' => sprintf( __( 'Successfully added the <code>%s</code> definition.', 'domainer' ), 'SUNRISE' ),
			), 30 );
		} else {
			// Log the error
			set_transient( 'domainer_sunrise_activate', array(
				'success' => false,
				'message' => sprintf( __( 'Unable to find a safe place to insert the <code>%s</code> definition. You must edit it manually.', 'domainer' ), 'SUNRISE' ),
			), 30 );
		}
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
