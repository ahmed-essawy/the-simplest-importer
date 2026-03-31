<?php
/**
 * Import history, mapping profiles, validation, and rollback.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * Helper — Import history CRUD
 * ------------------------------------------------------------------ */

/**
 * Record an import in history.
 *
 * @param string $history_id Unique import ID.
 * @param string $post_type  Post type imported.
 * @param string $mode       Import mode (insert, update, insert_update).
 * @param int    $inserted   Inserted count.
 * @param int    $updated    Updated count.
 * @param int    $skipped    Skipped count.
 * @param int    $errors     Error count.
 * @param array  $post_ids   Array of affected post IDs.
 * @return void
 */
function tsi_record_import_history( $history_id, $post_type, $mode, $inserted, $updated, $skipped, $errors, $post_ids ) {
	$history = get_option( TSI_HISTORY_OPTION, array() );

	$history[ $history_id ] = array(
		'id'        => $history_id,
		'date'      => current_time( 'mysql' ),
		'post_type' => $post_type,
		'mode'      => $mode,
		'inserted'  => $inserted,
		'updated'   => $updated,
		'skipped'   => $skipped,
		'errors'    => $errors,
		'post_ids'  => $post_ids,
	);

	/* Keep only last 50 entries */
	if ( count( $history ) > 50 ) {
		$history = array_slice( $history, -50, 50, true );
	}

	update_option( TSI_HISTORY_OPTION, $history, false );
}

/**
 * Get the import history array.
 *
 * @return array
 */
function tsi_get_import_history() {
	return get_option( TSI_HISTORY_OPTION, array() );
}

/* ------------------------------------------------------------------
 * Helper — Mapping profiles CRUD
 * ------------------------------------------------------------------ */

/**
 * Get saved mapping profiles.
 *
 * @return array
 */
function tsi_get_mapping_profiles() {
	return get_option( TSI_PROFILES_OPTION, array() );
}

/* ------------------------------------------------------------------
 * Helper — Scheduled imports CRUD
 * ------------------------------------------------------------------ */

/**
 * Get scheduled imports list.
 *
 * @return array
 */
function tsi_get_scheduled_imports() {
	return get_option( TSI_SCHEDULES_OPTION, array() );
}

/* ------------------------------------------------------------------
 * AJAX — Save mapping profile
 * ------------------------------------------------------------------ */

/**
 * Save a mapping profile.
 */
function tsi_ajax_save_profile() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$name      = isset( $_POST['profile_name'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_name'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$mapping   = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $name ) {
		wp_send_json_error( esc_html__( 'Profile name is required.', 'the-simplest-importer' ) );
	}

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	/* Sanitize mapping keys and values */
	$clean = array();
	foreach ( $mapping as $field => $info ) {
		$field = sanitize_text_field( $field );
		if ( is_array( $info ) ) {
			$clean[ $field ] = array_map( 'sanitize_text_field', $info );
		}
	}

	$profiles = tsi_get_mapping_profiles();
	$id       = sanitize_title( $name . '-' . $post_type );

	$profiles[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'post_type' => $post_type,
		'mapping'   => $clean,
	);

	update_option( TSI_PROFILES_OPTION, $profiles, false );

	wp_send_json_success( array(
		'profiles' => $profiles,
		/* translators: %s: profile name */
		'message'  => sprintf( esc_html__( 'Profile "%s" saved.', 'the-simplest-importer' ), $name ),
	) );
}
add_action( 'wp_ajax_tsi_save_profile', 'tsi_ajax_save_profile' );

/* ------------------------------------------------------------------
 * AJAX — Delete mapping profile
 * ------------------------------------------------------------------ */

/**
 * Delete a mapping profile.
 */
function tsi_ajax_delete_profile() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing profile ID.', 'the-simplest-importer' ) );
	}

	$profiles = tsi_get_mapping_profiles();
	if ( isset( $profiles[ $id ] ) ) {
		unset( $profiles[ $id ] );
		update_option( TSI_PROFILES_OPTION, $profiles, false );
	}

	wp_send_json_success( array(
		'profiles' => $profiles,
		'message'  => esc_html__( 'Profile deleted.', 'the-simplest-importer' ),
	) );
}
add_action( 'wp_ajax_tsi_delete_profile', 'tsi_ajax_delete_profile' );

/* ------------------------------------------------------------------
 * AJAX — Validate CSV data
 * ------------------------------------------------------------------ */

/**
 * Validate CSV data against the selected post type fields.
 */
function tsi_ajax_validate_csv() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$mapping   = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $token || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Missing parameters.', 'the-simplest-importer' ) );
	}

	$data = get_transient( 'tsi_csv_data_' . $token );
	if ( ! $data ) {
		wp_send_json_error( esc_html__( 'CSV data expired.', 'the-simplest-importer' ) );
	}

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	$mapping = tsi_sanitize_mapping( $mapping, count( $data['headers'] ) );

	$warnings = array();
	$errors   = array();
	$rows     = $data['rows'];

	/* Check a sample of rows (max 100) */
	$sample = array_slice( $rows, 0, min( 100, count( $rows ) ) );

	foreach ( $sample as $i => $row ) {
		$row_num = $i + 2;

		foreach ( $mapping as $field => $info ) {
			$value = ( 'custom' === $info['source'] )
				? $info['value']
				: ( isset( $row[ $info['col'] ] ) ? $row[ $info['col'] ] : '' );

			/* Empty required fields */
			if ( 'post_title' === $field && '' === trim( $value ) ) {
				/* translators: %d: row number */
				$warnings[] = sprintf( esc_html__( 'Row %d: Empty post title.', 'the-simplest-importer' ), $row_num );
			}

			/* Invalid dates */
			if ( 'post_date' === $field && '' !== $value && ! strtotime( $value ) ) {
				/* translators: %d: row number */
				$errors[] = sprintf( esc_html__( 'Row %d: Invalid date format.', 'the-simplest-importer' ), $row_num );
			}

			/* Invalid status */
			if ( 'post_status' === $field && '' !== $value ) {
				$allowed = array( 'publish', 'draft', 'pending', 'private', 'future' );
				if ( ! in_array( $value, $allowed, true ) ) {
					/* translators: 1: row number, 2: status value */
					$warnings[] = sprintf( esc_html__( 'Row %1$d: Unknown status "%2$s" — will default to draft.', 'the-simplest-importer' ), $row_num, esc_html( $value ) );
				}
			}

			/* Invalid author IDs */
			if ( 'post_author' === $field && '' !== $value && ! get_userdata( absint( $value ) ) ) {
				/* translators: 1: row number, 2: author ID */
				$warnings[] = sprintf( esc_html__( 'Row %1$d: Author ID %2$d not found.', 'the-simplest-importer' ), $row_num, absint( $value ) );
			}

			/* Invalid URL for thumbnail */
			if ( '_thumbnail_url' === $field && '' !== $value && ! wp_http_validate_url( $value ) ) {
				/* translators: %d: row number */
				$errors[] = sprintf( esc_html__( 'Row %d: Invalid thumbnail URL.', 'the-simplest-importer' ), $row_num );
			}
		}
	}

	wp_send_json_success( array(
		'warnings' => array_slice( $warnings, 0, 50 ),
		'errors'   => array_slice( $errors, 0, 50 ),
		'rows_checked' => count( $sample ),
		/* translators: %d: number of rows checked */
		'message'  => sprintf( esc_html__( 'Validated %d rows.', 'the-simplest-importer' ), count( $sample ) ),
	) );
}
add_action( 'wp_ajax_tsi_validate_csv', 'tsi_ajax_validate_csv' );

/* ------------------------------------------------------------------
 * AJAX — Rollback import
 * ------------------------------------------------------------------ */

/**
 * Rollback an import by deleting or trashing the imported posts.
 */
function tsi_ajax_rollback() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$history_id = isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( $_POST['history_id'] ) ) : '';
	if ( ! $history_id ) {
		wp_send_json_error( esc_html__( 'Missing history ID.', 'the-simplest-importer' ) );
	}

	$history = tsi_get_import_history();
	if ( ! isset( $history[ $history_id ] ) ) {
		wp_send_json_error( esc_html__( 'Import not found in history.', 'the-simplest-importer' ) );
	}

	$record   = $history[ $history_id ];
	$post_ids = isset( $record['post_ids'] ) ? $record['post_ids'] : array();
	$trashed  = 0;

	foreach ( $post_ids as $pid ) {
		$pid = absint( $pid );
		if ( $pid && get_post( $pid ) ) {
			wp_trash_post( $pid );
			$trashed++;
		}
	}

	/* Mark as rolled back in history */
	$history[ $history_id ]['rolled_back'] = true;
	$history[ $history_id ]['rolled_back_date'] = current_time( 'mysql' );
	update_option( TSI_HISTORY_OPTION, $history, false );

	wp_send_json_success( array(
		/* translators: %d: number of posts trashed */
		'message' => sprintf( esc_html__( '%d posts moved to trash.', 'the-simplest-importer' ), $trashed ),
		'trashed' => $trashed,
	) );
}
add_action( 'wp_ajax_tsi_rollback', 'tsi_ajax_rollback' );

/* ------------------------------------------------------------------
 * AJAX — Get import history
 * ------------------------------------------------------------------ */

/**
 * Return the import history as JSON.
 */
function tsi_ajax_get_history() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	wp_send_json_success( array(
		'history' => array_reverse( tsi_get_import_history() ),
	) );
}
add_action( 'wp_ajax_tsi_get_history', 'tsi_ajax_get_history' );

