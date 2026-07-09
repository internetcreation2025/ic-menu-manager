<?php
/**
 * Uninstall — intentionally conservative.
 *
 * v1 does NOT delete groups or assignments on uninstall, to avoid accidental
 * loss of a carefully built configuration. To fully remove IC Menu Manager data,
 * delete the options icmm_groups, icmm_role_groups, icmm_catalog and the user
 * meta icmm_group manually, or set ICMM_PURGE_ON_UNINSTALL true below.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$purge = defined( 'ICMM_PURGE_ON_UNINSTALL' ) && ICMM_PURGE_ON_UNINSTALL;

if ( $purge ) {
	delete_option( 'icmm_groups' );
	delete_option( 'icmm_role_groups' );
	delete_option( 'icmm_catalog' );
	delete_option( 'icmm_needs_seed' );
	delete_metadata( 'user', 0, 'icmm_group', '', true );
}
