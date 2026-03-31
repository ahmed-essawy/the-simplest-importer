<?php
/**
 * Import AJAX handlers and import helper functions.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * AJAX — Export blank template CSV
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_template', 'tsi_ajax_template' );

/**
 * Generate a blank CSV template for a given post type.
 *
 * @return void
 */
function tsi_ajax_template() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$fields = tsi_get_post_type_fields( $post_type );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array_keys( $fields ) );
	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-template.csv' ),
	) );
}

/* ------------------------------------------------------------------
 * AJAX — Batch import
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_import_batch', 'tsi_ajax_import_batch' );

/**
 * Process a batch of CSV rows for import.
 *
 * Expects: token, post_type, mapping (JSON), offset, batch_size.
 * Returns partial results so the client can call repeatedly until done.
 *
 * @return void
 */
function tsi_ajax_import_batch() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$post_type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 50;
	$mapping    = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and keys sanitized below.
	$dry_run    = ! empty( $_POST['dry_run'] );
	$dup_field  = isset( $_POST['dup_field'] ) ? sanitize_key( wp_unslash( $_POST['dup_field'] ) ) : '';
	$dup_meta   = isset( $_POST['dup_meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dup_meta_key'] ) ) : '';
	$transforms = isset( $_POST['transforms'] ) ? wp_unslash( $_POST['transforms'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.
	$import_mode = isset( $_POST['import_mode'] ) ? sanitize_key( wp_unslash( $_POST['import_mode'] ) ) : 'insert';
	$history_id  = isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( $_POST['history_id'] ) ) : '';
	$filters_raw = isset( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.
	$retry_rows_raw = isset( $_POST['retry_rows'] ) ? wp_unslash( $_POST['retry_rows'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $token || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Missing parameters.', 'the-simplest-importer' ) );
	}

	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$batch_size = min( max( $batch_size, 1 ), 200 );

	$data = get_transient( 'tsi_csv_data_' . $token );
	if ( ! $data ) {
		wp_send_json_error( esc_html__( 'CSV data expired. Please re-upload the file.', 'the-simplest-importer' ) );
	}

	$headers = $data['headers'];

	/* Retry mode: only process specific row indices */
	$retry_indices = array();
	if ( '' !== $retry_rows_raw ) {
		$retry_indices = json_decode( $retry_rows_raw, true );
		if ( ! is_array( $retry_indices ) ) {
			$retry_indices = array();
		}
		$retry_indices = array_map( 'absint', $retry_indices );
	}

	if ( ! empty( $retry_indices ) ) {
		$all_retry_rows = array();
		foreach ( $retry_indices as $ri ) {
			if ( isset( $data['rows'][ $ri ] ) ) {
				$all_retry_rows[ $ri ] = $data['rows'][ $ri ];
			}
		}
		$total       = count( $all_retry_rows );
		$retry_keys  = array_keys( $all_retry_rows );
		$batch_keys  = array_slice( $retry_keys, $offset, $batch_size );
		$rows        = array();
		$row_indices = array();
		foreach ( $batch_keys as $bk ) {
			$rows[]        = $all_retry_rows[ $bk ];
			$row_indices[] = $bk;
		}
	} else {
		$total       = count( $data['rows'] );
		$rows        = array_slice( $data['rows'], $offset, $batch_size );
		$row_indices = range( $offset, $offset + count( $rows ) - 1 );
	}

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	$clean_map = tsi_sanitize_mapping( $mapping, count( $headers ) );

	$transforms = json_decode( $transforms, true );
	if ( ! is_array( $transforms ) ) {
		$transforms = array();
	}
	/* Sanitize transform values — support both string and {transform, param} */
	$clean_transforms = array();
	$allowed_transforms = array( 'uppercase', 'lowercase', 'titlecase', 'trim', 'strip_tags', 'slug', 'date_ymd', 'date_dmy', 'date_mdy', 'date_iso', 'find_replace', 'prepend', 'append', 'math_multiply', 'math_add', 'number_format', 'url_encode' );
	foreach ( $transforms as $field_key => $t_val ) {
		$field_key = sanitize_text_field( $field_key );
		if ( is_array( $t_val ) ) {
			$t_name = isset( $t_val['transform'] ) ? sanitize_key( $t_val['transform'] ) : '';
			if ( in_array( $t_name, $allowed_transforms, true ) ) {
				$clean_transforms[ $field_key ] = array(
					'transform' => $t_name,
					'param'     => isset( $t_val['param'] ) ? sanitize_text_field( $t_val['param'] ) : '',
				);
			}
		} else {
			$t_name = sanitize_key( $t_val );
			if ( in_array( $t_name, $allowed_transforms, true ) ) {
				$clean_transforms[ $field_key ] = $t_name;
			}
		}
	}
	$transforms = $clean_transforms;

	$filters = json_decode( $filters_raw, true );
	if ( ! is_array( $filters ) ) {
		$filters = array();
	}
	/* Sanitize filter rules */
	$clean_filters = array();
	$allowed_ops   = array( 'equals', 'not_equals', 'contains', 'not_contains', 'gt', 'lt', 'empty', 'not_empty' );
	foreach ( $filters as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		$col = isset( $rule['col'] ) ? absint( $rule['col'] ) : 0;
		$op  = isset( $rule['op'] ) ? sanitize_key( $rule['op'] ) : '';
		$val = isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '';
		if ( in_array( $op, $allowed_ops, true ) ) {
			$clean_filters[] = array(
				'col'   => $col,
				'op'    => $op,
				'value' => $val,
			);
		}
	}

	$log         = array();
	$inserted    = 0;
	$updated     = 0;
	$skipped     = 0;
	$errors      = 0;
	$post_ids    = array();
	$failed_rows = array();

	foreach ( $rows as $i => $row ) {
		$row_num   = $row_indices[ $i ] + 2; // +2: header is row 1, data starts at row 2.
		$row_index = $row_indices[ $i ];

		/* Conditional row filtering — skip rows that don't match all rules */
		if ( ! empty( $clean_filters ) ) {
			$pass = tsi_row_matches_filters( $row, $clean_filters );

			/**
			 * Filter whether a row passes the import filter rules.
			 *
			 * @param bool  $pass    Whether the row passed.
			 * @param array $row     CSV row values.
			 * @param array $filters The filter rules.
			 * @param int   $row_num Row number.
			 */
			$pass = apply_filters( 'tsi_import_row_filter', $pass, $row, $clean_filters, $row_num );

			if ( ! $pass ) {
				$skipped++;
				/* translators: %d: row number */
				$log[] = sprintf( __( 'Row %d: Filtered — did not match filter rules.', 'the-simplest-importer' ), $row_num );
				continue;
			}
		}

		$result  = tsi_import_single_row( $row, $row_num, $post_type, $clean_map, $dry_run, $dup_field, $dup_meta, $transforms, $headers );

		$log[] = $result['message'];
		if ( ! empty( $result['post_id'] ) ) {
			$post_ids[] = $result['post_id'];
		}
		switch ( $result['status'] ) {
			case 'inserted':
				$inserted++;
				break;
			case 'updated':
				$updated++;
				break;
			case 'skipped':
				$skipped++;
				break;
			default:
				$errors++;
				$failed_rows[] = $row_index;
				break;
		}
	}

	$next_offset = $offset + count( $rows );
	$done        = $next_offset >= $total;

	if ( $done ) {
		/* Keep transient alive when there are failed rows for potential retry */
		if ( empty( $failed_rows ) ) {
			delete_transient( 'tsi_csv_data_' . $token );
		}

		/* Record import in history (skip for dry runs) */
		if ( ! $dry_run && ( $inserted > 0 || $updated > 0 ) ) {
			if ( ! $history_id ) {
				$history_id = wp_generate_password( 12, false );
			}
			tsi_record_import_history( $history_id, $post_type, $import_mode, $inserted, $updated, $skipped, $errors, $post_ids );
		}

		/**
		 * Fires when a full import is complete (last batch).
		 *
		 * @param string $post_type The imported post type.
		 * @param array  $stats     { inserted, updated, skipped, errors, post_ids, dry_run, history_id }
		 */
		do_action( 'tsi_import_completed', $post_type, array(
			'inserted'   => $inserted,
			'updated'    => $updated,
			'skipped'    => $skipped,
			'errors'     => $errors,
			'post_ids'   => $post_ids,
			'dry_run'    => $dry_run,
			'history_id' => $history_id,
		) );
	}

	wp_send_json_success( array(
		'offset'      => $next_offset,
		'total'       => $total,
		'done'        => $done,
		'log'         => $log,
		'inserted'    => $inserted,
		'updated'     => $updated,
		'skipped'     => $skipped,
		'errors'      => $errors,
		'post_ids'    => $post_ids,
		'history_id'  => $history_id ? $history_id : '',
		'dry_run'     => $dry_run,
		'failed_rows' => $failed_rows,
	) );
}

/* ------------------------------------------------------------------
 * Helpers — Mapping & Row Import
 * ------------------------------------------------------------------ */

/**
 * Sanitize the mapping array received from the client.
 *
 * @param array $mapping  Raw mapping from JSON decode.
 * @param int   $col_max  Number of CSV columns (for bounds checking).
 * @return array Sanitized mapping.
 */
function tsi_sanitize_mapping( $mapping, $col_max ) {
	$clean = array();

	foreach ( $mapping as $field => $info ) {
		$field = sanitize_text_field( $field );
		if ( ! is_array( $info ) ) {
			continue;
		}

		$source = isset( $info['source'] ) ? sanitize_text_field( $info['source'] ) : 'csv';

		if ( 'custom' === $source ) {
			$clean[ $field ] = array(
				'source' => 'custom',
				'value'  => sanitize_text_field( isset( $info['value'] ) ? $info['value'] : '' ),
			);
		} elseif ( 'merge' === $source ) {
			$clean[ $field ] = array(
				'source'   => 'merge',
				'template' => sanitize_text_field( isset( $info['template'] ) ? $info['template'] : '' ),
			);
		} else {
			$col = isset( $info['col'] ) ? absint( $info['col'] ) : 0;
			if ( $col >= $col_max ) {
				continue;
			}
			$clean[ $field ] = array(
				'source' => 'csv',
				'col'    => $col,
			);
		}
	}

	return $clean;
}

/**
 * Import a single CSV row: insert or update one post.
 *
 * @param array  $row        The CSV row values.
 * @param int    $row_num    Human-readable row number (for logs).
 * @param string $post_type  Target post type.
 * @param array  $mapping    Sanitized mapping array.
 * @param bool   $dry_run    If true, skip actual insert/update.
 * @param string $dup_field  Field to check for duplicates (post_title, post_name, meta_key).
 * @param string $dup_meta   Meta key name when dup_field is 'meta_key'.
 * @param array  $transforms Transforms to apply per field.
 * @param array  $headers    CSV column headers for merge template replacement.
 * @return array { status: string, message: string, post_id: int }
 */
function tsi_import_single_row( $row, $row_num, $post_type, $mapping, $dry_run = false, $dup_field = '', $dup_meta = '', $transforms = array(), $headers = array() ) {
	$post_data    = array( 'post_type' => $post_type );
	$meta_data    = array();
	$tax_data     = array();
	$thumb_url    = '';
	$gallery_urls = '';

	/**
	 * Fires before a single CSV row is imported.
	 *
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 * @param string $post_type Target post type.
	 * @param array  $mapping   Sanitized mapping array.
	 */
	do_action( 'tsi_before_import_row', $row, $row_num, $post_type, $mapping );

	foreach ( $mapping as $field => $info ) {
		if ( 'custom' === $info['source'] ) {
			$value = $info['value'];
		} elseif ( 'merge' === $info['source'] ) {
			$value = $info['template'];
			foreach ( $row as $col_idx => $col_val ) {
				if ( isset( $headers[ $col_idx ] ) ) {
					$value = str_replace( '{' . $headers[ $col_idx ] . '}', $col_val, $value );
				}
			}
		} else {
			$value = isset( $row[ $info['col'] ] ) ? $row[ $info['col'] ] : '';
		}

		/* Apply transform if set */
		if ( ! empty( $transforms[ $field ] ) ) {
			$value = tsi_apply_transform( $value, $transforms[ $field ] );
		}

		if ( 'ID' === $field ) {
			$post_data['ID'] = absint( $value );
		} elseif ( 'post_title' === $field ) {
			$post_data['post_title'] = sanitize_text_field( $value );
		} elseif ( 'post_content' === $field ) {
			$post_data['post_content'] = wp_kses_post( $value );
		} elseif ( 'post_excerpt' === $field ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $value );
		} elseif ( 'post_status' === $field ) {
			$allowed = array( 'publish', 'draft', 'pending', 'private', 'future' );
			$post_data['post_status'] = in_array( $value, $allowed, true ) ? $value : 'draft';
		} elseif ( 'post_date' === $field ) {
			$post_data['post_date'] = sanitize_text_field( $value );
		} elseif ( 'post_name' === $field ) {
			$post_data['post_name'] = sanitize_title( $value );
		} elseif ( 'post_author' === $field ) {
			$author_id = absint( $value );
			if ( $author_id && get_userdata( $author_id ) ) {
				$post_data['post_author'] = $author_id;
			}
		} elseif ( 'post_parent' === $field ) {
			$parent_val = trim( $value );
			if ( '' !== $parent_val ) {
				if ( is_numeric( $parent_val ) ) {
					$post_data['post_parent'] = absint( $parent_val );
				} else {
					/* Look up parent by title within the same post type. */
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time parent lookup during import.
					$parent_id = $wpdb->get_var( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status != 'trash' LIMIT 1",
						sanitize_text_field( $parent_val ),
						$post_type
					) );
					if ( $parent_id ) {
						$post_data['post_parent'] = absint( $parent_id );
					}
				}
			}
		} elseif ( '_thumbnail_url' === $field ) {
			$thumb_url = esc_url_raw( $value );
		} elseif ( '_product_gallery_urls' === $field ) {
			$gallery_urls = sanitize_text_field( $value );
		} elseif ( 0 === strpos( $field, 'tax__' ) ) {
			$tax_slug = sanitize_key( substr( $field, 5 ) );
			$terms    = array_map( 'trim', explode( ',', $value ) );
			$terms    = array_filter( $terms );
			if ( ! empty( $terms ) ) {
				$tax_data[ $tax_slug ] = $terms;
			}
		} elseif ( 0 === strpos( $field, 'meta__' ) ) {
			$meta_key              = sanitize_text_field( substr( $field, 6 ) );
			$meta_data[ $meta_key ] = sanitize_text_field( $value );
		}
	}

	/**
	 * Filter the row data array before inserting or updating a post.
	 *
	 * @param array  $post_data Post data for wp_insert_post / wp_update_post.
	 * @param array  $meta_data Meta key => value pairs.
	 * @param array  $tax_data  Taxonomy slug => terms pairs.
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 */
	$import_row_data = apply_filters( 'tsi_import_row_data', array(
		'post_data'    => $post_data,
		'meta_data'    => $meta_data,
		'tax_data'     => $tax_data,
		'thumb_url'    => $thumb_url,
		'gallery_urls' => $gallery_urls,
	), $row, $row_num, $post_type );

	$post_data    = $import_row_data['post_data'];
	$meta_data    = $import_row_data['meta_data'];
	$tax_data     = $import_row_data['tax_data'];
	$thumb_url    = $import_row_data['thumb_url'];
	$gallery_urls = $import_row_data['gallery_urls'];

	/* Determine insert vs. update */
	$is_update = false;
	if ( ! empty( $post_data['ID'] ) ) {
		$existing = get_post( $post_data['ID'] );
		if ( $existing && $existing->post_type === $post_type ) {
			$is_update = true;
		} elseif ( $existing ) {
			return array(
				'status'  => 'skipped',
				'post_id' => 0,
				/* translators: 1: row number, 2: post ID */
				'message' => sprintf( __( 'Row %1$d: Skipped — ID %2$d belongs to a different post type.', 'the-simplest-importer' ), $row_num, $post_data['ID'] ),
			);
		} else {
			unset( $post_data['ID'] ); // ID not found; insert as new.
		}
	}

	/* Duplicate detection (only for inserts) */
	if ( ! $is_update && $dup_field ) {
		$dup_found = tsi_check_duplicate( $post_type, $dup_field, $dup_meta, $post_data, $meta_data );
		if ( $dup_found ) {
			return array(
				'status'  => 'skipped',
				'post_id' => 0,
				/* translators: 1: row number, 2: post ID */
				'message' => sprintf( __( 'Row %1$d: Skipped — duplicate found (post #%2$d).', 'the-simplest-importer' ), $row_num, $dup_found ),
			);
		}
	}

	if ( ! $is_update && empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = 'draft';
	}

	/* Dry run — report what would happen without writing */
	if ( $dry_run ) {
		$action_label = $is_update
			/* translators: 1: row number, 2: post ID */
			? sprintf( __( 'Row %1$d: Would update post #%2$d', 'the-simplest-importer' ), $row_num, $post_data['ID'] )
			/* translators: %d: row number */
			: sprintf( __( 'Row %d: Would insert new post', 'the-simplest-importer' ), $row_num );
		return array(
			'status'  => $is_update ? 'updated' : 'inserted',
			'post_id' => 0,
			'message' => $action_label . ' [' . __( 'DRY RUN', 'the-simplest-importer' ) . ']',
		);
	}

	$result = $is_update
		? wp_update_post( $post_data, true )
		: wp_insert_post( $post_data, true );

	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			'post_id' => 0,
			/* translators: 1: row number, 2: error message */
			'message' => sprintf( __( 'Row %1$d: Error — %2$s', 'the-simplest-importer' ), $row_num, $result->get_error_message() ),
		);
	}

	$post_id = $result;

	foreach ( $meta_data as $key => $val ) {
		/*
		 * Use ACF's update_field() when available for ACF-registered fields.
		 * This ensures proper storage for ACF field types (select, true_false, etc.).
		 */
		if ( function_exists( 'acf_get_field' ) && function_exists( 'update_field' ) ) {
			$acf_field = acf_get_field( $key );
			if ( $acf_field ) {
				update_field( $key, $val, $post_id );
				continue;
			}
		}
		update_post_meta( $post_id, $key, $val );
	}

	foreach ( $tax_data as $tax => $terms ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$is_hierarchical = is_taxonomy_hierarchical( $tax );
		$term_ids        = array();

		foreach ( $terms as $term_string ) {
			if ( $is_hierarchical && false !== strpos( $term_string, '>' ) ) {
				/* Hierarchical: "Parent > Child > Grandchild" */
				$parts     = array_map( 'trim', explode( '>', $term_string ) );
				$parts     = array_filter( $parts );
				$parent_id = 0;
				foreach ( $parts as $part ) {
					$existing = term_exists( $part, $tax, $parent_id );
					if ( $existing ) {
						$parent_id = (int) $existing['term_id'];
					} else {
						$inserted_term = wp_insert_term( $part, $tax, array( 'parent' => $parent_id ) );
						if ( ! is_wp_error( $inserted_term ) ) {
							$parent_id = (int) $inserted_term['term_id'];
						}
					}
				}
				if ( $parent_id ) {
					$term_ids[] = $parent_id;
				}
			} else {
				$term_ids[] = $term_string;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $tax );
		}
	}

	if ( $thumb_url ) {
		tsi_set_featured_image( $post_id, $thumb_url );
	}

	if ( $gallery_urls ) {
		tsi_set_product_gallery( $post_id, $gallery_urls );
	}

	/**
	 * Fires after a single CSV row has been imported.
	 *
	 * @param int    $post_id   The imported post ID.
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 * @param bool   $is_update Whether this was an update.
	 */
	do_action( 'tsi_after_import_row', $post_id, $row, $row_num, $is_update );

	if ( $is_update ) {
		return array(
			'status'  => 'updated',
			'post_id' => $post_id,
			/* translators: 1: row number, 2: post ID */
			'message' => sprintf( __( 'Row %1$d: Updated post #%2$d', 'the-simplest-importer' ), $row_num, $post_id ),
		);
	}

	return array(
		'status'  => 'inserted',
		'post_id' => $post_id,
		/* translators: 1: row number, 2: post ID */
		'message' => sprintf( __( 'Row %1$d: Inserted new post #%2$d', 'the-simplest-importer' ), $row_num, $post_id ),
	);
}

/* ------------------------------------------------------------------
 * Helper — Set featured image from URL
 * ------------------------------------------------------------------ */

/**
 * Download an image from a URL and set it as a post's featured image.
 *
 * @param int    $post_id The post to attach the image to.
 * @param string $url     The image URL.
 * @return void
 */
function tsi_set_featured_image( $post_id, $url ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return;
	}

	$file_array = array(
		'name'     => sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
		'tmp_name' => $tmp,
	);

	$attach_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $attach_id ) ) {
		wp_delete_file( $tmp );
		return;
	}

	set_post_thumbnail( $post_id, $attach_id );
}

/* ------------------------------------------------------------------
 * Helper — Set product image gallery from URLs
 * ------------------------------------------------------------------ */

/**
 * Download images from comma-separated URLs and store as a product gallery.
 *
 * Stores pipe-separated attachment IDs in `_product_image_gallery` meta key,
 * compatible with WooCommerce. Works for any post type.
 *
 * @param int    $post_id     The post to attach the gallery to.
 * @param string $gallery_csv Comma-separated image URLs.
 * @return void
 */
function tsi_set_product_gallery( $post_id, $gallery_csv ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$urls = array_map( 'trim', explode( ',', $gallery_csv ) );
	$urls = array_filter( $urls );
	if ( empty( $urls ) ) {
		return;
	}

	$attachment_ids = array();
	foreach ( $urls as $url ) {
		$url = esc_url_raw( $url );
		if ( ! wp_http_validate_url( $url ) ) {
			continue;
		}

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}

		$file_array = array(
			'name'     => sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			wp_delete_file( $tmp );
			continue;
		}

		$attachment_ids[] = $attach_id;
	}

	if ( ! empty( $attachment_ids ) ) {
		update_post_meta( $post_id, '_product_image_gallery', implode( ',', $attachment_ids ) );
	}
}

/**
 * Apply a named transform to a field value.
 *
 * @param string       $value     The raw value.
 * @param string|array $transform The transform key (string) or array with 'transform' and 'param'.
 * @return string Transformed value.
 */
function tsi_apply_transform( $value, $transform ) {
	$param = '';
	if ( is_array( $transform ) ) {
		$param     = isset( $transform['param'] ) ? $transform['param'] : '';
		$transform = isset( $transform['transform'] ) ? $transform['transform'] : '';
	}

	switch ( $transform ) {
		case 'uppercase':
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value );
		case 'lowercase':
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		case 'titlecase':
			return function_exists( 'mb_convert_case' ) ? mb_convert_case( $value, MB_CASE_TITLE ) : ucwords( strtolower( $value ) );
		case 'trim':
			return trim( $value );
		case 'strip_tags':
			return wp_strip_all_tags( $value );
		case 'slug':
			return sanitize_title( $value );
		case 'date_ymd':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'Y-m-d', $ts ) : $value;
		case 'date_dmy':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'd/m/Y', $ts ) : $value;
		case 'date_mdy':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'm/d/Y', $ts ) : $value;
		case 'date_iso':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'c', $ts ) : $value;
		case 'find_replace':
			$parts = explode( '|', $param, 2 );
			if ( 2 === count( $parts ) ) {
				return str_replace( $parts[0], $parts[1], $value );
			}
			return $value;
		case 'prepend':
			return $param . $value;
		case 'append':
			return $value . $param;
		case 'math_multiply':
			if ( is_numeric( $value ) && is_numeric( $param ) ) {
				return (string) ( (float) $value * (float) $param );
			}
			return $value;
		case 'math_add':
			if ( is_numeric( $value ) && is_numeric( $param ) ) {
				return (string) ( (float) $value + (float) $param );
			}
			return $value;
		case 'number_format':
			if ( is_numeric( $value ) ) {
				return number_format( (float) $value, 2, '.', '' );
			}
			return $value;
		case 'url_encode':
			return rawurlencode( $value );
		default:
			return $value;
	}
}

/* ------------------------------------------------------------------
 * Helper — Duplicate detection
 * ------------------------------------------------------------------ */

/**
 * Check if a post already exists based on a given field.
 *
 * @param string $post_type Target post type.
 * @param string $field     The field to check (post_title, post_name, meta_key).
 * @param string $meta_key  Meta key name when field is 'meta_key'.
 * @param array  $post_data Post data array being imported.
 * @param array  $meta_data Meta data array being imported.
 * @return int Found post ID, or 0.
 */
function tsi_check_duplicate( $post_type, $field, $meta_key, $post_data, $meta_data ) {
	global $wpdb;

	if ( 'post_title' === $field && ! empty( $post_data['post_title'] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off duplicate check during import, caching not applicable.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status != 'trash' LIMIT 1",
				$post_type,
				$post_data['post_title']
			)
		);
		return $found ? (int) $found : 0;
	}

	if ( 'post_name' === $field && ! empty( $post_data['post_name'] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off duplicate check during import, caching not applicable.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status != 'trash' LIMIT 1",
				$post_type,
				$post_data['post_name']
			)
		);
		return $found ? (int) $found : 0;
	}

	if ( 'meta_key' === $field && $meta_key && ! empty( $meta_data[ $meta_key ] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off duplicate check during import, caching not applicable.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value = %s AND p.post_status != 'trash'
				LIMIT 1",
				$post_type,
				$meta_key,
				$meta_data[ $meta_key ]
			)
		);
		return $found ? (int) $found : 0;
	}

	return 0;
}

/* ------------------------------------------------------------------
 * Helper — Row filter evaluation
 * ------------------------------------------------------------------ */

/**
 * Check if a CSV row matches all filter rules (AND logic).
 *
 * @param array $row     The CSV row values.
 * @param array $filters Array of filter rules { col, op, value }.
 * @return bool True if the row matches all rules.
 */
function tsi_row_matches_filters( $row, $filters ) {
	foreach ( $filters as $rule ) {
		$cell = isset( $row[ $rule['col'] ] ) ? trim( (string) $row[ $rule['col'] ] ) : '';
		$val  = $rule['value'];

		switch ( $rule['op'] ) {
			case 'equals':
				if ( $cell !== $val ) {
					return false;
				}
				break;
			case 'not_equals':
				if ( $cell === $val ) {
					return false;
				}
				break;
			case 'contains':
				if ( false === stripos( $cell, $val ) ) {
					return false;
				}
				break;
			case 'not_contains':
				if ( false !== stripos( $cell, $val ) ) {
					return false;
				}
				break;
			case 'gt':
				if ( (float) $cell <= (float) $val ) {
					return false;
				}
				break;
			case 'lt':
				if ( (float) $cell >= (float) $val ) {
					return false;
				}
				break;
			case 'empty':
				if ( '' !== $cell ) {
					return false;
				}
				break;
			case 'not_empty':
				if ( '' === $cell ) {
					return false;
				}
				break;
		}
	}

	return true;
}

