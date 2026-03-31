<?php
/**
 * Plugin Name:       The Simplest Importer
 * Plugin URI:        https://github.com/ahmed-essawy/the-simplest-importer
 * Description:       Import, export, and manage posts and custom post types via CSV, JSON, and XML with visual column mapping and batch processing.
 * Version:           1.4.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Ahmed Essawy
 * Author URI:        https://minicad.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       the-simplest-importer
 * Domain Path:       /languages
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSI_VERSION', '1.4.0' );
define( 'TSI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSI_HISTORY_OPTION', 'tsi_import_history' );
define( 'TSI_PROFILES_OPTION', 'tsi_mapping_profiles' );
define( 'TSI_SCHEDULES_OPTION', 'tsi_scheduled_imports' );
define( 'TSI_EXPORT_SCHEDULES_OPTION', 'tsi_scheduled_exports' );

/* ------------------------------------------------------------------
 * Admin Menu — Tools submenu
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'tsi_register_admin_page' );

/**
 * Register the plugin page under Tools.
 *
 * @return void
 */
function tsi_register_admin_page() {
	add_management_page(
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Simplest Importer', 'the-simplest-importer' ),
		'manage_options',
		'the-simplest-importer',
		'tsi_render_admin_page'
	);
}

/* ------------------------------------------------------------------
 * WordPress Importer Registration — Tools → Import screen
 * ------------------------------------------------------------------ */

add_action( 'admin_init', 'tsi_register_wp_importer' );

/**
 * Register on the Tools → Import screen so the plugin appears in the
 * built-in importer list alongside other importers.
 *
 * @return void
 */
function tsi_register_wp_importer() {
	if ( ! function_exists( 'register_importer' ) ) {
		return;
	}
	register_importer(
		'the-simplest-importer',
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Import posts and custom post types from CSV, JSON, and XML files with visual column mapping.', 'the-simplest-importer' ),
		'tsi_wp_importer_dispatch'
	);
}

/**
 * Redirect from the Import screen to the dedicated plugin page.
 *
 * @return void
 */
function tsi_wp_importer_dispatch() {
	wp_safe_redirect( admin_url( 'tools.php?page=the-simplest-importer' ) );
	exit;
}

/* ------------------------------------------------------------------
 * Load includes
 * ------------------------------------------------------------------ */

require_once TSI_PLUGIN_DIR . 'includes/plugin-info.php';
require_once TSI_PLUGIN_DIR . 'includes/admin-page.php';
require_once TSI_PLUGIN_DIR . 'includes/ajax-core.php';
require_once TSI_PLUGIN_DIR . 'includes/ajax-export.php';
require_once TSI_PLUGIN_DIR . 'includes/ajax-import.php';
require_once TSI_PLUGIN_DIR . 'includes/history.php';
require_once TSI_PLUGIN_DIR . 'includes/scheduled.php';
require_once TSI_PLUGIN_DIR . 'includes/meta-box.php';
