<?php
/**
 * Admin-only routes under tbt-search-table/v1. There is no public route: the
 * VIP page gets its rows embedded in its own HTML.
 *
 * A row is addressed as /rows/{source}/{id}: manual/12, swipe/5 … (the
 * row key "manual:12" with the colon as a slash).
 */

defined( 'ABSPATH' ) || exit;

class TBTST_Rest {

	const NS = 'tbt-search-table/v1';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'nocache_all' ), 10, 3 );
	}

	/**
	 * @param WP_HTTP_Response $response Response.
	 * @param WP_REST_Server   $server   Server.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_HTTP_Response
	 */
	public static function nocache_all( $response, $server, $request ) {
		if ( $response instanceof WP_HTTP_Response && $request instanceof WP_REST_Request && 0 === strpos( (string) $request->get_route(), '/' . self::NS . '/' ) ) {
			foreach ( wp_get_nocache_headers() as $name => $value ) {
				if ( $value ) {
					$response->header( $name, $value );
				}
			}
		}
		return $response;
	}

	/**
	 * @return bool
	 */
	public static function can() {
		return current_user_can( 'manage_options' );
	}

	public static function routes() {
		$perm = array( __CLASS__, 'can' );
		$row  = '/rows/(?P<source>manual|lesson|swipe|mg|drill|test)/(?P<id>\d+)';

		register_rest_route( self::NS, '/rows', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'add_row' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, $row, array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'edit_row' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_row' ),
				'permission_callback' => $perm,
			),
		) );
		register_rest_route( self::NS, $row . '/hide', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'hide_row' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, $row . '/reset', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'reset_row' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/refresh', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'refresh' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/import/scan', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'scan' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/import/undo', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'undo' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/settings', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'settings' ),
			'permission_callback' => $perm,
		) );
	}

	/* ---------- Rows ---------- */

	/**
	 * POST /rows: a new manual row.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_row( WP_REST_Request $req ) {
		$f = self::fields( $req, true );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		$id = TBTST_DB::insert( $f + array( 'source' => 'manual', 'source_id' => 0, 'hidden' => 0 ) );
		if ( ! $id ) {
			return new WP_Error( 'tbtst_db', 'The row could not be saved. Try again.', array( 'status' => 500 ) );
		}
		return self::row_response( 'manual:' . $id );
	}

	/**
	 * PATCH /rows/{source}/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function edit_row( WP_REST_Request $req ) {
		$source = (string) $req['source'];
		$id     = (int) $req['id'];

		if ( 'manual' === $source ) {
			if ( ! TBTST_DB::get( $id ) ) {
				return self::gone();
			}
			$f = self::fields( $req, true );
			if ( is_wp_error( $f ) ) {
				return $f;
			}
			TBTST_DB::update( $id, $f );
			return self::row_response( 'manual:' . $id );
		}

		$src = TBTST_Rows::auto_row( $source, $id );
		if ( ! $src ) {
			return self::gone();
		}
		$f = self::fields( $req, false );
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		// A field equal to the source value follows the source (NULL), so a
		// row is only Edited when something really differs.
		$src_tags = implode( ', ', TBTST_Format::sort_tags( $src['tags'] ) );
		$new_tags = implode( ', ', TBTST_Format::sort_tags( TBTST_Format::split_tags( $f['tags'] ) ) );
		TBTST_DB::save_auto(
			$source,
			$id,
			array(
				'title'    => $f['title'] === $src['title'] ? null : $f['title'],
				'category' => $f['category'] === $src['category'] ? null : $f['category'],
				'tags'     => TBTST_Format::fold( $new_tags ) === TBTST_Format::fold( $src_tags ) ? null : $f['tags'],
				'level'    => $f['level'] === $src['level'] ? null : $f['level'],
			)
		);
		return self::row_response( $source . ':' . $id );
	}

	/**
	 * POST /rows/{source}/{id}/hide {hidden: bool}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function hide_row( WP_REST_Request $req ) {
		$source = (string) $req['source'];
		$id     = (int) $req['id'];
		$hidden = rest_sanitize_boolean( $req->get_param( 'hidden' ) );

		if ( 'manual' === $source ) {
			if ( ! TBTST_DB::get( $id ) ) {
				return self::gone();
			}
			TBTST_DB::update( $id, array( 'hidden' => $hidden ? 1 : 0 ) );
		} else {
			if ( ! TBTST_Rows::auto_row( $source, $id ) ) {
				return self::gone();
			}
			TBTST_DB::save_auto( $source, $id, array( 'hidden' => $hidden ) );
		}
		return self::row_response( $source . ':' . $id );
	}

	/**
	 * POST /rows/{source}/{id}/reset: automatic rows only.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reset_row( WP_REST_Request $req ) {
		$source = (string) $req['source'];
		$id     = (int) $req['id'];
		if ( 'manual' === $source ) {
			return new WP_Error( 'tbtst_manual', 'Manual rows have nothing to reset.', array( 'status' => 400 ) );
		}
		if ( ! TBTST_Rows::auto_row( $source, $id ) ) {
			return self::gone();
		}
		TBTST_DB::save_auto( $source, $id, array( 'title' => null, 'category' => null, 'tags' => null, 'level' => null ) );
		return self::row_response( $source . ':' . $id );
	}

	/**
	 * DELETE /rows/{source}/{id}: manual rows only.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_row( WP_REST_Request $req ) {
		if ( 'manual' !== (string) $req['source'] ) {
			return new WP_Error( 'tbtst_auto', 'Automatic rows can be hidden, not deleted.', array( 'status' => 400 ) );
		}
		$id = (int) $req['id'];
		if ( ! TBTST_DB::get( $id ) ) {
			return self::gone();
		}
		TBTST_DB::delete( $id );
		return rest_ensure_response( array( 'deleted' => 'manual:' . $id ) );
	}

	/* ---------- Refresh, import, settings ---------- */

	/**
	 * POST /refresh.
	 *
	 * @return WP_REST_Response
	 */
	public static function refresh() {
		TBTST_Rows::flush();
		$auto    = TBTST_Rows::auto();
		$cleaned = TBTST_Rows::clean_orphans();
		return rest_ensure_response( TBTST_Admin::payload() + array( 'auto_count' => count( $auto['rows'] ), 'cleaned' => $cleaned ) );
	}

	/**
	 * GET /import/scan.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function scan() {
		$scan = TBTST_Import::scan();
		return is_wp_error( $scan ) ? $scan : rest_ensure_response( $scan );
	}

	/**
	 * POST /import {rows: [{ref, category}]}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import( WP_REST_Request $req ) {
		$rows = $req->get_param( 'rows' );
		$done = TBTST_Import::import( is_array( $rows ) ? $rows : array() );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		return rest_ensure_response( $done + TBTST_Admin::payload() );
	}

	/**
	 * POST /import/undo.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function undo() {
		$done = TBTST_Import::undo();
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		return rest_ensure_response( $done + TBTST_Admin::payload() );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function settings( WP_REST_Request $req ) {
		TBTST_Settings::save(
			array(
				'lesson_cats' => $req->get_param( 'lesson_cats' ),
				'never_cats'  => $req->get_param( 'never_cats' ),
				'extra_cats'  => (string) $req->get_param( 'extra_cats' ),
				'md_hosts'    => (string) $req->get_param( 'md_hosts' ),
				'skip_cats'   => (string) $req->get_param( 'skip_cats' ),
			)
		);
		TBTST_Rows::flush();
		return rest_ensure_response( TBTST_Admin::payload() + array( 'auto_count' => count( TBTST_Rows::auto()['rows'] ), 'settings' => TBTST_Admin::settings_payload() ) );
	}

	/* ---------- Helpers ---------- */

	/**
	 * Validate the dialog's fields.
	 *
	 * @param WP_REST_Request $req    Request.
	 * @param bool            $manual Manual rows also need a link.
	 * @return array|WP_Error title, category, tags, level (+ url)
	 */
	private static function fields( WP_REST_Request $req, $manual ) {
		$title = TBTST_Format::trim( sanitize_text_field( (string) $req->get_param( 'title' ) ) );
		$cat   = TBTST_Format::trim( sanitize_text_field( (string) $req->get_param( 'category' ) ) );
		$tags  = TBTST_Format::split_tags( sanitize_text_field( (string) $req->get_param( 'tags' ) ) );
		$level = TBTST_Format::valid_level( $req->get_param( 'level' ) );

		if ( '' === $title ) {
			return new WP_Error( 'tbtst_title', 'Add a title.', array( 'status' => 400 ) );
		}
		if ( '' === $cat || ! in_array( $cat, TBTST_Rows::category_names(), true ) ) {
			return new WP_Error( 'tbtst_category', 'Pick a category.', array( 'status' => 400 ) );
		}
		$out = array(
			'title'    => mb_substr( $title, 0, 255 ),
			'category' => $cat,
			'tags'     => implode( ', ', $tags ),
			'level'    => $level,
		);
		if ( $manual ) {
			$raw    = TBTST_Format::trim( (string) $req->get_param( 'url' ) );
			$url    = esc_url_raw( $raw, array( 'http', 'https' ) );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return new WP_Error( 'tbtst_url', 'Add a link that starts with http:// or https://.', array( 'status' => 400 ) );
			}
			$out['url'] = mb_substr( $url, 0, 500 );
		}
		return $out;
	}

	/**
	 * @param string $key Row key.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function row_response( $key ) {
		$row = TBTST_Rows::one( $key );
		if ( ! $row ) {
			return self::gone();
		}
		return rest_ensure_response( array( 'row' => TBTST_Admin::client_row( $row ) ) + TBTST_Admin::cef_payload() );
	}

	/**
	 * @return WP_Error
	 */
	private static function gone() {
		return new WP_Error( 'tbtst_gone', 'That row no longer exists. Press Refresh now.', array( 'status' => 404 ) );
	}
}
