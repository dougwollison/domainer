<?php
/**
 * Domainer Backend Functionality
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Backend Functionality
 *
 * Hooks into various backend systems to load
 * custom assets and add the editor interface.
 *
 * @internal Used by the System.
 *
 * @since 1.0.0
 */
final class Backend extends Handler {
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

		// Setup stuff
		self::add_hook( 'plugins_loaded', 'load_textdomain', 10, 0 );

		// Plugin information
		self::add_hook( 'in_plugin_update_message-' . plugin_basename( DOMAINER_PLUGIN_FILE ), 'update_notice' );

		// Admin interface changes
		self::add_hook( 'wpmu_blogs_columns', 'add_domains_column', 15, 1 );
		self::add_hook( 'manage_sites_custom_column', 'do_domains_column', 10, 2 );

		// Sunrise installation/activation
		self::add_hook( 'network_admin_notices', 'print_sunrise_result', 10, 0 );
		self::add_hook( 'network_admin_notices', 'print_sunrise_notice', 10, 0 );
		self::add_hook( 'admin_post_domainer-install', 'attempt_sunrise_install', 10, 0 );
	}

	// =========================
	// ! Setup Stuff
	// =========================

	/**
	 * Load the text domain.
	 *
	 * @since 1.0.0
	 */
	public static function load_textdomain() {
		// Load the textdomain
		load_plugin_textdomain( 'domainer', false, dirname( DOMAINER_PLUGIN_FILE ) . '/languages' );
	}

	// =========================
	// ! Plugin Information
	// =========================

	/**
	 * In case of update, check for notice about the update.
	 *
	 * @since 1.0.0
	 *
	 * @param array $plugin The information about the plugin and the update.
	 */
	public static function update_notice( $plugin ) {
		// Get the version number that the update is for
		$version = $plugin['new_version'];

		// Check if there's a notice about the update
		$transient = "domainer-update-notice-{$version}";
		$notice = get_transient( $transient );
		if ( $notice === false ) {
			// Hasn't been saved, fetch it from the SVN repo
			$notice = file_get_contents( "http://plugins.svn.wordpress.org/domainer/assets/notice-{$version}.txt" ) ?: '';

			// Save the notice
			set_transient( $transient, $notice, YEAR_IN_SECONDS );
		}

		// Print out the notice if there is one
		if ( $notice ) {
			echo apply_filters( 'the_content', $notice );
		}
	}

	// =========================
	// ! Admin Interface Changes
	// =========================

	/**
	 * Register the "Domains" column for the sites table.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns The list of columns.
	 *
	 * @return array The modified columns.
	 */
	public static function add_domains_column( $columns ) {
		$columns['domainer'] = __( 'Domains', 'domainer' );
		return $columns;
	}

	/**
	 * Print the content of the domains column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The ID of the current column.
	 * @param int    $blog_id The current site.
	 */
	public static function do_domains_column( $column, $blog_id ) {
		global $wpdb;

		// Abort if not the right column
		if ( $column != 'domainer' ) {
			return;
		}

		// Get all domains, ordered by type (i.e. primary first)
		$domains = $wpdb->get_results( $wpdb->prepare( "SELECT name, type FROM $wpdb->domainer WHERE blog_id = %d ORDER BY FIELD( type, 'primary', 'alias', 'redirect' )", $blog_id ) );

		if ( $domains ) {
			foreach ( $domains as $domain ) {
				printf( '<a href="http://%1$s" target="_blank">%1$s</a> (%2$s) <br />', $domain->name, $domain->type );
			}
		}
	}

	// =========================
	// ! Sunrise Installation/Activation
	// =========================

	/**
	 * Print sunrise install/activate result if needed.
	 *
	 * @since 1.0.0
	 */
	public static function print_sunrise_result() {
		if ( $install_notice = get_transient( 'domainer_sunrise_install' ) ) {
			delete_transient( 'domainer_sunrise_install' );
			?>
			<div class="notice is-dismissible notice-<?php echo $install_notice['success'] ? 'success' : 'error'; ?>">
				<p><?php echo $install_notice['message']; ?></p>
			</div>
			<?php
		}

		if ( $activation_notice = get_transient( 'domainer_sunrise_activate' ) ) {
			delete_transient( 'domainer_sunrise_activate' );
			?>
			<div class="notice is-dismissible notice-<?php echo $activation_notice['success'] ? 'success' : 'error'; ?>">
				<p><?php echo $activation_notice['message']; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Print sunrise install/activate prompt if needed.
	 *
	 * @since 1.0.0
	 */
	public static function print_sunrise_notice() {
		$installed = defined( 'DOMAINER_LOADED' );
		$activated = defined( 'SUNRISE' );

		// We're good, no notice needed
		if ( $installed && $activated ) {
			return;
		}

		$url = admin_url( 'admin-post.php?action=domainer-install' );
		if ( ! $installed ) {
			$url = wp_nonce_url( $url, 'install-sunrise', '_sri' );
		}
		if ( ! $activated ) {
			$url = wp_nonce_url( $url, 'activate-sunrise', '_sra' );
		}
		?>
		<div class="notice notice-warning">
			<p><strong><?php _e( 'Domainer cannot function until you complete the following:', 'domainer' ); ?></strong></p>
			<ol>
				<?php if ( ! $installed ) : ?>
					<li><?php printf( __( 'Copy <code>%1$s</code> file in Domainer’s plugin folder to your site’s <code>%2$s</code> directory.', 'domainer' ), 'sunrise.php', 'wp-content' ); ?></li>
				<?php endif; ?>
				<?php if ( ! $activated ) : ?>
					<li><?php printf( __( 'Add <code>%1$s</code> to your site’s <code>%2$s</code> file.', 'domainer' ), "define('SUNRISE', true);", 'wp-config.php' ); ?></li>
				<?php endif; ?>
			</ol>
			<p><?php printf( __( 'Domainer may be able to do this itself. <a href="%s">Click here</a> to give it a shot.', 'domainer' ), $url ); ?></p>
		</div>
		<?php
	}

	/**
	 * Re-attempt to install/activate the Sunrise drop-in.
	 *
	 * @since 1.0.0
	 */
	public static function attempt_sunrise_install() {
		// Check if an install attempt was requested
		$install = isset( $_REQUEST['_sri'] );
		// Verify the request
		if ( $install && ! wp_verify_nonce( $_REQUEST['_sri'], 'install-sunrise' ) ) {
			cheatin();
		}

		// Check if an activation attempt was requested
		$activate = isset( $_REQUEST['_sra'] );
		// Verify the request
		if ( $activate && ! wp_verify_nonce( $_REQUEST['_sra'], 'activate-sunrise' ) ) {
			cheatin();
		}

		if ( $install ) {
			Installer::install_sunrise();
		}

		if ( $activate ) {
			Installer::activate_sunrise();
		}

		wp_redirect( wp_get_referer() );
		exit;
	}
}
