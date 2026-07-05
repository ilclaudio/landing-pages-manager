<?php
/**
 * Integration tests for runtime domain router behavior.
 *
 * @package LandingPageManager
 */

/**
 * Verifies request matching and fallback handling for the router module.
 */
class KKLPMDomainRouterModuleTest extends WP_UnitTestCase {

	/**
	 * Router module instance under test.
	 *
	 * @var KKLPM_Domain_Router_Module
	 */
	protected $module;

	/**
	 * Prepare a clean custom table and a fresh module instance.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		KKLPM_Domain_Map_Repository::create_table();
		$this->truncate_domain_map_table();
		$this->module = new KKLPM_Domain_Router_Module();
		$_SERVER['HTTP_HOST'] = 'example.org';
	}

	/**
	 * Reset table and server state after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->truncate_domain_map_table();
		unset( $_SERVER['HTTP_HOST'] );
		$this->set_permalink_structure( '' );
		parent::tear_down();
	}

	/**
	 * Ensures the router applies a mapped page request for a subdomain.
	 *
	 * @return void
	 */
	public function test_parse_request_routes_valid_subdomain_mapping() {
		$page_id = $this->create_published_page( 'promo-page' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subdomain',
				'value'   => 'promo.example.org',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = '';
		$_SERVER['HTTP_HOST'] = 'promo.example.org';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $page_id, (int) $wp->query_vars['page_id'] );
		$this->assertSame( 'promo-page', $wp->query_vars['pagename'] );
		$this->assertSame( KKLPM_Domain_Router_Module::MATCHED_RULE, $wp->matched_rule );
	}

	/**
	 * Ensures the router applies a mapped page request for an external domain.
	 *
	 * @return void
	 */
	public function test_parse_request_routes_valid_external_mapping() {
		$page_id = $this->create_published_page( 'external-landing' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'landing.example.net',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = '';
		$_SERVER['HTTP_HOST'] = 'landing.example.net';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $page_id, (int) $wp->query_vars['page_id'] );
		$this->assertSame( 'external-landing', $wp->query_vars['pagename'] );
	}

	/**
	 * Ensures the router applies a mapped page request for a subpath.
	 *
	 * @return void
	 */
	public function test_parse_request_routes_valid_subpath_mapping() {
		$page_id = $this->create_published_page( 'campaign' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = 'promo/';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( $page_id, (int) $wp->query_vars['page_id'] );
		$this->assertSame( 'campaign', $wp->query_vars['pagename'] );
	}

	/**
	 * Ensures inactive mappings do not hijack normal requests.
	 *
	 * @return void
	 */
	public function test_parse_request_ignores_inactive_mapping() {
		$page_id = $this->create_published_page( 'inactive-target' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $page_id,
				'active'  => 0,
			)
		);

		$wp = new WP();
		$wp->request = 'promo/';

		$this->module->handle_parse_request( $wp );

		$this->assertArrayNotHasKey( 'page_id', $wp->query_vars );
		$this->assertEmpty( $wp->matched_rule );
	}

	/**
	 * Ensures invalid mapped targets trigger a controlled 404 state.
	 *
	 * @return void
	 */
	public function test_parse_request_marks_invalid_mapping_target_as_not_found() {
		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'external',
				'value'   => 'broken.example.net',
				'page_id' => 999999,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = '';
		$_SERVER['HTTP_HOST'] = 'broken.example.net';

		$this->module->handle_parse_request( $wp );

		$this->assertSame( '404', $wp->query_vars['error'] );
		$this->assertSame( KKLPM_Domain_Router_Module::MATCHED_RULE, $wp->matched_rule );
	}

	/**
	 * Ensures ordinary requests stay untouched when no mapping matches.
	 *
	 * @return void
	 */
	public function test_parse_request_keeps_normal_request_when_no_mapping_matches() {
		$page_id = $this->create_published_page( 'normal-page' );

		$wp = new WP();
		$wp->request = 'normal-page';
		$wp->query_vars = array(
			'pagename' => 'normal-page',
		);

		$this->module->handle_parse_request( $wp );

		$this->assertArrayNotHasKey( 'page_id', $wp->query_vars );
		$this->assertSame( 'normal-page', $wp->query_vars['pagename'] );
		$this->assertNotEmpty( $page_id );
	}

	/**
	 * Ensures a subpath mapping does not hijack an existing native page path.
	 *
	 * @return void
	 */
	public function test_parse_request_keeps_native_page_when_subpath_mapping_collides() {
		$native_page_id = $this->create_published_page( 'promo' );
		$target_page_id = $this->create_published_page( 'mapped-target' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $target_page_id,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = 'promo';
		$wp->query_vars = array(
			'pagename' => 'promo',
		);

		$this->module->handle_parse_request( $wp );

		$this->assertArrayNotHasKey( 'page_id', $wp->query_vars );
		$this->assertSame( 'promo', $wp->query_vars['pagename'] );
		$this->assertNotSame( $target_page_id, $native_page_id );
	}

	/**
	 * Ensures a subpath mapping does not hijack a non-page post at the same path.
	 *
	 * @return void
	 */
	public function test_parse_request_keeps_native_post_when_subpath_mapping_collides_with_non_page_content() {
		$this->set_permalink_structure( '/%postname%/' );

		$native_post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Promo',
				'post_name'   => 'promo',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$target_page_id = $this->create_published_page( 'mapped-target' );

		KKLPM_Domain_Map_Repository::insert_mapping(
			array(
				'type'    => 'subpath',
				'value'   => '/promo',
				'page_id' => $target_page_id,
				'active'  => 1,
			)
		);

		$wp = new WP();
		$wp->request = 'promo';
		$wp->query_vars = array(
			'pagename' => 'promo',
		);

		$this->module->handle_parse_request( $wp );

		$this->assertArrayNotHasKey( 'page_id', $wp->query_vars );
		$this->assertSame( 'promo', $wp->query_vars['pagename'] );
		$this->assertNotSame( $target_page_id, $native_post_id );
	}

	/**
	 * Creates a published page with a stable slug.
	 *
	 * @param string $slug Desired page slug.
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
	 * Removes all rows from the plugin-owned custom table.
	 *
	 * @return void
	 */
	protected function truncate_domain_map_table() {
		global $wpdb;
		$table_name = KKLPM_Domain_Map_Repository::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
