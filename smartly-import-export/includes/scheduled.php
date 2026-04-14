<?php
/**
 * Scheduled imports and exports via WP-Cron.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * AJAX — Add scheduled import
 * ------------------------------------------------------------------ */

/**
 * Save a new scheduled import.
 */
function smie_ajax_add_schedule() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$name      = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
	$url       = isset( $_POST['csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['csv_url'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'daily';
	$profile   = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $name || ! $url || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Name, URL, and post type are required.', 'smartly-import-export' ) );
	}

	if ( ! wp_http_validate_url( $url ) ) {
		wp_send_json_error( esc_html__( 'Please enter a valid URL.', 'smartly-import-export' ) );
	}

	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'smartly-import-export' ) );
	}

	if ( $email && ! is_email( $email ) ) {
		wp_send_json_error( esc_html__( 'Please enter a valid notification email.', 'smartly-import-export' ) );
	}

	$allowed_freq = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
	if ( ! in_array( $frequency, $allowed_freq, true ) ) {
		$frequency = 'daily';
	}

	$schedules = smie_get_scheduled_imports();
	$id        = wp_generate_password( 8, false );

	$schedules[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'url'       => $url,
		'post_type' => $post_type,
		'frequency' => $frequency,
		'profile'   => $profile,
		'email'     => $email,
		'created'   => current_time( 'mysql' ),
		'last_run'  => '',
		'status'    => 'active',
	);

	update_option( SMIE_SCHEDULES_OPTION, $schedules, false );

	/* Schedule the cron event */
	if ( ! wp_next_scheduled( 'smie_scheduled_import', array( $id ) ) ) {
		wp_schedule_event( time(), $frequency, 'smie_scheduled_import', array( $id ) );
	}

	wp_send_json_success( array(
		'schedules' => $schedules,
		/* translators: %s: schedule name */
		'message'   => sprintf( esc_html__( 'Schedule "%s" created.', 'smartly-import-export' ), $name ),
	) );
}
add_action( 'wp_ajax_smie_add_schedule', 'smie_ajax_add_schedule' );

/* ------------------------------------------------------------------
 * AJAX — Delete scheduled import
 * ------------------------------------------------------------------ */

/**
 * Delete a scheduled import.
 */
function smie_ajax_delete_schedule() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$id = isset( $_POST['schedule_id'] ) ? sanitize_key( wp_unslash( $_POST['schedule_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing schedule ID.', 'smartly-import-export' ) );
	}

	$schedules = smie_get_scheduled_imports();
	if ( isset( $schedules[ $id ] ) ) {
		unset( $schedules[ $id ] );
		update_option( SMIE_SCHEDULES_OPTION, $schedules, false );

		/* Remove the cron event */
		$timestamp = wp_next_scheduled( 'smie_scheduled_import', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'smie_scheduled_import', array( $id ) );
		}

		$legacy_timestamp = wp_next_scheduled( SMIE_LEGACY_IMPORT_CRON_HOOK, array( $id ) );
		if ( $legacy_timestamp ) {
			wp_unschedule_event( $legacy_timestamp, SMIE_LEGACY_IMPORT_CRON_HOOK, array( $id ) );
		}
	}

	wp_send_json_success( array(
		'schedules' => $schedules,
		'message'   => esc_html__( 'Schedule deleted.', 'smartly-import-export' ),
	) );
}
add_action( 'wp_ajax_smie_delete_schedule', 'smie_ajax_delete_schedule' );

/* ------------------------------------------------------------------
 * WP-Cron — Execute scheduled import
 * ------------------------------------------------------------------ */

/**
 * Send an email notification for a scheduled import if an email is configured.
 *
 * @param array  $schedule The schedule config array.
 * @param string $body     The email body text.
 */
function smie_send_schedule_email( $schedule, $body ) {
	$email = ! empty( $schedule['email'] ) ? $schedule['email'] : '';
	if ( ! $email || ! is_email( $email ) ) {
		return;
	}

	/* translators: %s: schedule name */
	$subject = sprintf( esc_html__( '[%s] Scheduled Import Report', 'smartly-import-export' ), $schedule['name'] );
	wp_mail( $email, $subject, $body );
}

/**
 * Execute a scheduled import via WP-Cron.
 *
 * @param string $schedule_id The schedule ID.
 */
function smie_run_scheduled_import( $schedule_id ) {
	$schedules = smie_get_scheduled_imports();
	if ( ! isset( $schedules[ $schedule_id ] ) ) {
		return;
	}

	$schedule  = $schedules[ $schedule_id ];
	$post_type = $schedule['post_type'];
	$url       = $schedule['url'];

	/* Auto-convert Google Sheets URL */
	$url = smie_convert_google_sheets_url( $url );

	/* Fetch CSV from URL */
	$response = wp_remote_get( $url, array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'error';
		$schedules[ $schedule_id ]['last_error']  = __( 'Failed to fetch CSV from URL.', 'smartly-import-export' );
		update_option( SMIE_SCHEDULES_OPTION, $schedules, false );
		smie_send_schedule_email( $schedule, esc_html__( 'Failed to fetch CSV from URL.', 'smartly-import-export' ) );
		return;
	}

	$body = wp_remote_retrieve_body( $response );

	/* Write to temp file so we can reuse the file readers with format auto-detection. */
	$tmp = wp_tempnam( 'smie_sched_' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for import parsing.
	file_put_contents( $tmp, $body );

	/* Detect format from URL extension or Content-Type */
	$url_ext      = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	$format       = 'csv';
	if ( 'json' === $url_ext || false !== strpos( $content_type, 'json' ) ) {
		$format = 'json';
	} elseif ( 'xml' === $url_ext || false !== strpos( $content_type, 'xml' ) ) {
		$format = 'xml';
	}

	$parsed = smie_read_import_file( $tmp, $format );
	wp_delete_file( $tmp );

	if ( is_string( $parsed ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'error';
		$schedules[ $schedule_id ]['last_error']  = __( 'Failed to parse CSV data.', 'smartly-import-export' );
		update_option( SMIE_SCHEDULES_OPTION, $schedules, false );
		smie_send_schedule_email( $schedule, esc_html__( 'Failed to parse CSV data.', 'smartly-import-export' ) );
		return;
	}

	$headers = $parsed['headers'];
	$csv_token = $parsed['token'];

	/* Retrieve rows from transient */
	$csv_data = smie_get_import_data_transient( $csv_token );
	if ( ! $csv_data || empty( $csv_data['rows'] ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'empty';
		$schedules[ $schedule_id ]['last_error']  = __( 'CSV file was empty or contained no data rows.', 'smartly-import-export' );
		update_option( SMIE_SCHEDULES_OPTION, $schedules, false );
		smie_send_schedule_email( $schedule, esc_html__( 'CSV file was empty or contained no data rows.', 'smartly-import-export' ) );
		return;
	}

	$rows = $csv_data['rows'];

	/* Build mapping from profile or auto-match */
	$mapping = array();
	if ( ! empty( $schedule['profile'] ) ) {
		$profiles = smie_get_mapping_profiles();
		if ( isset( $profiles[ $schedule['profile'] ] ) ) {
			$mapping = $profiles[ $schedule['profile'] ]['mapping'];
		}
	}

	if ( empty( $mapping ) ) {
		/* Auto-match by header name */
		$fields = smie_get_post_type_fields( $post_type );
		foreach ( $headers as $col_index => $header ) {
			$header_clean = sanitize_title( trim( $header ) );
			foreach ( $fields as $field_key => $field_label ) {
				if ( sanitize_title( $field_key ) === $header_clean || sanitize_title( $field_label ) === $header_clean ) {
					$mapping[ $field_key ] = array(
						'source' => 'csv',
						'col'    => $col_index,
					);
					break;
				}
			}
		}
	}

	if ( empty( $mapping ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'no_mapping';
		$schedules[ $schedule_id ]['last_error']  = __( 'No column mapping could be determined.', 'smartly-import-export' );
		update_option( SMIE_SCHEDULES_OPTION, $schedules, false );
		smie_send_schedule_email( $schedule, esc_html__( 'No column mapping could be determined.', 'smartly-import-export' ) );
		return;
	}

	$clean_map = smie_sanitize_mapping( $mapping, count( $headers ) );

	$inserted       = 0;
	$updated        = 0;
	$skipped        = 0;
	$errors         = 0;
	$post_ids       = array();
	$history_actions = array();

	foreach ( $rows as $i => $row ) {
		$result = smie_import_single_row( $row, $i + 2, $post_type, $clean_map, false, '', '', array(), $headers, 'insert-update' );
		if ( ! empty( $result['post_id'] ) ) {
			$post_ids[] = $result['post_id'];
		}
		if ( ! empty( $result['history_action'] ) && is_array( $result['history_action'] ) ) {
			$history_actions[] = $result['history_action'];
		}
		if ( 'inserted' === $result['status'] ) {
			$inserted++;
		} elseif ( 'updated' === $result['status'] ) {
			$updated++;
		} elseif ( 'skipped' === $result['status'] ) {
			$skipped++;
		} else {
			$errors++;
		}
	}

	/* Record in history */
	$history_id = 'sched-' . $schedule_id . '-' . time();
	smie_record_import_history( $history_id, $post_type, 'scheduled', $inserted, $updated, $skipped, $errors, $post_ids, $history_actions, true );

	/* Clean up CSV transient */
	smie_delete_import_data_transient( $csv_token );

	/* Update schedule status */
	$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
	$schedules[ $schedule_id ]['last_status'] = 'success';
	$schedules[ $schedule_id ]['last_count']  = $inserted + $updated;
	$schedules[ $schedule_id ]['last_error']  = '';
	update_option( SMIE_SCHEDULES_OPTION, $schedules, false );

	/* Send email notification if configured */
	$lines = array();
	/* translators: %s: schedule name */
	$lines[] = sprintf( esc_html__( 'Schedule: %s', 'smartly-import-export' ), $schedule['name'] );
	/* translators: %s: post type slug */
	$lines[] = sprintf( esc_html__( 'Post type: %s', 'smartly-import-export' ), $post_type );
	/* translators: %d: number of inserted posts */
	$lines[] = sprintf( esc_html__( 'Inserted: %d', 'smartly-import-export' ), $inserted );
	/* translators: %d: number of updated posts */
	$lines[] = sprintf( esc_html__( 'Updated: %d', 'smartly-import-export' ), $updated );
	/* translators: %d: number of errors */
	$lines[] = sprintf( esc_html__( 'Errors: %d', 'smartly-import-export' ), $errors );
	/* translators: %s: date and time */
	$lines[] = sprintf( esc_html__( 'Completed at: %s', 'smartly-import-export' ), current_time( 'mysql' ) );
	smie_send_schedule_email( $schedule, implode( "\n", $lines ) );
}
add_action( SMIE_IMPORT_CRON_HOOK, 'smie_run_scheduled_import' );
add_action( SMIE_LEGACY_IMPORT_CRON_HOOK, 'smie_run_scheduled_import' );

/* ------------------------------------------------------------------
 * WP-Cron — Register weekly schedule interval
 * ------------------------------------------------------------------ */

/**
 * Add a weekly interval to WP-Cron schedules.
 *
 * @param array $schedules Existing cron schedules.
 * @return array
 */
function smie_add_cron_interval( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => esc_html__( 'Once Weekly', 'smartly-import-export' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'smie_add_cron_interval' );

/* ------------------------------------------------------------------
 * Helper — Scheduled exports CRUD
 * ------------------------------------------------------------------ */

/**
 * Get export schedules list.
 *
 * @return array
 */
function smie_get_export_schedules() {
	$export_schedules = smie_get_option_with_legacy( SMIE_EXPORT_SCHEDULES_OPTION, SMIE_LEGACY_EXPORT_SCHEDULES_OPTION, array() );

	return is_array( $export_schedules ) ? $export_schedules : array();
}

/* ------------------------------------------------------------------
 * AJAX — Add scheduled export
 * ------------------------------------------------------------------ */

/**
 * Save a new scheduled export.
 */
function smie_ajax_add_export_schedule() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$name      = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'weekly';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $name || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Name and post type are required.', 'smartly-import-export' ) );
	}

	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'smartly-import-export' ) );
	}

	if ( $email && ! is_email( $email ) ) {
		wp_send_json_error( esc_html__( 'Please enter a valid notification email.', 'smartly-import-export' ) );
	}

	$allowed_freq = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
	if ( ! in_array( $frequency, $allowed_freq, true ) ) {
		$frequency = 'weekly';
	}

	$export_schedules = smie_get_export_schedules();
	$id               = wp_generate_password( 8, false );

	$export_schedules[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'post_type' => $post_type,
		'frequency' => $frequency,
		'email'     => $email,
		'created'   => current_time( 'mysql' ),
		'last_run'  => '',
		'status'    => 'active',
	);

	update_option( SMIE_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

	/* Schedule the cron event */
	if ( ! wp_next_scheduled( 'smie_scheduled_export', array( $id ) ) ) {
		wp_schedule_event( time(), $frequency, 'smie_scheduled_export', array( $id ) );
	}

	wp_send_json_success( array(
		'export_schedules' => $export_schedules,
		/* translators: %s: schedule name */
		'message'          => sprintf( esc_html__( 'Export schedule "%s" created.', 'smartly-import-export' ), $name ),
	) );
}
add_action( 'wp_ajax_smie_add_export_schedule', 'smie_ajax_add_export_schedule' );

/* ------------------------------------------------------------------
 * AJAX — Delete scheduled export
 * ------------------------------------------------------------------ */

/**
 * Delete a scheduled export.
 */
function smie_ajax_delete_export_schedule() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$id = isset( $_POST['schedule_id'] ) ? sanitize_key( wp_unslash( $_POST['schedule_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing schedule ID.', 'smartly-import-export' ) );
	}

	$export_schedules = smie_get_export_schedules();
	if ( isset( $export_schedules[ $id ] ) ) {
		unset( $export_schedules[ $id ] );
		update_option( SMIE_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

		/* Remove the cron event */
		$timestamp = wp_next_scheduled( 'smie_scheduled_export', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'smie_scheduled_export', array( $id ) );
		}

		$legacy_timestamp = wp_next_scheduled( SMIE_LEGACY_EXPORT_CRON_HOOK, array( $id ) );
		if ( $legacy_timestamp ) {
			wp_unschedule_event( $legacy_timestamp, SMIE_LEGACY_EXPORT_CRON_HOOK, array( $id ) );
		}
	}

	wp_send_json_success( array(
		'export_schedules' => $export_schedules,
		'message'          => esc_html__( 'Export schedule deleted.', 'smartly-import-export' ),
	) );
}
add_action( 'wp_ajax_smie_delete_export_schedule', 'smie_ajax_delete_export_schedule' );

/* ------------------------------------------------------------------
 * WP-Cron — Execute scheduled export
 * ------------------------------------------------------------------ */

/**
 * Execute a scheduled export via WP-Cron.
 *
 * @param string $schedule_id The export schedule ID.
 */
function smie_run_scheduled_export( $schedule_id ) {
	$export_schedules = smie_get_export_schedules();
	if ( ! isset( $export_schedules[ $schedule_id ] ) ) {
		return;
	}

	$schedule  = $export_schedules[ $schedule_id ];
	$post_type = $schedule['post_type'];

	if ( ! post_type_exists( $post_type ) ) {
		$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$export_schedules[ $schedule_id ]['last_status'] = 'error';
		update_option( SMIE_EXPORT_SCHEDULES_OPTION, $export_schedules, false );
		return;
	}

	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	if ( empty( $posts ) ) {
		$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$export_schedules[ $schedule_id ]['last_status'] = 'empty';
		update_option( SMIE_EXPORT_SCHEDULES_OPTION, $export_schedules, false );
		return;
	}

	$fields    = smie_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author', 'post_parent' );

	$meta_fields = array();
	$tax_fields  = array();
	foreach ( $fields as $key => $label ) {
		if ( 0 === strpos( $key, 'meta__' ) ) {
			$meta_fields[] = substr( $key, 6 );
		} elseif ( 0 === strpos( $key, 'tax__' ) ) {
			$tax_fields[] = substr( $key, 5 );
		}
	}

	$header = $core_keys;
	foreach ( $tax_fields as $t ) {
		$header[] = 'tax__' . $t;
	}
	foreach ( $meta_fields as $m ) {
		$header[] = 'meta__' . $m;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $header );

	foreach ( $posts as $p ) {
		$row = array();
		foreach ( $core_keys as $ck ) {
			$row[] = isset( $p->$ck ) ? $p->$ck : '';
		}
		foreach ( $tax_fields as $t ) {
			$terms = wp_get_object_terms( $p->ID, $t, array( 'fields' => 'names' ) );
			$row[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
		}
		foreach ( $meta_fields as $m ) {
			$val   = get_post_meta( $p->ID, $m, true );
			$row[] = is_array( $val ) ? wp_json_encode( $val ) : ( isset( $val ) ? (string) $val : '' );
		}
		fputcsv( $output, $row );
	}

	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	/* Ensure export directory exists */
	$upload_dir  = wp_upload_dir();
	$export_dir  = $upload_dir['basedir'] . '/smie-exports';

	if ( ! file_exists( $export_dir ) ) {
		wp_mkdir_p( $export_dir );

		/* Protect directory from direct browsing */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing security files to export directory.
		file_put_contents( $export_dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing .htaccess to protect export directory.
		file_put_contents( $export_dir . '/.htaccess', "Options -Indexes\nDeny from all\n" );
	}

	/* Write the CSV file */
	$filename = sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d-His' ) . '.csv' );
	$filepath = $export_dir . '/' . $filename;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing scheduled export CSV to disk.
	file_put_contents( $filepath, $csv_string );

	/* Auto-clean exports older than 7 days */
	smie_cleanup_old_exports( $export_dir );

	/* Update schedule status */
	$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
	$export_schedules[ $schedule_id ]['last_status'] = 'success';
	/* translators: %d: number of posts exported */
	$export_schedules[ $schedule_id ]['last_count']  = count( $posts );
	update_option( SMIE_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

	/* Send email notification if configured */
	$email = ! empty( $schedule['email'] ) ? $schedule['email'] : '';
	if ( $email && is_email( $email ) ) {
		/* translators: %s: schedule name */
		$subject = sprintf( esc_html__( '[%s] Scheduled Export Complete', 'smartly-import-export' ), $schedule['name'] );

		$lines = array();
		/* translators: %s: schedule name */
		$lines[] = sprintf( esc_html__( 'Schedule: %s', 'smartly-import-export' ), $schedule['name'] );
		/* translators: %s: post type slug */
		$lines[] = sprintf( esc_html__( 'Post type: %s', 'smartly-import-export' ), $post_type );
		/* translators: %d: number of posts exported */
		$lines[] = sprintf( esc_html__( 'Posts exported: %d', 'smartly-import-export' ), count( $posts ) );
		/* translators: %s: date and time */
		$lines[] = sprintf( esc_html__( 'Completed at: %s', 'smartly-import-export' ), current_time( 'mysql' ) );
		$lines[] = esc_html__( 'The CSV file is attached to this email.', 'smartly-import-export' );

		wp_mail( $email, $subject, implode( "\n", $lines ), '', array( $filepath ) );
	}
}
add_action( SMIE_EXPORT_CRON_HOOK, 'smie_run_scheduled_export' );
add_action( SMIE_LEGACY_EXPORT_CRON_HOOK, 'smie_run_scheduled_export' );

/**
 * Remove exported CSV files older than 7 days.
 *
 * @param string $directory The export directory path.
 */
function smie_cleanup_old_exports( $directory ) {
	$files = glob( $directory . '/*.csv' );
	if ( ! is_array( $files ) ) {
		return;
	}
	$threshold = time() - ( 7 * DAY_IN_SECONDS );
	foreach ( $files as $file ) {
		if ( filemtime( $file ) < $threshold ) {
			wp_delete_file( $file );
		}
	}
}

