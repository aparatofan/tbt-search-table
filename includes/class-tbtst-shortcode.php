<?php
/**
 * [tbt_search_table per="10"]: the VIP page's advanced search.
 *
 * Renders the filter panel, toolbar, table and pager. The heading stays in
 * Divi. The visible rows are embedded in the page as JSON, so the page's own
 * protection is the only access rule and there is no public route listing
 * them.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Shortcode {

	const TAG = 'tbt_search_table';

	/** @var int Instances on this page, for unique IDs. */
	private static $n = 0;

	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 20 );
	}

	/**
	 * Register the assets, and enqueue them in the head when the page's
	 * content has the shortcode (so the styles don't arrive late).
	 */
	public static function register_assets() {
		// Hub registers tbt-tokens at priority 5; the bundled copy is the fallback.
		if ( ! wp_style_is( 'tbt-tokens', 'registered' ) ) {
			wp_register_style( 'tbt-tokens', TBTST_URL . 'assets/css/tbt-tokens.css', array(), self::ver( 'assets/css/tbt-tokens.css' ) );
		}
		wp_register_style( 'tbtst-fonts', TBTST_URL . 'assets/css/fonts.css', array(), self::ver( 'assets/css/fonts.css' ) );
		wp_register_style( 'tbtst-table', TBTST_URL . 'assets/css/table.css', array( 'tbt-tokens', 'tbtst-fonts' ), self::ver( 'assets/css/table.css' ) );
		wp_register_script( 'tbtst-table', TBTST_URL . 'assets/js/table.js', array(), self::ver( 'assets/js/table.js' ), true );

		$post = get_post();
		if ( is_singular() && $post && has_shortcode( (string) $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'tbtst-table' );
		}
	}

	/**
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'per' => '10' ), is_array( $atts ) ? $atts : array(), self::TAG );
		$per  = in_array( (int) $atts['per'], array( 5, 10, 25, 50, 100 ), true ) ? (int) $atts['per'] : 10;

		if ( ! wp_style_is( 'tbtst-table', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'tbtst-table' );
		wp_enqueue_script( 'tbtst-table' );

		$rows = array();
		foreach ( TBTST_Rows::merged( false ) as $r ) {
			$rows[] = array( $r['title'], $r['category'], array_values( $r['tags'] ), $r['level'], $r['url'], $r['is_tool'] );
		}

		$id = 'tbtst-' . ( ++self::$n );
		ob_start();
		?>
		<div class="tbtst" id="<?php echo esc_attr( $id ); ?>" data-per="<?php echo esc_attr( (string) $per ); ?>" data-rows="<?php echo esc_attr( self::json( $rows ) ); ?>">
			<div class="tbtst-filters">
				<div class="tbtst-field">
					<label for="<?php echo esc_attr( $id ); ?>-title">Title</label>
					<input id="<?php echo esc_attr( $id ); ?>-title" class="tbtst-inp" type="text" placeholder="Any title" data-f="title" autocomplete="off">
				</div>
				<div class="tbtst-field">
					<label for="<?php echo esc_attr( $id ); ?>-cat">Category</label>
					<select id="<?php echo esc_attr( $id ); ?>-cat" class="tbtst-sel" data-f="cat"><option value="">All categories</option></select>
				</div>
				<div class="tbtst-field">
					<label for="<?php echo esc_attr( $id ); ?>-tags">Tags</label>
					<input id="<?php echo esc_attr( $id ); ?>-tags" class="tbtst-inp" type="text" placeholder="e.g. sleep" data-f="tags" autocomplete="off">
				</div>
				<div class="tbtst-field">
					<label for="<?php echo esc_attr( $id ); ?>-lvl">Level</label>
					<select id="<?php echo esc_attr( $id ); ?>-lvl" class="tbtst-sel" data-f="level">
						<option value="">All levels</option>
						<?php foreach ( TBTST_Format::LEVELS as $l ) : ?>
							<option><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="tbtst-active" data-r="active" hidden></div>
			<div class="tbtst-bar">
				<div class="tbtst-show">
					<span>Show</span>
					<select class="tbtst-sel" data-f="per" aria-label="Rows per page">
						<?php foreach ( array( 5, 10, 25, 50, 100 ) as $n ) : ?>
							<option<?php selected( $n, $per ); ?>><?php echo (int) $n; ?></option>
						<?php endforeach; ?>
					</select>
					<span>entries</span>
				</div>
				<div class="tbtst-search">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<input class="tbtst-inp" type="search" placeholder="Search everything" aria-label="Search everything" data-f="q" autocomplete="off">
				</div>
			</div>
			<table class="tbtst-tbl">
				<thead>
					<tr>
						<th data-k="title" aria-sort="ascending" class="tbtst-th-title"><button type="button">Title <span class="tbtst-arr" aria-hidden="true">↑</span></button></th>
						<th data-k="cat" aria-sort="none"><button type="button">Category <span class="tbtst-arr" aria-hidden="true">↕</span></button></th>
						<th class="tbtst-plain">Tags</th>
						<th data-k="level" aria-sort="none" class="tbtst-th-level"><button type="button">Level <span class="tbtst-arr" aria-hidden="true">↕</span></button></th>
						<th class="tbtst-plain tbtst-th-link">Link</th>
					</tr>
				</thead>
				<tbody><tr class="tbtst-empty"><td colspan="5">Loading…</td></tr></tbody>
			</table>
			<div class="tbtst-pager">
				<span data-r="range" aria-live="polite"></span>
				<div class="tbtst-pages" data-r="pages"></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * JSON safe to put in an attribute or a script tag. & < > ' " inside
	 * strings become \u escapes, so esc_attr (which never double-encodes)
	 * cannot change a title that happens to contain "&amp;".
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	public static function json( $data ) {
		return (string) wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * File modification time as the asset version, so a deploy busts caches.
	 *
	 * @param string $rel Path inside the plugin.
	 * @return string
	 */
	public static function ver( $rel ) {
		$t = @filemtime( TBTST_DIR . $rel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $t ? (string) $t : TBTST_VERSION;
	}
}
