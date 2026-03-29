<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

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
