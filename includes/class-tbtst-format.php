<?php
/**
 * Pure helpers: no WordPress function is called here, so the whole class is
 * covered by tests/test-format.php without loading WordPress.
 *
 * fold() is the one comparison key for search, category matching and
 * duplicate tags. table.js carries a copy of it; keep the two in step.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Format {

	/** The six CEF levels, in order. */
	const LEVELS = array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' );

	/** The tool names, in the order the dropdowns list them. */
	const TOOLS = array( 'Swipe', 'Matching Game', 'Drill', 'Test', 'Multidecker' );

	/** Old table 185 category => the category it means now. Fixed in 0.1.0. */
	const ALIASES = array(
		'Flashcard Sets' => 'Multidecker',
		'Games'          => 'Language Games',
	);

	/** Query keys that identify a page, kept by normalise_url(). */
	const KEEP_QUERY = array( 'p', 'page_id', 'deck', 'test', 'drill' );

	/** Accents that Normalizer may not be there to strip. */
	const ACCENTS = array(
		'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
		'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
		'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
		'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
		'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
		'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
		'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
	);

	/**
	 * Lower-case, no accents, ł → l, whitespace collapsed and trimmed.
	 *
	 * @param mixed $s Text.
	 * @return string
	 */
	public static function fold( $s ) {
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		$s = strtr( $s, self::ACCENTS );
		if ( class_exists( 'Normalizer' ) ) {
			$n = Normalizer::normalize( $s, Normalizer::FORM_D );
			if ( is_string( $n ) ) {
				$s = (string) preg_replace( '/\p{Mn}+/u', '', $n );
			}
		}
		$s = (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $s );
		return trim( $s );
	}

	/**
	 * The level from the first tag that is a CEF level tag.
	 *
	 * @param array $tags Tag names.
	 * @return string 'A1'…'C2' or ''.
	 */
	public static function cef_level( array $tags ) {
		foreach ( $tags as $tag ) {
			$tag = str_replace( "\xC2\xA0", ' ', (string) $tag );
			if ( preg_match( '/^\s*cef\s*(a1|a2|b1|b2|c1|c2)\s*$/i', $tag, $m ) ) {
				return strtoupper( $m[1] );
			}
		}
		return '';
	}

	/**
	 * Split a tag string on | , or ; and drop empties and duplicates. The
	 * first spelling of a duplicate is kept.
	 *
	 * @param mixed $s Tags as one string.
	 * @return string[]
	 */
	public static function split_tags( $s ) {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/[|,;]/', (string) $s ) as $part ) {
			$part = self::trim( $part );
			$key  = self::fold( $part );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $part;
		}
		return $out;
	}

	/**
	 * Tags A–Z, ignoring case and accents.
	 *
	 * @param array $tags Tag names.
	 * @return string[]
	 */
	public static function sort_tags( array $tags ) {
		$tags = array_values( array_map( 'strval', $tags ) );
		usort(
			$tags,
			static function ( $a, $b ) {
				$c = strcmp( self::fold( $a ), self::fold( $b ) );
				return 0 !== $c ? $c : strcmp( $a, $b );
			}
		);
		return $tags;
	}

	/**
	 * The URL in a table 185 link cell: the part before `||`, else the first
	 * href, else the cell itself. HTML-decoded and trimmed.
	 *
	 * @param mixed $s Cell.
	 * @return string
	 */
	public static function href( $s ) {
		if ( null === $s ) {
			return '';
		}
		$s = (string) $s;
		if ( false !== strpos( $s, '||' ) ) {
			$s = substr( $s, 0, (int) strpos( $s, '||' ) );
		} elseif ( preg_match( '/href\s*=\s*(["\'])(.*?)\1/is', $s, $m ) ) {
			$s = $m[2];
		}
		$s = self::trim( html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $s || 0 === strcasecmp( $s, 'null' ) || preg_match( '/^</', $s ) ) {
			return '';
		}
		return $s;
	}

	/**
	 * A host without `www.`, lower-cased.
	 *
	 * @param mixed $host Host.
	 * @return string
	 */
	public static function host( $host ) {
		$host = strtolower( self::trim( (string) $host ) );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * A comparison form of a URL, for matching table 185 links to the site's
	 * own links. http and https count as the same link; the path is decoded
	 * and lower-cased (WordPress slugs are lower-case); only the query keys
	 * that name a page are kept.
	 *
	 * @param mixed $url URL.
	 * @return string
	 */
	public static function normalise_url( $url ) {
		$url = self::trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		$parts = parse_url( $url );
		if ( false === $parts ) {
			return mb_strtolower( $url, 'UTF-8' );
		}
		$host = isset( $parts['host'] ) ? self::host( $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? mb_strtolower( rawurldecode( $parts['path'] ), 'UTF-8' ) : '';
		$path = rtrim( $path, '/' );

		$query = '';
		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
			$keep = array();
			foreach ( self::KEEP_QUERY as $k ) {
				if ( isset( $q[ $k ] ) && is_scalar( $q[ $k ] ) && '' !== (string) $q[ $k ] ) {
					$keep[ $k ] = (string) $q[ $k ];
				}
			}
			ksort( $keep );
			$query = $keep ? '?' . http_build_query( $keep ) : '';
		}

		if ( '' === $host ) {
			return $path . $query;
		}
		return 'https://' . $host . $path . $query;
	}

	/**
	 * What a table 185 link points to.
	 *
	 * @param string $url       URL.
	 * @param string $site_host This site's host.
	 * @param array  $md_hosts  Multidecker hosts.
	 * @return string multidecker | file | draft | site | other
	 */
	public static function kind( $url, $site_host, array $md_hosts ) {
		$parts = parse_url( self::trim( (string) $url ) );
		$parts = is_array( $parts ) ? $parts : array();
		$host  = isset( $parts['host'] ) ? self::host( $parts['host'] ) : '';
		$path  = isset( $parts['path'] ) ? strtolower( $parts['path'] ) : '';
		$site  = self::host( $site_host );

		if ( '' !== $host && in_array( $host, array_map( array( __CLASS__, 'host' ), $md_hosts ), true ) ) {
			return 'multidecker';
		}
		if ( false !== strpos( $path, '/wp-content/uploads/' ) || '.pdf' === substr( $path, -4 ) ) {
			return 'file';
		}
		$on_site = '' === $host || $host === $site;
		if ( $on_site && isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
			if ( isset( $q['preview'] ) && 'true' === strtolower( (string) $q['preview'] ) ) {
				return 'draft';
			}
		}
		return $on_site ? 'site' : 'other';
	}

	/**
	 * The comparison key of a category name: decoded, & → and, folded.
	 *
	 * @param mixed $s Category name.
	 * @return string
	 */
	public static function cat_key( $s ) {
		$s = html_entity_decode( html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( '&', ' and ', $s );
		return self::fold( $s );
	}

	/**
	 * The current category a table 185 category value means. Each
	 * comma-separated part is tried in order: an alias first, then a name.
	 *
	 * @param mixed $text    Old category value.
	 * @param array $names   Current category names.
	 * @param array $aliases Old name => current name.
	 * @return string A name from $names, or ''.
	 */
	public static function match_category( $text, array $names, array $aliases = self::ALIASES ) {
		$by_key = array();
		foreach ( $names as $name ) {
			$k = self::cat_key( $name );
			if ( '' !== $k && ! isset( $by_key[ $k ] ) ) {
				$by_key[ $k ] = (string) $name;
			}
		}
		$alias_keys = array();
		foreach ( $aliases as $from => $to ) {
			$alias_keys[ self::cat_key( $from ) ] = self::cat_key( $to );
		}

		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( explode( ',', $text ) as $part ) {
			$k = self::cat_key( $part );
			if ( '' === $k ) {
				continue;
			}
			if ( isset( $alias_keys[ $k ] ) && isset( $by_key[ $alias_keys[ $k ] ] ) ) {
				return $by_key[ $alias_keys[ $k ] ];
			}
			if ( isset( $by_key[ $k ] ) ) {
				return $by_key[ $k ];
			}
		}
		return '';
	}

	/**
	 * Does any comma-separated part of $text match one of $list (by cat_key)?
	 *
	 * @param mixed $text Category value.
	 * @param array $list Category names.
	 * @return bool
	 */
	public static function category_in( $text, array $list ) {
		$keys = array();
		foreach ( $list as $name ) {
			$keys[ self::cat_key( $name ) ] = true;
		}
		unset( $keys[''] );
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( explode( ',', $text ) as $part ) {
			if ( isset( $keys[ self::cat_key( $part ) ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A single CEF level, or ''.
	 *
	 * @param mixed $s Level.
	 * @return string
	 */
	public static function valid_level( $s ) {
		$s = strtoupper( self::trim( (string) $s ) );
		return in_array( $s, self::LEVELS, true ) ? $s : '';
	}

	/**
	 * Non-empty trimmed lines, duplicates dropped (by fold).
	 *
	 * @param mixed $text Text, one value per line.
	 * @return string[]
	 */
	public static function lines( $text ) {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/\R/u', (string) $text ) as $line ) {
			$line = self::trim( $line );
			$key  = self::fold( $line );
			if ( '' !== $key && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$out[]        = $line;
			}
		}
		return $out;
	}

	/**
	 * trim() that also removes non-breaking spaces.
	 *
	 * @param mixed $s Text.
	 * @return string
	 */
	public static function trim( $s ) {
		return trim( str_replace( "\xC2\xA0", ' ', (string) $s ) );
	}
}
