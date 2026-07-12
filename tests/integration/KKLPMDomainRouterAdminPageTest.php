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
				'type'    => 'subpath',
				'value'   => ' campaign/ ',
				'page_id' => (string) $page_id,
				'active'  => '1',
				'lang'    => 'en',
			),
		);

		$this->admin_page->handle_save_action();

		$mappings = KKLPM_Domain_Map_Repository::get_all_mappings();

		$this->assertCount( 1, $mappings );
		$this->assertSame( '/campaign', $mappings[0]['value'] );
		$this->assertSame( 'subpath', $mappings[0]['type'] );
		$this->assertSame( 'en', $mappings[0]['lang'] );
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
				'type'    => 'subpath',
				'value'   => 'blocked',
				'page_id' => (string) $page_id,
				'active'  => '1',
				'lang'    => '',
			),
		);

		$this->expectException( WPDieException::class );

		$this->admin_page->handle_save_action();
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
			)
		);

		$_POST = array(
			'action'                                      => 'kklpm_domain_router_save_mapping',
			'mapping_id'                                  => (string) $mapping_id,
			KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_NAME => wp_create_nonce( KKLPM_Domain_Router_Admin_Page::SAVE_NONCE_ACTION ),
			'mapping'                                     => array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => (string) $page_id,
				'active'  => '0',
				'lang'    => '',
			),
		);

		$this->admin_page->handle_save_action();

		$mapping = KKLPM_Domain_Map_Repository::get_mapping( $mapping_id );

		$this->assertSame( 'external', $mapping['type'] );
		$this->assertSame( 'landing.example.net', $mapping['value'] );
		$this->assertSame( '0', $mapping['active'] );
		$this->assertNull( $mapping['lang'] );
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
				'lang'    => 'it',
			)
		);

		$this->assertSame(
			array(
				'type'    => 'subpath',
				'value'   => 'promo/',
				'page_id' => 42,
				'active'  => 1,
				'lang'    => 'it',
			),
			$sanitized
		);
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
