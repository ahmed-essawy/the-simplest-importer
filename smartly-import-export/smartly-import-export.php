<?php
/**
 * Plugin Name:       Smartly Import Export
 * Plugin URI:        https://github.com/ahmed-essawy/smartly-import-export
 * Description:       Import, export, and manage posts and custom post types via CSV, JSON, and XML with visual column mapping and batch processing.
 * Version:           1.4.3
 * Requires at least: 6.3
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            Ahmed Essawy
 * Author URI:        https://minicad.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartly-import-export
 * Domain Path:       /languages
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMIE_VERSION', '1.4.3' );
define( 'SMIE_PLUGIN_FILE', __FILE__ );
define( 'SMIE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SMIE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMIE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMIE_HISTORY_OPTION', 'smie_import_history' );
define( 'SMIE_PROFILES_OPTION', 'smie_mapping_profiles' );
define( 'SMIE_SCHEDULES_OPTION', 'smie_scheduled_imports' );
define( 'SMIE_EXPORT_SCHEDULES_OPTION', 'smie_scheduled_exports' );
define( 'SMIE_LEGACY_HISTORY_OPTION', 'tsi_import_history' );
define( 'SMIE_LEGACY_PROFILES_OPTION', 'tsi_mapping_profiles' );
define( 'SMIE_LEGACY_SCHEDULES_OPTION', 'tsi_scheduled_imports' );
define( 'SMIE_LEGACY_EXPORT_SCHEDULES_OPTION', 'tsi_scheduled_exports' );
define( 'SMIE_IMPORT_DATA_TRANSIENT_PREFIX', 'smie_csv_data_' );
define( 'SMIE_LEGACY_IMPORT_DATA_TRANSIENT_PREFIX', 'tsi_csv_data_' );
define( 'SMIE_IMPORT_ROLLBACK_OPTION_PREFIX', 'smie_import_rollback_' );
define( 'SMIE_LEGACY_IMPORT_ROLLBACK_OPTION_PREFIX', 'tsi_import_rollback_' );
define( 'SMIE_IMPORT_CRON_HOOK', 'smie_scheduled_import' );
define( 'SMIE_EXPORT_CRON_HOOK', 'smie_scheduled_export' );
define( 'SMIE_LEGACY_IMPORT_CRON_HOOK', 'tsi_scheduled_import' );
define( 'SMIE_LEGACY_EXPORT_CRON_HOOK', 'tsi_scheduled_export' );

/**
 * Get an option value, falling back to the legacy option name.
 *
 * @param string $option_name        Current option name.
 * @param string $legacy_option_name Legacy option name.
 * @param mixed  $default            Default value.
 * @return mixed
 */
function smie_get_option_with_legacy( $option_name, $legacy_option_name, $default = false ) {
	$missing = new stdClass();
	$value   = get_option( $option_name, $missing );

	if ( $missing !== $value ) {
		return $value;
	}

	$value = get_option( $legacy_option_name, $missing );

	return $missing === $value ? $default : $value;
}

/**
 * Copy a legacy option into the current option name when needed.
 *
 * @param string $option_name        Current option name.
 * @param string $legacy_option_name Legacy option name.
 * @return mixed
 */
function smie_maybe_copy_legacy_option( $option_name, $legacy_option_name ) {
	$missing = new stdClass();
	$current = get_option( $option_name, $missing );

	if ( $missing !== $current ) {
		return $current;
	}

	$legacy = get_option( $legacy_option_name, $missing );
	if ( $missing === $legacy ) {
		return null;
	}

	update_option( $option_name, $legacy, false );

	return $legacy;
}

/**
 * Build an import data transient name.
 *
 * @param string $token  Transient token.
 * @param bool   $legacy Whether to use the legacy prefix.
 * @return string
 */
function smie_get_import_data_transient_name( $token, $legacy = false ) {
	$prefix = $legacy ? SMIE_LEGACY_IMPORT_DATA_TRANSIENT_PREFIX : SMIE_IMPORT_DATA_TRANSIENT_PREFIX;

	return $prefix . $token;
}

/**
 * Store parsed import data in the current transient namespace.
 *
 * @param string $token Parsed data token.
 * @param array  $data  Parsed import data.
 * @return void
 */
function smie_set_import_data_transient( $token, $data ) {
	set_transient( smie_get_import_data_transient_name( $token ), $data, smie_get_import_data_ttl() );
}

/**
 * Get parsed import data from the current or legacy transient namespace.
 *
 * @param string $token Parsed data token.
 * @return mixed
 */
function smie_get_import_data_transient( $token ) {
	$data = get_transient( smie_get_import_data_transient_name( $token ) );
	if ( false !== $data ) {
		return $data;
	}

	return get_transient( smie_get_import_data_transient_name( $token, true ) );
}

/**
 * Delete parsed import data from both transient namespaces.
 *
 * @param string $token Parsed data token.
 * @return void
 */
function smie_delete_import_data_transient( $token ) {
	delete_transient( smie_get_import_data_transient_name( $token ) );
	delete_transient( smie_get_import_data_transient_name( $token, true ) );
}

/**
 * Check whether a custom hook name uses the current or legacy plugin prefix.
 *
 * @param string $smie_hook_name Hook name to validate.
 * @return bool
 */
function smie_is_prefixed_hook_name( $smie_hook_name ) {
	return 0 === strpos( $smie_hook_name, 'smie_' ) || 0 === strpos( $smie_hook_name, 'tsi_' );
}

/**
 * Apply both legacy and current filters for compatibility.
 *
 * @param string $hook_name Filter name.
 * @param mixed  $value     Value to filter.
 * @param mixed  ...$args   Extra arguments passed to callbacks.
 * @return mixed
 */
function smie_apply_filters( $hook_name, $value, ...$args ) {
	if ( ! smie_is_prefixed_hook_name( $hook_name ) ) {
		return $value;
	}

	$legacy_hook = 0 === strpos( $hook_name, 'smie_' ) ? 'tsi_' . substr( $hook_name, 5 ) : '';

	if ( '' !== $legacy_hook && has_filter( $legacy_hook ) ) {
		$value = apply_filters( $legacy_hook, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper only forwards validated legacy plugin hooks.
	}

	return apply_filters( $hook_name, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper only forwards validated plugin hooks.
}

/**
 * Fire both current and legacy actions for compatibility.
 *
 * @param string $hook_name Action name.
 * @param mixed  ...$args   Action arguments.
 * @return void
 */
function smie_do_action( $hook_name, ...$args ) {
	if ( ! smie_is_prefixed_hook_name( $hook_name ) ) {
		return;
	}

	do_action( $hook_name, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper only forwards validated plugin hooks.

	$legacy_hook = 0 === strpos( $hook_name, 'smie_' ) ? 'tsi_' . substr( $hook_name, 5 ) : '';
	if ( '' !== $legacy_hook && has_action( $legacy_hook ) ) {
		do_action( $legacy_hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Wrapper only forwards validated legacy plugin hooks.
	}
}

/**
 * Check whether a stored schedule should be active.
 *
 * @param array $schedule Schedule configuration.
 * @return bool
 */
function smie_is_schedule_active( $schedule ) {
	return empty( $schedule['status'] ) || 'active' === $schedule['status'];
}

/**
 * Migrate a scheduled cron event from the legacy hook name.
 *
 * @param string $hook_name      Current cron hook.
 * @param string $legacy_hook    Legacy cron hook.
 * @param string $schedule_id    Schedule identifier.
 * @param array  $schedule       Stored schedule data.
 * @param string $default_period Default cron schedule.
 * @return void
 */
function smie_migrate_legacy_cron_event( $hook_name, $legacy_hook, $schedule_id, $schedule, $default_period ) {
	$args             = array( $schedule_id );
	$legacy_timestamp = wp_next_scheduled( $legacy_hook, $args );

	if ( ! wp_next_scheduled( $hook_name, $args ) && smie_is_schedule_active( $schedule ) ) {
		$frequency = isset( $schedule['frequency'] ) ? sanitize_key( (string) $schedule['frequency'] ) : $default_period;
		$schedules = wp_get_schedules();
		$timestamp = $legacy_timestamp ? $legacy_timestamp : time();

		if ( ! isset( $schedules[ $frequency ] ) ) {
			$frequency = $default_period;
		}

		wp_schedule_event( $timestamp, $frequency, $hook_name, $args );
	}

	if ( $legacy_timestamp ) {
		wp_unschedule_event( $legacy_timestamp, $legacy_hook, $args );
	}
}

/**
 * Migrate stored options and cron events from the legacy prefix.
 *
 * @return void
 */
function smie_maybe_migrate_legacy_state() {
	smie_maybe_copy_legacy_option( SMIE_HISTORY_OPTION, SMIE_LEGACY_HISTORY_OPTION );
	smie_maybe_copy_legacy_option( SMIE_PROFILES_OPTION, SMIE_LEGACY_PROFILES_OPTION );
	$import_schedules = smie_maybe_copy_legacy_option( SMIE_SCHEDULES_OPTION, SMIE_LEGACY_SCHEDULES_OPTION );
	$export_schedules = smie_maybe_copy_legacy_option( SMIE_EXPORT_SCHEDULES_OPTION, SMIE_LEGACY_EXPORT_SCHEDULES_OPTION );

	if ( is_array( $import_schedules ) ) {
		foreach ( $import_schedules as $schedule_id => $schedule ) {
			smie_migrate_legacy_cron_event( SMIE_IMPORT_CRON_HOOK, SMIE_LEGACY_IMPORT_CRON_HOOK, $schedule_id, $schedule, 'daily' );
		}
	}

	if ( is_array( $export_schedules ) ) {
		foreach ( $export_schedules as $schedule_id => $schedule ) {
			smie_migrate_legacy_cron_event( SMIE_EXPORT_CRON_HOOK, SMIE_LEGACY_EXPORT_CRON_HOOK, $schedule_id, $schedule, 'weekly' );
		}
	}
}
add_action( 'init', 'smie_maybe_migrate_legacy_state', 5 );

/* ------------------------------------------------------------------
 * Admin Menu — Tools submenu
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'smie_register_admin_page' );

/**
 * Register the plugin page under Tools.
 *
 * @return void
 */
function smie_register_admin_page() {
	add_management_page(
		__( 'Smartly Import Export', 'smartly-import-export' ),
		__( 'Smartly Import Export', 'smartly-import-export' ),
		'manage_options',
		'smartly-import-export',
		'smie_render_admin_page'
	);
}

/* ------------------------------------------------------------------
 * WordPress Importer Registration — Tools → Import screen
 * ------------------------------------------------------------------ */

add_action( 'admin_init', 'smie_register_wp_importer' );

/**
 * Register on the Tools → Import screen so the plugin appears in the
 * built-in importer list alongside other importers.
 *
 * @return void
 */
function smie_register_wp_importer() {
	if ( ! function_exists( 'register_importer' ) ) {
		return;
	}
	register_importer(
		'smartly-import-export',
		__( 'Smartly Import Export', 'smartly-import-export' ),
		__( 'Import posts and custom post types from CSV, JSON, and XML files with visual column mapping.', 'smartly-import-export' ),
		'smie_wp_importer_dispatch'
	);
}

/**
 * Redirect from the Import screen to the dedicated plugin page.
 *
 * @return void
 */
function smie_wp_importer_dispatch() {
	wp_safe_redirect( admin_url( 'tools.php?page=smartly-import-export' ) );
	exit;
}

/* ------------------------------------------------------------------
 * Load includes
 * ------------------------------------------------------------------ */

require_once SMIE_PLUGIN_DIR . 'includes/plugin-info.php';
require_once SMIE_PLUGIN_DIR . 'includes/admin-page.php';
require_once SMIE_PLUGIN_DIR . 'includes/ajax-core.php';
require_once SMIE_PLUGIN_DIR . 'includes/ajax-export.php';
require_once SMIE_PLUGIN_DIR . 'includes/ajax-import.php';
require_once SMIE_PLUGIN_DIR . 'includes/history.php';
require_once SMIE_PLUGIN_DIR . 'includes/scheduled.php';
require_once SMIE_PLUGIN_DIR . 'includes/meta-box.php';
