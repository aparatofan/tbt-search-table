<?php
/**
 * TBT Hub → Search Table: All rows, Import from table 185, Missing CEF tag,
 * Settings. The page is a shell; admin.js renders it from the JSON embedded
 * here and talks to TBTST_Rest. Nothing is written on page load.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Admin {

	const SLUG = 'tbt-search-table';

	/** @var string */
	private static $hook = '';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		if ( defined( 'TBT_HUB_SLUG' ) ) {
			self::$hook = (string) add_submenu_page( TBT_HUB_SLUG, 'Search Table', 'Search Table', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
		} else {
			self::$hook = (string) add_menu_page( 'Search Table', 'Search Table', 'manage_options', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-search', 58 );
		}
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'tbtst-admin', TBTST_URL . 'assets/css/admin.css', array(), TBTST_Shortcode::ver( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'tbtst-admin', TBTST_URL . 'assets/js/admin.js', array(), TBTST_Shortcode::ver( 'assets/js/admin.js' ), true );

		$data = self::payload() + array(
			'rest'     => esc_url_raw( rest_url( TBTST_Rest::NS . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'labels'   => TBTST_Sources::LABELS,
			'levels'   => TBTST_Format::LEVELS,
			'wpcats'   => self::wp_categories(),
			'settings' => self::settings_payload(),
		);
		wp_add_inline_script( 'tbtst-admin', 'window.TBTST = ' . TBTST_Shortcode::json( $data ) . ';', 'before' );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'tbt-search-table' ) );
		}
		?>
		<div class="wrap tbtst-admin">
			<h1 class="tbtst-h1">
				Search Table
				<button type="button" class="button" id="tbtst-add">Add row</button>
				<button type="button" class="button" id="tbtst-refresh">Refresh now</button>
				<span class="tbtst-refreshed" id="tbtst-refreshed" role="status"></span>
			</h1>
			<div class="tbtst-tabs" role="tablist">
				<button type="button" role="tab" data-t="rows" aria-selected="true">All rows</button>
				<button type="button" role="tab" data-t="import" aria-selected="false">Import from table 185 <span class="tbtst-n" id="tbtst-n-imp" hidden></span></button>
				<button type="button" role="tab" data-t="cef" aria-selected="false">Missing CEF tag <span class="tbtst-n" id="tbtst-n-cef" hidden></span></button>
				<button type="button" role="tab" data-t="settings" aria-selected="false">Settings</button>
			</div>

			<section data-p="rows" role="tabpanel">
				<div class="tbtst-notice">Lessons and open activities are added automatically. Edit a row to change what the VIP table shows; <b>Reset</b> brings back the automatic values. Category is always picked from the list.</div>
				<div class="tbtst-wpbar">
					<select id="tbtst-f-src" aria-label="Filter by source"><option value="">All sources</option></select>
					<select id="tbtst-f-hid" aria-label="Filter by visibility">
						<option value="">All</option>
						<option value="visible">Visible</option>
						<option value="hidden">Hidden</option>
					</select>
					<input id="tbtst-f-q" type="search" placeholder="Search rows" aria-label="Search rows">
					<span id="tbtst-count" class="tbtst-muted"></span>
				</div>
				<table class="tbtst-wpt">
					<thead><tr><th>Title</th><th>Source</th><th>Category</th><th>Tags</th><th>Level</th></tr></thead>
					<tbody id="tbtst-rows"></tbody>
				</table>
				<div class="tbtst-pager" id="tbtst-rows-pager"></div>
			</section>

			<section data-p="import" role="tabpanel" hidden>
				<div id="tbtst-import"><p class="tbtst-muted">Open this tab to scan table 185. Scanning reads it only; nothing is written until you press <b>Import selected</b>.</p></div>
			</section>

			<section data-p="cef" role="tabpanel" hidden>
				<div class="tbtst-notice tbtst-warn">These lessons have no <b>CEF</b> tag, so they show no level in the VIP table. Add the tag in the post, then press <b>Refresh now</b>, and they drop off this list.</div>
				<table class="tbtst-wpt">
					<thead><tr><th>Lesson</th><th>Category</th><th>Tags now</th><th><span class="screen-reader-text">Edit</span></th></tr></thead>
					<tbody id="tbtst-cef"></tbody>
				</table>
				<h2 class="tbtst-h2">Odd CEF tags</h2>
				<p class="tbtst-muted">Tags that start with “CEF” but aren't a level the table can read (A1–C2). Rename or merge them in <b>Posts → Tags</b>.</p>
				<table class="tbtst-wpt">
					<thead><tr><th>Tag</th><th>Posts</th></tr></thead>
					<tbody id="tbtst-odd"></tbody>
				</table>
			</section>

			<section data-p="settings" role="tabpanel" hidden>
				<form id="tbtst-settings"></form>
			</section>

			<div id="tbtst-ov" class="tbtst-ov" hidden></div>
			<div id="tbtst-toast" class="tbtst-toast" role="status" hidden></div>
		</div>
		<?php
	}

	/* ---------- Data for the screen (also returned by REST) ---------- */

	/**
	 * Rows, categories, the CEF lists and the last import.
	 *
	 * @return array
	 */
	public static function payload() {
		$rows = array();
		foreach ( TBTST_Rows::merged( true ) as $r ) {
			$rows[] = self::client_row( $r );
		}
		return array(
			'rows'       => $rows,
			'categories' => TBTST_Rows::categories(),
			'last'       => TBTST_DB::last_batch(),
		) + self::cef_payload();
	}

	/**
	 * @param array $r Merged row.
	 * @return array
	 */
	public static function client_row( array $r ) {
		return array(
			'key'      => $r['key'],
			'source'   => $r['source'],
			'title'    => $r['title'],
			'category' => $r['category'],
			'tags'     => array_values( $r['tags'] ),
			'level'    => $r['level'],
			'url'      => $r['url'],
			'hidden'   => $r['hidden'],
			'edited'   => $r['edited'],
			'auto'     => $r['auto'],
			'imported' => '' !== $r['import_ref'],
		);
	}

	/**
	 * Lessons (not hidden) whose own tags have no CEF level, and tags that
	 * start with CEF but aren't a level.
	 *
	 * @return array array( 'cef' => array[], 'odd' => array[] )
	 */
	public static function cef_payload() {
		$cef = array();
		foreach ( TBTST_Rows::merged( false ) as $r ) {
			if ( 'lesson' !== $r['source'] || '' !== TBTST_Format::cef_level( $r['auto']['tags'] ) ) {
				continue;
			}
			$cef[] = array(
				'title'    => $r['title'],
				'category' => $r['category'],
				'tags'     => $r['auto']['tags'],
				'edit'     => (string) get_edit_post_link( $r['source_id'], 'raw' ),
			);
		}

		$odd   = array();
		$terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'name__like' => 'cef' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$name = TBTST_Sources::decode( $t->name );
			if ( 0 === strpos( TBTST_Format::fold( $name ), 'cef' ) && '' === TBTST_Format::cef_level( array( $name ) ) ) {
				$odd[] = array(
					'name'  => $name,
					'count' => (int) $t->count,
					'edit'  => (string) get_edit_term_link( $t->term_id, 'post_tag' ),
				);
			}
		}
		return array(
			'cef' => $cef,
			'odd' => $odd,
		);
	}

	/**
	 * Every category with its parent, for the Settings checkboxes.
	 *
	 * @return array[]
	 */
	private static function wp_categories() {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$out[] = array(
				'id'     => (int) $t->term_id,
				'name'   => TBTST_Sources::decode( $t->name ),
				'parent' => (int) $t->parent,
				'count'  => (int) $t->count,
			);
		}
		return $out;
	}

	/**
	 * @return array
	 */
	public static function settings_payload() {
		$s = TBTST_Settings::get();
		return array(
			'lesson_cats' => $s['lesson_cats'],
			'never_cats'  => $s['never_cats'],
			'extra_cats'  => implode( "\n", $s['extra_cats'] ),
			'md_hosts'    => implode( "\n", $s['md_hosts'] ),
			'skip_cats'   => implode( "\n", $s['skip_cats'] ),
			'saved'       => $s['saved'],
		);
	}
}
