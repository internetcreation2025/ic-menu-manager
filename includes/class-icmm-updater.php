<?php
/**
 * Self-contained GitHub Releases updater.
 *
 * Deliberately does NOT use Plugin Update Checker (PUC): the fleet's
 * `internetcreation` plugin bundles PUC 4.11 and claims the global
 * Puc_v4_Factory, which has caused wp-admin fatals when a second plugin
 * bundles a different PUC version. This updater uses no shared/global
 * symbols and fails silently if GitHub is unreachable — it can never fatal
 * a site. It reads the repo's "latest release" and, if the tag is newer than
 * the running version, offers the attached zip asset as the update package.
 *
 * Optional: define('ICMM_UPDATER_GITHUB_TOKEN', '...'); in wp-config.php to
 * authenticate the API calls (only needed for a private repo or to raise the
 * GitHub rate limit). Not required while the repo is public.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICMM_Updater {

	private $file;    // plugin basename, e.g. ic-menu-manager/ic-menu-manager.php
	private $slug;    // ic-menu-manager
	private $version;
	private $owner = 'internetcreation2025';
	private $repo  = 'ic-menu-manager';
	private $cache_key = 'icmm_updater_release';
	private $cache_ttl;
	private $fail_ttl;

	public function __construct( $plugin_file, $version ) {
		$this->file      = plugin_basename( $plugin_file );
		$this->slug      = dirname( $this->file );
		$this->version   = $version;
		$this->cache_ttl = 6 * HOUR_IN_SECONDS;
		$this->fail_ttl  = 15 * MINUTE_IN_SECONDS;
	}

	public function hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ) );
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->file, array( $this, 'action_link' ) );
		add_action( 'admin_init', array( $this, 'maybe_manual_check' ) );
		add_action( 'admin_notices', array( $this, 'checked_notice' ) );
	}

	/* ---- Remote release (cached) ---- */

	private function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				return $cached['ok'] ? $cached['data'] : false;
			}
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo );
		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'ic-menu-manager-updater',
			),
		);
		if ( defined( 'ICMM_UPDATER_GITHUB_TOKEN' ) && ICMM_UPDATER_GITHUB_TOKEN ) {
			$args['headers']['Authorization'] = 'Bearer ' . ICMM_UPDATER_GITHUB_TOKEN;
		}

		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			set_site_transient( $this->cache_key, array( 'ok' => false ), $this->fail_ttl );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['tag_name'] ) ) {
			set_site_transient( $this->cache_key, array( 'ok' => false ), $this->fail_ttl );
			return false;
		}

		$data = array(
			'version'   => ltrim( $body['tag_name'], 'vV' ),
			'package'   => $this->pick_package( $body ),
			'body'      => isset( $body['body'] ) ? $body['body'] : '',
			'html_url'  => isset( $body['html_url'] ) ? $body['html_url'] : '',
			'published' => isset( $body['published_at'] ) ? $body['published_at'] : '',
		);
		set_site_transient( $this->cache_key, array( 'ok' => true, 'data' => $data ), $this->cache_ttl );
		return $data;
	}

	/** Prefer an attached .zip asset (correct folder name); fall back to the zipball. */
	private function pick_package( $body ) {
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $a ) {
				if ( ! empty( $a['browser_download_url'] ) && '.zip' === strtolower( substr( $a['name'], -4 ) ) ) {
					return $a['browser_download_url'];
				}
			}
		}
		return isset( $body['zipball_url'] ) ? $body['zipball_url'] : '';
	}

	/* ---- WordPress update integration ---- */

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$rel = $this->get_release();
		if ( ! $rel || empty( $rel['package'] ) ) {
			return $transient;
		}

		$item = (object) array(
			'slug'        => $this->slug,
			'plugin'      => $this->file,
			'new_version' => $rel['version'],
			'url'         => $rel['html_url'],
			'package'     => $rel['package'],
		);

		if ( version_compare( $rel['version'], $this->version, '>' ) ) {
			$transient->response[ $this->file ] = $item;
		} else {
			// Keeps the row tidy (auto-update toggle, "up to date") without offering an update.
			$item->new_version = $this->version;
			$transient->no_update[ $this->file ] = $item;
		}
		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$rel = $this->get_release();
		if ( ! $rel ) {
			return $result;
		}
		return (object) array(
			'name'          => 'IC Menu Manager',
			'slug'          => $this->slug,
			'version'       => $rel['version'],
			'author'        => '<a href="https://internetcreation.net">Internet Creation</a>',
			'homepage'      => $rel['html_url'],
			'download_link' => $rel['package'],
			'trace'         => '',
			'sections'      => array(
				'description' => esc_html__( 'Control what users see and can access in the wp-admin sidebar and top admin bar.', 'ic-menu-manager' ),
				'changelog'   => $this->format_notes( $rel['body'] ),
			),
		);
	}

	/** Ensure the extracted folder is named after the plugin slug (matters for zipball fallback). */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->file ) {
			return $source;
		}
		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}
		return $source;
	}

	public function clear_cache() {
		delete_site_transient( $this->cache_key );
	}

	/**
	 * Turn on hands-off background auto-updates for this plugin fleet-wide, so every
	 * install self-updates within ~12h of a new release. Kill-switch:
	 * define('ICMM_DISABLE_AUTOUPDATE', true); in wp-config.php.
	 */
	public function enable_auto_update( $update, $item ) {
		if ( defined( 'ICMM_DISABLE_AUTOUPDATE' ) && ICMM_DISABLE_AUTOUPDATE ) {
			return $update;
		}
		if ( is_object( $item ) && ! empty( $item->plugin ) && $item->plugin === $this->file ) {
			return true;
		}
		return $update;
	}

	/* ---- Manual "Check for updates" link on the Plugins screen ---- */

	public function action_link( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$url = wp_nonce_url( add_query_arg( 'icmm_check_update', '1', admin_url( 'plugins.php' ) ), 'icmm_check_update' );
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'ic-menu-manager' ) . '</a>';
		}
		return $links;
	}

	public function maybe_manual_check() {
		if ( empty( $_GET['icmm_check_update'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		check_admin_referer( 'icmm_check_update' );
		$this->clear_cache();
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( add_query_arg( 'icmm_checked', '1', admin_url( 'plugins.php' ) ) );
		exit;
	}

	public function checked_notice() {
		if ( empty( $_GET['icmm_checked'] ) ) {
			return;
		}
		$rel = $this->get_release();
		$msg = ( $rel && version_compare( $rel['version'], $this->version, '>' ) )
			/* translators: %s: version */
			? sprintf( __( 'IC Menu Manager %s is available — see the update above.', 'ic-menu-manager' ), $rel['version'] )
			: __( 'IC Menu Manager is up to date.', 'ic-menu-manager' );
		printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}

	private function format_notes( $markdown ) {
		$text = wp_strip_all_tags( (string) $markdown );
		return wpautop( esc_html( $text ) );
	}
}
