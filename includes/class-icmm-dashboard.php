<?php
/**
 * Client dashboard (v1.3.0): per group, hide admin notices and replace the
 * default Dashboard with a greeting + shortcut tiles to the main sidebar sections.
 *
 * Tiles are built from the live $menu / $submenu at request time, which the
 * group has already pruned and ICMM_Order has already sorted, so a tile can
 * never point at a blocked page and always follows the site's Menu Order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Dashboard {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		// in_admin_header fires just before admin-header.php prints the notice hooks.
		add_action( 'in_admin_header', array( $this, 'maybe_hide_notices' ), PHP_INT_MAX );
		add_action( 'load-index.php', array( $this, 'maybe_render_dashboard' ) );
	}

	/* ---- Notices ---- */

	public function maybe_hide_notices() {
		$group = ICMM_Restrictions::instance()->current_group();
		if ( ! $group || empty( $group['hide_notices'] ) ) {
			return;
		}
		foreach ( array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' ) as $hook ) {
			remove_all_actions( $hook );
		}
		// Our own "no access" message still matters to the client.
		add_action( 'admin_notices', array( ICMM_Restrictions::instance(), 'blocked_notice' ) );
	}

	/* ---- Dashboard ---- */

	/**
	 * Replace the plain Dashboard (not index.php?page=… plugin screens) with the
	 * tile page, rendered between the standard admin header and footer.
	 */
	public function maybe_render_dashboard() {
		if ( ! empty( $_GET['page'] ) ) {
			return;
		}
		$group = ICMM_Restrictions::instance()->current_group();
		if ( ! $group || empty( $group['dashboard'] ) ) {
			return;
		}

		global $title, $parent_file;
		$title       = __( 'Dashboard', 'ic-menu-manager' );
		$parent_file = 'index.php';

		wp_enqueue_style( 'icmm-dashboard', ICMM_URL . 'assets/dashboard.css', array( 'dashicons' ), ICMM_VERSION );

		require_once ABSPATH . 'wp-admin/admin-header.php';
		$this->render( $group );
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	private function render( $group ) {
		$user = wp_get_current_user();
		$name = $user->first_name ? $user->first_name : $user->display_name;
		$hour = (int) current_time( 'G' );
		if ( $hour < 12 ) {
			$greeting = __( 'Good morning', 'ic-menu-manager' );
		} elseif ( $hour < 18 ) {
			$greeting = __( 'Good afternoon', 'ic-menu-manager' );
		} else {
			$greeting = __( 'Good evening', 'ic-menu-manager' );
		}

		$tiles = $this->tiles( (array) $group['dashboard_tiles'] );

		if ( ! empty( $group['hide_notices'] ) ) {
			// Catches notices that JavaScript injects after the page has loaded.
			echo '<style>#wpbody-content .notice:not(.icmm-notice),#wpbody-content .update-nag,#wpbody-content .updated,#wpbody-content .error{display:none!important}</style>';
		}

		echo '<div class="wrap icmm-dash">';
		echo '<header class="icmm-dash-head">';
		echo '<div class="icmm-dash-intro">';
		echo '<p class="icmm-dash-site">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		echo '<h1 class="icmm-dash-title">' . esc_html( $greeting . ', ' . $name ) . '</h1>';
		echo '</div>';
		echo '<a class="button icmm-dash-view" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'View website', 'ic-menu-manager' )
			. ' <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
		echo '</header>';
		echo '<hr class="wp-header-end">';

		if ( empty( $tiles ) ) {
			echo '<div class="icmm-dash-empty"><p>' . esc_html__( 'No shortcuts have been set up yet.', 'ic-menu-manager' ) . '</p></div>';
		} else {
			echo '<nav class="icmm-tiles" aria-label="' . esc_attr__( 'Shortcuts', 'ic-menu-manager' ) . '">';
			foreach ( $tiles as $i => $tile ) {
				echo '<a class="icmm-tile" href="' . esc_url( $tile['url'] ) . '" style="--icmm-i:' . (int) $i . '">';
				echo $this->icon_html( $tile['icon'] ); // Escaped inside icon_html().
				echo '<span class="icmm-tile-label">' . esc_html( $tile['title'] ) . '</span>';
				echo '<span class="dashicons dashicons-arrow-right-alt2 icmm-tile-arrow" aria-hidden="true"></span>';
				echo '</a>';
			}
			echo '</nav>';
		}

		echo '</div>';
	}

	/**
	 * The ticked sections this user can actually see, in live sidebar order.
	 *
	 * @param string[] $wanted Top-level menu slugs ticked for the group.
	 * @return array[] { title, url, icon }
	 */
	private function tiles( $wanted ) {
		global $menu, $submenu;
		$out = array();
		if ( empty( $wanted ) || ! is_array( $menu ) ) {
			return $out;
		}
		foreach ( $menu as $item ) {
			if ( empty( $item[2] ) || ! in_array( $item[2], $wanted, true ) ) {
				continue;
			}
			if ( ! empty( $item[1] ) && ! current_user_can( $item[1] ) ) {
				continue;
			}
			$url = $this->menu_url( $item[2], isset( $submenu[ $item[2] ] ) ? $submenu[ $item[2] ] : array() );
			if ( ! $url ) {
				continue;
			}
			$out[] = array(
				'title' => $this->clean_title( isset( $item[0] ) ? $item[0] : $item[2] ),
				'url'   => $url,
				'icon'  => isset( $item[6] ) ? (string) $item[6] : '',
			);
		}
		return $out;
	}

	/**
	 * Where the sidebar links a top-level item: its first accessible submenu
	 * item, else itself. Mirrors wp-admin/menu-header.php.
	 */
	private function menu_url( $slug, $subs ) {
		$target = $slug;
		$parent = 'admin.php';
		foreach ( (array) $subs as $sub ) {
			if ( ! empty( $sub[2] ) && ( empty( $sub[1] ) || current_user_can( $sub[1] ) ) ) {
				$target = $sub[2];
				$parent = $slug;
				break;
			}
		}

		if ( preg_match( '#^https?://#i', $target ) ) {
			return $target;
		}

		$file = strtok( $target, '?' );
		$hook = get_plugin_page_hook( $target, $parent );
		if ( $hook || ( 'index.php' !== $target && file_exists( WP_PLUGIN_DIR . '/' . $file ) && ! file_exists( ABSPATH . 'wp-admin/' . $file ) ) ) {
			return admin_url( 'admin.php?page=' . $target );
		}
		return admin_url( $target );
	}

	/** The section's own sidebar icon: a dashicon, an inline SVG or an image URL. */
	private function icon_html( $icon ) {
		if ( 0 === strpos( $icon, 'dashicons-' ) ) {
			return '<span class="icmm-tile-icon"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span></span>';
		}
		if ( preg_match( '#^data:image/svg\+xml;base64,[A-Za-z0-9+/=]+$#', $icon ) ) {
			// Used as a mask so every SVG takes the tile's accent colour.
			return '<span class="icmm-tile-icon"><span class="icmm-tile-svg" style="--icmm-svg:url(' . esc_attr( $icon ) . ')" aria-hidden="true"></span></span>';
		}
		if ( preg_match( '#^https?://#i', $icon ) ) {
			return '<span class="icmm-tile-icon"><img src="' . esc_url( $icon ) . '" alt=""></span>';
		}
		return '<span class="icmm-tile-icon"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span></span>';
	}

	/** Strip WordPress count bubbles / markup from a menu title. */
	private function clean_title( $title ) {
		$title = preg_replace( '/<span[^>]*>.*?<\/span>/is', '', (string) $title );
		$title = wp_strip_all_tags( $title );
		return trim( html_entity_decode( $title, ENT_QUOTES ) );
	}
}
