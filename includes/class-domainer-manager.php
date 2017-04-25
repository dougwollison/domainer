<?php
/**
 * Domainer Manager Funtionality
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Management System
 *
 * Hooks into the backend to add the interfaces for
 * managing the configuration of Domainer.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class Manager extends Handler {
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
		// Don't do anything if not in the backend
		if ( ! is_backend() ) {
			return;
		}

		// Settings & Pages
		self::add_hook( 'network_admin_menu', 'add_menu_pages' );
		self::add_hook( 'admin_init', 'register_settings' );
	}

	// =========================
	// ! Utilities
	// =========================

	// to be written

	// =========================
	// ! Settings Page Setup
	// =========================

	/**
	 * Register admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @uses Manager::settings_page() for general options page output.
	 * @uses Documenter::register_help_tabs() to register help tabs for all screens.
	 */
	public static function add_menu_pages() {
		// Main Options page
		$options_page_hook = add_menu_page(
			__( 'Domain Management Options', 'domainer' ), // page title
			_x( 'Domains', 'menu title', 'domainer' ), // menu title
			'manage_options', // capability
			'domainer-options', // slug
			array( get_called_class(), 'settings_page' ), // callback
			'dashicons-networking', // icon
			90 // Postion; after settings
		);

		// Setup the help tabs for each page
		Documenter::register_help_tabs( array(
			$options_page_hook => 'options',
		) );
	}

	// =========================
	// ! Settings Registration
	// =========================

	/**
	 * Register the settings/fields for the admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @uses Settings::register() to register the settings.
	 * @uses Manager::setup_options_fields() to add fields to the main options fields.
	 */
	public static function register_settings() {
		register_setting( 'domainer-options', 'domainer_options', array( __CLASS__, 'update_options' ) );
		self::setup_options_fields();
	}

	// =========================
	// ! Settings Saving
	// =========================

	/**
	 * Merge the updated options with the rest before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The options being updated.
	 *
	 * @return mixed The merged/sanitized options.
	 */
	public static function update_options( $updated_options ) {
		$all_options = get_option( 'domainer_options', array() );

		return array_merge( $all_options, $updated_options );
	}

	// =========================
	// ! Settings Fields Setup
	// =========================

	/**
	 * Fields for the Translations page.
	 *
	 * @since 1.0.0
	 */
	protected static function setup_options_fields() {
		/**
		 * General Settings
		 */
		$general_settings = array(
			// to be written
		);

		// Add the section and fields
		add_settings_section( 'default', null, null, 'domainer-options' );
		Settings::add_fields( $general_settings, 'options' );
	}

	// =========================
	// ! Settings Page Output
	// =========================

	/**
	 * Output for generic settings page.
	 *
	 * @since 1.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 */
	public static function settings_page() {
		global $plugin_page;
?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" id="<?php echo $plugin_page; ?>-form">
				<?php settings_fields( $plugin_page ); ?>
				<?php do_settings_sections( $plugin_page ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
