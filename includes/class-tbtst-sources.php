<?php
/**
 * Automatic rows: lesson posts and the open items of four tools.
 *
 * Read-only. Each tool is checked when it is read, never at load, and each
 * source runs in its own try/catch: a failing source logs one line and adds
 * no rows, and the others still render.
 *
 * What "open" means per tool (Phase 0, docs/PHASE-0.md):
 *   Swipe          deck_type 'open', status not 'draft'
 *   Matching Game  published tbt_match_game posts (the plugin has no class-only state)
 *   Drill          status 'published' with no class ("no class means an open drill")
 *   Test           status 'published' and access 'open'
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Sources {

	/** Source key => admin label. */
	const LABELS = array(
		'lesson' => 'Lesson',
		'swipe'  => 'Swipe',
		'mg'     => 'Matching Game',
		'drill'  => 'Drill',
		'test'   => 'Test',
		'manual' => 'Manual',
	);

	/** Tool source => the category its rows use. */
	const TOOL_CATEGORY = array(
		'swipe' => 'Swipe',
		'mg'    => 'Matching Game',
		'drill' => 'Drill',
		'test'  => 'Test',
	);

	/**
	 * Every automatic row, plus which sources were read successfully.
	 *
	 * @return array array( 'rows' => array[], 'read' => string[] )
	 */
	public static function all() {
		$rows = array();
		$read = array();
		foreach ( array( 'lesson', 'swipe', 'mg', 'drill', 'test' ) as $source ) {
			try {
				$got = call_user_func( array( __CLASS__, $source ) );
				if ( null === $got ) {
					continue; // The plugin is not active.
				}
				$read[] = $source;
				foreach ( $got as $row ) {
					$rows[] = $row;
				}
			} catch ( Throwable $e ) {
				error_log( 'TBT Search Table: the ' . $source . ' source failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
		return array(
			'rows' => $rows,
			'read' => $read,
		);
	}

	/* ---------- Lessons ---------- */

	/**
	 * @return array[]
	 */
	public static function lesson() {
		$settings   = TBTST_Settings::get();
		$lesson_ids = array_map( 'intval', $settings['lesson_cats'] );
		$never      = array_fill_keys( array_map( 'intval', $settings['never_cats'] ), true );
		if ( ! $lesson_ids ) {
			return array();
		}

		$q   = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'category__in'           => $lesson_ids,
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_map( 'intval', $q->posts );
		if ( ! $ids ) {
			return array();
		}

		// One query each for posts, terms and meta, never one per post.
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, false );
		}
		update_object_term_cache( $ids, 'post' );
		update_meta_cache( 'post', $ids );

		$parents = array();
		$names   = array();
		$terms   = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$parents[ (int) $t->term_id ] = (int) $t->parent;
			$names[ (int) $t->term_id ]   = $t->name;
		}
		$is_lesson = array_fill_keys( $lesson_ids, true );

		$rows = array();
		foreach ( $ids as $id ) {
			$cats = get_object_term_cache( $id, 'category' );
			$cats = is_array( $cats ) ? wp_list_pluck( $cats, 'term_id' ) : array();
			$cats = array_map( 'intval', $cats );
			if ( array_intersect_key( array_flip( $cats ), $never ) ) {
				continue;
			}
			$mine = array_values( array_filter( $cats, static function ( $c ) use ( $is_lesson ) {
				return isset( $is_lesson[ $c ] );
			} ) );
			if ( ! $mine ) {
				continue;
			}

			$tags = get_object_term_cache( $id, 'post_tag' );
			$tags = is_array( $tags ) ? array_map( array( __CLASS__, 'decode' ), wp_list_pluck( $tags, 'name' ) ) : array();
			$tags = TBTST_Format::sort_tags( $tags );

			$level = TBTST_Format::cef_level( $tags );
			if ( '' === $level ) {
				$level = TBTST_Format::valid_level( get_post_meta( $id, '_tbtlb_level', true ) );
			}

			$post   = get_post( $id );
			$rows[] = array(
				'source'    => 'lesson',
				'source_id' => $id,
				'title'     => self::decode( $post ? $post->post_title : '' ),
				'category'  => self::decode( self::pick_category( $mine, $parents, $names ) ),
				'tags'      => $tags,
				'level'     => $level,
				'url'       => (string) get_permalink( $id ),
			);
		}
		return $rows;
	}

	/**
	 * The one category a lesson shows: a child wins over its ancestor, then
	 * A–Z.
	 *
	 * @param int[] $cats    The post's lesson category IDs.
	 * @param array $parents term_id => parent.
	 * @param array $names   term_id => name.
	 * @return string
	 */
	public static function pick_category( array $cats, array $parents, array $names ) {
		$ancestors = array();
		foreach ( $cats as $c ) {
			$p    = $parents[ $c ] ?? 0;
			$hops = 0;
			while ( $p && $hops++ < 20 ) {
				$ancestors[ $p ] = true;
				$p               = $parents[ $p ] ?? 0;
			}
		}
		$keep = array_values( array_filter( $cats, static function ( $c ) use ( $ancestors ) {
			return ! isset( $ancestors[ $c ] );
		} ) );
		$keep = $keep ? $keep : $cats;
		$list = array();
		foreach ( $keep as $c ) {
			$list[] = (string) ( $names[ $c ] ?? '' );
		}
		usort( $list, static function ( $a, $b ) {
			return strcmp( TBTST_Format::fold( $a ), TBTST_Format::fold( $b ) );
		} );
		return (string) $list[0];
	}

	/* ---------- Tools ---------- */

	/**
	 * Open Swipe decks.
	 *
	 * @return array[]|null Null when Swipe is not active.
	 */
	public static function swipe() {
		if ( ! class_exists( 'TBTS_DB' ) || ! method_exists( 'TBTS_DB', 'deck_url' ) ) {
			return null;
		}
		global $wpdb;
		$table = TBTS_DB::sets_table();
		$sets  = $wpdb->get_results( "SELECT id, title, slug, status, level, deck_type FROM {$table} WHERE deck_type = 'open' AND status <> 'draft'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = array();
		foreach ( (array) $sets as $set ) {
			$url = (string) TBTS_DB::deck_url( $set );
			if ( '' === $url ) {
				continue;
			}
			$rows[] = self::tool_row( 'swipe', $set->id, $set->title, $set->level, $url );
		}
		return $rows;
	}

	/**
	 * Published matching games. Password-protected ones are left out: they
	 * are not open to anyone with the link.
	 *
	 * @return array[]|null
	 */
	public static function mg() {
		if ( ! post_type_exists( 'tbt_match_game' ) ) {
			return null;
		}
		$q   = new WP_Query(
			array(
				'post_type'              => 'tbt_match_game',
				'post_status'            => 'publish',
				'has_password'           => false,
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);
		$ids = array_map( 'intval', $q->posts );
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, false );
		}
		update_meta_cache( 'post', $ids );

		$rows = array();
		foreach ( $ids as $id ) {
			$data  = get_post_meta( $id, '_tbtmg_game_data', true );
			$level = is_array( $data ) && isset( $data['level'] ) && is_string( $data['level'] ) ? $data['level'] : '';
			// TBT Students' finer scale (B1.5) counts as its band.
			$level  = strtok( $level . '.', '.' );
			$post   = get_post( $id );
			$rows[] = self::tool_row( 'mg', $id, $post ? $post->post_title : '', $level, (string) get_permalink( $id ) );
		}
		return $rows;
	}

	/**
	 * Published drills with no class.
	 *
	 * @return array[]|null
	 */
	public static function drill() {
		if ( ! class_exists( 'TBTD_DB' ) || ! class_exists( 'TBTD_Urls' ) ) {
			return null;
		}
		global $wpdb;
		$table  = TBTD_DB::drills_table();
		$drills = $wpdb->get_results( "SELECT id, slug, title FROM {$table} WHERE status = 'published' AND ( class_id IS NULL OR class_id = 0 )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = array();
		foreach ( (array) $drills as $d ) {
			$url = (string) TBTD_Urls::drill_url( $d->slug );
			if ( '' !== $url ) {
				$rows[] = self::tool_row( 'drill', $d->id, $d->title, '', $url );
			}
		}
		return $rows;
	}

	/**
	 * Published tests open to guests.
	 *
	 * @return array[]|null
	 */
	public static function test() {
		if ( ! class_exists( 'TBTTM_DB' ) || ! class_exists( 'TBTTM_Urls' ) ) {
			return null;
		}
		global $wpdb;
		$table = TBTTM_DB::tests_table();
		$tests = $wpdb->get_results( "SELECT id, slug, title FROM {$table} WHERE status = 'published' AND access = 'open'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = array();
		foreach ( (array) $tests as $t ) {
			$url = (string) TBTTM_Urls::test_url( $t->slug );
			if ( '' !== $url ) {
				$rows[] = self::tool_row( 'test', $t->id, $t->title, '', $url );
			}
		}
		return $rows;
	}

	/**
	 * @param string $source Source.
	 * @param mixed  $id     Item ID.
	 * @param mixed  $title  Title.
	 * @param mixed  $level  Level.
	 * @param string $url    Link.
	 * @return array
	 */
	private static function tool_row( $source, $id, $title, $level, $url ) {
		return array(
			'source'    => $source,
			'source_id' => (int) $id,
			'title'     => self::decode( $title ),
			'category'  => self::TOOL_CATEGORY[ $source ],
			'tags'      => array(),
			'level'     => TBTST_Format::valid_level( $level ),
			'url'       => $url,
		);
	}

	/**
	 * Decode once before caching, so the front escapes exactly once.
	 *
	 * @param mixed $s Text.
	 * @return string
	 */
	public static function decode( $s ) {
		return TBTST_Format::trim( html_entity_decode( wp_specialchars_decode( (string) $s, ENT_QUOTES ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}
