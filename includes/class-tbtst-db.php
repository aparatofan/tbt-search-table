<?php
/**
 * The rows table: install, upgrade, reads and writes.
 *
 * One row per manual row, and one per automatic row that has an override or
 * is hidden. For automatic rows a NULL field means "follow the source".
 * (source, source_id) is unique for automatic rows; manual rows all have
 * source_id 0, so the index is not UNIQUE and get_auto() keeps it unique.
 */

defined( 'ABSPATH' ) || exit;

class TBTST_DB {

	const OPTION = 'tbtst_db_version';

	/** Fields a manual row owns, and an automatic row can override. */
	const FIELDS = array( 'title', 'category', 'tags', 'level' );

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tbtst_rows';
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source varchar(10) NOT NULL DEFAULT 'manual',
  source_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NULL DEFAULT NULL,
  category varchar(190) NULL DEFAULT NULL,
  tags text NULL,
  level varchar(2) NULL DEFAULT NULL,
  url varchar(500) NOT NULL DEFAULT '',
  hidden tinyint(1) NOT NULL DEFAULT 0,
  import_ref varchar(40) NOT NULL DEFAULT '',
  import_batch varchar(20) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY source_ref (source,source_id),
  KEY import_ref (import_ref)
) {$charset};"
		);
		update_option( self::OPTION, TBTST_DB_VERSION, false );
	}

	/**
	 * Install when the stored schema version differs from the code's. FTP
	 * deploys never fire activation, so this runs on plugins_loaded.
	 */
	public static function maybe_upgrade() {
		if ( (string) get_option( self::OPTION ) !== (string) TBTST_DB_VERSION ) {
			self::install();
		}
	}

	/* ---------- Reads ---------- */

	/**
	 * Every stored row.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * The override row of an automatic row, if it has one.
	 *
	 * @param string $source    lesson, swipe, mg, drill or test.
	 * @param int    $source_id Source item ID.
	 * @return object|null
	 */
	public static function get_auto( $source, $source_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source = %s AND source_id = %d ORDER BY id LIMIT 1", $source, (int) $source_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Every import_ref already used, as a set.
	 *
	 * @return array ref => true
	 */
	public static function import_refs() {
		global $wpdb;
		$table = self::table();
		$refs  = $wpdb->get_col( "SELECT import_ref FROM {$table} WHERE import_ref <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_fill_keys( array_map( 'strval', (array) $refs ), true );
	}

	/**
	 * The newest import batch and how many rows it still has.
	 *
	 * @return array|null array( 'batch' => string, 'rows' => int )
	 */
	public static function last_batch() {
		global $wpdb;
		$table = self::table();
		$batch = (string) $wpdb->get_var( "SELECT MAX(import_batch) FROM {$table} WHERE source = 'manual' AND import_batch <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( '' === $batch ) {
			return null;
		}
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source = 'manual' AND import_batch = %s", $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'batch' => $batch,
			'rows'  => $n,
		);
	}

	/* ---------- Writes ---------- */

	/**
	 * @param array $data Column => value.
	 * @return int New row ID, or 0.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$ok                 = $wpdb->insert( self::table(), $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a row. NULL values are written as SQL NULL.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Write an automatic row's overrides and hidden flag. The row is deleted
	 * when nothing is left to store, so an untouched row costs nothing.
	 *
	 * @param string $source    Source.
	 * @param int    $source_id Source item ID.
	 * @param array  $fields    Any of title, category, tags, level, hidden.
	 * @return bool
	 */
	public static function save_auto( $source, $source_id, array $fields ) {
		$row  = self::get_auto( $source, $source_id );
		$data = array();
		foreach ( self::FIELDS as $f ) {
			$data[ $f ] = array_key_exists( $f, $fields ) ? $fields[ $f ] : ( $row ? $row->$f : null );
		}
		$data['hidden'] = array_key_exists( 'hidden', $fields ) ? ( $fields['hidden'] ? 1 : 0 ) : ( $row ? (int) $row->hidden : 0 );

		$empty = ! $data['hidden'] && null === $data['title'] && null === $data['category'] && null === $data['tags'] && null === $data['level'];
		if ( $empty ) {
			return $row ? self::delete( (int) $row->id ) : true;
		}
		if ( $row ) {
			return self::update( (int) $row->id, $data );
		}
		$data['source']    = $source;
		$data['source_id'] = (int) $source_id;
		$data['url']       = '';
		return self::insert( $data ) > 0;
	}

	/**
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a batch's rows that were not touched after the import.
	 *
	 * @param string $batch Batch ID.
	 * @return array array( 'deleted' => int, 'kept' => int )
	 */
	public static function undo_batch( $batch ) {
		global $wpdb;
		$table   = self::table();
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source = 'manual' AND import_batch = %s AND updated_at = created_at", $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$kept    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source = 'manual' AND import_batch = %s", $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $kept ) {
			// The edited rows stay, out of the batch, so the next Undo reaches
			// the import before this one rather than stopping here again.
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET import_batch = '' WHERE source = 'manual' AND import_batch = %s", $batch ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return array(
			'deleted' => $deleted,
			'kept'    => $kept,
		);
	}
}
