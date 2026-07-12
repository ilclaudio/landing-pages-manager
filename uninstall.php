<?php
/**
 * Uninstall routine.
 *
 * Removes capabilities granted by the plugin from all roles that hold them.
 *
 * @package LandingPageManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$kklpm_uninstall_capabilities = array(
	'kklpm_manage_landing_pages',
	'kklpm_manage_domain_router',
);

foreach ( array_keys( wp_roles()->role_names ) as $kklpm_uninstall_role_slug ) {
	$kklpm_uninstall_role = get_role( $kklpm_uninstall_role_slug );

	if ( ! $kklpm_uninstall_role ) {
		continue;
	}

	foreach ( $kklpm_uninstall_capabilities as $kklpm_uninstall_capability ) {
		if ( $kklpm_uninstall_role->has_cap( $kklpm_uninstall_capability ) ) {
			$kklpm_uninstall_role->remove_cap( $kklpm_uninstall_capability );
		}
	}
}
