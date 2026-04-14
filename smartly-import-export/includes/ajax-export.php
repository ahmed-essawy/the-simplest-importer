<?php
/**
 * Export AJAX handler and XLSX generation.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * AJAX — Export posts as CSV
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_smie_export', 'smie_ajax_export' );

/**
 * Export all posts of a given type as a base64-encoded CSV.
 *
 * @return void
 */
function smie_ajax_export() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'smartly-import-export' ) );
	}

	$export_mode   = isset( $_POST['export_mode'] ) ? sanitize_key( wp_unslash( $_POST['export_mode'] ) ) : 'all';
	$export_format = isset( $_POST['export_format'] ) ? sanitize_key( wp_unslash( $_POST['export_format'] ) ) : 'csv';
	if ( ! in_array( $export_format, array( 'csv', 'xlsx' ), true ) ) {
		$export_format = 'csv';
	}

	/* Status filter */
	$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
	$export_statuses  = array();
	if ( ! empty( $_POST['export_statuses'] ) && is_array( $_POST['export_statuses'] ) ) {
		$raw_statuses = array_map( 'sanitize_key', wp_unslash( $_POST['export_statuses'] ) );
		foreach ( $raw_statuses as $s ) {
			if ( in_array( $s, $allowed_statuses, true ) ) {
				$export_statuses[] = $s;
			}
		}
	}

	/* Selective columns */
	$selected_fields = array();
	$raw_fields      = isset( $_POST['export_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['export_fields'] ) ) : array();
	if ( is_array( $raw_fields ) && ! empty( $raw_fields ) ) {
		$selected_fields = $raw_fields;
	}

	$query_args = array(
		'post_type'        => $post_type,
		'post_status'      => ! empty( $export_statuses ) ? $export_statuses : 'any',
		'posts_per_page'   => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	);

	if ( 'rows' === $export_mode ) {
		$row_from = isset( $_POST['row_from'] ) ? absint( $_POST['row_from'] ) : 1;
		$row_to   = isset( $_POST['row_to'] ) ? absint( $_POST['row_to'] ) : 0;

		if ( $row_from < 1 ) {
			$row_from = 1;
		}

		$query_args['offset'] = $row_from - 1;
		if ( $row_to >= $row_from ) {
			$query_args['posts_per_page'] = $row_to - $row_from + 1;
		}
	} elseif ( 'range' === $export_mode ) {
		$id_from = isset( $_POST['id_from'] ) ? absint( $_POST['id_from'] ) : 0;
		$id_to   = isset( $_POST['id_to'] ) ? absint( $_POST['id_to'] ) : 0;

		if ( $id_from || $id_to ) {
			$query_args['smie_id_from'] = $id_from;
			$query_args['smie_id_to']   = $id_to;

			add_filter( 'posts_where', 'smie_filter_export_id_range', 10, 2 );
		}
	} elseif ( 'dates' === $export_mode ) {
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$date_query = array();
		if ( $date_from ) {
			$date_query['after'] = $date_from;
		}
		if ( $date_to ) {
			$date_query['before']    = $date_to;
			$date_query['inclusive'] = true;
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive']  = true;
			$query_args['date_query'] = array( $date_query );
		}
	}

	$posts = get_posts( $query_args );

	remove_filter( 'posts_where', 'smie_filter_export_id_range', 10 );

	if ( empty( $posts ) ) {
		wp_send_json_error( esc_html__( 'No posts found for this post type.', 'smartly-import-export' ) );
	}

	$fields    = smie_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author', 'post_parent' );

	/**
	 * Filter the list of columns included in an export.
	 *
	 * @param array  $fields    Associative array of field_key => label.
	 * @param string $post_type The post type being exported.
	 */
	$fields = smie_apply_filters( 'smie_export_columns', $fields, $post_type );

	/* Apply selective field filter if specified */
	if ( ! empty( $selected_fields ) ) {
		$fields    = array_intersect_key( $fields, array_flip( $selected_fields ) );
		$core_keys = array_intersect( $core_keys, $selected_fields );
	}

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

	/* Collect all rows for export */
	$all_rows = array();
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
		/** This filter is documented above. */
		$row        = smie_apply_filters( 'smie_export_row', $row, $p, $header );
		$all_rows[] = $row;
	}

	/**
	 * Fires after an export completes.
	 *
	 * @param string $post_type  The exported post type.
	 * @param int    $post_count Number of posts exported.
	 */
	smie_do_action( 'smie_export_completed', $post_type, count( $posts ) );

	if ( 'xlsx' === $export_format && class_exists( 'ZipArchive' ) ) {
		$xlsx_data = smie_generate_xlsx( $header, $all_rows );
		if ( $xlsx_data ) {
			wp_send_json_success( array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport binary through JSON.
				'csv'      => base64_encode( $xlsx_data ),
				'filename' => sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d' ) . '.xlsx' ),
				'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			) );
		}
		/* Fall through to CSV if XLSX generation failed */
	}

	/* CSV output */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $header );
	foreach ( $all_rows as $row ) {
		fputcsv( $output, $row );
	}
	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d' ) . '.csv' ),
	) );
}

/* ------------------------------------------------------------------
 * Helper — Generate minimal XLSX from header + rows
 * ------------------------------------------------------------------ */

/**
 * Generate a minimal valid XLSX file using ZipArchive.
 *
 * Creates a standards-compliant Open XML spreadsheet without any
 * external dependencies. Requires the ZipArchive PHP extension.
 *
 * @param array $header Column header strings.
 * @param array $rows   Array of row arrays (each row is an indexed array of cell values).
 * @return string|false Binary XLSX data on success, false on failure.
 */
function smie_generate_xlsx( $header, $rows ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}

	$tmp_file = wp_tempnam( 'smie_xlsx_' );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return false;
	}

	/* [Content_Types].xml */
	$zip->addFromString( '[Content_Types].xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
		'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
		'<Default Extension="xml" ContentType="application/xml"/>' .
		'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
		'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
		'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
		'</Types>'
	);

	/* _rels/.rels */
	$zip->addFromString( '_rels/.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
		'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
		'</Relationships>'
	);

	/* xl/_rels/workbook.xml.rels */
	$zip->addFromString( 'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
		'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
		'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
		'</Relationships>'
	);

	/* xl/workbook.xml */
	$zip->addFromString( 'xl/workbook.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
		'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>' .
		'</workbook>'
	);

	/* Build shared strings table and sheet data */
	$strings     = array();
	$string_map  = array();
	$string_idx  = 0;

	/**
	 * Map a cell value to a shared string index.
	 */
	$get_string_index = function ( $val ) use ( &$strings, &$string_map, &$string_idx ) {
		$val = (string) $val;
		if ( ! isset( $string_map[ $val ] ) ) {
			$string_map[ $val ] = $string_idx;
			$strings[]          = $val;
			++$string_idx;
		}
		return $string_map[ $val ];
	};

	/* Sheet XML */
	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
		'<sheetData>';

	/* Header row */
	$sheet_xml .= '<row r="1">';
	foreach ( $header as $c => $cell ) {
		$col_letter = smie_xlsx_col_letter( $c );
		$si         = $get_string_index( $cell );
		$sheet_xml .= '<c r="' . $col_letter . '1" t="s"><v>' . $si . '</v></c>';
	}
	$sheet_xml .= '</row>';

	/* Data rows */
	foreach ( $rows as $r => $row ) {
		$row_num    = $r + 2;
		$sheet_xml .= '<row r="' . $row_num . '">';
		foreach ( $row as $c => $cell ) {
			$col_letter = smie_xlsx_col_letter( $c );
			$cell_str   = (string) $cell;

			/* Numeric values stored as numbers for Excel calculation support */
			if ( '' !== $cell_str && is_numeric( $cell_str ) && strlen( $cell_str ) < 15 ) {
				$sheet_xml .= '<c r="' . $col_letter . $row_num . '"><v>' . esc_html( $cell_str ) . '</v></c>';
			} else {
				$si         = $get_string_index( $cell_str );
				$sheet_xml .= '<c r="' . $col_letter . $row_num . '" t="s"><v>' . $si . '</v></c>';
			}
		}
		$sheet_xml .= '</row>';
	}

	$sheet_xml .= '</sheetData></worksheet>';
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );

	/* xl/sharedStrings.xml */
	$ss_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
		'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '" uniqueCount="' . count( $strings ) . '">';
	foreach ( $strings as $s ) {
		$ss_xml .= '<si><t>' . esc_html( $s ) . '</t></si>';
	}
	$ss_xml .= '</sst>';
	$zip->addFromString( 'xl/sharedStrings.xml', $ss_xml );

	$zip->close();

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading temp XLSX file for base64 encoding.
	$data = file_get_contents( $tmp_file );
	wp_delete_file( $tmp_file );

	return $data;
}

/**
 * Convert a zero-based column index to an Excel column letter (A, B, ..., Z, AA, AB, ...).
 *
 * @param int $index Zero-based column index.
 * @return string Column letter(s).
 */
function smie_xlsx_col_letter( $index ) {
	$letter = '';
	while ( $index >= 0 ) {
		$letter = chr( 65 + ( $index % 26 ) ) . $letter;
		$index  = intdiv( $index, 26 ) - 1;
	}
	return $letter;
}

/**
 * Filter posts_where to constrain by ID range during export.
 *
 * @param string   $where    The WHERE clause.
 * @param WP_Query $wp_query The query object.
 * @return string
 */
function smie_filter_export_id_range( $where, $wp_query ) {
	global $wpdb;

	$id_from = $wp_query->get( 'smie_id_from' );
	$id_to   = $wp_query->get( 'smie_id_to' );

	if ( $id_from ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", $id_from );
	}
	if ( $id_to ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", $id_to );
	}

	return $where;
}

