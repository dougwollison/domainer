<?php
/**
 * Domainer AJAX Handler
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The AJAX Request Handler
 *
 * Add necessary wp_ajax_* hooks to fullfill any
 * custom AJAX requests.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class AJAX extends Handler {
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
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		// Don't do anything if not doing an AJAX request
		if ( ! defined( 'DOING_AJAX' ) || DOING_AJAX !== true ) {
			return;
		}

		// to be written
	}
}
