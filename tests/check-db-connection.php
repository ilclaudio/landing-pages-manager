<?php
/**
 * Diagnostic script to verify direct DB connectivity for integration tests.
 *
 * Not a PHPUnit test. Run it manually only when you need to validate the
 * database credentials used for the WordPress integration suite.
 *
 * @package LandingPageManager
 */

$db_host     = getenv( 'KKLPM_TEST_DB_HOST' ) ?: '127.0.0.1';
$db_user     = getenv( 'KKLPM_TEST_DB_USER' ) ?: 'root';
$db_password = getenv( 'KKLPM_TEST_DB_PASSWORD' ) ?: 'root';
$db_name     = getenv( 'KKLPM_TEST_DB_NAME' ) ?: 'kklpm_wordpress_test';

$link = mysqli_connect( $db_host, $db_user, $db_password, $db_name );

if ( ! $link ) {
	die( 'Database connection failed: ' . mysqli_connect_error() );
}

echo "Database connection successful.\n";
mysqli_close( $link );
