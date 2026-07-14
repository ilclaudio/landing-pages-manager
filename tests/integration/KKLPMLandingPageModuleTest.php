<?php
/**
 * Integration tests for the landing page module behavior.
 *
 * @package LandingPageManager
 */

/**
 * Verifies landing page template switching and meta persistence behavior.
 */
class KKLPMLandingPageModuleTest extends WP_UnitTestCase {

	/**
	 * Landing page module instance under test.
	 *
	 * @var KKLPM_Landing_Page_Module
	 */
	protected $module;

	/**
	 * Test user without unfiltered_html but with page editing capabilities.
	 *
	 * @var int
	 */
	protected static $limited_editor_user_id = 0;

	/**
	 * Test user with page editing capabilities but without kklpm_manage_landing_pages.
	 *
	 * @var int
	 */
	protected static $no_landing_access_user_id = 0;

	/**
	 * Creates the custom roles required by the suite.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		unset( $factory );

		add_role(
			'kklpm_limited_editor',
			'KKLPM Limited Editor',
			array(
				'read'                       => true,
				'edit_pages'                 => true,
				'edit_published_pages'       => true,
				'publish_pages'              => true,
				'kklpm_manage_landing_pages' => true,
			)
		);

		self::$limited_editor_user_id = self::factory()->user->create(
			array(
				'role' => 'kklpm_limited_editor',
			)
		);

		add_role(
			'kklpm_no_landing_access',
			'KKLPM No Landing Access',
			array(
				'read'                 => true,
				'edit_pages'           => true,
				'edit_published_pages' => true,
				'publish_pages'        => true,
			)
		);

		self::$no_landing_access_user_id = self::factory()->user->create(
			array(
				'role' => 'kklpm_no_landing_access',
			)
		);
	}

	/**
	 * Removes the custom roles after the suite finishes.
	 *
	 * @return void
	 */
	public static function wpTearDownAfterClass() {
		remove_role( 'kklpm_limited_editor' );
		remove_role( 'kklpm_no_landing_access' );
	}

	/**
	 * Sets up the module instance for each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->module = new KKLPM_Landing_Page_Module();
		wp_set_current_user( 0 );
		$_POST = array();
	}

	/**
	 * Resets superglobals after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'kklpm_is_block_theme' );
		remove_all_filters( 'pre_get_block_template' );
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Ensures the plugin template is used when the landing page is enabled.
	 *
	 * @return void
	 */
	public function test_template_include_returns_plugin_template_for_enabled_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $page_id, KKLPM_Landing_Page_Meta::ENABLED, '1' );

		$this->go_to( get_permalink( $page_id ) );

		$resolved_template = $this->module->filter_template_include( 'theme-page.php' );

		$this->assertSame( KKLPM_PLUGIN_DIR . 'templates/landing-page.php', $resolved_template );
	}

	/**
	 * Ensures the original template remains when the landing page is disabled.
	 *
	 * @return void
	 */
	public function test_template_include_keeps_original_template_for_disabled_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $page_id, KKLPM_Landing_Page_Meta::ENABLED, '0' );

		$this->go_to( get_permalink( $page_id ) );

		$resolved_template = $this->module->filter_template_include( 'theme-page.php' );

		$this->assertSame( 'theme-page.php', $resolved_template );
	}

	/**
	 * Ensures saving the meta box updates the landing page configuration.
	 *
	 * @return void
	 */
	public function test_save_meta_box_updates_enabled_and_raw_content_fields() {
		$post = $this->create_page_for_administrator();

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled' => '1',
				'kklpm_landing_html'    => '<section>Hero</section>',
				'kklpm_landing_css'     => 'body { color: red; }',
				'kklpm_landing_js'      => 'console.log("hero");',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '<section>Hero</section>', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
		$this->assertSame( 'body { color: red; }', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, true ) );
		$this->assertSame( 'console.log("hero");', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, true ) );
	}

	/**
	 * Ensures saving the meta box persists the theme header/footer and wp_assets toggles.
	 *
	 * @return void
	 */
	public function test_save_meta_box_updates_theme_header_footer_and_wp_assets_toggles() {
		$post = $this->create_page_for_administrator();

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled'             => '1',
				'kklpm_landing_theme_header_footer' => '1',
				'kklpm_landing_wp_assets'            => '1',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, true ) );
		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, true ) );
	}

	/**
	 * Ensures enabling theme header/footer does not silently reset the stored wp_assets preference.
	 *
	 * @return void
	 */
	public function test_save_meta_box_preserves_wp_assets_when_theme_header_footer_disables_the_control() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, '1' );

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled'             => '1',
				'kklpm_landing_theme_header_footer' => '1',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, true ) );
		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, true ) );
	}

	/**
	 * Ensures raw content is preserved when the landing page gets disabled.
	 *
	 * @return void
	 */
	public function test_save_meta_box_preserves_raw_content_when_landing_page_is_disabled() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, '1' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '<section>Existing</section>' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, 'body { background: black; }' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, 'console.log("existing");' );

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_html' => '<section>Existing</section>',
				'kklpm_landing_css'  => 'body { background: black; }',
				'kklpm_landing_js'   => 'console.log("existing");',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '0', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '<section>Existing</section>', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
		$this->assertSame( 'body { background: black; }', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, true ) );
		$this->assertSame( 'console.log("existing");', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, true ) );
	}

	/**
	 * Ensures an invalid nonce blocks every landing page update.
	 *
	 * @return void
	 */
	public function test_save_meta_box_rejects_invalid_nonce() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, '0' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '<p>Original</p>' );

		$_POST = array(
			KKLPM_Landing_Page_Module::NONCE_NAME => 'invalid',
			'kklpm_landing_enabled'               => '1',
			'kklpm_landing_html'                  => '<p>Changed</p>',
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '0', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '<p>Original</p>', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
	}

	/**
	 * Ensures a user lacking edit permissions cannot save the landing settings.
	 *
	 * @return void
	 */
	public function test_save_meta_box_rejects_user_without_edit_post_capability() {
		$admin_user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$post          = self::factory()->post->create_and_get(
			array(
				'post_author' => $admin_user_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$subscriber_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		wp_set_current_user( $subscriber_id );

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled' => '1',
				'kklpm_landing_html'    => '<p>Blocked</p>',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
	}

	/**
	 * Ensures users without unfiltered_html can toggle landing mode but not save raw fields.
	 *
	 * @return void
	 */
	public function test_save_meta_box_skips_raw_fields_without_unfiltered_html_capability() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$limited_editor_user_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '<p>Original</p>' );

		wp_set_current_user( self::$limited_editor_user_id );

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled' => '1',
				'kklpm_landing_html'    => '<p>Blocked</p>',
				'kklpm_landing_css'     => 'body { color: blue; }',
				'kklpm_landing_js'      => 'console.log("blocked");',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '1', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '<p>Original</p>', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, true ) );
		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, true ) );
	}

	/**
	 * Ensures edit_post alone is not enough: kklpm_manage_landing_pages is required too.
	 *
	 * @return void
	 */
	public function test_save_meta_box_rejects_user_with_edit_post_but_without_landing_pages_capability() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$no_landing_access_user_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( self::$no_landing_access_user_id );

		$_POST = $this->build_valid_post_payload(
			array(
				'kklpm_landing_enabled' => '1',
				'kklpm_landing_html'    => '<p>Blocked</p>',
			)
		);

		$this->module->save_meta_box( $post->ID, $post );

		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, true ) );
		$this->assertSame( '', get_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, true ) );
	}

	/**
	 * Ensures a user without kklpm_manage_landing_pages never gets the meta box registered.
	 *
	 * @return void
	 */
	public function test_register_meta_box_skips_for_user_without_landing_pages_capability() {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$subscriber_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		wp_set_current_user( $subscriber_id );

		$this->module->register_meta_box();

		$page_meta_boxes = isset( $wp_meta_boxes['page']['normal']['high'] ) ? $wp_meta_boxes['page']['normal']['high'] : array();

		$this->assertArrayNotHasKey( 'kklpm-landing-page-settings', $page_meta_boxes );
	}

	/**
	 * Ensures an editor with kklpm_manage_landing_pages gets the meta box registered.
	 *
	 * @return void
	 */
	public function test_register_meta_box_registers_for_editor_with_landing_pages_capability() {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$editor_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		wp_set_current_user( $editor_id );

		$this->module->register_meta_box();

		$this->assertArrayHasKey( 'kklpm-landing-page-settings', $wp_meta_boxes['page']['normal']['high'] );
	}

	/**
	 * Ensures the meta box hides landing fields when the page is not enabled.
	 *
	 * @return void
	 */
	public function test_render_meta_box_hides_landing_fields_when_toggle_is_disabled() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, '0' );

		$markup = $this->render_meta_box_markup( $post );

		$this->assertStringContainsString( 'Enable Landing Page', $markup );
		$this->assertStringContainsString( 'id="kklpm-landing-fields" hidden', $markup );
		$this->assertStringContainsString( 'Disabling the landing page hides these fields in the editor, but keeps their saved content for later reuse.', $markup );
	}

	/**
	 * Ensures the meta box shows landing fields when the page is enabled.
	 *
	 * @return void
	 */
	public function test_render_meta_box_shows_landing_fields_when_toggle_is_enabled() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, '1' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '<section>Visible</section>' );

		$markup = $this->render_meta_box_markup( $post );

		$this->assertStringContainsString( 'checked=\'checked\'', $markup );
		$this->assertStringContainsString( 'id="kklpm-landing-fields" >', $markup );
		$this->assertStringContainsString( '&lt;section&gt;Visible&lt;/section&gt;', $markup );
	}

	/**
	 * Ensures the wp_head/wp_footer toggle is disabled when theme header/footer is enabled.
	 *
	 * @return void
	 */
	public function test_render_meta_box_disables_wp_assets_toggle_when_theme_header_footer_is_enabled() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::ENABLED, '1' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, '1' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, '1' );

		$markup = $this->render_meta_box_markup( $post );

		$this->assertStringContainsString( 'id="kklpm-landing-wp-assets"', $markup );
		$this->assertStringContainsString( 'disabled=\'disabled\'', $markup );
		$this->assertStringContainsString( 'Used only with the isolated landing template.', $markup );
	}

	/**
	 * Ensures the isolated template prints the placeholder when HTML is empty.
	 *
	 * @return void
	 */
	public function test_landing_page_template_outputs_placeholder_when_html_is_empty() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, '' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, '' );

		$markup = $this->render_landing_template( $post );

		$this->assertStringContainsString( 'Landing page ready', $markup );
		$this->assertStringContainsString( 'Add custom HTML, CSS, and JavaScript in the Landing Page panel to start building this page.', $markup );
		$this->assertStringContainsString( 'class="kklpm-placeholder"', $markup );
	}

	/**
	 * Ensures the isolated template renders stored HTML, CSS, and JS values.
	 *
	 * @return void
	 */
	public function test_landing_page_template_outputs_saved_html_css_and_js() {
		$post = $this->create_page_for_administrator();

		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::HTML, '<section class="hero">Hello</section>' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::CSS, 'body { color: green; }' );
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::JS, 'console.log("hello");' );

		$markup = $this->render_landing_template( $post );

		$this->assertStringContainsString( '<section class="hero">Hello</section>', $markup );
		$this->assertStringContainsString( 'body { color: green; }', $markup );
		$this->assertStringContainsString( 'console.log("hello");', $markup );
		$this->assertStringNotContainsString( 'Landing page ready', $markup );
	}

	/**
	 * Ensures the template calls get_header() and get_footer() when the toggle is enabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_calls_theme_header_and_footer_when_enabled() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, '1' );

		$header_called = false;
		$footer_called = false;

		add_action(
			'get_header',
			function () use ( &$header_called ) {
				$header_called = true;
			}
		);
		add_action(
			'get_footer',
			function () use ( &$footer_called ) {
				$footer_called = true;
			}
		);

		$this->render_landing_template( $post );

		add_action( 'wp_head', 'print_emoji_detection_script', 7 );

		$this->assertTrue( $header_called );
		$this->assertTrue( $footer_called );
	}

	/**
	 * Ensures block themes render template parts instead of calling classic theme wrappers.
	 *
	 * @return void
	 */
	public function test_landing_page_template_renders_block_theme_template_parts_when_enabled() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, '1' );

		$header_called = false;
		$footer_called = false;

		add_filter(
			'kklpm_is_block_theme',
			function () {
				return true;
			}
		);

		add_filter(
			'pre_get_block_template',
			function ( $template, $id, $template_type ) {
				unset( $template_type );

				if ( false !== strpos( $id, '//header' ) ) {
					return (object) array(
						'content' => '<div class="fake-block-header">Header Part</div>',
					);
				}

				if ( false !== strpos( $id, '//footer' ) ) {
					return (object) array(
						'content' => '<div class="fake-block-footer">Footer Part</div>',
					);
				}

				return $template;
			},
			10,
			3
		);

		add_action(
			'get_header',
			function () use ( &$header_called ) {
				$header_called = true;
			}
		);
		add_action(
			'get_footer',
			function () use ( &$footer_called ) {
				$footer_called = true;
			}
		);

		$markup = $this->render_landing_template( $post );

		$this->assertFalse( $header_called );
		$this->assertFalse( $footer_called );
		$this->assertStringContainsString( 'class="kklpm-theme-header"', $markup );
		$this->assertStringContainsString( 'class="fake-block-header"', $markup );
		$this->assertStringContainsString( 'class="kklpm-landing-content"', $markup );
		$this->assertStringContainsString( 'class="kklpm-theme-footer"', $markup );
		$this->assertStringContainsString( 'class="fake-block-footer"', $markup );
	}

	/**
	 * Ensures the template skips get_header() and get_footer() when the toggle is disabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_skips_theme_header_and_footer_when_disabled() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::THEME_HEADER_FOOTER, '0' );

		$header_called = false;
		$footer_called = false;

		add_action(
			'get_header',
			function () use ( &$header_called ) {
				$header_called = true;
			}
		);
		add_action(
			'get_footer',
			function () use ( &$footer_called ) {
				$footer_called = true;
			}
		);

		$this->render_landing_template( $post );

		$this->assertFalse( $header_called );
		$this->assertFalse( $footer_called );
	}

	/**
	 * Ensures the template calls wp_head() and wp_footer() when the toggle is enabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_outputs_wp_head_and_wp_footer_when_enabled() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, '1' );

		// The local WordPress test library ships an unbuilt `src/`, so the emoji
		// script is not on disk; skip it here, it is unrelated to what this test verifies.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );

		// Same reason: block-library CSS is not built in this checkout, so
		// wp_maybe_inline_styles() triggers a doing_it_wrong() notice on read failure.
		$this->setExpectedIncorrectUsage( 'wp_maybe_inline_styles' );

		$head_called   = false;
		$footer_called = false;

		add_action(
			'wp_head',
			function () use ( &$head_called ) {
				$head_called = true;
			}
		);
		add_action(
			'wp_footer',
			function () use ( &$footer_called ) {
				$footer_called = true;
			}
		);

		$this->render_landing_template( $post );

		add_action( 'wp_head', 'print_emoji_detection_script', 7 );

		$this->assertTrue( $head_called );
		$this->assertTrue( $footer_called );
	}

	/**
	 * Ensures the isolated template prints a canonical tag directly when wp_head() is disabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_outputs_canonical_tag_without_wp_head() {
		$post = $this->create_page_for_administrator();

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => $post->ID,
				'active'  => 1,
				'is_canonical' => 1,
			)
		);

		$markup = $this->render_landing_template( $post );

		$this->assertStringContainsString(
			'<link rel="canonical" href="http://landing.example.net/" />',
			$markup
		);
	}

	/**
	 * Ensures the template can emit a fallback canonical via wp_head() when assets are enabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_outputs_fallback_canonical_via_wp_head() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, '1' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $post->ID,
				'active'  => 1,
				'is_canonical' => 0,
			)
		);

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		$this->setExpectedIncorrectUsage( 'wp_maybe_inline_styles' );
		$this->go_to( get_permalink( $post ) );

		$markup = $this->render_landing_template( $post );

		add_action( 'wp_head', 'print_emoji_detection_script', 7 );

		$this->assertStringContainsString(
			'<link rel="canonical" href="' . esc_url( get_permalink( $post ) ) . '" />',
			$markup
		);
	}

	/**
	 * Ensures the template skips wp_head() and wp_footer() when the toggle is disabled.
	 *
	 * @return void
	 */
	public function test_landing_page_template_skips_wp_head_and_wp_footer_when_disabled() {
		$post = $this->create_page_for_administrator();
		update_post_meta( $post->ID, KKLPM_Landing_Page_Meta::WP_ASSETS, '0' );

		$head_called   = false;
		$footer_called = false;

		add_action(
			'wp_head',
			function () use ( &$head_called ) {
				$head_called = true;
			}
		);
		add_action(
			'wp_footer',
			function () use ( &$footer_called ) {
				$footer_called = true;
			}
		);

		$this->render_landing_template( $post );

		$this->assertFalse( $head_called );
		$this->assertFalse( $footer_called );
	}

	/**
	 * Creates a page authored by an administrator and sets the current user.
	 *
	 * @return WP_Post
	 */
	protected function create_page_for_administrator() {
		$admin_user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		return self::factory()->post->create_and_get(
			array(
				'post_author' => $admin_user_id,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Builds a valid POST payload for the landing page meta box save routine.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	protected function build_valid_post_payload( array $overrides ) {
		return array_merge(
			array(
				KKLPM_Landing_Page_Module::NONCE_NAME => wp_create_nonce( KKLPM_Landing_Page_Module::NONCE_ACTION ),
			),
			$overrides
		);
	}

	/**
	 * Renders the landing page meta box and returns the generated markup.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	protected function render_meta_box_markup( WP_Post $post ) {
		ob_start();
		$this->module->render_meta_box( $post );
		return (string) ob_get_clean();
	}

	/**
	 * Renders the isolated landing page template and returns the generated markup.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	protected function render_landing_template( WP_Post $post ) {
		$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		ob_start();
		require KKLPM_PLUGIN_DIR . 'templates/landing-page.php';
		$markup = (string) ob_get_clean();

		if ( $original_post instanceof WP_Post ) {
			$GLOBALS['post'] = $original_post;
			setup_postdata( $original_post );
		} else {
			unset( $GLOBALS['post'] );
			wp_reset_postdata();
		}

		return $markup;
	}
}
