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
 * Get the option name used to store rollback data for an import.
 *
 * @param string $history_id Unique import ID.
 * @return string
 */
function tsi_get_import_rollback_option_name( $history_id ) {
	return 'tsi_import_rollback_' . md5( (string) $history_id );
}

/**
 * Get rollback data for a specific import.
 *
 * @param string $history_id Unique import ID.
 * @return array<string, array>
 */
function tsi_get_import_rollback_data( $history_id ) {
	$data = get_option(
		tsi_get_import_rollback_option_name( $history_id ),
		array(
			'inserted_ids'  => array(),
			'updated_posts' => array(),
		)
	);

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	if ( empty( $data['inserted_ids'] ) || ! is_array( $data['inserted_ids'] ) ) {
		$data['inserted_ids'] = array();
	}

	if ( empty( $data['updated_posts'] ) || ! is_array( $data['updated_posts'] ) ) {
		$data['updated_posts'] = array();
	}

	return $data;
}

/**
 * Delete rollback data for a specific import.
 *
 * @param string $history_id Unique import ID.
 * @return void
 */
function tsi_delete_import_rollback_data( $history_id ) {
	delete_option( tsi_get_import_rollback_option_name( $history_id ) );
}

/**
 * Merge rollback actions into persistent rollback storage.
 *
 * @param string $history_id Unique import ID.
 * @param array  $actions    Rollback actions captured during import.
 * @return array<string, array>
 */
function tsi_store_import_rollback_actions( $history_id, $actions ) {
	$rollback_data = tsi_get_import_rollback_data( $history_id );

	if ( empty( $actions ) || ! is_array( $actions ) ) {
		return $rollback_data;
	}

	foreach ( $actions as $action ) {
		if ( ! is_array( $action ) || empty( $action['type'] ) ) {
			continue;
		}

		$post_id = isset( $action['post_id'] ) ? absint( $action['post_id'] ) : 0;
		if ( ! $post_id ) {
			continue;
		}

		if ( 'inserted' === $action['type'] ) {
			if ( ! in_array( $post_id, $rollback_data['inserted_ids'], true ) ) {
				$rollback_data['inserted_ids'][] = $post_id;
			}
			continue;
		}

		if ( 'updated' === $action['type'] && ! isset( $rollback_data['updated_posts'][ $post_id ] ) && ! empty( $action['snapshot'] ) && is_array( $action['snapshot'] ) ) {
			$rollback_data['updated_posts'][ $post_id ] = $action['snapshot'];
		}
	}

	update_option( tsi_get_import_rollback_option_name( $history_id ), $rollback_data, false );

	return $rollback_data;
}

/**
 * Remove rollback data for history records that have been pruned.
 *
 * @param array<string, array<string, mixed>> $history Import history array.
 * @return array<string, array<string, mixed>>
 */
function tsi_prune_import_history( $history ) {
	if ( count( $history ) <= 50 ) {
		return $history;
	}

	$excess = count( $history ) - 50;
	$keys   = array_keys( $history );

	foreach ( array_slice( $keys, 0, $excess ) as $history_id ) {
		unset( $history[ $history_id ] );
		tsi_delete_import_rollback_data( $history_id );
	}

	return $history;
}

/**
 * Capture a post snapshot before an update so rollback can restore it.
 *
 * @param int $post_id The post ID.
 * @return array<string, mixed>
 */
function tsi_capture_post_snapshot( $post_id ) {
	$post = get_post( $post_id, ARRAY_A );
	if ( empty( $post ) || ! is_array( $post ) ) {
		return array();
	}

	$restorable_post = array(
		'ID'             => absint( $post['ID'] ),
		'post_title'     => isset( $post['post_title'] ) ? $post['post_title'] : '',
		'post_content'   => isset( $post['post_content'] ) ? $post['post_content'] : '',
		'post_excerpt'   => isset( $post['post_excerpt'] ) ? $post['post_excerpt'] : '',
		'post_status'    => isset( $post['post_status'] ) ? $post['post_status'] : 'draft',
		'post_date'      => isset( $post['post_date'] ) ? $post['post_date'] : '',
		'post_date_gmt'  => isset( $post['post_date_gmt'] ) ? $post['post_date_gmt'] : '',
		'post_name'      => isset( $post['post_name'] ) ? $post['post_name'] : '',
		'post_author'    => isset( $post['post_author'] ) ? absint( $post['post_author'] ) : 0,
		'post_parent'    => isset( $post['post_parent'] ) ? absint( $post['post_parent'] ) : 0,
		'menu_order'     => isset( $post['menu_order'] ) ? absint( $post['menu_order'] ) : 0,
		'comment_status' => isset( $post['comment_status'] ) ? $post['comment_status'] : 'closed',
		'ping_status'    => isset( $post['ping_status'] ) ? $post['ping_status'] : 'closed',
		'post_password'  => isset( $post['post_password'] ) ? $post['post_password'] : '',
	);

	$taxonomies = array();
	foreach ( get_object_taxonomies( $post['post_type'], 'names' ) as $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		$taxonomies[ $taxonomy ] = is_wp_error( $terms ) ? array() : array_map( 'absint', $terms );
	}

	return array(
		'post'       => $restorable_post,
		'meta'       => get_post_meta( $post_id ),
		'taxonomies' => $taxonomies,
	);
}

/**
 * Restore a post snapshot captured before an update.
 *
 * @param array<string, mixed> $snapshot Snapshot data.
 * @return bool
 */
function tsi_restore_post_snapshot( $snapshot ) {
	if ( empty( $snapshot['post']['ID'] ) ) {
		return false;
	}

	$post_id = absint( $snapshot['post']['ID'] );
	if ( ! $post_id || ! get_post( $post_id ) ) {
		return false;
	}

	$post_data = is_array( $snapshot['post'] ) ? $snapshot['post'] : array();
	$result    = wp_update_post( $post_data, true );
	if ( is_wp_error( $result ) ) {
		return false;
	}

	$current_meta  = get_post_meta( $post_id );
	$snapshot_meta = isset( $snapshot['meta'] ) && is_array( $snapshot['meta'] ) ? $snapshot['meta'] : array();

	foreach ( $current_meta as $meta_key => $values ) {
		if ( ! array_key_exists( $meta_key, $snapshot_meta ) ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	foreach ( $snapshot_meta as $meta_key => $values ) {
		delete_post_meta( $post_id, $meta_key );
		if ( ! is_array( $values ) ) {
			continue;
		}

		foreach ( $values as $value ) {
			add_post_meta( $post_id, $meta_key, $value );
		}
	}

	$post_type         = get_post_type( $post_id );
	$snapshot_taxonomy = isset( $snapshot['taxonomies'] ) && is_array( $snapshot['taxonomies'] ) ? $snapshot['taxonomies'] : array();

	foreach ( get_object_taxonomies( $post_type, 'names' ) as $taxonomy ) {
		$term_ids = isset( $snapshot_taxonomy[ $taxonomy ] ) && is_array( $snapshot_taxonomy[ $taxonomy ] )
			? array_map( 'absint', $snapshot_taxonomy[ $taxonomy ] )
			: array();

		wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
	}

	clean_post_cache( $post_id );

	return true;
}

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
 * @param array  $actions    Rollback actions captured during import.
 * @param bool   $complete   Whether the import has finished.
 * @return void
 */
function tsi_record_import_history( $history_id, $post_type, $mode, $inserted, $updated, $skipped, $errors, $post_ids, $actions = array(), $complete = true ) {
	$history = get_option( TSI_HISTORY_OPTION, array() );
	$record  = isset( $history[ $history_id ] ) && is_array( $history[ $history_id ] )
		? $history[ $history_id ]
		: array(
			'id'             => $history_id,
			'date'           => current_time( 'mysql' ),
			'post_type'      => $post_type,
			'mode'           => $mode,
			'inserted'       => 0,
			'updated'        => 0,
			'skipped'        => 0,
			'errors'         => 0,
			'post_ids'       => array(),
			'rollback_ready' => false,
			'complete'       => false,
		);

	$record['post_type'] = $post_type;
	$record['mode']      = $mode;
	$record['inserted']  = absint( $record['inserted'] ) + absint( $inserted );
	$record['updated']   = absint( $record['updated'] ) + absint( $updated );
	$record['skipped']   = absint( $record['skipped'] ) + absint( $skipped );
	$record['errors']    = absint( $record['errors'] ) + absint( $errors );
	$record['post_ids']  = array_values( array_unique( array_map( 'absint', array_merge( isset( $record['post_ids'] ) && is_array( $record['post_ids'] ) ? $record['post_ids'] : array(), $post_ids ) ) ) );
	$record['complete']  = (bool) $complete;

	$rollback_data = tsi_store_import_rollback_actions( $history_id, $actions );
	$record['rollback_ready'] = ! empty( $rollback_data['inserted_ids'] ) || ! empty( $rollback_data['updated_posts'] );

	$history[ $history_id ] = $record;
	$history                = tsi_prune_import_history( $history );

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
		'profile_id' => $id,
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
	$rollback = tsi_get_import_rollback_data( $history_id );
	if ( empty( $rollback['inserted_ids'] ) && empty( $rollback['updated_posts'] ) ) {
		wp_send_json_error( esc_html__( 'Rollback data is no longer available for this import.', 'the-simplest-importer' ) );
	}

	$trashed  = 0;
	$restored = 0;

	foreach ( $rollback['inserted_ids'] as $pid ) {
		$pid = absint( $pid );
		if ( $pid && get_post( $pid ) ) {
			wp_trash_post( $pid );
			$trashed++;
		}
	}

	foreach ( $rollback['updated_posts'] as $snapshot ) {
		if ( tsi_restore_post_snapshot( $snapshot ) ) {
			$restored++;
		}
	}

	/* Mark as rolled back in history */
	$history[ $history_id ]['rolled_back'] = true;
	$history[ $history_id ]['rolled_back_date'] = current_time( 'mysql' );
	$history[ $history_id ]['rollback_ready'] = false;
	update_option( TSI_HISTORY_OPTION, $history, false );
	tsi_delete_import_rollback_data( $history_id );

	$message_parts = array();
	if ( $trashed > 0 ) {
		/* translators: %d: number of newly inserted posts moved to trash */
		$message_parts[] = sprintf( esc_html__( '%d inserted posts moved to trash.', 'the-simplest-importer' ), $trashed );
	}
	if ( $restored > 0 ) {
		/* translators: %d: number of updated posts restored */
		$message_parts[] = sprintf( esc_html__( '%d updated posts restored.', 'the-simplest-importer' ), $restored );
	}
	if ( empty( $message_parts ) ) {
		$message_parts[] = esc_html__( 'No posts needed to be rolled back.', 'the-simplest-importer' );
	}

	wp_send_json_success( array(
		'message'  => implode( ' ', $message_parts ),
		'trashed'  => $trashed,
		'restored' => $restored,
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

