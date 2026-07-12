<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LandingPageManager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates module bootstrapping for the plugin.
 */
class KKLPM_Plugin {

	/**
	 * Plugin modules.
	 *
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		$plugin = new self();
		$plugin->register_modules();
		$plugin->register_hooks();
	}

	/**
	 * Runs plugin activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		KKLPM_Domain_Map_Repository::create_table();
		( new self() )->grant_default_capabilities();
	}

	/**
	 * Registers plugin modules.
	 *
	 * @return void
	 */
	protected function register_modules() {
		$this->modules[] = new KKLPM_Landing_Page_Module();
		$this->modules[] = new KKLPM_Domain_Router_Module();
		$this->modules[] = new KKLPM_Domain_Router_Admin_Page();
	}

	/**
	 * Registers hooks for all modules.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'grant_default_capabilities' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KKLPM_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
		}
	}

	/**
	 * Grants the plugin's default capabilities to core roles, if missing.
	 *
	 * Runs on activation and defensively on every admin_init, since
	 * register_activation_hook does not fire for already-active installs
	 * upgrading from a version predating these capabilities.
	 *
	 * @return void
	 */
	public function grant_default_capabilities() {
		$this->add_capability_if_missing( 'administrator', 'kklpm_manage_landing_pages' );
		$this->add_capability_if_missing( 'administrator', 'kklpm_manage_domain_router' );
		$this->add_capability_if_missing( 'editor', 'kklpm_manage_landing_pages' );
	}

	/**
	 * Adds a capability to a role only if it doesn't already have it.
	 *
	 * @param string $role_name  Role slug.
	 * @param string $capability Capability slug.
	 * @return void
	 */
	protected function add_capability_if_missing( $role_name, $capability ) {
		$role = get_role( $role_name );

		if ( $role && ! $role->has_cap( $capability ) ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Adds a "Manage routes" link to the plugin row on the Plugins list page.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( $links ) {
		if ( ! current_user_can( KKLPM_Domain_Router_Admin_Page::CAPABILITY ) ) {
			return $links;
		}

		$routes_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( KKLPM_Domain_Router_Admin_Page::get_page_url() ),
			esc_html__( 'Manage routes', 'landing-pages-manager' )
		);

		array_unshift( $links, $routes_link );

		return $links;
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'landing-pages-manager',
			false,
			dirname( plugin_basename( KKLPM_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
