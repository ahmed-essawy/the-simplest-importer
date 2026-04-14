<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SmartlyImportExport
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
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_tsi_csv_data_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_tsi_csv_data_' ) . '%',
		$wpdb->esc_like( '_transient_smie_csv_data_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_smie_csv_data_' ) . '%'
	)
);

/* Unschedule all cron events for scheduled imports */
$import_schedule_sets = array(
	get_option( 'tsi_scheduled_imports', array() ),
	get_option( 'smie_scheduled_imports', array() ),
);

foreach ( $import_schedule_sets as $schedules ) {
	if ( ! is_array( $schedules ) ) {
		continue;
	}

	foreach ( $schedules as $id => $schedule ) {
		$timestamp = wp_next_scheduled( 'tsi_scheduled_import', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_import', array( $id ) );
		}

		$timestamp = wp_next_scheduled( 'smie_scheduled_import', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'smie_scheduled_import', array( $id ) );
		}
	}
}

/* Clean up rollback data saved per import. */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'tsi_import_rollback_' ) . '%',
		$wpdb->esc_like( 'smie_import_rollback_' ) . '%'
	)
);

/* Clean up plugin options added in v1.1.0 */
delete_option( 'tsi_import_history' );
delete_option( 'tsi_mapping_profiles' );
delete_option( 'tsi_scheduled_imports' );
delete_option( 'smie_import_history' );
delete_option( 'smie_mapping_profiles' );
delete_option( 'smie_scheduled_imports' );

/* Unschedule all cron events for scheduled exports */
$export_schedule_sets = array(
	get_option( 'tsi_scheduled_exports', array() ),
	get_option( 'smie_scheduled_exports', array() ),
);

foreach ( $export_schedule_sets as $export_schedules ) {
	if ( ! is_array( $export_schedules ) ) {
		continue;
	}

	foreach ( $export_schedules as $id => $schedule ) {
		$timestamp = wp_next_scheduled( 'tsi_scheduled_export', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_export', array( $id ) );
		}

		$timestamp = wp_next_scheduled( 'smie_scheduled_export', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'smie_scheduled_export', array( $id ) );
		}
	}
}

/* Clean up plugin options added in v1.2.0 */
delete_option( 'tsi_scheduled_exports' );
delete_option( 'smie_scheduled_exports' );
