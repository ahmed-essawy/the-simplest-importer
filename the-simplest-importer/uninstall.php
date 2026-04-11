<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TheSimplestImporter
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// cspell:ignore wpdb

/* Clean up transients created by the plugin. */
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_tsi_csv_data_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_tsi_csv_data_' ) . '%'
	)
);

/* Unschedule all cron events for scheduled imports */
$schedules = get_option( 'tsi_scheduled_imports', array() );
if ( is_array( $schedules ) ) {
	foreach ( $schedules as $id => $schedule ) {
		$timestamp = wp_next_scheduled( 'tsi_scheduled_import', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_import', array( $id ) );
		}
	}
}

/* Clean up rollback data saved per import. */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'tsi_import_rollback_' ) . '%'
	)
);

/* Clean up plugin options added in v1.1.0 */
delete_option( 'tsi_import_history' );
delete_option( 'tsi_mapping_profiles' );
delete_option( 'tsi_scheduled_imports' );

/* Unschedule all cron events for scheduled exports */
$export_schedules = get_option( 'tsi_scheduled_exports', array() );
if ( is_array( $export_schedules ) ) {
	foreach ( $export_schedules as $id => $schedule ) {
		$timestamp = wp_next_scheduled( 'tsi_scheduled_export', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_export', array( $id ) );
		}
	}
}

/* Clean up plugin options added in v1.2.0 */
delete_option( 'tsi_scheduled_exports' );
