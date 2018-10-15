<?php
/**
 * Domainer System
 *
 * @package Domainer
 * @subpackage Handlers
 *
 * @since 1.0.0
 */

namespace Domainer;

/**
 * The Main System
 *
 * Sets up the database table aliases, the Registry,
 * and all the Handler classes.
 *
 * @api
 *
 * @since 1.0.0
 */
final class System extends Handler {
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
	 * Redirect using the appropriate status.
	 *
	 * @since 1.1.3 Apply trailingslashit to URI.
	 * @since 1.1.2 Improved $path_prefix handling.
	 * @since 1.0.1 Fixed $path_prefix handling.
	 * @since 1.0.0
	 *
	 * @param string $domain      The domain and path.
	 * @param string $path_prefix The path prefix to remove.
	 */
	public static function redirect( $domain, $path_prefix = '' ) {
		// Get the redirect status to use (301 vs 302)
		$status = Registry::get( 'redirection_permanent' ) ? 301 : 302;

		// Remove the prefix from the path if present
		$path = trailingslashit( $_SERVER['REQUEST_URI'] );
		if ( $path_prefix && strpos( $path, $path_prefix ) === 0 ) {
			$path = substr( $path, strlen( $path_prefix ) );
		}

		// Build the rewritten URL
		$redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $domain . '/' . ltrim( $path, '/' );

		// As a failsafe, abort if the domains match
		if ( defined( 'DOMAINER_ORIGINAL_URL' ) && $redirect_url == DOMAINER_ORIGINAL_URL ) {
			return;
		}

		// Perform the redirect
		if ( wp_redirect( $redirect_url, $status ) ) {
			exit;
		}
	}

	// =========================
	// ! Master Setup Method
	// =========================

	/**
	 * Register hooks and load options.
	 *
	 * @since 1.0.1 Add check for Multisite install.
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 *
	 * @uses Registry::load() to load the options.
	 * @uses Loader::register_hooks() to setup plugin management.
	 * @uses System::register_hooks() to setup global functionality.
	 * @uses Backend::register_hooks() to setup backend functionality.
	 * @uses AJAX::register_hooks() to setup AJAX functionality.
	 * @uses Manager::register_hooks() to setup admin screens.
	 * @uses Documenter::register_hooks() to setup admin documentation.
	 */
	public static function setup() {
		global $wpdb;

		// Abort and print notice if using without Multisite
		if ( ! defined( 'MULTISITE' ) || ! MULTISITE ) {
			self::add_hook( 'admin_notices', 'no_multisite_notice', 10, 0 );
			return;
		}

		// Setup the domainer table alias
		$wpdb->domainer = $wpdb->base_prefix . 'domainer';

		// Setup the registry
		Registry::load();

		// Register the Installer stuff
		Installer::register_hooks();

		// Register global hooks
		self::register_hooks();

		// Register the hooks of the subsystems
		Frontend::register_hooks();
		Backend::register_hooks();
		Manager::register_hooks();
		Documenter::register_hooks();
	}

	/**
	 * Notify the user that Domainer is useless without Multisite.
	 *
	 * @since 1.0.1
	 */
	public static function no_multisite_notice() {
		?>
		<div class="notice notice-warning">
			<p><strong><?php _e( 'You installation of WordPress doesn’t seem to be using a Network setup. Domainer is useless withou this.', 'domainer' ); ?></strong></p>
		</div>
		<?php
	}

	// =========================
	// ! Setup Utilities
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 1.1.4 Added content_url hook.
	 * @since 1.1.0 Added switch_blog hook.
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		// Redirection Handling
		self::add_hook( 'plugins_loaded', 'maybe_redirect_to_original', 10, 0 );
		self::add_hook( 'init', 'maybe_redirect_to_primary', 10, 0 );
		self::add_hook( 'init', 'maybe_redirect_to_www', 10, 0 );

		// Apply filters as needed
		if ( defined( 'DOMAINER_REWRITTEN' ) && DOMAINER_REWRITTEN ) {
			self::add_hook( 'option_siteurl', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'option_home', 'rewrite_domain_in_url', 0, 1 );

			self::add_hook( 'content_url', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'plugins_url', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'theme_root_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'stylesheet_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'stylesheet_directory_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'template_directory_uri', 'rewrite_domain_in_url', 0, 1 );
			self::add_hook( 'get_the_guid', 'rewrite_domain_in_url', 0, 1 );

			self::add_hook( 'the_content', 'rewrite_domain_in_content', 0, 1 );
			self::add_hook( 'the_excerpt', 'rewrite_domain_in_content', 0, 1 );

			self::add_hook( 'upload_dir', 'rewrite_domain_in_upload_dir', 0, 1 );
		}

		// Admin UI Tweaks
		self::add_hook( 'admin_bar_menu', 'add_domains_item', 25, 1 );

		// Blog switching handler
		self::add_hook( 'switch_blog', 'rewrite_current_blog_object', 10, 2 );
	}

	// =========================
	// ! Domain Redirecting
	// =========================

	/**
	 * Redirect to the original domain if applicable.
	 *
	 * @since 1.0.0
	 *
	 * @global \WP_Site $current_blog The current site object.
	 */
	public static function maybe_redirect_to_original() {
		global $current_blog;

		// Skip if Domainer didn't rewrite anything
		if ( ! DOMAINER_REWRITTEN ) {
			return;
		}

		// Skip if on the frontend
		if ( ! is_backend() ) {
			return;
		}

		// Skip if redirect_backend is enabled
		if ( Registry::get( 'redirect_backend' ) ) {
			return;
		}

		// Skip if not for a HEAD/GET request
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ) ) ) {
			return;
		}

		self::redirect( get_true_url() );
	}

	/**
	 * Redirect to the primary domain if applicable.
	 *
	 * @since 1.1.2 Use property_exists() to check if domain_id and true_path exist on blog object.
	 * @since 1.0.0
	 *
	 * @global \WP_Site $current_blog The current site object.
	 */
	public static function maybe_redirect_to_primary() {
		global $current_blog;

		// Skip if not for a HEAD/GET request
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( $_SERVER['REQUEST_METHOD'] ), array( 'GET', 'HEAD' ) ) ) {
			return;
		}

		// Skip if in the backend and redirect_backend isn't enabled
		if ( is_backend() && ! Registry::get( 'redirect_backend' ) ) {
			return;
		}

		// Skip if logged in and no_redirect_users is enabled
		if ( ! is_backend() && ( is_user_logged_in() && Registry::get( 'no_redirect_users' ) ) ) {
			return;
		}

		// Get the domain if one was matched
		if ( property_exists( $current_blog, 'domain_id' ) ) {
			$domain = Registry::get_domain( $current_blog->domain_id );

			// If the domain is found and is not a redirect, skip
			if ( $domain && $domain->type !== 'redirect' ) {
				return;
			}
		}

		// Find a primary domain for this site
		if ( $domain = Registry::get_primary_domain( $current_blog->blog_id ) ) {
			// Use the current path as the prefix, get the true one if it exists
			$path = $current_blog->path;
			if ( property_exists( $current_blog, 'true_path' ) ) {
				$path = $current_blog->true_path;
			}

			self::redirect( $domain->fullname(), $path );
		}
	}

	/**
	 * Redirect to the domain with/out www if applicable.
	 *
	 * @since 1.1.2 Use property_exists() to check if domain_id exists on blog object.
	 * @since 1.0.0
	 *
	 * @global \WP_Site $current_blog The current site object.
	 */
	public static function maybe_redirect_to_www() {
		global $current_blog;

		// Skip if Domainer didn't rewrite anything
		if ( ! DOMAINER_REWRITTEN ) {
			return;
		}

		// Skip if unable to find the domain
		if ( ! property_exists( $current_blog, 'domain_id' ) || ! ( $domain = Registry::get_domain( $current_blog->domain_id ) ) ) {
			return;
		}

		// Skip if the the domains rule is upheld
		if ( $domain->www == 'auto' || ( $domain->www == 'always' && DOMAINER_USING_WWW ) || ( $domain->www == 'never' && ! DOMAINER_USING_WWW ) ) {
			return;
		}

		self::redirect( $domain->fullname() );
	}

	// =========================
	// ! Domain Rewriting
	// =========================

	/**
	 * Filter the URL to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to rewrite.
	 *
	 * @return string The filtered URL.
	 */
	public static function rewrite_domain_in_url( $url ) {
		$url = str_replace( get_true_url(), get_current_url(), $url );

		return $url;
	}

	/**
	 * Filter the content to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The content to filter.
	 *
	 * @return string The filtered content.
	 */
	public static function rewrite_domain_in_content( $content ) {
		// Only replace instances prefixed with a double slash, to prevent it affecting email addresses
		$content = str_replace( '//' . get_true_url(), '//' . get_current_url(), $content );

		return $content;
	}

	/**
	 * Filter the upload_dir array to replace the domain name.
	 *
	 * @since 1.0.0
	 *
	 * @param array $upload_dir The array to filter.
	 *
	 * @return array The filtered array.
	 */
	public static function rewrite_domain_in_upload_dir( $upload_dir ) {
		$upload_dir['baseurl'] = self::rewrite_domain_in_url( $upload_dir['baseurl'] );
		$upload_dir['url'] = self::rewrite_domain_in_url( $upload_dir['url'] );

		return $upload_dir;
	}

	// =========================
	// ! Admin UI Tweaks
	// =========================

	/**
	 * Add a Domains item to the Network Admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar object.
	 */
	public static function add_domains_item( $wp_admin_bar ) {
		$wp_admin_bar->add_node( array(
			'id' => 'network-admin-domainer',
			'title' => __( 'Domains', 'domainer' ),
			'parent' => 'network-admin',
			'href' => network_admin_url( 'admin.php?page=domainer' ),
		) );
	}

	// =========================
	// ! Blog Switching
	// =========================

	/**
	 * Modify the $current_blog global to reflect the switch.
	 *
	 * This will allow domain rewrite handling to work during switch_to_blog().
	 *
	 * @since 1.1.0
	 *
	 * @param int $blog_id The ID of the blog being switched to.
	 */
	public static function rewrite_current_blog_object( $blog_id ) {
		global $current_blog, $current_site;

		// Get the new blog
		$new_blog = \WP_Site::get_instance( $blog_id );

		// Get the requested domain if on the original blog, otherwise the primary
		if ( defined( 'DOMAINER_REQUESTED_BLOG' ) && DOMAINER_REQUESTED_BLOG === $blog_id ) {
			$domain = Registry::get_domain( DOMAINER_REQUESTED_DOMAIN );
		} else {
			$domain = Registry::get_primary_domain( $blog_id );
		}

		if ( $new_blog ) {
			// Replace current blog
			$current_blog = $new_blog;

			if ( $domain ) {
				// Store the true domain/path, along with the requested domain's ID
				$current_blog->true_domain = $current_blog->domain;
				$current_blog->true_path = $current_blog->path;
				$current_blog->domain_id = $domain->id;

				// Rewrite the domain/path
				$current_blog->domain = $domain->fullname();
				$current_blog->path = '/';
			}
		}
	}
}
