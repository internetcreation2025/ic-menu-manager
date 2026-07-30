<?php
/**
 * Admin UI: the Menu Manager page (Groups builder + Assignments).
 * Only registered for unrestricted managers; restricted users never see it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Admin {

	const SLUG = 'ic-menu-manager';
	const CAP  = 'manage_options';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_icmm_save_group', array( $this, 'handle_save_group' ) );
		add_action( 'admin_post_icmm_delete_group', array( $this, 'handle_delete_group' ) );
		add_action( 'admin_post_icmm_save_roles', array( $this, 'handle_save_roles' ) );
		add_action( 'admin_post_icmm_save_user', array( $this, 'handle_save_user' ) );
		add_action( 'admin_post_icmm_bulk_assign', array( $this, 'handle_bulk_assign' ) );
		add_filter( 'plugin_action_links_' . ICMM_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_content' ), 10, 3 );
	}

	private function can_manage() {
		return current_user_can( self::CAP )
			&& '' === ICMM_Restrictions::instance()->effective_group_id( get_current_user_id() );
	}

	public function register_page() {
		if ( ! $this->can_manage() ) {
			return;
		}
		add_menu_page(
			__( 'Menu Manager', 'ic-menu-manager' ),
			__( 'Menu Manager', 'ic-menu-manager' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-menu-alt',
			80
		);
	}

	public function action_links( $links ) {
		if ( $this->can_manage() ) {
			$url = admin_url( 'admin.php?page=' . self::SLUG );
			array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ic-menu-manager' ) . '</a>' );
		}
		return $links;
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'icmm-admin', ICMM_URL . 'assets/admin.css', array(), ICMM_VERSION );
		wp_enqueue_script( 'icmm-admin', ICMM_URL . 'assets/admin.js', array(), ICMM_VERSION, true );
	}

	/* ---------------------------------------------------------------------
	 * Users list column
	 * ------------------------------------------------------------------- */

	public function users_column( $columns ) {
		if ( $this->can_manage() ) {
			$columns['icmm_group'] = __( 'Menu Group', 'ic-menu-manager' );
		}
		return $columns;
	}

	public function users_column_content( $output, $column, $user_id ) {
		if ( 'icmm_group' !== $column || ! $this->can_manage() ) {
			return $output;
		}
		$url = add_query_arg( array( 'page' => self::SLUG, 'tab' => 'assignments' ), admin_url( 'admin.php' ) );

		// Direct per-user assignment wins.
		$direct = ICMM_Groups::user_group( $user_id );
		if ( $direct ) {
			$g = ICMM_Groups::get( $direct );
			if ( $g ) {
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $g['name'] ) . '</a>';
			}
		}

		// Otherwise show the group inherited from the first matching role.
		$user = get_userdata( $user_id );
		if ( $user ) {
			$role_groups = ICMM_Groups::role_groups();
			foreach ( (array) $user->roles as $role ) {
				if ( ! empty( $role_groups[ $role ] ) ) {
					$g = ICMM_Groups::get( $role_groups[ $role ] );
					if ( $g ) {
						return '<a href="' . esc_url( $url ) . '">' . esc_html( $g['name'] ) . '</a> <span class="description">'
							. esc_html__( '(via role)', 'ic-menu-manager' ) . '</span>';
					}
				}
			}
		}

		return '<span class="description">&mdash;</span>';
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------- */

	private function verify( $action ) {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ic-menu-manager' ) );
		}
		check_admin_referer( $action );
	}

	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save_group() {
		$this->verify( 'icmm_save_group' );
		$id  = isset( $_POST['group_id'] ) ? sanitize_key( wp_unslash( $_POST['group_id'] ) ) : '';
		$raw = array(
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'block_menu'     => isset( $_POST['block_menu'] ) ? (array) wp_unslash( $_POST['block_menu'] ) : array(),
			'block_submenu'  => isset( $_POST['block_submenu'] ) ? (array) wp_unslash( $_POST['block_submenu'] ) : array(),
			'block_adminbar' => isset( $_POST['block_adminbar'] ) ? (array) wp_unslash( $_POST['block_adminbar'] ) : array(),
		);
		if ( '' === trim( $raw['name'] ) ) {
			$this->redirect( array( 'tab' => 'groups', 'action' => 'new', 'icmm_status' => 'noname' ) );
		}
		$id = ICMM_Groups::save( $id, $raw );
		$this->redirect( array( 'tab' => 'groups', 'icmm_status' => 'saved' ) );
	}

	public function handle_delete_group() {
		$this->verify( 'icmm_delete_group' );
		$id = isset( $_POST['group_id'] ) ? sanitize_key( wp_unslash( $_POST['group_id'] ) ) : '';
		if ( $id ) {
			ICMM_Groups::delete( $id );
		}
		$this->redirect( array( 'tab' => 'groups', 'icmm_status' => 'deleted' ) );
	}

	public function handle_save_roles() {
		$this->verify( 'icmm_save_roles' );
		$map = isset( $_POST['role_group'] ) ? (array) wp_unslash( $_POST['role_group'] ) : array();
		foreach ( self::roles() as $role => $label ) {
			$gid = isset( $map[ $role ] ) ? sanitize_key( $map[ $role ] ) : '';
			ICMM_Groups::set_role_group( $role, $gid );
		}
		$this->redirect( array( 'tab' => 'assignments', 'icmm_status' => 'roles' ) );
	}

	public function handle_save_user() {
		$this->verify( 'icmm_save_user' );
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$gid     = isset( $_POST['group_id'] ) ? sanitize_key( wp_unslash( $_POST['group_id'] ) ) : '';
		if ( $user_id ) {
			ICMM_Groups::set_user_group( $user_id, $gid );
		}
		$this->redirect( array( 'tab' => 'assignments', 'icmm_status' => 'user' ) );
	}

	public function handle_bulk_assign() {
		$this->verify( 'icmm_bulk_assign' );
		$ids = isset( $_POST['user_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['user_ids'] ) ) : array();
		$gid = isset( $_POST['group_id'] ) ? sanitize_key( wp_unslash( $_POST['group_id'] ) ) : '';
		$count = 0;
		foreach ( $ids as $uid ) {
			if ( $uid ) {
				ICMM_Groups::set_user_group( $uid, $gid );
				$count++;
			}
		}
		$this->redirect( array( 'tab' => 'assignments', 'icmm_status' => ( '' === $gid ? 'bulk_removed' : 'bulk' ), 'n' => $count ) );
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	public static function roles() {
		$roles = wp_roles()->get_names();
		$out   = array();
		foreach ( $roles as $key => $name ) {
			$out[ $key ] = translate_user_role( $name );
		}
		return $out;
	}

	public function render() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'ic-menu-manager' ) );
		}
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'groups';
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		echo '<div class="wrap icmm-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Menu Manager', 'ic-menu-manager' ) . '</h1>';
		$this->notices();

		echo '<nav class="nav-tab-wrapper icmm-tabs">';
		$this->tab_link( 'groups', __( 'Groups', 'ic-menu-manager' ), $tab );
		$this->tab_link( 'assignments', __( 'Assignments', 'ic-menu-manager' ), $tab );
		echo '</nav>';

		if ( 'assignments' === $tab ) {
			$this->render_assignments();
		} elseif ( 'new' === $action || 'edit' === $action ) {
			$this->render_builder();
		} else {
			$this->render_group_list();
		}

		echo '</div>';
	}

	private function tab_link( $slug, $label, $current ) {
		$url   = add_query_arg( array( 'page' => self::SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
		$class = 'nav-tab' . ( $current === $slug ? ' nav-tab-active' : '' );
		printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
	}

	private function notices() {
		$status = isset( $_GET['icmm_status'] ) ? sanitize_key( $_GET['icmm_status'] ) : '';
		$map    = array(
			'saved'   => array( 'success', __( 'Group saved.', 'ic-menu-manager' ) ),
			'deleted' => array( 'success', __( 'Group deleted.', 'ic-menu-manager' ) ),
			'roles'   => array( 'success', __( 'Role assignments saved.', 'ic-menu-manager' ) ),
			'user'    => array( 'success', __( 'User assignment saved.', 'ic-menu-manager' ) ),
			'noname'  => array( 'error', __( 'Please give the group a name.', 'ic-menu-manager' ) ),
		);
		if ( isset( $map[ $status ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $status ][0] ), esc_html( $map[ $status ][1] ) );
		} elseif ( 'bulk' === $status || 'bulk_removed' === $status ) {
			$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0;
			$msg = 'bulk_removed' === $status
				/* translators: %d: number of users */
				? sprintf( _n( 'Group removed from %d user.', 'Group removed from %d users.', $n, 'ic-menu-manager' ), $n )
				/* translators: %d: number of users */
				: sprintf( _n( 'Group assigned to %d user.', 'Group assigned to %d users.', $n, 'ic-menu-manager' ), $n );
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
		}
	}

	private function new_url() {
		return add_query_arg( array( 'page' => self::SLUG, 'tab' => 'groups', 'action' => 'new' ), admin_url( 'admin.php' ) );
	}

	private function edit_url( $id ) {
		return add_query_arg( array( 'page' => self::SLUG, 'tab' => 'groups', 'action' => 'edit', 'group' => $id ), admin_url( 'admin.php' ) );
	}

	private function render_group_list() {
		$groups = ICMM_Groups::all();
		echo '<a href="' . esc_url( $this->new_url() ) . '" class="page-title-action">' . esc_html__( 'Add Group', 'ic-menu-manager' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'A group is a block-list: tick the items to hide and block. Assign a group to users or roles on the Assignments tab.', 'ic-menu-manager' ) . '</p>';

		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'No groups yet.', 'ic-menu-manager' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Group', 'ic-menu-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Blocked items', 'ic-menu-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Assigned to', 'ic-menu-manager' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'ic-menu-manager' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $groups as $id => $g ) {
			$blocked = count( (array) $g['block_menu'] )
				+ array_sum( array_map( 'count', (array) $g['block_submenu'] ) )
				+ count( (array) $g['block_adminbar'] );
			$counts  = ICMM_Groups::assignment_count( $id );
			$assigned = sprintf(
				/* translators: 1: user count, 2: role count */
				__( '%1$d users, %2$d roles', 'ic-menu-manager' ),
				$counts['users'],
				$counts['roles']
			);

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $this->edit_url( $id ) ) . '">' . esc_html( $g['name'] ) . '</a></strong></td>';
			echo '<td>' . esc_html( (string) $blocked ) . '</td>';
			echo '<td>' . esc_html( $assigned ) . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( $this->edit_url( $id ) ) . '">' . esc_html__( 'Edit', 'ic-menu-manager' ) . '</a> | ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="icmm-inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete this group?', 'ic-menu-manager' ) ) . '\');">';
			wp_nonce_field( 'icmm_delete_group' );
			echo '<input type="hidden" name="action" value="icmm_delete_group">';
			echo '<input type="hidden" name="group_id" value="' . esc_attr( $id ) . '">';
			echo '<button type="submit" class="button-link icmm-delete">' . esc_html__( 'Delete', 'ic-menu-manager' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_builder() {
		$id    = isset( $_GET['group'] ) ? sanitize_key( $_GET['group'] ) : '';
		$group = $id ? ICMM_Groups::get( $id ) : null;
		$group = $group ? $group : array( 'name' => '', 'block_menu' => array(), 'block_submenu' => array(), 'block_adminbar' => array() );
		$catalog = ICMM_Catalog::get();

		$is_new = ! $id || ! ICMM_Groups::get( $id );
		echo '<h2>' . esc_html( $is_new ? __( 'Add Group', 'ic-menu-manager' ) : __( 'Edit Group', 'ic-menu-manager' ) ) . '</h2>';

		if ( empty( $catalog['menu'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'The menu catalog has not been captured yet. Visit any wp-admin page as a full administrator, then reload this page.', 'ic-menu-manager' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="icmm-builder">';
		wp_nonce_field( 'icmm_save_group' );
		echo '<input type="hidden" name="action" value="icmm_save_group">';
		echo '<input type="hidden" name="group_id" value="' . esc_attr( $id ) . '">';

		echo '<table class="form-table"><tr>';
		echo '<th scope="row"><label for="icmm-name">' . esc_html__( 'Group name', 'ic-menu-manager' ) . '</label></th>';
		echo '<td><input name="name" id="icmm-name" type="text" class="regular-text" value="' . esc_attr( $group['name'] ) . '" required></td>';
		echo '</tr></table>';

		echo '<div class="icmm-panels">';

		/* Panel A: sidebar */
		echo '<div class="icmm-panel">';
		echo '<h3>' . esc_html__( 'Admin Sidebar', 'ic-menu-manager' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Tick a top-level item to block the whole menu, or tick individual sub-items.', 'ic-menu-manager' ) . '</p>';

		foreach ( (array) $catalog['menu'] as $item ) {
			$slug        = $item['slug'];
			$top_checked = in_array( $slug, (array) $group['block_menu'], true );
			echo '<div class="icmm-menu-block">';
			echo '<label class="icmm-top"><input type="checkbox" class="icmm-top-cb" name="block_menu[]" value="' . esc_attr( $slug ) . '" ' . checked( $top_checked, true, false ) . '> <strong>' . esc_html( $item['title'] ) . '</strong></label>';

			if ( ! empty( $catalog['submenu'][ $slug ] ) ) {
				echo '<div class="icmm-subs">';
				foreach ( $catalog['submenu'][ $slug ] as $sub ) {
					$sub_checked = isset( $group['block_submenu'][ $slug ] ) && in_array( $sub['slug'], (array) $group['block_submenu'][ $slug ], true );
					echo '<label class="icmm-sub"><input type="checkbox" name="block_submenu[' . esc_attr( $slug ) . '][]" value="' . esc_attr( $sub['slug'] ) . '" ' . checked( $sub_checked, true, false ) . ( $top_checked ? ' disabled' : '' ) . '> ' . esc_html( $sub['title'] ) . '</label>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		/* Panel B: admin bar */
		echo '<div class="icmm-panel">';
		echo '<h3>' . esc_html__( 'Admin Bar (top)', 'ic-menu-manager' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Tick items to remove from the top admin bar.', 'ic-menu-manager' ) . '</p>';
		if ( empty( $catalog['adminbar'] ) ) {
			echo '<p>' . esc_html__( 'No admin-bar items captured yet.', 'ic-menu-manager' ) . '</p>';
		}
		foreach ( (array) $catalog['adminbar'] as $node ) {
			$checked = in_array( $node['id'], (array) $group['block_adminbar'], true );
			echo '<label class="icmm-bar"><input type="checkbox" name="block_adminbar[]" value="' . esc_attr( $node['id'] ) . '" ' . checked( $checked, true, false ) . '> ' . esc_html( $node['title'] ) . ' <code>' . esc_html( $node['id'] ) . '</code></label>';
		}
		echo '</div>';

		echo '</div>'; // panels

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Group', 'ic-menu-manager' ) . '</button> ';
		echo '<a href="' . esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => 'groups' ), admin_url( 'admin.php' ) ) ) . '" class="button">' . esc_html__( 'Cancel', 'ic-menu-manager' ) . '</a></p>';
		echo '</form>';
	}

	private function render_assignments() {
		$groups = ICMM_Groups::all();
		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'Create a group first, then assign it here.', 'ic-menu-manager' ) . '</p>';
			return;
		}

		/* Roles */
		echo '<h2>' . esc_html__( 'Roles', 'ic-menu-manager' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'icmm_save_roles' );
		echo '<input type="hidden" name="action" value="icmm_save_roles">';
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Role', 'ic-menu-manager' ) . '</th><th>' . esc_html__( 'Group', 'ic-menu-manager' ) . '</th></tr></thead><tbody>';
		$role_groups = ICMM_Groups::role_groups();
		foreach ( self::roles() as $role => $label ) {
			$current = isset( $role_groups[ $role ] ) ? $role_groups[ $role ] : '';
			echo '<tr><td><strong>' . esc_html( $label ) . '</strong>';
			if ( 'administrator' === $role ) {
				echo ' <span class="icmm-warn">' . esc_html__( '— careful: this restricts every administrator, including you.', 'ic-menu-manager' ) . '</span>';
			}
			echo '</td><td>' . $this->group_select( 'role_group[' . esc_attr( $role ) . ']', $current, $groups ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save Role Assignments', 'ic-menu-manager' ) . '</button></p>';
		echo '</form>';

		/* Users with an assignment */
		echo '<h2>' . esc_html__( 'Users', 'ic-menu-manager' ) . '</h2>';
		$assigned = get_users( array( 'meta_key' => ICMM_Groups::USER_META, 'number' => 500 ) );
		if ( $assigned ) {
			echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'User', 'ic-menu-manager' ) . '</th><th>' . esc_html__( 'Group', 'ic-menu-manager' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $assigned as $u ) {
				$gid = ICMM_Groups::user_group( $u->ID );
				echo '<tr><td><strong>' . esc_html( $u->display_name ) . '</strong> <span class="description">' . esc_html( $u->user_email ) . '</span></td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="icmm-inline">';
				wp_nonce_field( 'icmm_save_user' );
				echo '<input type="hidden" name="action" value="icmm_save_user">';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( $u->ID ) . '">';
				echo $this->group_select( 'group_id', $gid, $groups );
				echo ' <button type="submit" class="button">' . esc_html__( 'Update', 'ic-menu-manager' ) . '</button>';
				echo '</form></td><td></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No users have a group assigned yet.', 'ic-menu-manager' ) . '</p>';
		}

		/* Assign one or more users at once */
		echo '<h3>' . esc_html__( 'Assign users', 'ic-menu-manager' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="icmm-bulk-assign">';
		wp_nonce_field( 'icmm_bulk_assign' );
		echo '<input type="hidden" name="action" value="icmm_bulk_assign">';
		echo '<p><input type="search" class="icmm-user-filter" placeholder="' . esc_attr__( 'Filter users…', 'ic-menu-manager' ) . '" autocomplete="off"></p>';
		echo '<select name="user_ids[]" multiple size="12" class="icmm-user-multiselect" required>';
		foreach ( get_users( array( 'number' => 1000, 'orderby' => 'display_name' ) ) as $u ) {
			echo '<option value="' . esc_attr( $u->ID ) . '">' . esc_html( $u->display_name . ' (' . $u->user_login . ')' ) . '</option>';
		}
		echo '</select>';
		echo '<p class="icmm-bulk-controls"><button type="button" class="button icmm-select-all">' . esc_html__( 'Select all shown', 'ic-menu-manager' ) . '</button> ';
		echo '<button type="button" class="button icmm-select-none">' . esc_html__( 'Clear selection', 'ic-menu-manager' ) . '</button></p>';
		echo '<p>' . esc_html__( 'Assign group:', 'ic-menu-manager' ) . ' ' . $this->group_select( 'group_id', '', $groups );
		echo ' <button type="submit" class="button button-primary">' . esc_html__( 'Assign to selected', 'ic-menu-manager' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Hold Ctrl (Cmd on Mac) to pick several, or drag across names. Choosing “— None —” removes the group from the selected users.', 'ic-menu-manager' ) . '</p>';
		echo '</form>';
	}

	private function group_select( $name, $selected, $groups ) {
		$html  = '<select name="' . esc_attr( $name ) . '">';
		$html .= '<option value="">' . esc_html__( '— None —', 'ic-menu-manager' ) . '</option>';
		foreach ( $groups as $id => $g ) {
			$html .= '<option value="' . esc_attr( $id ) . '" ' . selected( $selected, $id, false ) . '>' . esc_html( $g['name'] ) . '</option>';
		}
		$html .= '</select>';
		return $html;
	}
}
