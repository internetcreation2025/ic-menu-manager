<?php
/**
 * Custom ordering of the top-level wp-admin sidebar menu.
 *
 * Stores one global slug order (Options API) and applies it site-wide through
 * WordPress's own custom_menu_order / menu_order filters. Top-level items only;
 * submenu order is left to WordPress. Composes cleanly with the hide/block
 * groups — items a group removes simply aren't in the list to order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Order {

	const OPTION = 'icmm_menu_order';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_filter( 'custom_menu_order', array( $this, 'enable' ) );
		add_filter( 'menu_order', array( $this, 'apply' ) );
	}

	/** @return string[] the saved top-level slug order (empty = WordPress default). */
	public static function get() {
		$order = get_option( self::OPTION, array() );
		return is_array( $order ) ? array_values( array_map( 'strval', $order ) ) : array();
	}

	/**
	 * Save a new order. Only slugs that exist in the live catalog are kept, in
	 * the given order — so posted data can't inject arbitrary menu slugs, and
	 * stale entries drop out on their own.
	 *
	 * @param array $slugs Ordered menu slugs.
	 * @return string[] the cleaned, stored order.
	 */
	public static function save( $slugs ) {
		$known = array();
		foreach ( (array) ICMM_Catalog::get()['menu'] as $item ) {
			if ( ! empty( $item['slug'] ) ) {
				$known[] = (string) $item['slug'];
			}
		}
		$clean = array();
		foreach ( (array) $slugs as $slug ) {
			$slug = (string) $slug;
			if ( in_array( $slug, $known, true ) && ! in_array( $slug, $clean, true ) ) {
				$clean[] = $slug;
			}
		}
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	/** Turn on custom ordering only when we actually have a saved order to apply. */
	public function enable( $enabled ) {
		return self::get() ? true : $enabled;
	}

	/**
	 * Reorder the incoming top-level slug list: saved items first (in the saved
	 * order, only those still present), then everything else — separators and
	 * any newly-added items — in their original relative order.
	 *
	 * @param array $menu_order WordPress's current top-level slug order.
	 * @return array
	 */
	public function apply( $menu_order ) {
		$saved = self::get();
		if ( empty( $saved ) || ! is_array( $menu_order ) ) {
			return $menu_order;
		}
		$ordered = array();
		foreach ( $saved as $slug ) {
			if ( in_array( $slug, $menu_order, true ) ) {
				$ordered[] = $slug;
			}
		}
		foreach ( $menu_order as $slug ) {
			if ( ! in_array( $slug, $ordered, true ) ) {
				$ordered[] = $slug;
			}
		}
		return $ordered;
	}
}
