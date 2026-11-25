<?php
/**
 * The one-off import from wpDataTables table 185 ("TBT CATALOGUE").
 *
 * Read-only until Import selected. The rows live in the table named by
 * mysql_table_name for ID 185 in wp_wpdatatables (wp_wpdatatable_62 on the
 * live site), with the columns wdt_ID, name (the link), title, category,
 * tags and level. wpDataTables itself is never written to.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Import {

	const TABLE_ID = 185;

	/** Left-to-check groups, in screen order. */
	const GROUPS = array(
		'multidecker' => 'Multidecker links',
		'file'        => 'Files',
		'site'        => 'Links to this site',
		'other'       => 'Other sites',
	);

	/**
	 * The data table's name, checked.
	 *
	 * @return string|WP_Error
	 */
	public static function table() {
		global $wpdb;
		$list = $wpdb->prefix . 'wpdatatables';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $list ) ) ) !== $list ) {
			return new WP_Error( 'tbtst_no_wpdt', 'The wpDataTables table list (' . $list . ') is missing, so table 185 can\'t be read.', array( 'status' => 404 ) );
		}
		$name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT mysql_table_name FROM {$list} WHERE id = %d", self::TABLE_ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' === $name || ! preg_match( '/^[A-Za-z0-9_]+$/', $name ) ) {
			return new WP_Error( 'tbtst_no_185', 'wpDataTables has no table 185 with its rows in the database.', array( 'status' => 404 ) );
		}
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ) !== $name ) {
			return new WP_Error( 'tbtst_no_table', 'Table 185 says its rows are in ' . $name . ', but that table is missing.', array( 'status' => 404 ) );
		}
		return $name;
	}

	/**
	 * Rows of table 185, all or the given wdt_IDs.
	 *
	 * @param int[]|null $refs wdt_IDs, or null for every row.
	 * @return array[]|WP_Error
	 */
	public static function read( $refs = null ) {
		global $wpdb;
		$table = self::table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		$sql = "SELECT wdt_ID, name, title, category, tags, level FROM `{$table}`";
		if ( null !== $refs ) {
			$refs = array_values( array_unique( array_filter( array_map( 'intval', $refs ) ) ) );
			if ( ! $refs ) {
				return array();
			}
			$sql .= ' WHERE wdt_ID IN (' . implode( ',', $refs ) . ')';
		}
		$sql .= ' ORDER BY wdt_ID';
		$wpdb->suppress_errors( true );
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$err  = $wpdb->last_error;
		$wpdb->suppress_errors( false );
		if ( $err ) {
			return new WP_Error( 'tbtst_read_185', 'Table 185 could not be read: ' . $err, array( 'status' => 500 ) );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Sort every row of table 185 into skipped and left-to-check.
	 *
	 * @return array|WP_Error
	 */
	public static function scan() {
		$rows = self::read();
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$settings  = TBTST_Settings::get();
		$site_host = TBTST_Format::host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$names     = TBTST_Rows::category_names();
		$imported  = TBTST_DB::import_refs();

		// Automatic rows by link, and the ones that are posts (lessons and
		// matching games) by post ID.
		$auto_urls = array();
		$auto_post = array();
		foreach ( TBTST_Rows::auto()['rows'] as $a ) {
			$auto_urls[ TBTST_Format::normalise_url( $a['url'] ) ] = true;
			if ( 'lesson' === $a['source'] || 'mg' === $a['source'] ) {
				$auto_post[ (int) $a['source_id'] ] = true;
			}
		}

		$skipped = array(
			'empty'    => array(),
			'rule'     => array(),
			'draft'    => array(),
			'covered'  => array(),
			'dupe'     => array(),
			'imported' => array(),
		);
		$groups  = array_fill_keys( array_keys( self::GROUPS ), array() );
		$seen    = array();

		foreach ( $rows as $r ) {
			$c    = self::clean( $r, $names, $settings, $site_host );
			$item = array(
				'ref'   => $c['ref'],
				'title' => $c['title'],
				'url'   => $c['url'],
			);

			if ( '' === $c['url'] ) {
				$skipped['empty'][] = $item;
				continue;
			}
			if ( $settings['skip_cats'] && TBTST_Format::category_in( $c['raw_category'], $settings['skip_cats'] ) ) {
				$skipped['rule'][] = $item + array( 'category' => $c['raw_category'] );
				continue;
			}
			if ( 'draft' === $c['kind'] ) {
				$skipped['draft'][] = $item;
				continue;
			}

			$norm    = TBTST_Format::normalise_url( $c['url'] );
			$post_id = 0;
			$post    = null;
			if ( ! isset( $auto_urls[ $norm ] ) && 'site' === $c['kind'] ) {
				$post_id = self::resolve_post( $c['url'] );
				$post    = $post_id ? get_post( $post_id ) : null;
			}
			$live = $post && 'publish' === $post->post_status;
			if ( isset( $auto_urls[ $norm ] ) || ( $live && isset( $auto_post[ $post_id ] ) ) ) {
				$skipped['covered'][] = $item;
				continue;
			}
			// An old and a new address of the same post are one link.
			$keys = array( $norm );
			if ( $live ) {
				$keys[] = 'post:' . $post_id;
			}
			$dupe = false;
			foreach ( $keys as $k ) {
				$dupe = $dupe || isset( $seen[ $k ] );
				$seen[ $k ] = true;
			}
			if ( $dupe ) {
				$skipped['dupe'][] = $item;
				continue;
			}
			if ( isset( $imported[ $c['ref'] ] ) ) {
				$skipped['imported'][] = $item;
				continue;
			}

			$broken  = 'site' === $c['kind'] && ! $live && ! self::looks_like_file( $c['url'] );
			$reason  = '';
			$note    = '';
			$current = '';
			if ( $live ) {
				$reason  = self::why_not_automatic( $post, $settings );
				$current = (string) get_permalink( $post );
				if ( TBTST_Format::normalise_url( $current ) === $norm ) {
					$current = '';
				}
			} elseif ( $broken && $post ) {
				$note = 'The post at this address is ' . self::status_label( $post->post_status ) . '.';
			}
			$groups[ $c['kind'] ][] = array(
				'ref'          => $c['ref'],
				'title'        => $c['title'],
				'url'          => $c['url'],
				'category'     => $c['category'],
				'raw_category' => $c['raw_category'],
				'tags'         => $c['tags'],
				'level'        => $c['level'],
				'ticked'       => ! $broken,
				'broken'       => $broken,
				'reason'       => $reason,
				'note'         => $note,
				'current_url'  => $current,
			);
		}

		$left = 0;
		foreach ( $groups as $g ) {
			$left += count( $g );
		}
		return array(
			'total'   => count( $rows ),
			'skipped' => $skipped,
			'groups'  => $groups,
			'left'    => $left,
			'last'    => TBTST_DB::last_batch(),
		);
	}

	/**
	 * Import the ticked rows. Each ref is re-read from table 185 here; only
	 * the category comes from the browser.
	 *
	 * @param array $picks list of array( 'ref' => int, 'category' => string ).
	 * @return array|WP_Error
	 */
	public static function import( array $picks ) {
		$want = array();
		foreach ( $picks as $p ) {
			if ( is_array( $p ) && isset( $p['ref'] ) ) {
				$want[ (string) (int) $p['ref'] ] = isset( $p['category'] ) ? TBTST_Format::trim( (string) $p['category'] ) : '';
			}
		}
		if ( ! $want ) {
			return new WP_Error( 'tbtst_nothing', 'Tick at least one row to import.', array( 'status' => 400 ) );
		}

		$names = TBTST_Rows::category_names();
		$no    = 0;
		foreach ( $want as $cat ) {
			if ( ! in_array( $cat, $names, true ) ) {
				$no++;
			}
		}
		if ( $no ) {
			return new WP_Error( 'tbtst_no_category', sprintf( 'Pick a category for %d row%s first.', $no, 1 === $no ? '' : 's' ), array( 'status' => 400 ) );
		}

		$rows = self::read( array_keys( $want ) );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$settings  = TBTST_Settings::get();
		$site_host = TBTST_Format::host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$imported  = TBTST_DB::import_refs();
		$batch     = gmdate( 'YmdHis' );
		$done      = 0;
		$skipped   = 0;

		foreach ( $rows as $r ) {
			$c   = self::clean( $r, $names, $settings, $site_host );
			$url = esc_url_raw( $c['url'], array( 'http', 'https' ) );
			if ( '' === $url || isset( $imported[ $c['ref'] ] ) ) {
				$skipped++;
				continue;
			}
			$title = '' !== $c['title'] ? $c['title'] : $url;
			$id    = TBTST_DB::insert(
				array(
					'source'       => 'manual',
					'source_id'    => 0,
					'title'        => mb_substr( $title, 0, 255 ),
					'category'     => $want[ $c['ref'] ],
					'tags'         => implode( ', ', $c['tags'] ),
					'level'        => $c['level'],
					'url'          => mb_substr( $url, 0, 500 ),
					'hidden'       => 0,
					'import_ref'   => $c['ref'],
					'import_batch' => $batch,
				)
			);
			if ( $id ) {
				$done++;
				$imported[ $c['ref'] ] = true;
			} else {
				$skipped++;
			}
		}

		return array(
			'imported' => $done,
			'skipped'  => $skipped,
			'batch'    => $batch,
		);
	}

	/**
	 * Undo the newest import batch.
	 *
	 * @return array|WP_Error
	 */
	public static function undo() {
		$last = TBTST_DB::last_batch();
		if ( ! $last ) {
			return new WP_Error( 'tbtst_no_batch', 'There is no import to undo.', array( 'status' => 404 ) );
		}
		return TBTST_DB::undo_batch( $last['batch'] ) + array( 'batch' => $last['batch'] );
	}

	/**
	 * One table 185 row, cleaned.
	 *
	 * @param array  $r         Row.
	 * @param array  $names     Current category names.
	 * @param array  $settings  Settings.
	 * @param string $site_host This site's host.
	 * @return array
	 */
	private static function clean( array $r, array $names, array $settings, $site_host ) {
		$url  = TBTST_Format::href( $r['name'] ?? null );
		$tags = TBTST_Format::split_tags( TBTST_Sources::decode( $r['tags'] ?? '' ) );
		$lvl  = TBTST_Format::valid_level( $r['level'] ?? '' );
		$raw  = TBTST_Format::trim( html_entity_decode( (string) ( $r['category'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$raw  = 0 === strcasecmp( $raw, 'null' ) ? '' : $raw;
		$tit  = TBTST_Sources::decode( $r['title'] ?? '' );
		return array(
			'ref'          => (string) (int) ( $r['wdt_ID'] ?? 0 ),
			'url'          => $url,
			'title'        => 0 === strcasecmp( $tit, 'null' ) ? '' : $tit,
			'raw_category' => $raw,
			'category'     => TBTST_Format::match_category( $raw, $names ),
			'tags'         => $tags,
			'level'        => '' !== $lvl ? $lvl : TBTST_Format::cef_level( $tags ),
			'kind'         => '' === $url ? '' : TBTST_Format::kind( $url, $site_host, $settings['md_hosts'] ),
		);
	}

	/**
	 * The post a link to this site points to, including through an old
	 * address. No HTTP request is made. In order:
	 *   1. ?p= / ?page_id=: that ID, if the post exists;
	 *   2. url_to_postid();
	 *   3. the last path segment as a post or page slug (get_page_by_path);
	 *   4. the newest published post with that slug in _wp_old_slug (what
	 *      WordPress's old-slug redirect would open).
	 * A post found in 2–3 that isn't published is returned only when step 4
	 * finds nothing, so a draft that took an address doesn't hide the
	 * published post that used to have it.
	 *
	 * @param string $url Link.
	 * @return int Post ID, or 0.
	 */
	public static function resolve_post( $url ) {
		global $wpdb;

		$id = TBTST_Format::query_post_id( $url );
		if ( $id ) {
			return get_post( $id ) ? $id : 0;
		}

		$fallback = 0;
		$id       = (int) url_to_postid( $url );
		if ( $id ) {
			if ( 'publish' === get_post_status( $id ) ) {
				return $id;
			}
			$fallback = $id;
		}

		$slug = TBTST_Format::slug_from_url( $url );
		if ( '' === $slug ) {
			return $fallback;
		}

		$p = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );
		if ( $p instanceof WP_Post ) {
			if ( 'publish' === $p->post_status ) {
				return (int) $p->ID;
			}
			$fallback = $fallback ? $fallback : (int) $p->ID;
		}

		$old = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->postmeta} m
				 JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = '_wp_old_slug' AND m.meta_value = %s
				   AND p.post_status = 'publish' AND p.post_type NOT IN ( 'revision', 'attachment', 'nav_menu_item' )
				 ORDER BY p.post_date DESC, p.ID DESC LIMIT 1",
				$slug
			)
		);
		return $old ? $old : $fallback;
	}

	/**
	 * Why a published post isn't an automatic row.
	 *
	 * @param WP_Post $post     Post.
	 * @param array   $settings Settings.
	 * @return string
	 */
	private static function why_not_automatic( WP_Post $post, array $settings ) {
		if ( 'post' !== $post->post_type ) {
			$obj   = get_post_type_object( $post->post_type );
			$label = $obj ? strtolower( $obj->labels->singular_name ) : $post->post_type;
			return 'Lesson exists, but it is a ' . $label . ', not a post';
		}
		$cats  = get_the_category( $post->ID );
		$never = array();
		$names = array();
		foreach ( $cats as $t ) {
			$names[] = TBTST_Sources::decode( $t->name );
			if ( in_array( (int) $t->term_id, array_map( 'intval', $settings['never_cats'] ), true ) ) {
				$never[] = TBTST_Sources::decode( $t->name );
			}
		}
		if ( $never ) {
			return 'Lesson exists, but it is in Never list (' . implode( ', ', $never ) . ')';
		}
		$in_lesson = array_intersect( array_map( 'intval', wp_list_pluck( $cats, 'term_id' ) ), array_map( 'intval', $settings['lesson_cats'] ) );
		if ( ! $in_lesson ) {
			return 'Lesson exists, but its category (' . ( $names ? implode( ', ', $names ) : 'none' ) . ') isn\'t in Lesson categories';
		}
		return 'Lesson exists, but the table hasn\'t picked it up yet: press Refresh now';
	}

	/**
	 * @param string $status Post status.
	 * @return string
	 */
	private static function status_label( $status ) {
		$map = array(
			'draft'   => 'a draft',
			'pending' => 'waiting for review',
			'private' => 'private',
			'future'  => 'scheduled, not published yet',
			'trash'   => 'in the trash',
		);
		return $map[ $status ] ?? 'not published (' . $status . ')';
	}

	/**
	 * Does the link's last path segment have a file extension?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function looks_like_file( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return (bool) preg_match( '/\.[a-z0-9]{2,5}$/i', $path );
	}
}
