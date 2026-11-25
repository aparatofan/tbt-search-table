<?php
/**
 * The Settings tab's values, with their defaults.
 *
 * Categories are stored by term ID (the checkboxes). Until the settings are
 * first saved, the defaults are matched to the site's categories by name.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Settings {

	const OPTION = 'tbtst_settings';

	/** The 23 lesson categories with banner icons (the approved prototype). */
	const DEFAULT_LESSON_CATEGORIES = array(
		'Better communicator', 'Better writer', 'Business English', 'Culture', 'English grammar',
		'Entertain Yourself', 'Everyday English', 'Health and fitness', 'How to learn', 'How to say it 1',
		'How to say it 3', 'Learn from TED', 'Learn more words', 'Man and Society', 'Personal productivity',
		'Science stuff', 'Surprise me', 'Talking Picture', 'Tests and Quizzes', 'The arts',
		'Travel and nature', 'What do you think', 'Wise quotes',
	);

	const DEFAULT_NEVER = array( 'darmowa lekcja' );

	const DEFAULT_EXTRAS = array( 'Language Games', 'Mind-maps' );

	const DEFAULT_MD_HOSTS = array( 'multidecker.com', 'squarewords.arturdelura.com' );

	const DEFAULT_SKIP = array( 'Handouts', 'Handout', 'Video Library', 'Audio library', 'Articles' );

	/** @var array|null */
	private static $cache = null;

	/**
	 * All settings, defaults filled in.
	 *
	 * @return array lesson_cats int[], never_cats int[], extra_cats string[], md_hosts string[], skip_cats string[], saved bool
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( self::OPTION, null );
		$saved  = is_array( $stored );
		$stored = $saved ? $stored : array();

		self::$cache = array(
			'lesson_cats' => isset( $stored['lesson_cats'] ) ? array_map( 'intval', (array) $stored['lesson_cats'] ) : self::ids_by_name( self::DEFAULT_LESSON_CATEGORIES ),
			'never_cats'  => isset( $stored['never_cats'] ) ? array_map( 'intval', (array) $stored['never_cats'] ) : self::ids_by_name( self::DEFAULT_NEVER ),
			'extra_cats'  => isset( $stored['extra_cats'] ) ? array_values( (array) $stored['extra_cats'] ) : self::DEFAULT_EXTRAS,
			'md_hosts'    => isset( $stored['md_hosts'] ) ? array_values( (array) $stored['md_hosts'] ) : self::DEFAULT_MD_HOSTS,
			'skip_cats'   => isset( $stored['skip_cats'] ) ? array_values( (array) $stored['skip_cats'] ) : self::DEFAULT_SKIP,
			'saved'       => $saved,
		);
		return self::$cache;
	}

	/**
	 * Validate and store. Term IDs that are not categories are dropped.
	 *
	 * @param array $in Raw values.
	 * @return array The stored settings.
	 */
	public static function save( array $in ) {
		$valid = array_map( 'intval', (array) get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) ) );
		$ids   = static function ( $v ) use ( $valid ) {
			$v = array_unique( array_map( 'intval', is_array( $v ) ? $v : array() ) );
			return array_values( array_intersect( $v, $valid ) );
		};
		$text  = static function ( $v ) {
			return TBTST_Format::lines( is_array( $v ) ? implode( "\n", $v ) : (string) $v );
		};

		$hosts = array();
		foreach ( $text( $in['md_hosts'] ?? '' ) as $h ) {
			$h = preg_replace( '#^[a-z]+://#i', '', $h );
			$h = TBTST_Format::host( strtok( (string) $h, '/' ) );
			if ( '' !== $h ) {
				$hosts[ $h ] = $h;
			}
		}

		$extras = array();
		foreach ( $text( $in['extra_cats'] ?? '' ) as $e ) {
			$extras[] = sanitize_text_field( $e );
		}

		$data = array(
			'lesson_cats' => $ids( $in['lesson_cats'] ?? array() ),
			'never_cats'  => $ids( $in['never_cats'] ?? array() ),
			'extra_cats'  => array_values( array_filter( $extras, 'strlen' ) ),
			'md_hosts'    => array_values( $hosts ),
			'skip_cats'   => array_map( 'sanitize_text_field', $text( $in['skip_cats'] ?? '' ) ),
		);
		update_option( self::OPTION, $data, false );
		self::$cache = null;
		return self::get();
	}

	/**
	 * Category term IDs whose names match (by cat_key).
	 *
	 * @param array $names Category names.
	 * @return int[]
	 */
	private static function ids_by_name( array $names ) {
		$want = array();
		foreach ( $names as $n ) {
			$want[ TBTST_Format::cat_key( $n ) ] = true;
		}
		$ids   = array();
		$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			if ( isset( $want[ TBTST_Format::cat_key( $t->name ) ] ) ) {
				$ids[] = (int) $t->term_id;
			}
		}
		return $ids;
	}
}
