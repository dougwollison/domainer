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
	// ! Utilities
	// =========================

	/**
	 * Generate a semi-unique ID for the visitor.
	 *
	 * Based on their IP address and User Agent.
	 *
	 * @since 1.1.1
	 *
	 * @return string The SHA1 encoded visitor ID.
	 */
	private static function visitor_id() {
		return sha1( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . AUTH_SALT );
	}

	/**
	 * Test if remote login related actions should proceed.
	 *
	 * @since 1.1.0
	 *
	 * @param string $session_key Optional. Additionally check if a $_SESSION key exists.
	 */
	private static function should_do_remote_login( $session_key = null ) {
		// Skip if remote_login is not enabled
		// or if redirect_backend is disabled (useless if so)
		if ( ! Registry::get( 'remote_login' ) || ! Registry::get( 'redirect_backend' ) ) {
			return false;
		}

		// Skip if specified session key is present
		if ( $session_key && ( ! isset( $_SESSION ) || ! isset( $_SESSION[ $session_key ] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate a set of tokens.
	 *
	 * @since 1.1.1 Now salts the secret with visitor_id() result.
	 * @since 1.1.0
	 *
	 * @param string  $type The type of tokens to generate ('login', 'logout').
	 * @param WP_User $user The user to generate tokens for.
	 * @param array   $data Optional. The data to store the secret with.
	 */
	private static function generate_tokens( $type, \WP_User $user, $data = array() ) {
		global $blog_id;

		// Check if we should proceed
		if ( ! self::should_do_remote_login() ) {
			return;
		}

		// Get the visitor ID
		$visitor_id = self::visitor_id();

		$tokens = array();

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

			// Store the data as a transient
			$data['secret'] = wp_hash_password( $secret . $visitor_id );
			set_transient( "domainer-{$type}-" . sha1( $key ), $data, 30 );

			restore_current_blog();

			$tokens[ $site->userblog_id ] = "{$key}-{$secret}";
		}

		$_SESSION[ "domainer-{$type}-tokens" ] = $tokens;
	}

	/**
	 * Print a set of tokens.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type The type of tokens to print ('login', 'logout').
	 */
	private static function print_tokens( $type ) {
		global $blog_id;

		$action = "domainer-{$type}";
		$key = "{$action}-tokens";

		// Check if we should proceed
		if ( ! self::should_do_remote_login( $key ) ) {
			return;
		}

		$urls = array();
		foreach ( $_SESSION[ $key ] as $site => $token ) {
			// Skip if for the current blog somehow
			if ( $site == $blog_id ) {
				continue;
			}

			switch_to_blog( $site );

			$url = admin_url( 'admin-post.php' );
			$url = add_query_arg( array(
				'action' => $action,
				'token' => $token,
			), $url );

			printf( '<script class="domainer-auth-url" data-url="%s"></script>', esc_attr( $url ) );

			restore_current_blog();
		}

		unset( $_SESSION[ $key ] );
	}

	/**
	 * Verify a token.
	 *
	 * @since 1.1.1 Now salts the secret with visitor_id() result.
	 * @since 1.1.0
	 *
	 * @param string $type The type of token to verify ('login', 'logout').
	 */
	private static function verify_token( $type ) {
		// Fail if remote_login is not enabled
		if ( ! Registry::get( 'remote_login' ) ) {
			header( 'HTTP/1.1 403 Forbidden' );
			die( "/* remote $type disabled */" );
		}

		// Fail if no token is present
		if ( ! isset( $_REQUEST['token'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( "/* remote $type token missing */" );
		}

		// Get the visitor ID
		$visitor_id = self::visitor_id();

		// Get the key/secret parts
		list( $key, $secret ) = explode( '-', $_REQUEST['token'] );
		$key = sha1( $key );

		$transient = "domainer-$type-$key";
		$data = get_transient( $transient );
		delete_transient( $transient );

		// Fail if the data could not be found
		if ( ! $data ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( "/* remote $type data not found */" );
		}

		// If specified, fail if the user does not exist or does not belong to this site
		if ( isset( $data['user'] ) && ( ! get_userdata( $data['user'] ) || ! is_user_member_of_blog( $data['user'] ) ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( "/* user not authorized for " . COOKIE_DOMAIN . " */" );
		}

		// Fail if the secret is missing
		if ( ! isset( $data['secret'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( "/* remote $type secret not found */" );
		}

		// Fail if the secret doesn't pass
		if ( ! wp_check_password( $secret . $visitor_id, $data['secret'] ) ) {
			header( 'HTTP/1.1 401 Unauthorized' );
			die( "/* $type token invalid */" );
		}

		return $data;
	}

	// =========================
	// ! Hook Registration
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 1.2.0 Rework session handling.
	 * @since 1.1.0 Added remote login/logout hooks.
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

		// Script/Style Enqueues
		self::add_hook( 'login_enqueue_scripts', 'enqueue_assets', 10, 0 );
		self::add_hook( 'admin_enqueue_scripts', 'enqueue_assets', 10, 0 );

		// Admin interface changes
		self::add_hook( 'wpmu_blogs_columns', 'add_domains_column', 15, 1 );
		self::add_hook( 'manage_sites_custom_column', 'do_domains_column', 10, 2 );

		// Sunrise installation/activation
		self::add_hook( 'network_admin_notices', 'print_sunrise_result', 10, 0 );
		self::add_hook( 'network_admin_notices', 'print_sunrise_notice', 10, 0 );
		self::add_hook( 'admin_post_domainer-install', 'attempt_sunrise_install', 10, 0 );

		// Shared remote login/logout handling
		self::add_hook( 'admin_notices', 'print_message_template', 10, 0 );
		self::add_hook( 'login_header', 'print_message_template', 10, 0 );

		// Remote login handling
		self::add_hook( 'wp_login', 'generate_login_tokens', 10, 2 );
		self::add_hook( 'admin_head', 'print_login_links', 10, 0 );
		self::add_hook( 'admin_post_domainer-login', 'verify_login_token', 10, 0 );
		self::add_hook( 'admin_post_nopriv_domainer-login', 'verify_login_token', 10, 0 );
		self::add_hook( 'admin_notices', 'print_login_notice', 10, 0 );

		// Remote logout handling
		self::add_hook( 'login_form_logout', 'generate_logout_tokens', 10, 0 );
		self::add_hook( 'login_head', 'print_logout_links', 10, 0 );
		self::add_hook( 'admin_post_domainer-logout', 'verify_logout_token', 10, 0 );
		self::add_hook( 'admin_post_nopriv_domainer-logout', 'verify_logout_token', 10, 0 );
		self::add_hook( 'login_header', 'print_logout_notice', 10, 0 );

		// Session Handling (if remote login is enabled)
		if ( self::should_do_remote_login() ) {
			self::add_hook( 'login_init', 'start_session', 10, 0 );
			self::add_hook( 'admin_init', 'start_session', 10, 0 );
			self::add_hook( 'login_footer', 'end_session', 10, 0 );
			self::add_hook( 'admin_footer', 'end_session', 10, 0 );
		}
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
	// ! Script/Style Enqueues
	// =========================

	/**
	 * Enqueue necessary styles and scripts.
	 *
	 * @since 1.1.0
	 */
	public static function enqueue_assets() {
		// General network admin styling
		if ( is_network_admin() ) {
			wp_enqueue_style( 'domainer-admin', plugins_url( 'css/admin.css', DOMAINER_PLUGIN_FILE ), array(), DOMAINER_PLUGIN_VERSION, 'screen' );
		}

		// Check if we should proceed with Remote Login related assets
		if ( ! self::should_do_remote_login() ) {
			return;
		}

		// Notice styling for admin/login screens
		wp_enqueue_style( 'domainer-notice', plugins_url( 'css/notice.css', DOMAINER_PLUGIN_FILE ), array(), DOMAINER_PLUGIN_VERSION, 'screen' );

		// Login/Logout URL handling on admin/login screens
		wp_enqueue_script( 'domainer-authenticate', plugins_url( 'js/authenticate.js', DOMAINER_PLUGIN_FILE ), array( 'jquery' ), DOMAINER_PLUGIN_VERSION, 'in footer' );

		// Determine which phrasing to use based on context
		if ( current_action() == 'login_enqueue_scripts' ) {
			$waiting = __( 'Attempting remote logout on %s...', 'domainer' );
			$success = __( 'Remote logout on %s successful.', 'domainer' );
			$error = __( 'Remote logout on %s failed.', 'domainer' );
		} else {
			$waiting = __( 'Attempting remote login on %s...', 'domainer' );
			$success = __( 'Remote login on %s successful.', 'domainer' );
			$error = __( 'Remote login on %s failed.', 'domainer' );
		}

		// Localization of authenticate script
		wp_localize_script( 'domainer-authenticate', 'domainerL10n', array(
			'waiting' => $waiting,
			'success' => $success,
			'error' => $error,
		) );
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
	 * @since 1.1.1 Also order domains by type AND name
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

		// Get all domains, ordered by type (i.e. primary first), then name
		$domains = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->domainer WHERE blog_id = %d ORDER BY FIELD( type, 'primary', 'alias', 'redirect' ), name", $blog_id ) );

		// Get the labels for the domain types
		$types = Documenter::domain_type_names();

		$items = array();
		if ( $domains ) {
			foreach ( $domains as $domain ) {
				$domain = new Domain( $domain );
				$items[] = sprintf( '<a href="%1$s%2$s" target="_blank" class="domain-%3$s">%2$s</a> (%4$s)', is_ssl() ? 'https://' : 'http://', $domain->fullname(), $domain->type, $types[ $domain->type ] );
			}
		}

		echo implode( ' <br />', $items );
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
	 * @since 1.2.1 Added writable check on wp-content & wp-config.
	 * @since 1.1.3 Added checks for DOMAINER_*_SUNRISE flags.
	 * @since 1.0.0
	 */
	public static function print_sunrise_notice() {
		$installed = defined( 'DOMAINER_LOADED' ) || defined( 'DOMAINER_INSTALLED_SUNRISE' );
		$activated = defined( 'SUNRISE' ) || defined( 'DOMAINER_ACTIVATED_SUNRISE' );

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
			<?php if ( ( is_writable( WP_CONTENT_DIR ) || is_writable( WP_CONTENT_DIR . 'sunrise.php' ) ) && is_writable( ABSPATH . 'wp-config.php' ) ) : ?>
				<p><?php printf( __( 'Domainer may be able to do this itself. <a href="%s">Click here</a> to give it a shot.', 'domainer' ), $url ); ?></p>
			<?php endif; ?>
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
	// ! Login/Logout Handling
	// =========================

	/**
	 * Print the template for notice messages.
	 *
	 * @since 1.1.0
	 */
	public static function print_message_template() {
		// Check if we should proceed
		if ( ! self::should_do_remote_login() ) {
			return;
		}

		?>
		<script type="text/template" id="domainer_message_template">
			<p><span class="icon dashicons"></span> <span class="text"></span></p>
		</script>
		<?php
	}

	// =========================
	// ! Remote Login Handling
	// =========================

	/**
	 * Generate login tokens for all sites the user belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $username The user's login name.
	 * @param \WP_User $user     The user object.
	 */
	public static function generate_login_tokens( $username, $user ) {
		self::generate_tokens( 'login', $user, array(
			'user' => $user->ID,
			'remember' => isset( $_POST['rememberme'] ) && $_POST['rememberme'],
		) );
	}

	/**
	 * Print <script> tags for login links.
	 *
	 * @since 1.1.0
	 */
	public static function print_login_links() {
		self::print_tokens( 'login' );
	}

	/**
	 * Print an empty notice box for the remote login results.
	 *
	 * @since 1.1.0
	 */
	public static function print_login_notice() {
		// Check if we should proceed
		if ( ! self::should_do_remote_login() ) {
			return;
		}

		echo '<div class="notice is-dismissible domainer-notice"></div>';
	}

	/**
	 * Verify the login token and authenticate the user.
	 *
	 * @since 1.1.0
	 */
	public static function verify_login_token() {
		$data = self::verify_token( 'login' );

		wp_set_auth_cookie( $data['user'], $data['remember'] );
		header( 'HTTP/1.1 200 OK' );
		die( '/* logged in on ' . COOKIE_DOMAIN . ' */' );
	}

	// =========================
	// ! Remote Logout Handling
	// =========================

	/**
	 * Generate logout tokens for all sites the user belongs to.
	 *
	 * @since 1.1.0
	 */
	public static function generate_logout_tokens() {
		$user = wp_get_current_user();
		self::generate_tokens( 'logout', $user );
	}

	/**
	 * Print <script> tags for logout links.
	 *
	 * @since 1.1.0
	 */
	public static function print_logout_links() {
		self::print_tokens( 'logout' );
	}

	/**
	 * Print an empty notice box for the remote logout results.
	 *
	 * @since 1.1.0
	 */
	public static function print_logout_notice() {
		// Check if we should proceed
		if ( ! self::should_do_remote_login() ) {
			return;
		}

		echo '<div class="message domainer-notice"></div>';
	}

	/**
	 * Verify the logout token and end the users session.
	 *
	 * @since 1.1.0
	 */
	public static function verify_logout_token() {
		self::verify_token( 'logout' );

		// Logout the user
		wp_logout();
		header( 'HTTP/1.1 200 OK' );
		die( '/* logged out on ' . COOKIE_DOMAIN . ' */' );
	}

	// =========================
	// ! Session Handling
	// =========================

	/**
	 * Start the session if not already started.
	 *
	 * @since 1.2.0
	 */
	public static function start_session() {
		// Don't start session for ajax requests
		if ( ! defined( 'DOING_AJAX' ) && session_status() == PHP_SESSION_NONE ) {
		    session_start();
		}
	}

	/**
	 * If a session is active, write and close it.
	 *
	 * @since 1.2.0
	 */
	public static function end_session() {
		if ( session_status() == PHP_SESSION_ACTIVE ) {
			session_write_close();
		}
	}
}
