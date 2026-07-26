<?php
/**
 * Integration tests for the Domain Router admin page.
 *
 * @package LandingPageManager
 */

/**
 * Verifies CRUD actions and sanitization for the admin UI layer.
 */
class KKLPMDomainRouterAdminPageTest extends WP_UnitTestCase {

	/**
	 * Admin page instance under test.
	 *
	 * @var KKLPM_Domain_Router_Admin_Page
	 */
	protected $admin_page;

	/**
	 * Prepare a clean state for each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		KKLPM_Domain_Map_Repository::create_table();
		$this->truncate_domain_map_table();
		$this->admin_page = new KKLPM_Domain_Router_Admin_Page();
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'administrator',
				)
			)
		);
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Reset superglobals and table content after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->truncate_domain_map_table();
		wp_set_current_user( 0 );
		unset( $GLOBALS['kklpm_test_pll_current_language'] );
		unset( $GLOBALS['kklpm_test_pll_languages_list'] );
		unset( $GLOBALS['kklpm_test_pll_translations'] );
		KKLPM_Language_Adapter_Resolver::reset();
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Ensures create action persists sanitized mapping data.
	 *
	 * @return void
	 */
	public function test_handle_save_action_creates_mapping() {
		$page_id = $this->create_published_page( 'admin-created' );

		$_POST = array(
			'action'                                      => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                  => '0',
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                     => array(
				'type'         => 'subpath',
				'value'        => ' campaign/ ',
				'page_id'      => (string) $page_id,
				'active'       => '1',
				'is_canonical' => '1',
			),
		);

		$this->admin_page->handle_save_action();

		$mappings = KKLPM_Domain_Map_Repository::get_all_mappings();

		$this->assertCount( 1, $mappings );
		$this->assertSame( '/campaign', $mappings[0]['value'] );
		$this->assertSame( 'subpath', $mappings[0]['type'] );
		$this->assertSame( '1', $mappings[0]['is_canonical'] );
		$this->assertNull( $mappings[0]['lang'] );
	}

	/**
	 * Ensures a user without kklpm_manage_domain_router is denied the settings page.
	 *
	 * Editors get kklpm_manage_landing_pages by default but must not get
	 * kklpm_manage_domain_router, so this also verifies the two capabilities
	 * stay independent.
	 *
	 * @return void
	 */
	public function test_render_page_rejects_user_without_domain_router_capability() {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'editor',
				)
			)
		);

		$this->expectException( WPDieException::class );

		$this->admin_page->render_page();
	}

	/**
	 * Ensures a user without kklpm_manage_domain_router cannot submit mapping changes.
	 *
	 * @return void
	 */
	public function test_handle_save_action_rejects_user_without_domain_router_capability() {
		$page_id = $this->create_published_page( 'admin-blocked' );

		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'editor',
				)
			)
		);

		$_POST = array(
			'action'                                      => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                  => '0',
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                     => array(
				'type'         => 'subpath',
				'value'        => 'blocked',
				'page_id'      => (string) $page_id,
				'active'       => '1',
				'is_canonical' => '0',
			),
		);

		$this->expectException( WPDieException::class );

		$this->admin_page->handle_save_action();
	}

	/**
	 * Ensures a scheme-prefixed Value is rejected instead of silently persisted.
	 *
	 * @return void
	 */
	public function test_handle_save_action_rejects_scheme_prefixed_value() {
		$page_id = $this->create_published_page( 'admin-scheme-rejected' );

		$_POST = array(
			'action'                                      => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                  => '0',
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                     => array(
				'type'         => 'subdomain',
				'value'        => 'http://promo.example.com',
				'page_id'      => (string) $page_id,
				'active'       => '1',
				'is_canonical' => '0',
			),
		);

		// admin-post.php handlers normally exit() after wp_safe_redirect(); headers
		// are already sent in the test environment, so redirect_with_notice() just
		// returns instead, letting execution reach this assertion.
		$this->admin_page->handle_save_action();

		$this->assertCount( 0, KKLPM_Domain_Map_Repository::get_all_mappings() );
	}

	/**
	 * Ensures update action overwrites an existing mapping safely.
	 *
	 * @return void
	 */
	public function test_handle_save_action_updates_existing_mapping() {
		$page_id    = $this->create_published_page( 'admin-updated' );
		$mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
				'lang'    => 'en',
			)
		);

		$_POST = array(
			'action'                                      => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                  => (string) $mapping_id,
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                     => array(
				'type'         => 'external',
				'value'        => 'landing.example.net',
				'page_id'      => (string) $page_id,
				'active'       => '0',
				'is_canonical' => '1',
			),
		);

		$this->admin_page->handle_save_action();

		$mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'external', $mapping['type'] );
		$this->assertSame( 'landing.example.net', $mapping['value'] );
		$this->assertSame( '0', $mapping['active'] );
		$this->assertSame( '1', $mapping['is_canonical'] );
		$this->assertSame( 'en', $mapping['lang'] );
	}

	/**
	 * Ensures toggle action flips the active flag.
	 *
	 * @return void
	 */
	public function test_handle_toggle_action_flips_mapping_status() {
		$page_id    = $this->create_published_page( 'toggle-target' );
		$mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$_REQUEST = array(
			'action'                                            => 'kklpm_domain_router_toggle_mapping',
			'mapping_id'                                        => (string) $mapping_id,
			KKLPM_Domain_Router_Admin_Page::ACTION_NONCE_NAME => wp_create_nonce( 'kklpm_domain_router_toggle_mapping:' . $mapping_id ),
		);

		$this->admin_page->handle_toggle_action();

		$mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( '0', $mapping['active'] );
	}

	/**
	 * Ensures delete action removes the mapping row.
	 *
	 * @return void
	 */
	public function test_handle_delete_action_removes_mapping() {
		$page_id    = $this->create_published_page( 'delete-target' );
		$mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$_REQUEST = array(
			'action'                                            => 'kklpm_domain_router_delete_mapping',
			'mapping_id'                                        => (string) $mapping_id,
			KKLPM_Domain_Router_Admin_Page::ACTION_NONCE_NAME => wp_create_nonce( 'kklpm_domain_router_delete_mapping:' . $mapping_id ),
		);

		$this->admin_page->handle_delete_action();

		$this->assertNull( KKLPM_Domain_Map_Repository::get_mapping( $mapping_id ) );
	}

	/**
	 * Ensures sanitize_mapping_input() trims the input down to supported fields.
	 *
	 * @return void
	 */
	public function test_sanitize_mapping_input_returns_expected_shape() {
		$sanitized = $this->admin_page->sanitize_mapping_input(
			array(
				'type'    => 'subpath',
				'value'   => ' promo/ ',
				'page_id' => '42',
				'active'  => '1',
				'is_canonical' => '1',
			)
		);

		$this->assertSame(
			array(
				'type'    => 'subpath',
				'value'   => 'promo/',
				'page_id' => 42,
				'active'  => 1,
				'is_canonical' => 1,
				'lang'    => '',
			),
			$sanitized
		);
	}

	/**
	 * Ensures saving a canonical mapping clears the flag from other mappings of the same page.
	 *
	 * @return void
	 */
	public function test_handle_save_action_keeps_only_one_canonical_mapping_per_page() {
		$page_id = $this->create_published_page( 'admin-canonical' );

		$first_mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
				'is_canonical' => 1,
			)
		);
		$second_mapping_id = KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => $page_id,
				'active'  => 1,
				'is_canonical' => 0,
			)
		);

		$_POST = array(
			'action'                                        => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                    => (string) $second_mapping_id,
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                       => array(
				'type'         => 'external',
				'value'        => 'landing.example.net',
				'page_id'      => (string) $page_id,
				'active'       => '1',
				'is_canonical' => '1',
			),
		);

		$this->admin_page->handle_save_action();

		$first_mapping  = KKLPM_Domain_Map_Repository::get_mapping( $first_mapping_id );
		$second_mapping = KKLPM_Domain_Map_Repository::get_mapping( $second_mapping_id );

		$this->assertSame( '0', $first_mapping['is_canonical'] );
		$this->assertSame( '1', $second_mapping['is_canonical'] );
	}

	/**
	 * Ensures the standard admin UI no longer exposes the legacy Language field.
	 *
	 * @return void
	 */
	public function test_render_page_hides_legacy_language_field() {
		ob_start();
		$this->admin_page->render_page();
		$markup = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'kklpm-domain-lang', $markup );
		$this->assertStringNotContainsString( '>Language<', $markup );
	}

	/**
	 * Ensures the rendered "Copy link" action carries the correct full URL per mapping type.
	 *
	 * @return void
	 */
	public function test_render_page_outputs_full_url_for_each_mapping_type() {
		$page_id = $this->create_published_page( 'copy-link-target' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);
		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.com',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);
		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'www.example.org',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		ob_start();
		$this->admin_page->render_page();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-link="' . esc_url( home_url( '/promo' ) ) . '"', $markup );
		$this->assertStringContainsString( 'data-link="http://promo.example.com/"', $markup );
		$this->assertStringContainsString( 'data-link="http://www.example.org/"', $markup );
	}

	/**
	 * Ensures the admin page warns when a mapped source page has translations without landing enabled.
	 *
	 * @return void
	 */
	public function test_render_page_warns_about_translation_landing_gaps() {
		$page_id       = $this->create_published_page( 'mapped-source' );
		$translated_id = $this->create_published_page( 'mapped-source-it' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$GLOBALS['kklpm_test_pll_languages_list']   = array( 'it' );
		$GLOBALS['kklpm_test_pll_translations']     = array(
			$page_id => array(
				'it' => $translated_id,
			),
		);
		$GLOBALS['kklpm_test_pll_current_language'] = 'en';
		KKLPM_Language_Adapter_Resolver::reset();

		ob_start();
		$this->admin_page->render_page();
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString( 'Some mapped pages have translations that are not ready as landing pages yet.', $markup );
		$this->assertStringContainsString( 'Mapped Source (#' . $page_id . '): IT -&gt; Mapped Source It (#' . $translated_id . ') has Landing Page disabled', $markup );
	}

	/**
	 * Creates a published page with a fixed slug.
	 *
	 * @param string $slug Page slug.
	 * @return int
	 */
	protected function create_published_page( $slug ) {
		return self::factory()->post->create(
			array(
				'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
				'post_name'   => $slug,
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Removes all rows from the custom mapping table.
	 *
	 * @return void
	 */
	protected function truncate_domain_map_table() {
		global $wpdb;
		$table_name = KKLPM_Domain_Map_Repository::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$_REQUEST = array();
	}
}
