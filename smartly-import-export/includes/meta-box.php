<?php
/**
 * Post edit screen meta box and dashboard widget.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', 'smie_enqueue_meta_box_assets' );

/**
 * Enqueue the single-export meta box script on post edit screens.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function smie_enqueue_meta_box_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	wp_enqueue_script(
		'smie-meta-box',
		SMIE_PLUGIN_URL . 'assets/meta-box.js',
		array(),
		(string) filemtime( SMIE_PLUGIN_DIR . 'assets/meta-box.js' ),
		true
	);
}
/* ------------------------------------------------------------------
 * Meta Box — Export single post from edit screen
 * ------------------------------------------------------------------ */

/**
 * Register the Smartly Import Export meta box on all post types with show_ui.
 */
function smie_register_meta_box() {
	$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'smie-single-export',
			esc_html__( 'Smartly Import Export', 'smartly-import-export' ),
			'smie_render_meta_box',
			$pt,
			'side',
			'low'
		);
	}
}
add_action( 'add_meta_boxes', 'smie_register_meta_box' );

/**
 * Render the meta box content.
 *
 * @param WP_Post $post The current post object.
 */
function smie_render_meta_box( $post ) {
	wp_nonce_field( 'smie_nonce', 'smie_meta_box_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Export this post as a CSV file.', 'smartly-import-export' ); ?></p>
	<button type="button" class="button button-small" id="smie-export-single" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Download CSV', 'smartly-import-export' ); ?></button>
	<?php
}

/**
 * AJAX handler — export a single post as CSV.
 */
function smie_ajax_export_single_post() {
	check_ajax_referer( 'smie_nonce', 'smie_meta_box_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'smartly-import-export' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( esc_html__( 'Invalid post ID.', 'smartly-import-export' ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( esc_html__( 'Post not found.', 'smartly-import-export' ) );
	}

	$post_type = $post->post_type;
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

	$row = array();
	foreach ( $core_keys as $ck ) {
		$row[] = isset( $post->$ck ) ? $post->$ck : '';
	}
	foreach ( $tax_fields as $t ) {
		$terms = wp_get_object_terms( $post->ID, $t, array( 'fields' => 'names' ) );
		$row[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
	}
	foreach ( $meta_fields as $m ) {
		$val   = get_post_meta( $post->ID, $m, true );
		$row[] = is_array( $val ) ? wp_json_encode( $val ) : ( isset( $val ) ? (string) $val : '' );
	}
	fputcsv( $output, $row );

	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-' . $post_id . '-export.csv' ),
	) );
}
add_action( 'wp_ajax_smie_export_single_post', 'smie_ajax_export_single_post' );

/* ------------------------------------------------------------------
 * Dashboard Widget — Import statistics
 * ------------------------------------------------------------------ */

add_action( 'wp_dashboard_setup', 'smie_register_dashboard_widget' );

/**
 * Register a dashboard widget showing recent import activity.
 *
 * @return void
 */
function smie_register_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'smie_dashboard_widget',
		__( 'Smartly Import Export — Activity', 'smartly-import-export' ),
		'smie_render_dashboard_widget'
	);
}

/**
 * Render the dashboard widget content.
 *
 * @return void
 */
function smie_render_dashboard_widget() {
	$history   = smie_get_import_history();
	$schedules = smie_get_scheduled_imports();
	$exports   = smie_get_export_schedules();

	/* Recent imports — last 5 */
	$recent = array_slice( array_reverse( $history ), 0, 5 );

	if ( empty( $recent ) ) {
		echo '<p>' . esc_html__( 'No imports yet.', 'smartly-import-export' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="border:0"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'smartly-import-export' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'smartly-import-export' ) . '</th>';
		echo '<th>' . esc_html__( 'Rows', 'smartly-import-export' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'smartly-import-export' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $recent as $entry ) {
			$date      = isset( $entry['date'] ) ? esc_html( $entry['date'] ) : '—';
			$type      = isset( $entry['post_type'] ) ? esc_html( $entry['post_type'] ) : '—';
			$inserted  = isset( $entry['inserted'] ) ? absint( $entry['inserted'] ) : 0;
			$updated   = isset( $entry['updated'] ) ? absint( $entry['updated'] ) : 0;
			$errors    = isset( $entry['errors'] ) ? absint( $entry['errors'] ) : 0;
			$total     = $inserted + $updated + $errors;
			$status    = $errors > 0
				/* translators: 1: error count, 2: total rows */
				? sprintf( __( '%1$d errors / %2$d', 'smartly-import-export' ), $errors, $total )
				/* translators: %d: total rows */
				: sprintf( __( '%d OK', 'smartly-import-export' ), $total );

			echo '<tr>';
			echo '<td>' . esc_html( $date ) . '</td>';
			echo '<td>' . esc_html( $type ) . '</td>';
			echo '<td>' . esc_html( $total ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/* Next scheduled import */
	$next_import = '';
	foreach ( $schedules as $sch ) {
		$ts = wp_next_scheduled( 'smie_scheduled_import', array( isset( $sch['id'] ) ? $sch['id'] : '' ) );
		if ( $ts ) {
			/* translators: %s: human-readable time difference */
			$next_import = sprintf( __( 'Next import: %s', 'smartly-import-export' ), human_time_diff( $ts ) . ' ' . __( 'from now', 'smartly-import-export' ) );
			break;
		}
	}

	$next_export = '';
	foreach ( $exports as $sch ) {
		$ts = wp_next_scheduled( 'smie_scheduled_export', array( isset( $sch['id'] ) ? $sch['id'] : '' ) );
		if ( $ts ) {
			/* translators: %s: human-readable time difference */
			$next_export = sprintf( __( 'Next export: %s', 'smartly-import-export' ), human_time_diff( $ts ) . ' ' . __( 'from now', 'smartly-import-export' ) );
			break;
		}
	}

	if ( $next_import || $next_export ) {
		echo '<p style="margin:8px 0 4px;font-size:12px;color:#646970">';
		if ( $next_import ) {
			echo '<span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> ' . esc_html( $next_import );
		}
		if ( $next_import && $next_export ) {
			echo '<br>';
		}
		if ( $next_export ) {
			echo '<span class="dashicons dashicons-clock" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> ' . esc_html( $next_export );
		}
		echo '</p>';
	}

	echo '<p style="margin:8px 0 0"><a href="' . esc_url( admin_url( 'tools.php?page=smartly-import-export' ) ) . '">' . esc_html__( 'Open Importer →', 'smartly-import-export' ) . '</a></p>';
}

