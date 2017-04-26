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
		self::add_hook( 'admin_init', 'setup_domain_fields' );
		self::add_hook( 'admin_init', 'setup_options_fields' );

		// Action Handling
		self::add_hook( 'admin_post_domainer-update', 'update_domain' );
		self::add_hook( 'admin_post_domainer-disable', 'disable_domain' );
		self::add_hook( 'admin_post_domainer-enable', 'enable_domain' );
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
	 * Update a domain.
	 *
	 * @since 1.0.0
	 */
	public static function update_domain() {
		global $wpdb;

		if ( ! isset( $_POST['domainer_domain'] ) || ! check_admin_referer( 'edit-domainer-' . $_POST['domain_id'] ) ) {
			cheatin();
			exit;
		}

		if ( $_POST['domain_id'] == 'new' ) {
			$wpdb->insert( $wpdb->domainer, $_POST['domainer_domain'] );
			$success_message = __( 'Domain added.', 'domainer' );
		} else {
			$wpdb->update( $wpdb->domainer, $_POST['domainer_domain'], array(
				'id' => $_POST['domain_id'],
			) );
			$success_message = __( 'Domain updated.', 'domainer' );
		}

		if ( $wpdb->last_error ) {
			add_settings_error(
				'domainer',
				'domainer_wpdb',
				sprintf( _x( 'Unexpected error updating domain: %s', 'domainer' ), $wpdb->last_error ),
				'error'
			);
		}

		// Check for setting errors; add the "updated" message if none are found
		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'domainer', 'settings_updated', $success_message, 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_redirect( admin_url( 'network/admin.php?page=domainer&settings-updated=true' ) );
		exit;
	}

	/**
	 * Handle a domain action (disable, enable, delete).
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The action to perform.
	 * @param string $message The update message on success.
	 */
	protected static function handle_domain_action( $action, $message ) {
		global $wpdb;

		if ( ! isset( $_REQUEST['domain_id'] ) || ! check_admin_referer( $action . '-' . $_REQUEST['domain_id'] ) ) {
			cheatin();
			exit;
		}

		switch ( $action ) {
			case 'disable':
			case 'enable':
				$wpdb->update( $wpdb->domainer, array(
					'active' => $action == 'enable',
				), array(
					'id' => $_REQUEST['domain_id'],
				) );
				break;

			case 'delete':
				$wpdb->delete( $wpdb->domainer, array(
					'id' => $_REQUEST['domain_id'],
				) );
				break;
		}

		if ( $wpdb->last_error ) {
			add_settings_error(
				'domainer',
				'domainer_wpdb',
				sprintf( _x( 'Unexpected error updating domain: %s', 'domainer' ), $wpdb->last_error ),
				'error'
			);
		}

		// Check for setting errors; add the "updated" message if none are found
		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'domainer', 'settings_updated', $message, 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_redirect( admin_url( 'network/admin.php?page=domainer&settings-updated=true' ) );
		exit;
	}

	/**
	 * Disable a domain.
	 *
	 * @since 1.0.0
	 */
	public static function disable_domain() {
		self::handle_domain_action( 'disable', __( 'Domain disabled.', 'domainer' ) );
	}

	/**
	 * Enable a domain.
	 *
	 * @since 1.0.0
	 */
	public static function enable_domain() {
		self::handle_domain_action( 'enable', __( 'Domain enabled.', 'domainer' ) );
	}

	/**
	 * Delete a domain.
	 *
	 * @since 1.0.0
	 */
	public static function delete_domain() {
		self::handle_domain_action( 'delete', __( 'Domain deleted.', 'domainer' ) );
	}

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
				$options[ $setting ] = $data[ $setting ];
			}
		}

		update_site_option( 'domainer_options', $options );

		// Add an "updated" message
		add_settings_error( 'domainer-options', 'settings_updated', __( 'Options updated.', 'domainer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_redirect( admin_url( 'network/admin.php?page=domainer-options&settings-updated=true' ) );
		exit;
	}

	// =========================
	// ! Settings Fields Setup
	// =========================

	/**
	 * Fields for the domain editor page.
	 *
	 * @since 1.0.0
	 */
	public static function setup_domain_fields() {
		$sites = get_sites();
		$site_options = array();
		foreach ( $sites as $site ) {
			$site_options[ $site->blog_id ] = $site->blogname;
		}

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
			'active' => array(
				'title' => __( 'Active?', 'domainer' ),
				'help'  => __( 'Uncheck to keep this domain on file but ignore handling of it.', 'domainer' ),
				'type'  => 'checkbox',
			),
		);

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
		global $plugin_page, $wpdb;
?>
		<div class="wrap">
			<?php if ( isset( $_GET['domain_id'] ) ) : $domain_id = $_GET['domain_id']; ?>
				<?php $domain = Registry::get_domain( $domain_id ) ?: new Domain(); ?>

				<?php if ( $_GET['domain_id'] == 'new' ) : ?>
					<h2><?php _e( 'Add New Domain', 'domainer' ); ?></h2>
				<?php else : ?>
					<h2><?php printf( __( 'Edit Domain: %s', 'domainer' ), $domain->name ); ?></h2>
				<?php endif; ?>

				<?php settings_errors(); ?>
				<form method="post" action="<?php echo admin_url( 'admin-post.php?action=domainer-update' ); ?>" id="<?php echo $plugin_page; ?>-form">
					<?php wp_nonce_field( 'edit-domainer-' . $domain_id ); ?>
					<input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
					<?php do_settings_sections( 'domainer-domain' ); ?>
					<?php submit_button(); ?>
				</form>
			<?php else : ?>
				<h2>
					<?php echo get_admin_page_title(); ?>
					<a href="<?php echo admin_url( 'network/admin.php?page=domainer&domain_id=new' ); ?>" class="page-title-action"><?php _e( 'Add New', 'domainer' ); ?></a>
				</h2>

				<?php
				$domains = $wpdb->get_results( "SELECT * FROM $wpdb->domainer", ARRAY_A );
				$domains = array_map( __NAMESPACE__ . '\Domain::create_instance', $domains );

				$domain_types = Documenter::domain_type_names();
				?>

				<br>

				<?php settings_errors(); ?>
				<table id="domainer_domains" class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="domainer-domain-name"><?php _ex( 'Name', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-blog"><?php _ex( 'Site', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-type"><?php _ex( 'Type', 'domain field', 'domainer' ); ?></th>
							<th scope="col" class="domainer-domain-status"><?php _ex( 'Status', 'domain field', 'domainer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $domains as $domain ) :
							$type = $domain_types[ $domain->type ];

							$site_url = get_blog_option( $domain->blog_id, 'home' );
							$site_name = get_blog_option( $domain->blog_id, 'blogname' );
						?>
							<tr>
								<td class="domainer-domain-name" data-colname="<?php _ex( 'Name', 'domain field', 'domainer' ); ?>">
									<a href="<?php echo admin_url( 'network/admin.php?page=domainer&domain_id='. $domain->id ); ?>"><?php echo $domain->name; ?></a>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo admin_url( 'network/admin.php?page=domainer&domain_id='. $domain->id ); ?>"><?php _ex( 'Edit', 'domain action', 'domainer' ); ?></a> | </span>

										<?php if ( $domain->active ) : ?>
											<span class="disable"><a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=domainer-disable&domain_id=' . $domain->id ), 'disable-' . $domain->id ); ?>"><?php _ex( 'Disable', 'domain action', 'domainer' ); ?></a> | </span>
										<?php else: ?>
											<span class="enable"><a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=domainer-enable&domain_id=' . $domain->id ), 'enable-' . $domain->id ); ?>"><?php _ex( 'Enable', 'domain action', 'domainer' ); ?></a> | </span>
										<?php endif; ?>
										<span class="delete"><a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=domainer-delete&domain_id=' . $domain->id ), 'delete-' . $domain->id ); ?>"><?php _ex( 'Delete', 'domain action', 'domainer' ); ?></a></span>
									</div>
								</td>
								<td class="domainer-domain-blog" data-colname="<?php _ex( 'Site', 'domain field', 'domainer' ); ?>">
									<a href="<?php echo $site_url; ?>" target="_blank"><?php echo $site_name; ?></a>
								</td>
								<td class="domainer-domain-type" data-colname="<?php _ex( 'Type', 'domain field', 'domainer' ); ?>"><?php echo $type; ?></td>
								<td class="domainer-domain-status" data-colname="<?php _ex( 'Status', 'domain field', 'domainer' ); ?>"><?php echo $domain->active ? _x( 'Active', 'domain status', 'domainer' ) : _x( 'Inactive', 'domain status', 'domainer' ); ?></td>
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
