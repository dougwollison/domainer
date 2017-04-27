<?php
/**
 * Domainer Domain Model
 *
 * @package Domainer
 * @subpackage Structures
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Domain Model
 *
 * Provides a predictable interface for accessing
 * properties of Domains stored in the database.
 *
 * @api
 *
 * @since 1.0.0
 */
final class Domain extends Model {
	// =========================
	// ! Properties
	// =========================

	/**
	 * The database ID of the domain.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @var int
	 */
	public $id = '';

	/**
	 * The full domain name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * The ID of the site this maps to.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @var int
	 */
	public $blog_id = 0;

	/**
	 * The type of domain (primary, redirect, alias).
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @var bool
	 */
	public $type = 'primary';

	/**
	 * The www handling rule (auto, always, never).
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @var bool
	 */
	public $www = 'never';

	// =========================
	// ! Methods
	// =========================

	/**
	 * Setup/sanitize the property values.
	 *
	 * @since 1.0.0
	 *
	 * @see Domain::$properties for a list of allowed values.
	 *
	 * @uses Model::__construct() to setup the values.
	 *
	 * @param array $values The property values.
	 *		@option string "name"    The full domain name.
	 *		@option int    "blog_id" The ID of the site this maps to.
	 *		@option string "type"    The type of domain (primary, redirect, or alias).
	 *      @option string "www"     The www handling rule (auto, always, never).
	 */
	public function __construct( $values = array() ) {
		// Setup the object with the provided values
		parent::__construct( $values );

		// Sanitize name (lowercase, no WWW)
		$this->name = self::sanitize( $this->name );

		// Ensure $blog_id is integer
		$this->blog_id = intval( $this->blog_id );

		// Ensure $type is a valid value
		if ( ! in_array( $this->type, array( 'primary', 'redirect', 'alias' ) ) ) {
			$this->type = 'redirect';
		}
	}

	/**
	 * Get the full domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $www Optional. If the rule is auto, which should be used?
	 *
	 * @return string The full domain name.
	 */
	public function fullname( $www = DOMAINER_USING_WWW ) {
		$name = $this->name;

		// If never, return the name
		if ( $this->www == 'never' ) {
			return $name;
		}

		// If always, force $www to true
		if ( $this->www == 'always' ) {
			$www = true;
		}

		// Return with or without www based on $www
		return $www ? "www.$name" : $name;
	}

	// =========================
	// ! Utilities
	// =========================

	/**
	 * Sanitize the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The name to sanitize.
	 *
	 * @return string The sanitized name.
	 */
	public static function sanitize( $name ) {
		$name = strtolower( $name ); // lowercase
		$name = preg_replace( '/^www\./', '', $name ); // strip www

		return $name;
	}
}
