<?php
/**
 * Domainer Frontend Functionality
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Frontend Functionality
 *
 * Handles redirection and rewriting of the frontend output.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class Frontend extends Handler {
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
		// Don't do anything if in the backend
		if ( is_backend() ) {
			return;
		}
	}
}
