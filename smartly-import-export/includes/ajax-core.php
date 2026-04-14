<?php
/**
 * Core AJAX handlers: post types, fields, file parsing.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * AJAX — Get post types
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_smie_get_post_types', 'smie_ajax_get_post_types' );

/**
 * Return available post types with post counts.
 *
 * @return void
 */
function smie_ajax_get_post_types() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$skip  = array(
		'attachment',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'wp_global_styles',
	);

	$result = array();
	foreach ( $types as $slug => $obj ) {
		if ( in_array( $slug, $skip, true ) ) {
			continue;
		}

		$counts = wp_count_posts( $slug );
		$total  = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $s ) {
			$total += isset( $counts->$s ) ? (int) $counts->$s : 0;
		}

		$max_id = 0;
		if ( $total > 0 ) {
			$latest = get_posts( array(
				'post_type'      => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			) );
			$max_id = ! empty( $latest ) ? (int) $latest[0] : 0;
		}

		$result[] = array(
			'slug'   => $slug,
			'label'  => $obj->labels->singular_name,
			'count'  => $total,
			'max_id' => $max_id,
		);
	}

	/**
	 * Filter the list of post types shown in the importer dropdown.
	 *
	 * @param array $result Array of post type data.
	 */
	$result = smie_apply_filters( 'smie_post_types', $result );

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Get fields for a post type
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_smie_get_fields', 'smie_ajax_get_fields' );

/**
 * Return importable fields for a given post type.
 *
 * @return void
 */
function smie_ajax_get_fields() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'smartly-import-export' ) );
	}

	wp_send_json_success( smie_get_post_type_fields( $post_type ) );
}

/**
 * Build the list of importable / exportable fields for a post type.
 *
 * @param string $post_type The post type slug.
 * @return array Associative array of field_key => label.
 */
function smie_get_post_type_fields( $post_type ) {
	$fields = array(
		'ID'           => __( 'ID (update existing)', 'smartly-import-export' ),
		'post_title'   => __( 'Title', 'smartly-import-export' ),
		'post_content' => __( 'Content', 'smartly-import-export' ),
		'post_excerpt' => __( 'Excerpt', 'smartly-import-export' ),
		'post_status'  => __( 'Status', 'smartly-import-export' ),
		'post_date'    => __( 'Date', 'smartly-import-export' ),
		'post_name'    => __( 'Slug', 'smartly-import-export' ),
		'post_author'  => __( 'Author ID', 'smartly-import-export' ),
		'post_parent'  => __( 'Parent (ID or title)', 'smartly-import-export' ),
	);

	/* Taxonomies */
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $tax_slug => $tax_obj ) {
		/* translators: %s: taxonomy singular name */
		$fields[ 'tax__' . $tax_slug ] = sprintf( __( 'Tax: %s', 'smartly-import-export' ), $tax_obj->labels->singular_name );
	}

	/* Meta keys already in use */
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta key discovery; not cacheable.
	$meta_keys = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT pm.meta_key
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = %s
		   AND pm.meta_key NOT LIKE %s
		 ORDER BY pm.meta_key",
		$post_type,
		$wpdb->esc_like( '_edit_' ) . '%'
	) );

	$skip_meta = array( '_wp_old_slug', '_wp_attached_file', '_wp_attachment_metadata', '_pingme', '_encloseme' );
	foreach ( $meta_keys as $key ) {
		if ( in_array( $key, $skip_meta, true ) ) {
			continue;
		}
		/* translators: %s: meta key name */
		$fields[ 'meta__' . $key ] = sprintf( __( 'Meta: %s', 'smartly-import-export' ), $key );
	}

	$fields['_thumbnail_url']       = __( 'Featured Image URL', 'smartly-import-export' );
	$fields['_product_gallery_urls'] = __( 'Image Gallery URLs (comma-separated)', 'smartly-import-export' );

	/* SEO plugin fields — auto-detect Yoast SEO or Rank Math */
	if ( defined( 'WPSEO_VERSION' ) ) {
		$fields['meta___yoast_wpseo_title']    = __( 'SEO: Title (Yoast)', 'smartly-import-export' );
		$fields['meta___yoast_wpseo_metadesc'] = __( 'SEO: Description (Yoast)', 'smartly-import-export' );
		$fields['meta___yoast_wpseo_focuskw']  = __( 'SEO: Focus Keyword (Yoast)', 'smartly-import-export' );
	}

	if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
		$fields['meta__rank_math_title']       = __( 'SEO: Title (Rank Math)', 'smartly-import-export' );
		$fields['meta__rank_math_description'] = __( 'SEO: Description (Rank Math)', 'smartly-import-export' );
		$fields['meta__rank_math_focus_keyword'] = __( 'SEO: Focus Keyword (Rank Math)', 'smartly-import-export' );
	}

	/* ACF fields — auto-detect Advanced Custom Fields */
	if ( function_exists( 'acf_get_field_groups' ) ) {
		$acf_groups = acf_get_field_groups( array(
			'post_type' => $post_type,
		) );

		$acf_simple_types = array(
			'text', 'textarea', 'number', 'range', 'email', 'url', 'password',
			'wysiwyg', 'select', 'radio', 'true_false', 'date_picker',
			'date_time_picker', 'time_picker', 'color_picker',
		);

		foreach ( $acf_groups as $group ) {
			$acf_fields = acf_get_fields( $group );
			if ( ! is_array( $acf_fields ) ) {
				continue;
			}
			foreach ( $acf_fields as $acf_field ) {
				$key = 'meta__' . $acf_field['name'];
				/* Only add if not already discovered via meta query above */
				if ( isset( $fields[ $key ] ) ) {
					/* Upgrade label from raw meta to friendly ACF label */
					/* translators: %s: ACF field label */
					$fields[ $key ] = sprintf( __( 'ACF: %s', 'smartly-import-export' ), $acf_field['label'] );
				} elseif ( in_array( $acf_field['type'], $acf_simple_types, true ) ) {
					/* translators: %s: ACF field label */
					$fields[ $key ] = sprintf( __( 'ACF: %s', 'smartly-import-export' ), $acf_field['label'] );
				} elseif ( 'image' === $acf_field['type'] || 'file' === $acf_field['type'] ) {
					/* translators: 1: ACF field label, 2: field type */
					$fields[ $key ] = sprintf( __( 'ACF: %1$s (%2$s URL)', 'smartly-import-export' ), $acf_field['label'], $acf_field['type'] );
				}
			}
		}
	}

	/**
	 * Filter the importable fields for a post type.
	 *
	 * @param array  $fields    Associative array of field_key => label.
	 * @param string $post_type The post type slug.
	 */
	return smie_apply_filters( 'smie_post_type_fields', $fields, $post_type );
}

/* ------------------------------------------------------------------
 * AJAX — Parse uploaded CSV (file)
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_smie_parse_csv', 'smie_ajax_parse_csv' );

/**
 * Parse an uploaded CSV file and store rows in a transient.
 *
 * @return void
 */
function smie_ajax_parse_csv() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	if ( empty( $_FILES['csv_file'] ) ) {
		wp_send_json_error( esc_html__( 'No file uploaded.', 'smartly-import-export' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array handled by PHP/WP upload processing.
	$file = $_FILES['csv_file'];

	$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'csv', 'json', 'xml' ), true ) ) {
		wp_send_json_error( esc_html__( 'Only .csv, .json, and .xml files are allowed.', 'smartly-import-export' ) );
	}

	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$mime  = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );

	$allowed = array(
		'text/csv',
		'text/plain',
		'application/csv',
		'application/vnd.ms-excel',
		'application/json',
		'text/json',
		'text/xml',
		'application/xml',
	);
	if ( ! in_array( $mime, $allowed, true ) ) {
		wp_send_json_error( esc_html__( 'Invalid file type.', 'smartly-import-export' ) );
	}

	$result = smie_read_import_file( $file['tmp_name'], $ext );
	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Parse CSV from a URL
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_smie_parse_csv_url', 'smie_ajax_parse_csv_url' );

/**
 * Fetch a remote CSV by URL and parse it.
 *
 * @return void
 */
function smie_ajax_parse_csv_url() {
	check_ajax_referer( 'smie_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$url = isset( $_POST['csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['csv_url'] ) ) : '';
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		wp_send_json_error( esc_html__( 'Invalid URL.', 'smartly-import-export' ) );
	}

	/* Auto-convert Google Sheets URL to CSV export URL */
	$url = smie_convert_google_sheets_url( $url );

	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			/* translators: %s: HTTP error message */
			sprintf( esc_html__( 'Failed to fetch: %s', 'smartly-import-export' ), $response->get_error_message() )
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		wp_send_json_error( esc_html__( 'Empty response from URL.', 'smartly-import-export' ) );
	}

	$tmp = wp_tempnam( 'smie_csv_' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for CSV parsing.
	file_put_contents( $tmp, $body );

	/* Detect file format from URL extension or Content-Type header */
	$url_ext      = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$content_type = wp_remote_retrieve_header( $response, 'content-type' );
	$format       = 'csv';

	if ( 'json' === $url_ext || false !== strpos( $content_type, 'json' ) ) {
		$format = 'json';
	} elseif ( 'xml' === $url_ext || false !== strpos( $content_type, 'xml' ) ) {
		$format = 'xml';
	}

	$result = smie_read_import_file( $tmp, $format );
	wp_delete_file( $tmp );

	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/**
 * Read a CSV file, store data in a transient, and return parsed info.
 *
 * Automatically detects the delimiter (comma, semicolon, tab, pipe).
 *
 * @param string $filepath Absolute path to the CSV file.
 * @return array|string Parsed data array on success, error message on failure.
 */
function smie_read_csv_file( $filepath ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fopen needed for fgetcsv streaming.
	$handle = fopen( $filepath, 'r' );
	if ( ! $handle ) {
		return esc_html__( 'Cannot read file.', 'smartly-import-export' );
	}

	/* Skip BOM */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- fread needed for BOM detection on fgetcsv stream.
	$bom = fread( $handle, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $handle );
	}

	/* Auto-detect delimiter by reading the first line */
	$first_line_pos = ftell( $handle );
	$first_line     = fgets( $handle );
	fseek( $handle, $first_line_pos );

	$delimiter  = ',';
	$delimiters = array( ',' => 0, ';' => 0, "\t" => 0, '|' => 0 );
	if ( $first_line ) {
		foreach ( $delimiters as $d => &$count ) {
			$count = substr_count( $first_line, $d );
		}
		unset( $count );
		arsort( $delimiters );
		$best = key( $delimiters );
		if ( $delimiters[ $best ] > 0 ) {
			$delimiter = $best;
		}
	}

	$headers = fgetcsv( $handle, 0, $delimiter );
	if ( ! $headers ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
		fclose( $handle );
		return esc_html__( 'Empty CSV or invalid format.', 'smartly-import-export' );
	}

	$headers = array_map( 'trim', $headers );

	$rows = array();
	while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
		if ( count( $row ) === 1 && '' === trim( (string) $row[0] ) ) {
			continue; // Skip blank rows.
		}
		$rows[] = $row;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
	fclose( $handle );

	if ( empty( $rows ) ) {
		return esc_html__( 'CSV file contains headers but no data rows.', 'smartly-import-export' );
	}

	/**
	 * Filter parsed CSV data before storing in transient.
	 *
	 * @param array $csv_data {
	 *     @type array  $headers   Column headers.
	 *     @type array  $rows      All data rows.
	 *     @type string $delimiter Detected delimiter character.
	 * }
	 */
	$csv_data = smie_apply_filters( 'smie_csv_parsed', array(
		'headers'   => $headers,
		'rows'      => $rows,
		'delimiter' => $delimiter,
	) );

	$headers   = $csv_data['headers'];
	$rows      = $csv_data['rows'];
	$delimiter = $csv_data['delimiter'];

	$token = wp_generate_password( 20, false );
	smie_set_import_data_transient( $token, $csv_data );

	$delimiter_labels = array(
		','  => __( 'comma', 'smartly-import-export' ),
		';'  => __( 'semicolon', 'smartly-import-export' ),
		"\t" => __( 'tab', 'smartly-import-export' ),
		'|'  => __( 'pipe', 'smartly-import-export' ),
	);

	return array(
		'headers'   => $headers,
		'row_count' => count( $rows ),
		'preview'   => array_slice( $rows, 0, 5 ),
		'token'     => $token,
		'delimiter' => isset( $delimiter_labels[ $delimiter ] ) ? $delimiter_labels[ $delimiter ] : $delimiter,
	);
}

/**
 * Get the transient lifetime used for parsed import files.
 *
 * @return int
 */
function smie_get_import_data_ttl() {
	$ttl = (int) smie_apply_filters( 'smie_import_data_ttl', DAY_IN_SECONDS );

	if ( $ttl < HOUR_IN_SECONDS ) {
		return HOUR_IN_SECONDS;
	}

	return $ttl;
}

/**
 * Read a JSON file, store data in a transient, and return parsed info.
 *
 * Expects an array of objects (each object = one row) or a wrapper object
 * containing an array in one of its top-level keys.
 *
 * @param string $filepath Absolute path to the JSON file.
 * @return array|string Parsed data array on success, error message on failure.
 */
function smie_read_json_file( $filepath ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading local temp file for JSON parsing.
	$raw = file_get_contents( $filepath );
	if ( false === $raw || '' === trim( $raw ) ) {
		return esc_html__( 'Cannot read file or file is empty.', 'smartly-import-export' );
	}

	$data = json_decode( $raw, true );
	if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
		return esc_html__( 'Invalid JSON format.', 'smartly-import-export' );
	}

	/* If root is an object, look for the first array value */
	if ( ! wp_is_numeric_array( $data ) ) {
		$found = false;
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) && wp_is_numeric_array( $val ) && ! empty( $val ) ) {
				$data  = $val;
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return esc_html__( 'JSON must contain an array of objects.', 'smartly-import-export' );
		}
	}

	if ( empty( $data ) ) {
		return esc_html__( 'JSON array is empty.', 'smartly-import-export' );
	}

	/* Flatten nested keys with dot notation and collect all unique keys */
	$all_keys = array();
	$flat_rows = array();

	foreach ( $data as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$flat = array();
		smie_flatten_array( $item, $flat, '' );
		$flat_rows[] = $flat;
		foreach ( array_keys( $flat ) as $k ) {
			$all_keys[ $k ] = true;
		}
	}

	if ( empty( $flat_rows ) ) {
		return esc_html__( 'JSON contains no valid row objects.', 'smartly-import-export' );
	}

	$headers = array_keys( $all_keys );

	/* Convert associative rows to indexed arrays matching headers order */
	$rows = array();
	foreach ( $flat_rows as $flat ) {
		$row = array();
		foreach ( $headers as $h ) {
			$val   = isset( $flat[ $h ] ) ? $flat[ $h ] : '';
			$row[] = is_array( $val ) ? wp_json_encode( $val ) : (string) $val;
		}
		$rows[] = $row;
	}

	/** This filter is documented in smie_read_csv_file */
	$csv_data = smie_apply_filters( 'smie_csv_parsed', array(
		'headers'   => $headers,
		'rows'      => $rows,
		'delimiter' => 'json',
	) );

	$headers = $csv_data['headers'];
	$rows    = $csv_data['rows'];

	$token = wp_generate_password( 20, false );
	smie_set_import_data_transient( $token, $csv_data );

	return array(
		'headers'   => $headers,
		'row_count' => count( $rows ),
		'preview'   => array_slice( $rows, 0, 5 ),
		'token'     => $token,
		'delimiter' => 'json',
	);
}

/**
 * Recursively flatten a nested associative array.
 *
 * Nested keys are joined with a dot separator (e.g. meta.price).
 *
 * @param array  $array  The source array.
 * @param array  $result The flat result array (passed by reference).
 * @param string $prefix The current key prefix.
 * @return void
 */
function smie_flatten_array( $array, &$result, $prefix ) {
	foreach ( $array as $key => $value ) {
		$full_key = '' !== $prefix ? $prefix . '.' . $key : $key;
		if ( is_array( $value ) && ! wp_is_numeric_array( $value ) ) {
			smie_flatten_array( $value, $result, $full_key );
		} else {
			$result[ $full_key ] = $value;
		}
	}
}

/**
 * Read an XML file, store data in a transient, and return parsed info.
 *
 * Supports common structures:
 * - Root element with repeating child elements (each child = one row).
 * - WP WXR format: <channel><item>...</item></channel>
 *
 * @param string $filepath Absolute path to the XML file.
 * @return array|string Parsed data array on success, error message on failure.
 */
function smie_read_xml_file( $filepath ) {
	if ( ! function_exists( 'simplexml_load_string' ) ) {
		return esc_html__( 'XML parsing requires the SimpleXML PHP extension.', 'smartly-import-export' );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading local temp file for XML parsing.
	$raw = file_get_contents( $filepath );
	if ( false === $raw || '' === trim( $raw ) ) {
		return esc_html__( 'Cannot read file or file is empty.', 'smartly-import-export' );
	}

	$prev = libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( false === $xml ) {
		return esc_html__( 'Invalid XML format.', 'smartly-import-export' );
	}

	/* Determine item elements */
	$items = array();

	/* Try WXR: <rss><channel><item> */
	if ( isset( $xml->channel->item ) && count( $xml->channel->item ) > 0 ) {
		foreach ( $xml->channel->item as $item ) {
			$items[] = $item;
		}
	}

	/* Try direct children (all same tag name, e.g. <items><item>) */
	if ( empty( $items ) ) {
		$children   = $xml->children();
		$tag_counts = array();
		foreach ( $children as $child ) {
			$name = $child->getName();
			if ( ! isset( $tag_counts[ $name ] ) ) {
				$tag_counts[ $name ] = 0;
			}
			++$tag_counts[ $name ];
		}
		/* Use the most common repeating child tag */
		if ( ! empty( $tag_counts ) ) {
			arsort( $tag_counts );
			$best_tag = key( $tag_counts );
			if ( $tag_counts[ $best_tag ] > 1 ) {
				foreach ( $children as $child ) {
					if ( $child->getName() === $best_tag ) {
						$items[] = $child;
					}
				}
			}
		}
	}

	/* Last resort: if root has exactly one child that is a wrapper, go one level deeper */
	if ( empty( $items ) && 1 === count( $xml->children() ) ) {
		$wrapper = $xml->children()[0];
		foreach ( $wrapper->children() as $child ) {
			$items[] = $child;
		}
	}

	if ( empty( $items ) ) {
		return esc_html__( 'Could not find repeating item elements in XML.', 'smartly-import-export' );
	}

	/* Collect all unique element names and convert to flat rows */
	$all_keys  = array();
	$flat_rows = array();

	foreach ( $items as $item ) {
		$flat = array();
		smie_xml_element_to_flat( $item, $flat, '' );
		$flat_rows[] = $flat;
		foreach ( array_keys( $flat ) as $k ) {
			$all_keys[ $k ] = true;
		}
	}

	if ( empty( $flat_rows ) ) {
		return esc_html__( 'XML items contain no data.', 'smartly-import-export' );
	}

	$headers = array_keys( $all_keys );

	$rows = array();
	foreach ( $flat_rows as $flat ) {
		$row = array();
		foreach ( $headers as $h ) {
			$row[] = isset( $flat[ $h ] ) ? (string) $flat[ $h ] : '';
		}
		$rows[] = $row;
	}

	/** This filter is documented in smie_read_csv_file */
	$csv_data = smie_apply_filters( 'smie_csv_parsed', array(
		'headers'   => $headers,
		'rows'      => $rows,
		'delimiter' => 'xml',
	) );

	$headers = $csv_data['headers'];
	$rows    = $csv_data['rows'];

	$token = wp_generate_password( 20, false );
	smie_set_import_data_transient( $token, $csv_data );

	return array(
		'headers'   => $headers,
		'row_count' => count( $rows ),
		'preview'   => array_slice( $rows, 0, 5 ),
		'token'     => $token,
		'delimiter' => 'xml',
	);
}

/**
 * Recursively flatten a SimpleXMLElement into key-value pairs.
 *
 * Child element names become dot-separated keys (e.g. address.city).
 *
 * @param SimpleXMLElement $element The XML element.
 * @param array            $result  The flat result array (passed by reference).
 * @param string           $prefix  The current key prefix.
 * @return void
 */
function smie_xml_element_to_flat( $element, &$result, $prefix ) {
	$children = $element->children();
	if ( 0 === count( $children ) ) {
		$key = '' !== $prefix ? $prefix : $element->getName();
		$result[ $key ] = trim( (string) $element );
		return;
	}
	foreach ( $children as $child ) {
		$name     = $child->getName();
		$full_key = '' !== $prefix ? $prefix . '.' . $name : $name;
		if ( count( $child->children() ) > 0 ) {
			smie_xml_element_to_flat( $child, $result, $full_key );
		} else {
			$result[ $full_key ] = trim( (string) $child );
		}
	}
}

/**
 * Detect file format and call the appropriate reader.
 *
 * @param string $filepath  Absolute path to the file.
 * @param string $extension File extension hint (csv, json, xml). Empty for auto-detect.
 * @return array|string Parsed data array on success, error message on failure.
 */
function smie_read_import_file( $filepath, $extension = '' ) {
	if ( '' === $extension ) {
		$extension = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
	}

	switch ( $extension ) {
		case 'json':
			return smie_read_json_file( $filepath );
		case 'xml':
			return smie_read_xml_file( $filepath );
		default:
			return smie_read_csv_file( $filepath );
	}
}

/**
 * Convert a Google Sheets URL to its CSV export equivalent.
 *
 * Accepts formats:
 *   https://docs.google.com/spreadsheets/d/{ID}/edit#gid=0
 *   https://docs.google.com/spreadsheets/d/{ID}/edit?...
 *   https://docs.google.com/spreadsheets/d/{ID}/pub?...
 *   https://docs.google.com/spreadsheets/d/e/{PUBID}/pub?output=csv
 *
 * If the URL is not a Google Sheets URL, returns it unchanged.
 *
 * @param string $url The original URL.
 * @return string The CSV export URL, or original if not a Google Sheets URL.
 */
function smie_convert_google_sheets_url( $url ) {
	/* Already a CSV export URL */
	if ( preg_match( '/output=csv/', $url ) ) {
		return $url;
	}

	/* Standard Google Sheets URL: /spreadsheets/d/{ID}/... */
	if ( preg_match( '#docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $matches ) ) {
		$sheet_id = $matches[1];

		/* Extract gid if present */
		$gid = '0';
		if ( preg_match( '/gid=(\d+)/', $url, $gid_matches ) ) {
			$gid = $gid_matches[1];
		}

		return 'https://docs.google.com/spreadsheets/d/' . $sheet_id . '/export?format=csv&gid=' . $gid;
	}

	/* Published Google Sheets URL: /spreadsheets/d/e/{PUBID}/pub */
	if ( preg_match( '#docs\.google\.com/spreadsheets/d/e/([a-zA-Z0-9_-]+)/pub#', $url, $matches ) ) {
		$pub_id = $matches[1];

		$gid = '0';
		if ( preg_match( '/gid=(\d+)/', $url, $gid_matches ) ) {
			$gid = $gid_matches[1];
		}

		return 'https://docs.google.com/spreadsheets/d/e/' . $pub_id . '/pub?output=csv&gid=' . $gid;
	}

	return $url;
}

