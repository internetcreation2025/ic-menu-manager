<?php
/**
 * Builds the ready-made "IC Client" group. Core items are blocked by their stable
 * slug/id; plugin-specific items (Novamira, HustleWP, ACF) are resolved by title
 * against the live catalog so absent plugins are simply skipped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Seed {

	const GROUP_ID   = 'ic-client';
	const GROUP_NAME = 'IC Client';

	/**
	 * Apply (or repair) the IC Client group using the given catalog.
	 * Does not overwrite the group if an admin has already customised its name.
	 */
	public static function apply( $catalog ) {
		$groups = ICMM_Groups::all();

		// If it exists and was renamed by a human, leave it alone.
		if ( isset( $groups[ self::GROUP_ID ] ) && self::GROUP_NAME !== ( $groups[ self::GROUP_ID ]['name'] ?? '' ) ) {
			return;
		}

		$catalog = wp_parse_args(
			is_array( $catalog ) ? $catalog : array(),
			array( 'menu' => array(), 'submenu' => array(), 'adminbar' => array() )
		);

		$block_menu     = array();
		$block_submenu  = array();
		$block_adminbar = array();

		// --- Sidebar: stable core slugs ---
		$block_menu[] = 'plugins.php';        // Plugins
		$block_menu[] = 'options-general.php'; // Settings (+ submenus cascade at runtime)

		// Appearance > Theme File Editor (submenu of themes.php).
		$block_submenu['themes.php'] = array( 'theme-editor.php' );

		// --- Sidebar: plugin items resolved by title ---
		foreach ( array( 'Novamira', 'Hustle', 'ACF' ) as $needle ) {
			$slug = self::find_menu_slug( $catalog['menu'], $needle );
			if ( $slug ) {
				$block_menu[] = $slug;
			}
		}
		// ACF sometimes labels its top menu "Custom Fields" instead of "ACF".
		if ( ! self::find_menu_slug( $catalog['menu'], 'ACF' ) ) {
			$slug = self::find_menu_slug( $catalog['menu'], 'Custom Fields' );
			if ( $slug ) {
				$block_menu[] = $slug;
			}
		}

		// --- Admin bar ---
		$block_adminbar[] = 'wp-logo'; // WordPress logo + its dropdown
		$block_adminbar[] = 'updates';  // Update-core icon
		$novamira_node = self::find_adminbar_id( $catalog['adminbar'], 'Novamira' );
		if ( $novamira_node ) {
			$block_adminbar[] = $novamira_node;
		}

		$groups[ self::GROUP_ID ] = ICMM_Groups::normalise( array(
			'name'           => self::GROUP_NAME,
			'block_menu'     => array_values( array_unique( $block_menu ) ),
			'block_submenu'  => $block_submenu,
			'block_adminbar' => array_values( array_unique( $block_adminbar ) ),
		) );

		update_option( ICMM_Groups::OPT_GROUPS, $groups, false );
	}

	private static function find_menu_slug( $menu, $needle ) {
		foreach ( (array) $menu as $item ) {
			if ( isset( $item['title'] ) && false !== stripos( $item['title'], $needle ) ) {
				return $item['slug'];
			}
		}
		return '';
	}

	private static function find_adminbar_id( $nodes, $needle ) {
		foreach ( (array) $nodes as $node ) {
			$hay = ( isset( $node['title'] ) ? $node['title'] : '' ) . ' ' . ( isset( $node['id'] ) ? $node['id'] : '' );
			if ( false !== stripos( $hay, $needle ) ) {
				return $node['id'];
			}
		}
		return '';
	}
}
