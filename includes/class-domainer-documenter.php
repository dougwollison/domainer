<?php
/**
 * Domainer Documentation Utility
 *
 * @package Domainer
 * @subpackage Helpers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Documenter System
 *
 * Handles printing out the help screen tabs/sidebar for
 * documenting different parts of the admin interface.
 *
 * @internal
 *
 * @since 1.0.0
 */
final class Documenter extends Handler {
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

	/**
	 * A directory of all help tabs available.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private static $directory = array();

	/**
	 * An index of screens registered for help tabs.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private static $registered_screens = array();

	// =========================
	// ! Dynamic Properties
	// =========================

	/**
	 * Get a reference list for domain types.
	 *
	 * @since 2.0.0
	 *
	 * @return array The list of names, localized.
	 */
	public static function domain_type_names() {
		return array(
			'primary'  => _x( 'Primary',  'domain type', 'domainer' ),
			'redirect' => _x( 'Redirect', 'domain type', 'domainer' ),
			'alias'    => _x( 'Alias',    'domain type', 'domainer' ),
		);
	}

	// =========================
	// ! Hook Registration
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		// Don't do anything if not in the backend
		if ( ! is_backend() ) {
			return;
		}

		self::add_hook( 'admin_head', 'setup_help_tabs', 10, 0 );
	}

	// =========================
	// ! Help Tab Registration
	// =========================

	/**
	 * Register a help tab for a screen.
	 *
	 * @since 1.0.0
	 *
	 * @uses Documenter::$registered_screens to store the screen and tab IDs.
	 *
	 * @param string $screen The screen ID to add the tab to.
	 * @param string $tab    The tab ID to add to the screen.
	 */
	public static function register_help_tab( $screen, $tab ) {
		self::$registered_screens[ $screen ] = $tab;
	}

	/**
	 * Register help tabs for multiple screens.
	 *
	 * @since 1.0.0
	 *
	 * @uses Documenter::register_help_tab() to register each screen/tab.
	 *
	 * @param string $screens An array of screen=>tab IDs to register.
	 */
	public static function register_help_tabs( $screens ) {
		foreach ( $screens as $screen => $tab ) {
			self::register_help_tab( $screen, $tab );
		}
	}

	// =========================
	// ! Help Tab Content
	// =========================

	/**
	 * Load the sepecified tab's file and return it's ID/title/content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab     The ID of the tab to get.
	 * @param string $section Optional. The section the tab belongs to.
	 *
	 * @return array The ID, title, and content of the help tab.
	 */
	public static function get_tab_data( $tab, $section = null ) {
		// Sanitize JUST in case...
		$tab = sanitize_file_name( $tab );
		$section = sanitize_file_name( $section );

		// Build the path to the doc file
		$path = DOMAINER_PLUGIN_DIR . '/documentation';

		// If a section is specified, add to the path
		if ( ! is_null( $section ) ) {
			$path .= '/' . $section;
		}

		// Add the actual tab filename
		$path .= '/' . $tab . '.php';

		// Fail if the file does not exist
		if ( ! file_exists( $path ) ) {
			return null;
		}

		// Get the contents of the file
		ob_start();
		include( $path );
		$html = ob_get_clean();

		// Parse the HTML to get the title and content
		preg_match( '#^(?:<title>(.+?)</title>)?\s*([\s\S]+)$#', $html, $matches );

		// Return the parsed data
		return array(
			'id' => "domainer-{$section}-{$tab}",
			'title' => $matches[1],
			'content' => wpautop( $matches[2] ),
		);
	}

	// =========================
	// ! Help Tab Output
	// =========================

	/**
	 * Setup the help tabs for the current screen.
	 *
	 * A specific tab set ID can be specified, otherwise, it will
	 * search for a tab set registered already for the screen.
	 *
	 * @since 1.0.0
	 *
	 * @uses Documenter::$registered_screens to get the tab set ID.
	 * @uses Documenter::$directory to retrieve the help tab settings.
	 * @uses Documenter::get_tab_data() to get the ID/title/content for the tab.
	 *
	 * @param string $help_id Optional. The ID of the tabset to setup.
	 */
	public static function setup_help_tabs( $help_id = null ) {
		// Get the screen object
		$screen = get_current_screen();

		// If no help tab ID is passed, see if one is registered for the screen.
		if ( is_null( $help_id ) ) {
			// Abort if no help tab is registered for this screen
			if ( ! isset( self::$registered_screens[ $screen->id ] ) ) {
				return;
			}

			// Get the help tabset
			$help_id = self::$registered_screens[ $screen->id ];
		}

		// Fail if no matching help tab exists
		if ( ! isset( self::$directory[ $help_id ] ) ) {
			return;
		}

		// Get the help info for this page
		$help = self::$directory[ $help_id ];

		// Add each tab defined
		foreach ( $help['tabs'] as $tab ) {
			$data = self::get_tab_data( $tab, $help_id );

			// Only add if there's data
			if ( $data ) {
				$screen->add_help_tab( $data );
			}
		}

		// Add sidebar if enabled
		if ( isset( $help['sidebar'] ) ) {
			$data = self::get_tab_data( 'sidebar', $help_id );

			// Only add if there's data
			if ( $data ) {
				$screen->set_help_sidebar( $data['content'] );
			}
		}
	}
}
