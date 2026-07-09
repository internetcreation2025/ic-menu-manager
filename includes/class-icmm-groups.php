<?php
/**
 * Data model: Menu Groups (block-lists) and their assignment to roles and users.
 * Stored in the Options API + user meta. No custom tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Groups {

	const OPT_GROUPS    = 'icmm_groups';
	const OPT_ROLES     = 'icmm_role_groups';
	const USER_META     = 'icmm_group';

	/** @return array group_id => group array */
	public static function all() {
		$groups = get_option( self::OPT_GROUPS, array() );
		return is_array( $groups ) ? $groups : array();
	}

	public static function get( $id ) {
		$groups = self::all();
		return isset( $groups[ $id ] ) ? $groups[ $id ] : null;
	}

	/** Normalise a raw group array into the stored shape. */
	public static function normalise( $raw ) {
		return array(
			'name'           => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
			'block_menu'     => isset( $raw['block_menu'] ) ? array_values( array_unique( array_map( 'strval', (array) $raw['block_menu'] ) ) ) : array(),
			'block_submenu'  => self::normalise_submenu( isset( $raw['block_submenu'] ) ? $raw['block_submenu'] : array() ),
			'block_adminbar' => isset( $raw['block_adminbar'] ) ? array_values( array_unique( array_map( 'strval', (array) $raw['block_adminbar'] ) ) ) : array(),
		);
	}

	private static function normalise_submenu( $submenu ) {
		$out = array();
		foreach ( (array) $submenu as $parent => $slugs ) {
			$parent = (string) $parent;
			$clean  = array_values( array_unique( array_map( 'strval', (array) $slugs ) ) );
			if ( $clean ) {
				$out[ $parent ] = $clean;
			}
		}
		return $out;
	}

	/**
	 * Create or update a group. If $id is empty a new id is generated.
	 * @return string the group id.
	 */
	public static function save( $id, $raw ) {
		$groups = self::all();
		$id     = $id ? sanitize_key( $id ) : self::generate_id( $groups );
		$groups[ $id ] = self::normalise( $raw );
		update_option( self::OPT_GROUPS, $groups, false );
		return $id;
	}

	public static function delete( $id ) {
		$groups = self::all();
		if ( isset( $groups[ $id ] ) ) {
			unset( $groups[ $id ] );
			update_option( self::OPT_GROUPS, $groups, false );
		}
		// Detach from any role assignments.
		$roles = self::role_groups();
		foreach ( $roles as $role => $gid ) {
			if ( $gid === $id ) {
				unset( $roles[ $role ] );
			}
		}
		update_option( self::OPT_ROLES, $roles, false );
		// Detach from any user assignments.
		$user_ids = get_users( array( 'meta_key' => self::USER_META, 'meta_value' => $id, 'fields' => 'ID' ) );
		foreach ( $user_ids as $uid ) {
			delete_user_meta( $uid, self::USER_META );
		}
	}

	private static function generate_id( $existing ) {
		do {
			$id = 'g' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 );
		} while ( isset( $existing[ $id ] ) );
		return $id;
	}

	/* ---- Assignments ---- */

	public static function role_groups() {
		$roles = get_option( self::OPT_ROLES, array() );
		return is_array( $roles ) ? $roles : array();
	}

	public static function set_role_group( $role, $group_id ) {
		$roles = self::role_groups();
		if ( $group_id ) {
			$roles[ $role ] = sanitize_key( $group_id );
		} else {
			unset( $roles[ $role ] );
		}
		update_option( self::OPT_ROLES, $roles, false );
	}

	public static function user_group( $user_id ) {
		return (string) get_user_meta( $user_id, self::USER_META, true );
	}

	public static function set_user_group( $user_id, $group_id ) {
		if ( $group_id ) {
			update_user_meta( $user_id, self::USER_META, sanitize_key( $group_id ) );
		} else {
			delete_user_meta( $user_id, self::USER_META );
		}
	}

	/** Count how many roles + users a group is assigned to (for the list view). */
	public static function assignment_count( $id ) {
		$roles = array_keys( self::role_groups(), $id, true );
		$users = get_users( array( 'meta_key' => self::USER_META, 'meta_value' => $id, 'fields' => 'ID' ) );
		return array( 'roles' => count( $roles ), 'users' => count( $users ) );
	}
}
