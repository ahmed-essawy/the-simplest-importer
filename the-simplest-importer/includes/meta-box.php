<?php
/**
 * Post edit screen meta box and dashboard widget.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * Meta Box — Export single post from edit screen
 * ------------------------------------------------------------------ */

/**
 * Register the TSI meta box on all post types with show_ui.
 */
function tsi_register_meta_box() {
	$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'tsi-single-export',
			esc_html__( 'The Simplest Importer', 'the-simplest-importer' ),
			'tsi_render_meta_box',
			$pt,
			'side',
			'low'
		);
	}
}
add_action( 'add_meta_boxes', 'tsi_register_meta_box' );

/**
 * Render the meta box content.
 *
 * @param WP_Post $post The current post object.
 */
function tsi_render_meta_box( $post ) {
	wp_nonce_field( 'tsi_nonce', 'tsi_meta_box_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Export this post as a CSV file.', 'the-simplest-importer' ); ?></p>
	<button type="button" class="button button-small" id="tsi-export-single" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Download CSV', 'the-simplest-importer' ); ?></button>
	<script>
	(function () {
		document.getElementById('tsi-export-single').addEventListener('click', function () {
			var postId = this.getAttribute('data-post-id');
			var nonce  = document.getElementById('tsi_meta_box_nonce').value;
			var data   = new FormData();
			data.append('action', 'tsi_export_single_post');
			data.append('nonce', nonce);
			data.append('post_id', postId);
			fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) {
						window.alert(res.data || 'Export failed.');
						return;
					}
					var raw = atob(res.data.csv);
					var bytes = new Uint8Array(raw.length);
					for (var i = 0; i < raw.length; i++) { bytes[i] = raw.charCodeAt(i); }
					var blob = new Blob([bytes], { type: 'text/csv;charset=utf-8' });
					var url  = URL.createObjectURL(blob);
					var a    = document.createElement('a');
					a.href = url;
					a.download = res.data.filename;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
				});
		});
	})();
	</script>
	<?php
}

/**
 * AJAX handler — export a single post as CSV.
 */
function tsi_ajax_export_single_post() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( esc_html__( 'Invalid post ID.', 'the-simplest-importer' ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( esc_html__( 'Post not found.', 'the-simplest-importer' ) );
	}

	$post_type = $post->post_type;
	$fields    = tsi_get_post_type_fields( $post_type );
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
add_action( 'wp_ajax_tsi_export_single_post', 'tsi_ajax_export_single_post' );

/* ------------------------------------------------------------------
 * Dashboard Widget — Import statistics
 * ------------------------------------------------------------------ */

add_action( 'wp_dashboard_setup', 'tsi_register_dashboard_widget' );

/**
 * Register a dashboard widget showing recent import activity.
 *
 * @return void
 */
function tsi_register_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'tsi_dashboard_widget',
		__( 'Simplest Importer — Activity', 'the-simplest-importer' ),
		'tsi_render_dashboard_widget'
	);
}

/**
 * Render the dashboard widget content.
 *
 * @return void
 */
function tsi_render_dashboard_widget() {
	$history   = get_option( TSI_HISTORY_OPTION, array() );
	$schedules = get_option( TSI_SCHEDULES_OPTION, array() );
	$exports   = get_option( TSI_EXPORT_SCHEDULES_OPTION, array() );

	/* Recent imports — last 5 */
	$recent = array_slice( $history, 0, 5 );

	if ( empty( $recent ) ) {
		echo '<p>' . esc_html__( 'No imports yet.', 'the-simplest-importer' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="border:0"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'the-simplest-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'the-simplest-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Rows', 'the-simplest-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'the-simplest-importer' ) . '</th>';
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
				? sprintf( __( '%1$d errors / %2$d', 'the-simplest-importer' ), $errors, $total )
				/* translators: %d: total rows */
				: sprintf( __( '%d OK', 'the-simplest-importer' ), $total );

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
		$ts = wp_next_scheduled( 'tsi_scheduled_import', array( isset( $sch['id'] ) ? $sch['id'] : '' ) );
		if ( $ts ) {
			/* translators: %s: human-readable time difference */
			$next_import = sprintf( __( 'Next import: %s', 'the-simplest-importer' ), human_time_diff( $ts ) . ' ' . __( 'from now', 'the-simplest-importer' ) );
			break;
		}
	}

	$next_export = '';
	foreach ( $exports as $sch ) {
		$ts = wp_next_scheduled( 'tsi_scheduled_export', array( isset( $sch['id'] ) ? $sch['id'] : '' ) );
		if ( $ts ) {
			/* translators: %s: human-readable time difference */
			$next_export = sprintf( __( 'Next export: %s', 'the-simplest-importer' ), human_time_diff( $ts ) . ' ' . __( 'from now', 'the-simplest-importer' ) );
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

	echo '<p style="margin:8px 0 0"><a href="' . esc_url( admin_url( 'tools.php?page=the-simplest-importer' ) ) . '">' . esc_html__( 'Open Importer →', 'the-simplest-importer' ) . '</a></p>';
}

