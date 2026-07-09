<?php
/**
 * Captures the live wp-admin sidebar menu and top admin-bar nodes into an option
 * so the group builder can present a real, current checklist of items to block.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Catalog {

	const OPTION = 'icmm_catalog';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		// Late so every plugin has registered its menus/nodes first.
		add_action( 'admin_menu', array( $this, 'capture_menu' ), 9998 );
		add_action( 'admin_bar_menu', array( $this, 'capture_admin_bar' ), 99998 );
		add_action( 'shutdown', array( $this, 'persist' ) );
	}

	/** Working copy assembled during the request, flushed on shutdown. */
	private $catalog = null;

	/**
	 * Only unrestricted managers (full admins with no group) capture the catalog,
	 * so we always store the complete, un-pruned menu — never a restricted subset.
	 */
	private function should_capture() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return '' === ICMM_Restrictions::instance()->effective_group_id( get_current_user_id() );
	}

	public function capture_menu() {
		if ( ! $this->should_capture() ) {
			return;
		}
		global $menu, $submenu;

		$data = array( 'menu' => array(), 'submenu' => array() );

		foreach ( (array) $menu as $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}
			// Skip separators.
			if ( ! empty( $item[4] ) && false !== strpos( $item[4], 'wp-menu-separator' ) ) {
				continue;
			}
			$slug  = $item[2];
			$title = $this->clean_title( isset( $item[0] ) ? $item[0] : $slug );
			if ( '' === $title ) {
				$title = $slug;
			}
			$data['menu'][] = array( 'slug' => $slug, 'title' => $title );

			if ( ! empty( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $sub ) {
					if ( empty( $sub[2] ) ) {
						continue;
					}
					$data['submenu'][ $slug ][] = array(
						'slug'  => $sub[2],
						'title' => $this->clean_title( isset( $sub[0] ) ? $sub[0] : $sub[2] ),
					);
				}
			}
		}

		$this->catalog                 = is_array( $this->catalog ) ? $this->catalog : array();
		$this->catalog['menu']         = $data['menu'];
		$this->catalog['submenu']      = $data['submenu'];
	}

	public function capture_admin_bar( $wp_admin_bar ) {
		if ( ! $this->should_capture() ) {
			return;
		}
		$nodes = $wp_admin_bar->get_nodes();
		if ( empty( $nodes ) ) {
			return;
		}

		$top = array();
		foreach ( $nodes as $node ) {
			// Only offer top-level nodes as choices; blocking one removes its children too.
			if ( ! empty( $node->parent ) ) {
				continue;
			}
			$id = $node->id;
			$title = $this->clean_title( isset( $node->title ) ? $node->title : '' );
			if ( '' === $title ) {
				$title = $this->admin_bar_label( $id );
			}
			$top[] = array( 'id' => $id, 'title' => $title );
		}

		$this->catalog            = is_array( $this->catalog ) ? $this->catalog : array();
		$this->catalog['adminbar'] = $top;
	}

	/** Persist once per request if anything was captured and it actually changed. */
	public function persist() {
		if ( ! is_array( $this->catalog ) ) {
			return;
		}
		$existing = get_option( self::OPTION, array() );
		if ( md5( maybe_serialize( $existing ) ) === md5( maybe_serialize( $this->catalog ) ) ) {
			return;
		}
		update_option( self::OPTION, $this->catalog, false );

		// The seed needs a live catalog; apply it now that we have one.
		if ( get_option( 'icmm_needs_seed' ) ) {
			ICMM_Seed::apply( $this->catalog );
			delete_option( 'icmm_needs_seed' );
		}
	}

	public static function get() {
		$catalog = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $catalog ) ? $catalog : array(),
			array( 'menu' => array(), 'submenu' => array(), 'adminbar' => array() )
		);
	}

	/** Strip WordPress count bubbles / markup from a menu title. */
	private function clean_title( $title ) {
		$title = preg_replace( '/<span[^>]*>.*?<\/span>/is', '', (string) $title );
		$title = wp_strip_all_tags( $title );
		return trim( html_entity_decode( $title, ENT_QUOTES ) );
	}

	/** Friendly fallbacks for admin-bar nodes that render as icons with no text. */
	private function admin_bar_label( $id ) {
		$map = array(
			'wp-logo'      => 'WordPress Logo',
			'updates'      => 'Updates',
			'comments'     => 'Comments',
			'new-content'  => 'New',
			'my-account'   => 'My Account',
			'site-name'    => 'Site Name',
			'search'       => 'Search',
			'customize'    => 'Customize',
			'menu-toggle'  => 'Menu Toggle',
		);
		return isset( $map[ $id ] ) ? $map[ $id ] : $id;
	}
}
