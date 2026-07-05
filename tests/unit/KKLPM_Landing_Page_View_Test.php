<?php
/**
 * Unit tests for KKLPM_Landing_Page_View.
 *
 * @package LandingPageManager
 */

use PHPUnit\Framework\TestCase;

/**
 * Covers pure landing page view decisions.
 */
class KKLPM_Landing_Page_View_Test extends TestCase {

	/**
	 * Ensures the toggle value normalizes to string flags.
	 *
	 * @return void
	 */
	public function test_normalize_enabled_value_returns_expected_string_flags() {
		$this->assertSame( '1', KKLPM_Landing_Page_View::normalize_enabled_value( '1' ) );
		$this->assertSame( '1', KKLPM_Landing_Page_View::normalize_enabled_value( true ) );
		$this->assertSame( '0', KKLPM_Landing_Page_View::normalize_enabled_value( '' ) );
		$this->assertSame( '0', KKLPM_Landing_Page_View::normalize_enabled_value( null ) );
	}

	/**
	 * Ensures the plugin template wins only when every condition is met.
	 *
	 * @return void
	 */
	public function test_resolve_template_path_returns_plugin_template_only_when_request_is_eligible() {
		$this->assertSame(
			'plugin-template.php',
			KKLPM_Landing_Page_View::resolve_template_path( true, 42, true, 'plugin-template.php', true, 'theme-template.php' )
		);
		$this->assertSame(
			'theme-template.php',
			KKLPM_Landing_Page_View::resolve_template_path( false, 42, true, 'plugin-template.php', true, 'theme-template.php' )
		);
		$this->assertSame(
			'theme-template.php',
			KKLPM_Landing_Page_View::resolve_template_path( true, 0, true, 'plugin-template.php', true, 'theme-template.php' )
		);
		$this->assertSame(
			'theme-template.php',
			KKLPM_Landing_Page_View::resolve_template_path( true, 42, false, 'plugin-template.php', true, 'theme-template.php' )
		);
		$this->assertSame(
			'theme-template.php',
			KKLPM_Landing_Page_View::resolve_template_path( true, 42, true, 'plugin-template.php', false, 'theme-template.php' )
		);
	}

	/**
	 * Ensures placeholder markup is used only when HTML content is empty.
	 *
	 * @return void
	 */
	public function test_resolve_html_content_uses_placeholder_only_for_empty_html() {
		$this->assertSame(
			'<p>Placeholder</p>',
			KKLPM_Landing_Page_View::resolve_html_content( '', '<p>Placeholder</p>' )
		);
		$this->assertSame(
			'<p>Placeholder</p>',
			KKLPM_Landing_Page_View::resolve_html_content( '   ', '<p>Placeholder</p>' )
		);
		$this->assertSame(
			'<section>Custom</section>',
			KKLPM_Landing_Page_View::resolve_html_content( '<section>Custom</section>', '<p>Placeholder</p>' )
		);
	}
}
