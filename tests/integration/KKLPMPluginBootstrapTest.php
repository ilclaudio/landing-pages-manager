<?php
/**
 * Integration smoke tests for plugin bootstrap.
 *
 * @package LandingPageManager
 */

/**
 * Verifies that the plugin bootstrap loads in the WordPress test environment.
 */
class KKLPMPluginBootstrapTest extends WP_UnitTestCase {

	/**
	 * Ensure the plugin bootstrap defines expected constants and loads utilities.
	 *
	 * @return void
	 */
	public function test_plugin_bootstrap_loads_expected_constants_and_classes() {
		$this->assertTrue( defined( 'KKLPM_VERSION' ) );
		$this->assertSame( '0.0.1', KKLPM_VERSION );
		$this->assertTrue( defined( 'KKLPM_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'KKLPM_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'KKLPM_PLUGIN_URL' ) );
		$this->assertTrue( class_exists( 'KKLPM_Path_Utils' ) );
		$this->assertTrue( class_exists( 'KKLPM_Plugin' ) );
		$this->assertTrue( class_exists( 'KKLPM_Landing_Page_Module' ) );
		$this->assertTrue( class_exists( 'KKLPM_Domain_Router_Module' ) );
		$this->assertTrue( class_exists( 'KKLPM_Domain_Router_Admin_Page' ) );
		$this->assertSame( '/landing', KKLPM_Path_Utils::normalize_route_path( 'landing/' ) );
		$this->assertSame( '_kklpm_landing_enabled', KKLPM_Landing_Page_Meta::ENABLED );
	}

	/**
	 * Ensure the plugin registers its core i18n and landing page hooks.
	 *
	 * @return void
	 */
	public function test_plugin_registers_expected_core_hooks() {
		$this->assertTrue( $this->hook_has_callback( 'init', 'KKLPM_Plugin', 'load_textdomain' ) );
		$this->assertTrue( $this->hook_has_callback( 'add_meta_boxes', 'KKLPM_Landing_Page_Module', 'register_meta_box' ) );
		$this->assertTrue( $this->hook_has_callback( 'save_post_page', 'KKLPM_Landing_Page_Module', 'save_meta_box' ) );
		$this->assertTrue( $this->hook_has_callback( 'template_include', 'KKLPM_Landing_Page_Module', 'filter_template_include' ) );
		$this->assertTrue( $this->hook_has_callback( 'parse_request', 'KKLPM_Domain_Router_Module', 'handle_parse_request' ) );
		$this->assertTrue( $this->hook_has_callback( 'admin_menu', 'KKLPM_Domain_Router_Admin_Page', 'register_menu' ) );
		$this->assertTrue( $this->hook_has_callback( 'admin_post_kklpm_domain_router_save_mapping', 'KKLPM_Domain_Router_Admin_Page', 'handle_save_action' ) );
		$this->assertTrue( $this->hook_has_callback( 'admin_post_kklpm_domain_router_delete_mapping', 'KKLPM_Domain_Router_Admin_Page', 'handle_delete_action' ) );
		$this->assertTrue( $this->hook_has_callback( 'admin_post_kklpm_domain_router_toggle_mapping', 'KKLPM_Domain_Router_Admin_Page', 'handle_toggle_action' ) );
	}

	/**
	 * Checks whether a WordPress hook contains a callback for a class method.
	 *
	 * @param string $hook_name   Hook name.
	 * @param string $class_name  Callback class name.
	 * @param string $method_name Callback method name.
	 * @return bool
	 */
	protected function hook_has_callback( $hook_name, $class_name, $method_name ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof WP_Hook ) {
			return false;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as $callbacks_at_priority ) {
			foreach ( $callbacks_at_priority as $callback ) {
				if ( ! is_array( $callback['function'] ) ) {
					continue;
				}

				if ( ! is_object( $callback['function'][0] ) ) {
					continue;
				}

				if ( $class_name !== get_class( $callback['function'][0] ) ) {
					continue;
				}

				if ( $method_name !== $callback['function'][1] ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}
}
