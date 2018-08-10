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

		// Remote login handling
		self::add_hook( 'wp_login', 'generate_auth_tokens', 10, 2 );
		self::add_hook( 'admin_head', 'print_auth_links', 10, 0 );
		self::add_hook( 'admin_post_domainer-authenticate', 'verify_auth_token', 10, 0 );
		self::add_hook( 'admin_post_nopriv_domainer-authenticate', 'verify_auth_token', 10, 0 );
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
	 * @global \wpdb $wpdb The database abstraction class instance.
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
		$domains = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->domainer WHERE blog_id = %d ORDER BY FIELD( type, 'primary', 'alias', 'redirect' )", $blog_id ) );

		if ( $domains ) {
			foreach ( $domains as $domain ) {
				$domain = new Domain( $domain );
				printf( '<a href="%1$s%2$s" target="_blank">%2$s</a> (%3$s) <br />', is_ssl() ? 'https://' : 'http://', $domain->fullname(), $domain->type );
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

	// =========================
	// ! Remote Login Handling
	// =========================

	/**
	 * Generate login auth tokens for each site the user belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $username The user's login name.
	 * @param \WP_User $user     The user object.
	 */
	public static function generate_auth_tokens( $username, $user ) {
		global $blog_id;

		// Skip if remote_login is not enabled
		// or if redirect_backend is disabled (useless if so)
		if ( ! Registry::get( 'remote_login' ) || ! Registry::get( 'redirect_backend' ) ) {
			return;
		}

		$tokens = array();

		$remember = isset( $_POST['rememberme'] ) && $_POST['rememberme'];

		// Loop through all sites the user belongs to
		$sites = get_blogs_of_user( $user->ID );
		foreach ( $sites as $site ) {
			// Skip for current blog
			if ( $site->userblog_id == $blog_id ) {
				continue;
			}

			// Generate a unique key and token
			$key = str_replace( '.', '', microtime( true ) );
			$secret = wp_generate_password( 40, false, false );

			switch_to_blog( $site->userblog_id );

			set_transient( 'domainer-auth-' . sha1( $key ), array(
				'user' => $user->ID,
				'secret' => wp_hash_password( $secret ),
				'remember' => $remember,
			), 30 );

			restore_current_blog();

			$tokens[ $site->userblog_id ] = "{$key}-{$secret}";
		}

		$_SESSION['domainer-auth-tokens'] = $tokens;
	}

	/**
	 * Print <script> tags for auth links.
	 *
	 * @since 1.1.0
	 */
	public static function print_auth_links() {
		global $blog_id;

		// Skip if remote_login is not enabled
		// or if redirect_backend is disabled (useless if so)
		if ( ! Registry::get( 'remote_login' ) || ! Registry::get( 'redirect_backend' ) ) {
			return;
		}

		// Skip if no tokens are present in the session
		if ( ! isset( $_SESSION['domainer-auth-tokens'] ) ) {
			return;
		}

		foreach ( $_SESSION['domainer-auth-tokens'] as $site => $token ) {
			// Skip if for the current blog somehow
			if ( $site == $blog_id ) {
				continue;
			}

			switch_to_blog( $site );

			$url = admin_url( 'admin-post.php?action=domainer-authenticate&token=' . $token );

			restore_current_blog();

			printf( '<script src="%s"></script>', $url );
		}

		unset( $_SESSION['domainer-auth-tokens'] );
	}

	/**
	 * Verify the auth token and authenticate the user.
	 *
	 * @since 1.1.0
	 */
	public static function verify_auth_token() {
		// Fail if remote_login is not enabled
		if ( ! Registry::get( 'remote_login' ) ) {
			header( 'HTTP/1.1 403 Forbidden' );
			die( '/* Remote login disabled */' );
		}

		// Fail if no token is present
		if ( ! isset( $_REQUEST['token'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( '/* Remote login token missing */' );
		}

		// Get the key/secret parts
		list( $key, $secret ) = explode( '-', $_REQUEST['token'] );

		$transient = 'domainer-auth-' . sha1( $key );
		$data = get_transient( $transient );
		delete_transient( $transient );

		// Fail if the data could not be retrieved
		if ( ! $data ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( '/* Remote login data not found */' );
		}

		// Fail if the user specified does not exist or does not belong to this site
		if ( ! get_userdata( $data['user'] ) || ! is_user_member_of_blog( $data['user'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( '/* User not authorized for this site */' );
		}

		// Fail if the secret doesn't pass
		if ( ! wp_check_password( $secret, $data['secret'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( '/* Authentication token invalid */' );
		}

		wp_set_auth_cookie( $data['user'], $data['remember'] );
		header( 'HTTP/1.1 200 OK' );
		die( '/* Authenticated on ' . COOKIE_DOMAIN . ' */' );
	}
}
