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
		self::add_hook( 'admin_init', 'setup_options_fields' );
		self::add_hook( 'admin_post_domainer-options', 'save_options' );
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
		// Domain manager
		$domains_page_hook = add_menu_page(
			__( 'Domain Manager', 'domainer' ), // page title
			_x( 'Domains', 'menu title', 'domainer' ), // menu title
			'manage_options', // capability
			'domainer', // slug
			array( get_called_class(), 'domains_manager' ), // callback
			'dashicons-networking', // icon
			90 // Postion; after settings
		);

		// Options manager
		$options_page_hook = add_submenu_page(
			'domainer', // parent
			__( 'Domain Handling Options', 'domainer' ), // page title
			_x( 'Options', 'menu title', 'domainer' ), // menu title
			'manage_options', // capability
			'domainer-options', // slug
			array( get_called_class(), 'options_manager' ) // callback
		);

		// Setup the help tabs for each page
		Documenter::register_help_tabs( array(
			"{$domains_page_hook}-network" => 'domains',
			"{$options_page_hook}-network" => 'options',
		) );
	}

	// =========================
	// ! Settings Saving
	// =========================

	/**
	 * Save the options.
	 *
	 * @since 1.0.0
	 */
	public static function save_options() {
		if ( ! isset( $_POST['domainer_options'] ) || ! check_admin_referer( 'manage-domainer-options' ) ) {
			cheatin();
			exit;
		}

		$settings = Registry::get_defaults();
		$options = get_site_option( 'domainer_options', array() );
		$data = (array) $_POST['domainer_options'];

		foreach ( $settings as $setting => $default ) {
			if ( isset( $data[ $setting ] ) ) {
				$options[ $setting ] = settype( $data[ $setting ], gettype( $default ) );
			}
		}

		update_site_option( 'domainer_options', $options );

		wp_redirect( admin_url( 'network/admin.php?page=domainer-options' ) );
		exit;
	}

	// =========================
	// ! Settings Fields Setup
	// =========================

	/**
	 * Fields for the Translations page.
	 *
	 * @since 1.0.0
	 */
	public static function setup_options_fields() {
		/**
		 * General Settings
		 */
		$general_settings = array(
			'redirection_permanent' => array(
				'title' => __( 'Permanently Redirect to Primary/Default Domain?', 'domainer' ),
				'help'  => __( 'Use "permanent" (HTTP 301) instead of "temporary" (HTTP 302) redirects?', 'domainer' ),
				'type'  => 'checkbox',
			),
		);

		// Add the section and fields
		add_settings_section( 'default', null, null, 'domainer-options' );
		Settings::add_fields( $general_settings, 'options' );
	}

	// =========================
	// ! Settings Page Output
	// =========================

	/**
	 * Output for the domains manager.
	 *
	 * @since 1.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 */
	public static function domains_manager() {
		global $plugin_page;
?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<?php settings_errors(); ?>
			<form method="post" action="<?php echo admin_url( 'admin-post.php?action=' . $plugin_page ); ?>" id="<?php echo $plugin_page; ?>-form">
				<?php wp_nonce_field( "manage-$plugin_page" ); ?>

				<!-- to be written -->

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Output for the options manager.
	 *
	 * @since 1.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 */
	public static function options_manager() {
		global $plugin_page;
?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<?php settings_errors(); ?>
			<form method="post" action="<?php echo admin_url( 'admin-post.php?action=' . $plugin_page ); ?>" id="<?php echo $plugin_page; ?>-form">
				<input type="hidden" name="domainer_options" value="" />
				<?php wp_nonce_field( "manage-$plugin_page" ); ?>
				<?php do_settings_sections( $plugin_page ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
