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
		$this->assertTrue( class_exists( 'KKLPM_Path_Utils' ) );
		$this->assertSame( '/landing', KKLPM_Path_Utils::normalize_route_path( 'landing/' ) );
	}
}
