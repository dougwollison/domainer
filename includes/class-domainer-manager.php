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
		self::add_hook( 'network_admin_menu', 'register_network_admin_pages' );
		self::add_hook( 'admin_menu', 'register_site_admin_pages' );
		self::add_hook( 'admin_init', 'setup_domain_fields' );
		self::add_hook( 'admin_init', 'setup_options_fields' );

		// Action Handling
		self::add_hook( 'admin_post_domainer-update', 'update_domain' );
		self::add_hook( 'admin_post_domainer-delete', 'delete_domain' );
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
	 * Register network admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @uses Documenter::register_help_tabs() to register help tabs for all screens.
	 */
	public static function register_network_admin_pages() {
		// Domain manager
		$domains_page_hook = add_menu_page(
			__( 'Domain Manager', 'domainer' ), // page title
			_x( 'Domains', 'menu title', 'domainer' ), // menu title
			'manage_sites', // capability
			'domainer', // slug
			array( __CLASS__, 'domains_manager' ), // callback
			'dashicons-networking', // icon
			90 // Postion; after settings
		);

		// Options manager
		$options_page_hook = add_submenu_page(
			'domainer', // parent
			__( 'Domain Handling Options', 'domainer' ), // page title
			_x( 'Options', 'menu title', 'domainer' ), // menu title
			'manage_sites', // capability
			'domainer-options', // slug
			array( __CLASS__, 'options_manager' ) // callback
		);

		// Setup the help tabs for each page
		Documenter::register_help_tabs( array(
			"{$domains_page_hook}-network" => 'domains',
			"{$options_page_hook}-network" => 'options',
		) );
	}

	/**
	 * Register site admin pages if applicable.
	 *
	 * @since 1.0.0
	 *
	 * @uses Documenter::register_help_tabs() to register help tabs for all screens.
	 */
	public static function register_site_admin_pages() {
		// Setup the domain manager page if allowed
		if ( Registry::get( 'admin_domain_management' ) ) {
			$domains_page_hook = add_options_page(
				__( 'Domain Manager', 'domainer' ), // page title
				_x( 'Manage Domains', 'menu title', 'domainer' ), // menu title
				'manage_options', // capability
				'domainer-manager', // slug
				array( __CLASS__, 'domains_manager' ) // callback
			);

			// Setup the help tab
			Documenter::register_help_tab( $domains_page_hook, 'domains' );
		}

		// Setup the option manager page if allowed
		if ( Registry::get( 'admin_option_management' ) ) {
			$options_page_hook = add_options_page(
				__( 'Domain Handling Options', 'domainer' ), // page title
				_x( 'Domain Options', 'menu title', 'domainer' ), // menu title
				'manage_options', // capability
				'domainer-options', // slug
				array( __CLASS__, 'options_manager' ) // callback
			);

			// Setup the help tab
			Documenter::register_help_tab( $options_page_hook, 'options' );
		}
	}

	// =========================
	// ! Settings Saving
	// =========================

	/**
	 * Update a domain.
	 *
	 * @since 1.3.0 Add checks for name/blog_id being specified.
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 */
	public static function update_domain() {
		global $wpdb, $current_blog;

		// Abort if insufficient permissions, no data passed, or failed referer/nonce check
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['domainer_domain'] ) || ! check_admin_referer( 'edit-domainer-' . $_POST['domain_id'] ) ) {
			cheatin();
			exit;
		}

		$domain_id = intval( $_POST['domain_id'] );
		$data = $_POST['domainer_domain'];

		if ( empty( $data['name'] ) ) {
			wp_die( __( 'You must specify a domain name.', 'domainer' ) );
		}

		if ( empty( $data['blog_id'] ) ) {
			wp_die( __( 'You must specify a blog to assign this to.', 'domainer' ) );
		}

		if ( ! from_network_admin() ) {
			$domain = Registry::get_domain( $domain_id );
			if ( $domain && $domain->blog_id != $current_blog->blog_id ) {
				wp_die( __( 'You cannot edit this domain because it does not belong to your site.', 'domainer' ) );
			}

			$data['blog_id'] = $current_blog->blog_id;
		}

		// Strip leading www
		$data['name'] = preg_replace( '/^www\./', '', $data['name'] );

		if ( ( $domain = Registry::get_domain( $data['name'] ) ) && $domain->id !== $domain_id ) {
			wp_die( sprintf( __( 'The domain "%s" has already been registered.', 'domainer' ), $data['name'] ) );
		}

		if ( $domain_id == 'new' ) {
			$wpdb->insert( $wpdb->domainer, $data );
			$domain_id = $wpdb->insert_id;

			$success_message = __( 'Domain added.', 'domainer' );
		} else {
			$wpdb->update( $wpdb->domainer, $data, array(
				'id' => $domain_id,
			) );

			$success_message = __( 'Domain updated.', 'domainer' );
		}

		// Report error if applicable
		if ( $wpdb->last_error ) {
			add_settings_error(
				'domainer',
				'domainer_wpdb',
				sprintf( _x( 'Unexpected error updating domain: %s', 'domainer' ), $wpdb->last_error ),
				'error'
			);
		} else
		// If this was a primary domain, change all others for the site to redirect
		if ( $data['type'] == 'primary' ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->domainer SET type = 'redirect' WHERE blog_id = %d AND type = 'primary' AND id != %d", $data['blog_id'], $domain_id ) );
		}

		// Check for setting errors; add the "updated" message if none are found
		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'domainer', 'settings_updated', $success_message, 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$redirect = from_network_admin() ? 'network/admin.php?page=domainer' : 'options-general.php?page=domainer-manager';

		wp_redirect( add_query_arg( 'settings-updated', true, admin_url( $redirect ) ) );
		exit;
	}

	/**
	 * Delete a domain.
	 *
	 * @since 1.0.0
	 *
	 * @global \wpdb $wpdb The database abstraction class instance.
	 */
	public static function delete_domain() {
		global $wpdb, $current_blog;

		// Abort if insufficient permissions, no data passed, or failed referer/nonce check
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_REQUEST['domain_id'] ) || ! check_admin_referer( 'delete-' . $_REQUEST['domain_id'] ) ) {
			cheatin();
			exit;
		}

		// Abort if a non super admin is requesting from the network admin
		if ( from_network_admin() && ! current_user_can( 'manage_sites' ) ) {
			cheatin();
			exit;
		}

		$domain = Registry::get_domain( $_REQUEST['domain_id'] );
		if ( ! from_network_admin() && $domain->blog_id != $current_blog->blog_id ) {
			wp_die( __( 'You cannot delete this domain because it does not belong to your site.', 'domainer' ) );
		}

		$wpdb->delete( $wpdb->domainer, array(
			'id' => $domain->id,
		) );

		// Add the "deleted" message
		add_settings_error( 'domainer', 'settings_updated', __( 'Domain deleted.', 'domainer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$redirect = from_network_admin() ? 'network/admin.php?page=domainer' : 'options-general.php?page=domainer-manager';

		wp_redirect( add_query_arg( 'settings-updated', true, admin_url( $redirect ) ) );
		exit;
	}

	/**
	 * Save the options.
	 *
	 * @since 1.0.0
	 */
	public static function save_options() {
		// Abort if insufficient permissions, no data passed, or failed referer/nonce check
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['domainer_options'] ) || ! check_admin_referer( 'manage-domainer-options' ) ) {
			cheatin();
			exit;
		}

		// Abort if a non super admin is requesting from the network admin
		if ( from_network_admin() && ! current_user_can( 'manage_sites' ) ) {
			cheatin();
			exit;
		}

		$data = (array) $_POST['domainer_options'];

		$as_override = ! from_network_admin();
		foreach ( $data as $option => $value ) {
			Registry::set( $option, $value, $as_override );
		}

		if ( $as_override ) {
			Registry::save_overrides();
		} else {
			Registry::save_options();
		}

		// Add an "updated" message
		add_settings_error( 'domainer-options', 'settings_updated', __( 'Options updated.', 'domainer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$redirect = from_network_admin() ? 'network/admin.php' : 'options-general.php';
		$redirect .= '?page=domainer-options';

		wp_redirect( add_query_arg( 'settings-updated', true, admin_url( $redirect ) ) );
		exit;
	}

	// =========================
	// ! Settings Fields Setup
	// =========================

	/**
	 * Fields for the domain editor page.
	 *
	 * @since 1.3.0 Sort $site_options by blogname.
	 * @since 1.1.0 Fixed get_sites() call to return ALL sites.
	 * @since 1.0.0
	 */
	public static function setup_domain_fields() {
		$sites = get_sites( array(
			'number' => 0, // unlimited
		) );

		$site_options = array( '' => '&mdash; Select &mdash;' );
		foreach ( $sites as $site ) {
			$site_options[ $site->blog_id ] = $site->blogname;
		}

		// Sort by value
		asort( $site_options, SORT_NATURAL|SORT_FLAG_CASE );

		/**
		 * Domain Settings
		 */
		$domain_settings = array(
			'name' => array(
				'title' => __( 'Domain Name', 'domainer' ),
				'help'  => __( 'The fully qualified domain name (leave out the www)', 'domainer' ),
				'type'  => 'text',
			),
			'blog_id' => array(
				'title' => __( 'Target Site', 'domainer' ),
				'help'  => __( 'The site this domain will route to.', 'domainer' ),
				'type'  => 'select',
				'data'  => $site_options,
			),
			'type' => array(
				'title' => __( 'Domain Type', 'domainer' ),
				'help'  => __( 'Should redirection involving this domain be handled?', 'domainer' ),
				'type'  => 'select',
				'data'  => Documenter::domain_type_names(),
			),
			'www' => array(
				'title' => __( 'WWW Rule', 'domainer' ),
				'help'  => __( 'Should the domain be used with www?', 'domainer' ),
				'type'  => 'select',
				'data'  => Documenter::www_rule_names(),
			),
		);

		if ( ! is_network_admin() ) {
			unset( $domain_settings['blog_id'] );
		}

		// Add the section and fields
		add_settings_section( 'default', null, null, 'domainer-domain' );
		Settings::add_fields( $domain_settings, 'domain' );
	}

	/**
	 * Fields for the options page.
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
			'redirect_backend' => array(
				'title' => __( 'Redirect Backend URLs?', 'domainer' ),
				'help'  => __( 'Force the backend to run behind the primary domain.', 'domainer' ),
				'type'  => 'checkbox',
			),
			'no_redirect_users' => array(
				'title' => __( 'No Redirection for Users?', 'domainer' ),
				'help'  => __( 'Allow logged in users to view the site on it’s original domain?', 'domainer' ),
				'type'  => 'checkbox',
			),
			'remote_login' => array(
				'title' => __( 'Enable Remote Login?', 'domainer' ),
				'help'  => __( 'Provide cross-domain authentication for users belonging to multiple sites.', 'domainer' ),
				'type'  => 'checkbox',
			),
		);

		if ( is_network_admin() ) {
			$general_settings['admin_domain_management'] = array(
				'title' => __( 'Enable Per-Site Domain Management?', 'domainer' ),
				'help'  => __( 'Allow admins of individual sites to manage their domains?', 'domainer' ),
				'type'  => 'checkbox',
			);
			$general_settings['admin_option_management'] = array(
				'title' => __( 'Enable Per-Site Option Management?', 'domainer' ),
				'help'  => __( 'Allow admins to independently override these options for their sites?', 'domainer' ),
				'type'  => 'checkbox',
			);
		}

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
	 * @since 1.1.1 Order domains by site, type, then name.
	 * @since 1.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 */
	public static function domains_manager() {
		global $blog_id, $plugin_page, $wpdb;

		if ( ! is_network_admin() && isset( $_GET['domain_id'] ) && ( $domain = Registry::get_domain( $_GET['domain_id'] ) ) && $domain->blog_id != $blog_id ) {
			wp_die( __( 'You cannot edit this domain because it belongs to a different site.', 'domain' ) );
		}

		$edit_url = is_network_admin() ? network_admin_url( 'admin.php?page=' . $plugin_page ) : menu_page_url( $plugin_page, false );
?>
		<div class="wrap">
			<?php if ( isset( $_GET['domain_id'] ) ) : $domain_id = $_GET['domain_id']; ?>
				<?php $domain = Registry::get_domain( $domain_id ) ?: new Domain(); ?>

				<?php if ( $_GET['domain_id'] == 'new' ) : ?>
					<h2><?php _e( 'Add New Domain', 'domainer' ); ?></h2>
				<?php else : ?>
					<h2><?php printf( __( 'Edit Domain: %s', 'domainer' ), $domain->name ); ?></h2>
				<?php endif; ?>

				<?php if ( is_network_admin() ) settings_errors(); ?>
				<form method="post" action="<?php echo admin_url( 'admin-post.php?action=domainer-update' ); ?>" id="<?php echo $plugin_page; ?>-form">
					<?php wp_nonce_field( 'edit-domainer-' . $domain_id ); ?>
					<input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
					<?php do_settings_sections( 'domainer-domain' ); ?>
					<?php submit_button(); ?>
				</form>
			<?php else : ?>
				<h2>
					<?php echo get_admin_page_title(); ?>
					<a href="<?php echo add_query_arg( 'domain_id', 'new', $edit_url ); ?>" class="page-title-action"><?php _e( 'Add New', 'domainer' ); ?></a>
				</h2>

				<?php
				$where = is_network_admin() ? '' : $wpdb->prepare( "WHERE blog_id = %d", $blog_id );

				$domains = $wpdb->get_results( "SELECT * FROM $wpdb->domainer $where ORDER BY blog_id ASC, FIELD( type, 'primary', 'alias', 'redirect' ), name ASC", ARRAY_A );
				$domains = array_map( __NAMESPACE__ . '\Domain::create_instance', $domains );

				$domain_types = Documenter::domain_type_names();
				$www_rules = Documenter::www_rule_names();
				?>

				<br>

				<?php if ( is_network_admin() ) settings_errors(); ?>
				<table id="domainer_domains" class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="domainer-domain-name"><?php _ex( 'Name', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-blog"><?php _ex( 'Site', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-type"><?php _ex( 'Type', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-type"><?php _ex( 'WWW?', 'domain field', 'domainer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $domains as $domain ) :
							$type = $domain_types[ $domain->type ];
							$www = $www_rules[ $domain->www ];

							$site_url = get_blog_option( $domain->blog_id, 'home' );
							$site_name = get_blog_option( $domain->blog_id, 'blogname' );
						?>
							<tr>
								<td class="domainer-domain-name" data-colname="<?php _ex( 'Name', 'domain field', 'domainer' ); ?>">
									<a href="<?php echo add_query_arg( 'domain_id', $domain->id, $edit_url ); ?>"><?php echo $domain->name; ?></a>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo add_query_arg( 'domain_id', $domain->id, $edit_url  ); ?>"><?php _ex( 'Edit', 'domain action', 'domainer' ); ?></a> | </span>
										<span class="delete"><a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=domainer-delete&domain_id=' . $domain->id ), 'delete-' . $domain->id ); ?>"><?php _ex( 'Delete', 'domain action', 'domainer' ); ?></a></span>
									</div>
								</td>
								<td class="domainer-domain-blog" data-colname="<?php _ex( 'Site', 'domain field', 'domainer' ); ?>">
									<a href="<?php echo $site_url; ?>" target="_blank"><?php echo $site_name; ?></a>
								</td>
								<td class="domainer-domain-type" data-colname="<?php _ex( 'Type', 'domain field', 'domainer' ); ?>"><?php echo $type; ?></td>
								<td class="domainer-domain-www" data-colname="<?php _ex( 'WWW?', 'domain field', 'domainer' ); ?>"><?php echo $www; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
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
			<?php if ( is_network_admin() ) settings_errors(); ?>
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
