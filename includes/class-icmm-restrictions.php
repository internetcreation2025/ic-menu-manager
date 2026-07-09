<?php
/**
 * Runtime enforcement: for a user with an effective group, remove the blocked
 * sidebar items and admin-bar nodes AND block direct-URL access to blocked pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Restrictions {

	private static $instance = null;
	private $cache = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'strip_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'guard_access' ), 1 );
		add_action( 'admin_bar_menu', array( $this, 'strip_admin_bar' ), 999 );
		add_action( 'admin_notices', array( $this, 'blocked_notice' ) );
	}

	/** Master kill-switch. */
	public function safe_mode() {
		return defined( 'ICMM_SAFE_MODE' ) && ICMM_SAFE_MODE;
	}

	/**
	 * The group that actually applies to a user: their own assignment wins,
	 * otherwise the first of their roles that has a group. '' means unrestricted.
	 */
	public function effective_group_id( $user_id ) {
		if ( $this->safe_mode() ) {
			return '';
		}
		$user_id = (int) $user_id;
		if ( isset( $this->cache[ $user_id ] ) ) {
			return $this->cache[ $user_id ];
		}

		$gid = '';
		if ( $user_id ) {
			$own = ICMM_Groups::user_group( $user_id );
			if ( $own && ICMM_Groups::get( $own ) ) {
				$gid = $own;
			} else {
				$user = get_userdata( $user_id );
				if ( $user ) {
					$role_groups = ICMM_Groups::role_groups();
					foreach ( (array) $user->roles as $role ) {
						if ( ! empty( $role_groups[ $role ] ) && ICMM_Groups::get( $role_groups[ $role ] ) ) {
							$gid = $role_groups[ $role ];
							break;
						}
					}
				}
			}
		}

		$this->cache[ $user_id ] = $gid;
		return $gid;
	}

	private function current_group() {
		$gid = $this->effective_group_id( get_current_user_id() );
		return $gid ? ICMM_Groups::get( $gid ) : null;
	}

	/** All page slugs to guard for a group: blocked tops + their submenus + explicit submenus. */
	private function blocked_page_slugs( $group ) {
		$catalog = ICMM_Catalog::get();
		$slugs   = array();

		foreach ( (array) $group['block_menu'] as $top ) {
			$slugs[] = $top;
			if ( ! empty( $catalog['submenu'][ $top ] ) ) {
				foreach ( $catalog['submenu'][ $top ] as $sub ) {
					$slugs[] = $sub['slug'];
				}
			}
		}
		foreach ( (array) $group['block_submenu'] as $subs ) {
			foreach ( (array) $subs as $s ) {
				$slugs[] = $s;
			}
		}
		// A restricted user must never reach this plugin's own settings page.
		$slugs[] = ICMM_Admin::SLUG;

		return array_values( array_unique( $slugs ) );
	}

	/* ---- Sidebar ---- */

	public function strip_menu() {
		$group = $this->current_group();
		if ( ! $group ) {
			return;
		}
		foreach ( (array) $group['block_menu'] as $slug ) {
			remove_menu_page( $slug );
		}
		foreach ( (array) $group['block_submenu'] as $parent => $subs ) {
			foreach ( (array) $subs as $s ) {
				remove_submenu_page( $parent, $s );
			}
		}
	}

	/* ---- Access guard ---- */

	public function guard_access() {
		if ( wp_doing_ajax() ) {
			return;
		}
		$group = $this->current_group();
		if ( ! $group ) {
			return;
		}

		$blocked = $this->blocked_page_slugs( $group );
		foreach ( $blocked as $slug ) {
			if ( $this->request_matches( $slug ) ) {
				$landing = in_array( 'index.php', $blocked, true ) ? 'profile.php' : 'index.php';
				wp_safe_redirect( add_query_arg( 'icmm_blocked', '1', admin_url( $landing ) ) );
				exit;
			}
		}
	}

	/** Does the current admin request correspond to this menu slug? */
	private function request_matches( $slug ) {
		global $pagenow;
		$page = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';

		// Slug carries a query string, e.g. edit.php?post_type=acf-field-group.
		if ( false !== strpos( $slug, '?' ) ) {
			list( $file, $qs ) = explode( '?', $slug, 2 );
			if ( $pagenow !== $file ) {
				return false;
			}
			parse_str( $qs, $args );
			foreach ( $args as $k => $v ) {
				if ( ! isset( $_GET[ $k ] ) || (string) wp_unslash( $_GET[ $k ] ) !== (string) $v ) {
					return false;
				}
			}
			return true;
		}

		// Plugin page: admin.php?page=slug (or tools.php?page=slug, etc.).
		if ( '' !== $page ) {
			return $page === $slug;
		}

		// File-based slug: plugins.php, options-general.php, theme-editor.php…
		if ( $pagenow === $slug ) {
			if ( 'edit.php' === $slug && ! empty( $_GET['post_type'] ) && 'post' !== $_GET['post_type'] ) {
				return false;
			}
			return true;
		}

		return false;
	}

	public function blocked_notice() {
		if ( empty( $_GET['icmm_blocked'] ) ) {
			return;
		}
		echo '<div class="notice notice-error is-dismissible"><p>'
			. esc_html__( 'You do not have permission to access that page.', 'ic-menu-manager' )
			. '</p></div>';
	}

	/* ---- Admin bar ---- */

	public function strip_admin_bar( $wp_admin_bar ) {
		$group = $this->current_group();
		if ( ! $group ) {
			return;
		}
		foreach ( (array) $group['block_adminbar'] as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}
	}
}
