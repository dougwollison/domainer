<?php
/**
 * Domainer Registry API
 *
 * @package Domainer
 * @subpackage Tools
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Registry
 *
 * Stores all the configuration options for the system.
 *
 * @api
 *
 * @since 1.0.0
 */
final class Registry {
	// =========================
	// ! Properties
	// =========================

	/**
	 * The loaded status flag.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	protected static $__loaded = false;

	/**
	 * The options storage array
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $options = array();

	/**
	 * The site-specific option overrides.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $option_overrides = array();

	/**
	 * The options whitelist/defaults.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $options_whitelist = array(
		// - The permanent redirection option
		'redirection_permanent' => false,

		// - The rewrite backend option
		'redirect_backend' => false,

		// - The no-rewrite for users option
		'no_redirect_users' => false,

		// - The per-site domain management option
		'admin_domain_management' => false,

		// - The per-site options management option
		'admin_option_management' => false,

		// - The remote login option
		'remote_login' => false, // Support pending
	);

	/**
	 * The overrides whitelist.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $overrides_whitelist = array(
		// - The permanent redirection option
		'redirection_permanent' => false,

		// - The rewrite backend option
		'redirect_backend' => false,

		// - The no-rewrite for users option
		'no_redirect_users' => false,

		// - The remote login option
		'remote_login' => false, // Support pending
	);

	/**
	 * The deprecated options and their alternatives.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected static $options_deprecated = array();

	// =========================
	// ! Property Accessing
	// =========================

	/**
	 * Retrieve the whitelist.
	 *
	 * @internal Used by the Installer and Manager.
	 *
	 * @since 1.0.0
	 *
	 * @return array The options whitelist.
	 */
	public static function get_defaults() {
		return self::$options_whitelist;
	}

	/**
	 * Check if an option is supported.
	 *
	 * Will also udpate the option value if it was deprecated
	 * but has a sufficient alternative.
	 *
	 * @since 1.0.0
	 *
	 * @param string &$option The option name.
	 *
	 * @return bool Wether or not the option is supported.
	 */
	public static function has( &$option ) {
		if ( isset( self::$options_deprecated[ $option ] ) ) {
			$option = self::$options_deprecated[ $option ];
		}

		return isset( self::$options_whitelist[ $option ] );
	}

	/**
	 * Retrieve a option value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option       The option name.
	 * @param mixed  $default      Optional. The default value to return.
	 * @param bool   $true_value   Optional. Get the true value, bypassing any overrides.
	 * @param bool   $has_override Optional. By-reference boolean to identify if an override exists.
	 *
	 * @return mixed The property value.
	 */
	public static function get( $option, $default = null, $true_value = false, &$has_override = null ) {
		// Trigger notice error if trying to get an unsupported option
		if ( ! self::has( $option ) ) {
			trigger_error( "[Domainer] The option '{$option}' is not supported.", E_USER_NOTICE );
		}

		// Check if it's set, return it's value.
		if ( isset( self::$options[ $option ] ) ) {
			// Check if it's been overriden, use that unless otherwise requested
			$has_override = isset( self::$option_overrides[ $option ] );
			if ( $has_override && ! $true_value ) {
				$value = self::$option_overrides[ $option ];
			} else {
				$value = self::$options[ $option ];
			}
		} else {
			$value = $default;
		}

		return $value;
	}

	/**
	 * Set/Override an option value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option   The option name.
	 * @param mixed  $value    Optional. The value to assign.
	 * @param bool   $override Optional. Wether or not to set as an override.
	 */
	public static function set( $option, $value = null, $override = false ) {
		// Trigger notice error if trying to set an unsupported option
		if ( ! self::has( $option ) ) {
			trigger_error( "[Domainer] The option '{$option}' is not supported", E_USER_NOTICE );
		}

		if ( $override ) {
			self::$option_overrides[ $option ] = $value;
		} else {
			self::$options[ $option ] = $value;
		}
	}

	// =========================
	// ! Domain Accessing
	// =========================

	/**
	 * Get the info for a domain.
	 *
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 *
	 * @param int|string $id_or_name The ID or name of the domain.
	 *
	 * @return Domain The domain object, FALSE if not found.
	 */
	public static function get_domain( $id_or_name ) {
		global $wpdb;

		$field = 'name';
		if ( is_numeric( $id_or_name ) ) {
			$field = 'id';
		}

		// Check if it's cached, return if so
		$cached = wp_cache_get( "{$field}:{$id_or_name}", 'domainer:domain', false, $found );
		if ( $found ) {
			return $cached;
		}

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->domainer WHERE $field = %s LIMIT 1", $id_or_name ), ARRAY_A );
		if ( ! $result ) {
			return false;
		}

		$domain = new Domain( $result );

		// Cache both ways of finding the domain
		wp_cache_set( "id:{$domain->id}", $domain, 'domainer:domain' );
		wp_cache_set( "name:{$domain->name}", $domain, 'domainer:domain' );

		return $domain;
	}

	/**
	 * Get the primary domain for a site.
	 *
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 *
	 * @param int $blog_id The ID of the site to fetch for.
	 *
	 * @return Domain The domain object, FALSE if not found.
	 */
	public static function get_primary_domain( $blog_id ) {
		global $wpdb;

		// Check if it's cached, return if so
		$cached = wp_cache_get( $blog_id, 'domainer:primary', false, $found );
		if ( $found ) {
			return $cached;
		}

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->domainer WHERE blog_id = %d AND type = 'primary' LIMIT 1", $blog_id ), ARRAY_A );
		if ( ! $result ) {
			return false;
		}

		$domain = new Domain( $result );

		// Cache the result
		wp_cache_set( $blog_id, $domain, 'domainer:primary' );

		return $domain;
	}

	// =========================
	// ! Setup Method
	// =========================

	/**
	 * Load the relevant options.
	 *
	 * @since 1.0.0
	 *
	 * @see Registry::$__loaded to prevent unnecessary reloading.
	 * @see Registry::$options_whitelist to filter the found options.
	 * @see Registry::set() to actually set the value.
	 *
	 * @param bool $reload Should we reload the options?
	 */
	public static function load( $reload = false ) {
		if ( self::$__loaded && ! $reload ) {
			// Already did this
			return;
		}

		// Load the options
		$options = get_site_option( 'domainer_options', array() );
		foreach ( self::$options_whitelist as $option => $default ) {
			$value = $default;
			if ( isset( $options[ $option ] ) ) {
				$value = $options[ $option ];

				// Ensure the value is the same type as the default
				settype( $value, gettype( $default ) );
			}

			self::set( $option, $value );
		}

		// Load local options if applicable
		if ( ! is_network_admin() && ! from_network_admin() && Registry::get( 'admin_option_management' ) ) {
			$overrides = get_option( 'domainer_option_overrides', array() );
			foreach ( self::$overrides_whitelist as $option => $default ) {
				if ( isset( $overrides[ $option ] ) ) {
					$value = $overrides[ $option ];

					// Ensure the value is the same type as the default
					settype( $value, gettype( $default ) );

					self::set( $option, $value, 'override' );
				}
			}
		}

		// Flag that we've loaded everything
		self::$__loaded = true;
	}

	/**
	 * Save the options to the database.
	 *
	 * @since 1.0.0
	 */
	public static function save_options() {
		update_site_option( 'domainer_options', self::$options );
	}

	/**
	 * Save the options to the database.
	 *
	 * @since 1.0.0
	 */
	public static function save_overrides() {
		update_option( 'domainer_option_overrides', self::$option_overrides );
	}
}
