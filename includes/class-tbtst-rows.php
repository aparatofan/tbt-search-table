<?php
/**
 * The rows the table shows: automatic rows (cached), with overrides and the
 * hidden flag applied, then manual rows. Overrides and manual rows are read
 * after the cache, so an admin edit shows at once.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Rows {

	const TRANSIENT = 'tbtst_auto';

	public static function register() {
		add_action( 'save_post_post', array( __CLASS__, 'flush' ) );
		add_action( 'save_post_tbt_match_game', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 2 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms' ), 10, 4 );
		add_action( 'edited_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'on_term' ), 10, 3 );
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post (WordPress 5.5+).
	 */
	public static function on_deleted_post( $post_id, $post = null ) {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( in_array( $type, array( 'post', 'tbt_match_game' ), true ) ) {
			self::flush();
		}
	}

	/**
	 * @param int    $object_id Object.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy IDs.
	 * @param string $taxonomy  Taxonomy.
	 */
	public static function on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			self::flush();
		}
	}

	/**
	 * @param int    $term_id  Term.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_term( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			self::flush();
		}
	}

	/**
	 * The automatic rows, cached for an hour.
	 *
	 * @return array array( 'rows' => array[], 'read' => string[] )
	 */
	public static function auto() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['rows'], $cached['read'] ) ) {
			return $cached;
		}
		$fresh = TBTST_Sources::all();
		set_transient( self::TRANSIENT, $fresh, HOUR_IN_SECONDS );
		return $fresh;
	}

	/**
	 * Every row, merged and sorted by title.
	 *
	 * @param bool $include_hidden Admin wants hidden rows too.
	 * @return array[] key, source, source_id, title, category, tags[], level, url, is_tool, hidden, edited, auto (source values or null), import_ref
	 */
	public static function merged( $include_hidden ) {
		$overrides = array();
		$manual    = array();
		foreach ( TBTST_DB::all() as $r ) {
			if ( 'manual' === $r->source ) {
				$manual[] = $r;
			} else {
				$overrides[ $r->source . ':' . (int) $r->source_id ] = $r;
			}
		}

		$extras = self::extras();
		$out    = array();
		foreach ( self::auto()['rows'] as $a ) {
			$key = $a['source'] . ':' . (int) $a['source_id'];
			$o   = $overrides[ $key ] ?? null;
			$row = array(
				'key'        => $key,
				'source'     => $a['source'],
				'source_id'  => (int) $a['source_id'],
				'title'      => $a['title'],
				'category'   => $a['category'],
				'tags'       => $a['tags'],
				'level'      => $a['level'],
				'url'        => $a['url'],
				'hidden'     => false,
				'edited'     => false,
				'auto'       => array(
					'title'    => $a['title'],
					'category' => $a['category'],
					'tags'     => $a['tags'],
					'level'    => $a['level'],
				),
				'import_ref' => '',
			);
			if ( $o ) {
				if ( null !== $o->title ) {
					$row['title'] = (string) $o->title;
				}
				if ( null !== $o->category ) {
					$row['category'] = (string) $o->category;
				}
				if ( null !== $o->tags ) {
					$row['tags'] = TBTST_Format::split_tags( $o->tags );
				}
				if ( null !== $o->level ) {
					$row['level'] = TBTST_Format::valid_level( $o->level );
				}
				$row['edited'] = null !== $o->title || null !== $o->category || null !== $o->tags || null !== $o->level;
				$row['hidden'] = (bool) $o->hidden;
			}
			$out[] = $row;
		}

		foreach ( $manual as $m ) {
			$out[] = array(
				'key'        => 'manual:' . (int) $m->id,
				'source'     => 'manual',
				'source_id'  => (int) $m->id,
				'title'      => (string) $m->title,
				'category'   => (string) $m->category,
				'tags'       => TBTST_Format::split_tags( $m->tags ),
				'level'      => TBTST_Format::valid_level( $m->level ),
				'url'        => (string) $m->url,
				'hidden'     => (bool) $m->hidden,
				'edited'     => false,
				'auto'       => null,
				'import_ref' => (string) $m->import_ref,
			);
		}

		if ( ! $include_hidden ) {
			$out = array_values( array_filter( $out, static function ( $r ) {
				return ! $r['hidden'];
			} ) );
		}
		foreach ( $out as &$r ) {
			$r['is_tool'] = in_array( $r['category'], TBTST_Format::TOOLS, true ) || in_array( $r['category'], $extras, true );
		}
		unset( $r );

		usort( $out, static function ( $a, $b ) {
			$c = strcmp( TBTST_Format::fold( $a['title'] ), TBTST_Format::fold( $b['title'] ) );
			return 0 !== $c ? $c : strcmp( $a['key'], $b['key'] );
		} );
		return $out;
	}

	/**
	 * One merged row, hidden or not.
	 *
	 * @param string $key Row key.
	 * @return array|null
	 */
	public static function one( $key ) {
		foreach ( self::merged( true ) as $r ) {
			if ( $r['key'] === $key ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * The automatic row with this source and ID, as the source has it.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Item ID.
	 * @return array|null
	 */
	public static function auto_row( $source, $source_id ) {
		foreach ( self::auto()['rows'] as $a ) {
			if ( $a['source'] === $source && (int) $a['source_id'] === (int) $source_id ) {
				return $a;
			}
		}
		return null;
	}

	/**
	 * Delete override rows whose item no longer exists. Only for sources
	 * that were read and gave rows: a plugin switched off, or a deck page
	 * not set, must not wipe Mariusz's edits.
	 *
	 * @return int Rows deleted.
	 */
	public static function clean_orphans() {
		$auto  = self::auto();
		$live  = array();
		$count = array();
		foreach ( $auto['rows'] as $a ) {
			$live[ $a['source'] . ':' . (int) $a['source_id'] ] = true;
			$count[ $a['source'] ]                               = ( $count[ $a['source'] ] ?? 0 ) + 1;
		}
		$deleted = 0;
		foreach ( TBTST_DB::all() as $r ) {
			if ( 'manual' === $r->source || ! in_array( $r->source, $auto['read'], true ) || empty( $count[ $r->source ] ) ) {
				continue;
			}
			if ( ! isset( $live[ $r->source . ':' . (int) $r->source_id ] ) && TBTST_DB::delete( (int) $r->id ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * The category list, in dropdown order.
	 *
	 * @return array array( 'tools' => string[], 'extras' => string[], 'wp' => string[] )
	 */
	public static function categories() {
		$wp    = array();
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$wp[] = TBTST_Sources::decode( $t->name );
		}
		$wp = array_values( array_unique( $wp ) );
		usort( $wp, static function ( $a, $b ) {
			return strcmp( TBTST_Format::fold( $a ), TBTST_Format::fold( $b ) );
		} );
		return array(
			'tools'  => TBTST_Format::TOOLS,
			'extras' => self::extras(),
			'wp'     => $wp,
		);
	}

	/**
	 * Every category name, flat.
	 *
	 * @return string[]
	 */
	public static function category_names() {
		$c = self::categories();
		return array_values( array_unique( array_merge( $c['tools'], $c['extras'], $c['wp'] ) ) );
	}

	/**
	 * @return string[]
	 */
	private static function extras() {
		return array_values( array_map( 'strval', TBTST_Settings::get()['extra_cats'] ) );
	}
}
