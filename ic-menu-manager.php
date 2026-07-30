<?php
/**
 * Plugin Name:       IC Menu Manager
 * Plugin URI:        https://github.com/internetcreation2025/ic-menu-manager
 * Description:        Control what users see and can access in the wp-admin sidebar menu and the top admin bar. Build reusable Menu Groups (block-lists) and assign them to users and roles.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Internet Creation
 * Author URI:        https://internetcreation.net
 * License:           GPL-2.0-or-later
 * Text Domain:       ic-menu-manager
 *
 * Safety kill-switch: define('ICMM_SAFE_MODE', true); in wp-config.php disables all restrictions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ICMM_VERSION', '1.1.2' );
define( 'ICMM_FILE', __FILE__ );
define( 'ICMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICMM_URL', plugin_dir_url( __FILE__ ) );
define( 'ICMM_BASENAME', plugin_basename( __FILE__ ) );

require_once ICMM_DIR . 'includes/class-icmm-catalog.php';
require_once ICMM_DIR . 'includes/class-icmm-groups.php';
require_once ICMM_DIR . 'includes/class-icmm-seed.php';
require_once ICMM_DIR . 'includes/class-icmm-restrictions.php';
require_once ICMM_DIR . 'includes/class-icmm-admin.php';
require_once ICMM_DIR . 'includes/class-icmm-updater.php';

/**
 * Boot the plugin once WordPress has loaded.
 */
function icmm_boot() {
	// Catalog capture runs on every admin load so the builder always reflects the live menus.
	ICMM_Catalog::instance()->hooks();

	// Runtime restrictions (hide + block) for the current user.
	ICMM_Restrictions::instance()->hooks();

	// GitHub Releases auto-updater (hooked always, so cron update checks work too).
	( new ICMM_Updater( ICMM_FILE, ICMM_VERSION ) )->hooks();

	// Admin UI (only relevant in wp-admin).
	if ( is_admin() ) {
		ICMM_Admin::instance()->hooks();
	}
}
add_action( 'plugins_loaded', 'icmm_boot' );

/**
 * On activation, flag that the seeded "IC Client" group should be (re)built on the
 * next admin load, when the live catalog is available to resolve plugin-specific items.
 */
function icmm_activate() {
	if ( false === get_option( 'icmm_groups', false ) ) {
		add_option( 'icmm_groups', array() );
	}
	if ( false === get_option( 'icmm_role_groups', false ) ) {
		add_option( 'icmm_role_groups', array() );
	}
	// Defer the actual seed until the catalog exists (first admin page load).
	update_option( 'icmm_needs_seed', 1, false );
}
register_activation_hook( __FILE__, 'icmm_activate' );
