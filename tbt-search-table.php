<?php
/**
 * Plugin Name:       TBT Search Table
 * Description:       The VIP page's advanced search: lessons and open activities, filled in from the site, with edits, hidden rows and manual rows. Replaces wpDataTables table 185.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            The Blue Tree
 * Text Domain:       tbt-search-table
 */

defined( 'ABSPATH' ) || exit;

define( 'TBTST_VERSION', '0.1.1' );
define( 'TBTST_DB_VERSION', '1' );
define( 'TBTST_FILE', __FILE__ );
define( 'TBTST_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBTST_URL', plugin_dir_url( __FILE__ ) );

require_once TBTST_DIR . 'includes/class-tbtst-format.php';
require_once TBTST_DIR . 'includes/class-tbtst-db.php';
require_once TBTST_DIR . 'includes/class-tbtst-settings.php';
require_once TBTST_DIR . 'includes/class-tbtst-sources.php';
require_once TBTST_DIR . 'includes/class-tbtst-rows.php';
require_once TBTST_DIR . 'includes/class-tbtst-import.php';
require_once TBTST_DIR . 'includes/class-tbtst-rest.php';
require_once TBTST_DIR . 'includes/class-tbtst-admin.php';
require_once TBTST_DIR . 'includes/class-tbtst-shortcode.php';

register_activation_hook( __FILE__, array( 'TBTST_DB', 'install' ) );

/**
 * Register this plugin on the TBT Hub Overview page. A filter on Hub's own
 * hook: when Hub is absent nothing applies it.
 *
 * @param array $items Existing hub items.
 * @return array
 */
function tbtst_register_hub_item( $items ) {
	$items   = is_array( $items ) ? $items : array();
	$items[] = array(
		'slug'        => 'tbt-search-table',
		'title'       => 'TBT Search Table',
		'description' => 'The VIP page\'s advanced search: lessons and open activities',
		'capability'  => 'manage_options',
		'url'         => admin_url( 'admin.php?page=' . TBTST_Admin::SLUG ),
	);
	return $items;
}
add_filter( 'tbt_hub_items', 'tbtst_register_hub_item' );

// Hub and the four tools are checked when they are used, never here, so
// none of them being missing can stop this plugin loading.
add_action(
	'plugins_loaded',
	static function () {
		TBTST_DB::maybe_upgrade();
		TBTST_Rows::register();
		TBTST_Admin::register();
		TBTST_Rest::register();
		TBTST_Shortcode::register();
	}
);
