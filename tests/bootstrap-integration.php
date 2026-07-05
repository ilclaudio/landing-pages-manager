<?php
/**
 * Integration tests bootstrap.
 *
 * @package LandingPageManager
 */

// Get the WordPress tests framework directory.
$kklpm_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $kklpm_tests_dir ) {
	$kklpm_tests_dir = 'C:/WordpressDEV/wordpress-develop/tests/phpunit';
}

// Verify the path exists.
if ( ! file_exists( $kklpm_tests_dir . '/includes/functions.php' ) ) {
	die( "WordPress test library not found at: {$kklpm_tests_dir}\n" );
}

// Give access to tests_add_filter() function.
require_once $kklpm_tests_dir . '/includes/functions.php';

if ( ! class_exists( 'ACF' ) ) {
	/**
	 * Minimal ACF marker class for integration tests.
	 */
	class ACF {}
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * Minimal get_field() shim for integration tests.
	 *
	 * @param string $field_name Field name.
	 * @param int    $post_id    Post ID.
	 * @return mixed
	 */
	function get_field( $field_name, $post_id ) {
		return get_post_meta( $post_id, $field_name, true );
	}
}

if ( ! function_exists( 'update_field' ) ) {
	/**
	 * Minimal update_field() shim for integration tests.
	 *
	 * Mimics ACF's no-op false return when the stored value is unchanged.
	 *
	 * @param string $field_name Field name.
	 * @param mixed  $value      Value to store.
	 * @param int    $post_id    Post ID.
	 * @return bool
	 */
	function update_field( $field_name, $value, $post_id ) {
		$current = get_post_meta( $post_id, $field_name, true );
		if ( $current === $value ) {
			return false;
		}

		return false !== update_post_meta( $post_id, $field_name, $value );
	}
}

if ( ! function_exists( 'get_field_objects' ) ) {
	/**
	 * Minimal get_field_objects() shim for integration tests.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	function get_field_objects( $post_id ) {
		unset( $post_id );
		return array();
	}
}

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	/**
	 * Minimal acf_add_local_field_group() shim for integration tests.
	 *
	 * @param array $field_group Field group definition.
	 * @return void
	 */
	function acf_add_local_field_group( $field_group ) {
		unset( $field_group );
	}
}

/**
 * Manually load the plugin being tested.
 *
 * @return void
 */
function kklpm_manually_load_plugin() {
	$plugin_main_file = dirname( __DIR__ ) . '/landing-pages-manager.php';

	if ( ! file_exists( $plugin_main_file ) ) {
		die( "Plugin bootstrap not found at: {$plugin_main_file}\n" );
	}

	require $plugin_main_file;

	// Mirror activation: the Domain Router table must exist for every test,
	// not only for test classes that create it explicitly, since
	// KKLPM_Domain_Router_Module queries it on every parse_request.
	KKLPM_Domain_Map_Repository::create_table();
}

// Load the plugin before WordPress boots fully.
tests_add_filter( 'muplugins_loaded', 'kklpm_manually_load_plugin' );

// Start up the WP testing environment.
require $kklpm_tests_dir . '/includes/bootstrap.php';
