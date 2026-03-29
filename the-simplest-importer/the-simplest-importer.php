<?php
/**
 * Plugin Name:       The Simplest Importer
 * Plugin URI:        https://github.com/ahmed-essawy/the-simplest-importer
 * Description:       Import, export, and manage posts and custom post types via CSV with visual column mapping and batch processing.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ahmed Essawy
 * Author URI:        https://profiles.wordpress.org/ahm.elessawy/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       the-simplest-importer
 * Domain Path:       /languages
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSI_VERSION', '1.0.0' );
define( 'TSI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------
 * Admin Menu — Tools submenu
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'tsi_register_admin_page' );

/**
 * Register the plugin page under Tools.
 *
 * @return void
 */
function tsi_register_admin_page() {
	add_management_page(
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Simplest Importer', 'the-simplest-importer' ),
		'manage_options',
		'the-simplest-importer',
		'tsi_render_admin_page'
	);
}

/* ------------------------------------------------------------------
 * WordPress Importer Registration — Tools → Import screen
 * ------------------------------------------------------------------ */

add_action( 'admin_init', 'tsi_register_wp_importer' );

/**
 * Register on the Tools → Import screen so the plugin appears in the
 * built-in importer list alongside other importers.
 *
 * @return void
 */
function tsi_register_wp_importer() {
	if ( ! function_exists( 'register_importer' ) ) {
		return;
	}
	register_importer(
		'the-simplest-importer',
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Import posts and custom post types from CSV files with visual column mapping.', 'the-simplest-importer' ),
		'tsi_wp_importer_dispatch'
	);
}

/**
 * Redirect from the Import screen to the dedicated plugin page.
 *
 * @return void
 */
function tsi_wp_importer_dispatch() {
	wp_safe_redirect( admin_url( 'tools.php?page=the-simplest-importer' ) );
	exit;
}

/* ------------------------------------------------------------------
 * Enqueue Admin Assets
 * ------------------------------------------------------------------ */

add_action( 'admin_enqueue_scripts', 'tsi_enqueue_admin_assets' );

/**
 * Enqueue CSS and JS only on the plugin admin page.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function tsi_enqueue_admin_assets( $hook ) {
	if ( 'tools_page_the-simplest-importer' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'tsi-admin',
		TSI_PLUGIN_URL . 'assets/style.css',
		array(),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/style.css' )
	);

	wp_enqueue_script(
		'tsi-admin',
		TSI_PLUGIN_URL . 'assets/app.js',
		array( 'jquery' ),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/app.js' ),
		true
	);

	wp_localize_script( 'tsi-admin', 'tsiImporter', array(
		'ajax_url'   => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'tsi_nonce' ),
		'batch_size' => absint( apply_filters( 'tsi_import_batch_size', 50 ) ),
	) );
}

/* ------------------------------------------------------------------
 * Admin Page Markup
 * ------------------------------------------------------------------ */

/**
 * Render the plugin admin page.
 *
 * @return void
 */
function tsi_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap tsi-wrap">

		<div class="tsi-header">
			<h1>
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'The Simplest Importer', 'the-simplest-importer' ); ?>
			</h1>
			<p class="tsi-subtitle"><?php esc_html_e( 'Import, export, and manage your WordPress content using CSV files.', 'the-simplest-importer' ); ?></p>
		</div>

		<!-- Step 1 — Choose content type -->
		<div class="tsi-card" id="tsi-step-entity">
			<div class="tsi-card-header">
				<span class="tsi-step-num">1</span>
				<div>
					<h2><?php esc_html_e( 'Choose Content Type', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Select the post type you want to work with.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<select id="tsi-post-type" class="tsi-select" aria-label="<?php esc_attr_e( 'Select content type', 'the-simplest-importer' ); ?>">
					<option value=""><?php esc_html_e( '— Select content type —', 'the-simplest-importer' ); ?></option>
				</select>
			</div>
		</div>

		<!-- Step 2 — Choose action -->
		<div class="tsi-card" id="tsi-step-actions" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">2</span>
				<div>
					<h2><?php esc_html_e( 'Choose Action', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'What would you like to do with this content type?', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-action-grid">
					<button type="button" id="tsi-btn-import" class="tsi-action-card">
						<span class="dashicons dashicons-upload"></span>
						<strong><?php esc_html_e( 'Import', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Upload a CSV to create or update posts', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-export" class="tsi-action-card">
						<span class="dashicons dashicons-download"></span>
						<strong><?php esc_html_e( 'Export', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Download all posts as a CSV file', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-template" class="tsi-action-card">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<strong><?php esc_html_e( 'Template', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Get a blank CSV with correct headers', 'the-simplest-importer' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 3 — Provide CSV -->
		<div class="tsi-card" id="tsi-step-export" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Export Options', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Choose which posts to include in the export.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<fieldset class="tsi-export-options">
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="all" checked>
							<strong><?php esc_html_e( 'Export All', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export every post of this content type.', 'the-simplest-importer' ); ?></span>
						</label>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="rows">
							<strong><?php esc_html_e( 'Export by Row Number', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export a specific range of rows (ordered by ID, starting from row 1).', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-range-fields" id="tsi-export-row-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From row:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-row-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To row:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-row-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="range">
							<strong><?php esc_html_e( 'Export by Post ID Range', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts whose ID property falls within a specific range.', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-range-fields" id="tsi-export-range-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From ID:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-id-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To ID:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-id-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="dates">
							<strong><?php esc_html_e( 'Export by Date Range', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts published between two dates.', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-date-fields" id="tsi-export-date-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From:', 'the-simplest-importer' ); ?>
								<input type="date" id="tsi-export-date-from">
							</label>
							<label>
								<?php esc_html_e( 'To:', 'the-simplest-importer' ); ?>
								<input type="date" id="tsi-export-date-to">
							</label>
						</div>
					</div>
				</fieldset>
				<div class="tsi-export-actions">
					<button type="button" id="tsi-btn-run-export" class="button button-primary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export', 'the-simplest-importer' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 3 — Provide CSV (Import) -->
		<div class="tsi-card" id="tsi-step-source" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Provide CSV File', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Upload a file from your computer or fetch one from a URL.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-source-tabs">
					<button type="button" class="tsi-source-tab tsi-source-tab--active" data-tab="upload">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Upload File', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" class="tsi-source-tab" data-tab="url">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'From URL', 'the-simplest-importer' ); ?>
					</button>
				</div>

				<div id="tsi-source-upload" class="tsi-source-panel">
					<div class="tsi-dropzone" id="tsi-dropzone">
						<div class="tsi-dropzone-inner">
							<span class="dashicons dashicons-cloud-upload"></span>
							<p class="tsi-dropzone-title"><?php esc_html_e( 'Drag & drop your CSV file here', 'the-simplest-importer' ); ?></p>
							<p class="tsi-dropzone-or"><?php esc_html_e( 'or', 'the-simplest-importer' ); ?></p>
							<button type="button" class="button" id="tsi-browse-btn"><?php esc_html_e( 'Browse Files', 'the-simplest-importer' ); ?></button>
							<input type="file" id="tsi-csv-file" accept=".csv">
							<p class="description"><?php esc_html_e( 'Accepts .csv files · UTF-8 recommended · first row must be column headers', 'the-simplest-importer' ); ?></p>
						</div>
					</div>
				</div>

				<div id="tsi-source-url" class="tsi-source-panel" style="display:none">
					<div class="tsi-url-row">
						<input type="url" id="tsi-csv-url" class="regular-text" placeholder="https://example.com/data.csv">
						<button type="button" id="tsi-btn-fetch-url" class="button button-primary"><?php esc_html_e( 'Fetch', 'the-simplest-importer' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Enter a direct link to a publicly accessible CSV file.', 'the-simplest-importer' ); ?></p>
				</div>

				<div id="tsi-file-info" class="tsi-file-info" style="display:none"></div>
				<div id="tsi-preview" style="display:none"></div>
			</div>
		</div>

		<!-- Step 4 — Map columns -->
		<div class="tsi-card" id="tsi-step-mapping" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">4</span>
				<div>
					<h2><?php esc_html_e( 'Map Columns', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Match CSV columns to post fields. Uncheck fields you do not need.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-mapping-toolbar">
					<button type="button" id="tsi-select-all" class="button button-small"><?php esc_html_e( 'Select All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-deselect-all" class="button button-small"><?php esc_html_e( 'Deselect All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-reset-all" class="button button-small"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset Mappings', 'the-simplest-importer' ); ?></button>
					<span class="tsi-mapping-count" id="tsi-mapping-count"></span>
				</div>
				<div class="tsi-table-wrap">
					<table class="widefat tsi-mapping-table" id="tsi-mapping-table">
						<thead>
							<tr>
								<th class="tsi-col-check"><?php esc_html_e( 'Use', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-field"><?php esc_html_e( 'Post Field', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-map"><?php esc_html_e( 'CSV Column / Value', 'the-simplest-importer' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="tsi-mapping-actions">
					<button type="button" id="tsi-add-extra" class="button">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom Field', 'the-simplest-importer' ); ?>
					</button>
					<div class="tsi-import-buttons">
						<button type="button" id="tsi-btn-insert" class="button button-primary tsi-btn-run">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Insert New', 'the-simplest-importer' ); ?>
						</button>
						<button type="button" id="tsi-btn-update" class="button button-primary tsi-btn-run" style="display:none">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Update Existing', 'the-simplest-importer' ); ?>
						</button>
						<button type="button" id="tsi-btn-insert-update" class="button button-primary tsi-btn-run" style="display:none">
							<span class="dashicons dashicons-controls-repeat"></span>
							<?php esc_html_e( 'Insert / Update', 'the-simplest-importer' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Step 5 — Progress -->
		<div class="tsi-card" id="tsi-step-progress" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num tsi-step-num--active">
					<span class="spinner is-active" style="margin:0;float:none"></span>
				</span>
				<div>
					<h2 id="tsi-progress-title"><?php esc_html_e( 'Importing…', 'the-simplest-importer' ); ?></h2>
					<p id="tsi-progress-detail"><?php esc_html_e( 'Preparing your import…', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-progress">
					<div class="tsi-progress-bar">
						<div class="tsi-progress-fill" id="tsi-progress-fill"></div>
					</div>
					<span class="tsi-progress-pct" id="tsi-progress-pct">0%</span>
				</div>
				<div class="tsi-live-log" id="tsi-live-log"></div>
			</div>
		</div>

		<!-- Step 6 — Results -->
		<div class="tsi-card" id="tsi-step-results" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num tsi-step-num--done">
					<span class="dashicons dashicons-yes-alt"></span>
				</span>
				<div>
					<h2><?php esc_html_e( 'Import Complete', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Review the results below.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div id="tsi-results-summary" class="tsi-summary"></div>
				<div id="tsi-results-log"></div>
				<div class="tsi-results-actions">
					<button type="button" id="tsi-btn-new" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Start New Import', 'the-simplest-importer' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Overlay spinner (export / template) -->
		<div id="tsi-overlay" class="tsi-overlay" style="display:none">
			<div class="tsi-overlay-inner">
				<span class="spinner is-active"></span>
				<span id="tsi-overlay-text"><?php esc_html_e( 'Processing…', 'the-simplest-importer' ); ?></span>
			</div>
		</div>

	</div>
	<?php
}

/* ------------------------------------------------------------------
 * AJAX — Get post types
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_get_post_types', 'tsi_ajax_get_post_types' );

/**
 * Return available post types with post counts.
 *
 * @return void
 */
function tsi_ajax_get_post_types() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
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
	$result = apply_filters( 'tsi_post_types', $result );

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Get fields for a post type
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_get_fields', 'tsi_ajax_get_fields' );

/**
 * Return importable fields for a given post type.
 *
 * @return void
 */
function tsi_ajax_get_fields() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	wp_send_json_success( tsi_get_post_type_fields( $post_type ) );
}

/**
 * Build the list of importable / exportable fields for a post type.
 *
 * @param string $post_type The post type slug.
 * @return array Associative array of field_key => label.
 */
function tsi_get_post_type_fields( $post_type ) {
	$fields = array(
		'ID'           => __( 'ID (update existing)', 'the-simplest-importer' ),
		'post_title'   => __( 'Title', 'the-simplest-importer' ),
		'post_content' => __( 'Content', 'the-simplest-importer' ),
		'post_excerpt' => __( 'Excerpt', 'the-simplest-importer' ),
		'post_status'  => __( 'Status', 'the-simplest-importer' ),
		'post_date'    => __( 'Date', 'the-simplest-importer' ),
		'post_name'    => __( 'Slug', 'the-simplest-importer' ),
		'post_author'  => __( 'Author ID', 'the-simplest-importer' ),
	);

	/* Taxonomies */
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $tax_slug => $tax_obj ) {
		/* translators: %s: taxonomy singular name */
		$fields[ 'tax__' . $tax_slug ] = sprintf( __( 'Tax: %s', 'the-simplest-importer' ), $tax_obj->labels->singular_name );
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
		$fields[ 'meta__' . $key ] = sprintf( __( 'Meta: %s', 'the-simplest-importer' ), $key );
	}

	$fields['_thumbnail_url'] = __( 'Featured Image URL', 'the-simplest-importer' );

	/**
	 * Filter the importable fields for a post type.
	 *
	 * @param array  $fields    Associative array of field_key => label.
	 * @param string $post_type The post type slug.
	 */
	return apply_filters( 'tsi_post_type_fields', $fields, $post_type );
}

/* ------------------------------------------------------------------
 * AJAX — Parse uploaded CSV (file)
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_parse_csv', 'tsi_ajax_parse_csv' );

/**
 * Parse an uploaded CSV file and store rows in a transient.
 *
 * @return void
 */
function tsi_ajax_parse_csv() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	if ( empty( $_FILES['csv_file'] ) ) {
		wp_send_json_error( esc_html__( 'No file uploaded.', 'the-simplest-importer' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array handled by PHP/WP upload processing.
	$file = $_FILES['csv_file'];

	$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
	if ( 'csv' !== $ext ) {
		wp_send_json_error( esc_html__( 'Only .csv files are allowed.', 'the-simplest-importer' ) );
	}

	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$mime  = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );

	$allowed = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		wp_send_json_error( esc_html__( 'Invalid file type.', 'the-simplest-importer' ) );
	}

	$result = tsi_read_csv_file( $file['tmp_name'] );
	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Parse CSV from a URL
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_parse_csv_url', 'tsi_ajax_parse_csv_url' );

/**
 * Fetch a remote CSV by URL and parse it.
 *
 * @return void
 */
function tsi_ajax_parse_csv_url() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$url = isset( $_POST['csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['csv_url'] ) ) : '';
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		wp_send_json_error( esc_html__( 'Invalid URL.', 'the-simplest-importer' ) );
	}

	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			/* translators: %s: HTTP error message */
			sprintf( esc_html__( 'Failed to fetch: %s', 'the-simplest-importer' ), $response->get_error_message() )
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		wp_send_json_error( esc_html__( 'Empty response from URL.', 'the-simplest-importer' ) );
	}

	$tmp = wp_tempnam( 'tsi_csv_' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for CSV parsing.
	file_put_contents( $tmp, $body );

	$result = tsi_read_csv_file( $tmp );
	wp_delete_file( $tmp );

	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/**
 * Read a CSV file, store data in a transient, and return parsed info.
 *
 * @param string $filepath Absolute path to the CSV file.
 * @return array|string Parsed data array on success, error message on failure.
 */
function tsi_read_csv_file( $filepath ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fopen needed for fgetcsv streaming.
	$handle = fopen( $filepath, 'r' );
	if ( ! $handle ) {
		return esc_html__( 'Cannot read file.', 'the-simplest-importer' );
	}

	/* Skip BOM */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- fread needed for BOM detection on fgetcsv stream.
	$bom = fread( $handle, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $handle );
	}

	$headers = fgetcsv( $handle );
	if ( ! $headers ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
		fclose( $handle );
		return esc_html__( 'Empty CSV or invalid format.', 'the-simplest-importer' );
	}

	$headers = array_map( 'trim', $headers );

	$rows = array();
	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		if ( count( $row ) === 1 && '' === trim( (string) $row[0] ) ) {
			continue; // Skip blank rows.
		}
		$rows[] = $row;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
	fclose( $handle );

	if ( empty( $rows ) ) {
		return esc_html__( 'CSV file contains headers but no data rows.', 'the-simplest-importer' );
	}

	$token = wp_generate_password( 20, false );
	set_transient( 'tsi_csv_data_' . $token, array(
		'headers' => $headers,
		'rows'    => $rows,
	), HOUR_IN_SECONDS );

	return array(
		'headers'   => $headers,
		'row_count' => count( $rows ),
		'preview'   => array_slice( $rows, 0, 5 ),
		'token'     => $token,
	);
}

/* ------------------------------------------------------------------
 * AJAX — Export posts as CSV
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_export', 'tsi_ajax_export' );

/**
 * Export all posts of a given type as a base64-encoded CSV.
 *
 * @return void
 */
function tsi_ajax_export() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$export_mode = isset( $_POST['export_mode'] ) ? sanitize_key( wp_unslash( $_POST['export_mode'] ) ) : 'all';

	$query_args = array(
		'post_type'        => $post_type,
		'post_status'      => 'any',
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
			$query_args['tsi_id_from'] = $id_from;
			$query_args['tsi_id_to']   = $id_to;

			add_filter( 'posts_where', 'tsi_filter_export_id_range', 10, 2 );
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

	remove_filter( 'posts_where', 'tsi_filter_export_id_range', 10 );

	if ( empty( $posts ) ) {
		wp_send_json_error( esc_html__( 'No posts found for this post type.', 'the-simplest-importer' ) );
	}

	$fields    = tsi_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author' );

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

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d' ) . '.csv' ),
	) );
}

/**
 * Filter posts_where to constrain by ID range during export.
 *
 * @param string   $where    The WHERE clause.
 * @param WP_Query $wp_query The query object.
 * @return string
 */
function tsi_filter_export_id_range( $where, $wp_query ) {
	global $wpdb;

	$id_from = $wp_query->get( 'tsi_id_from' );
	$id_to   = $wp_query->get( 'tsi_id_to' );

	if ( $id_from ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", $id_from );
	}
	if ( $id_to ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", $id_to );
	}

	return $where;
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
	$total   = count( $data['rows'] );
	$rows    = array_slice( $data['rows'], $offset, $batch_size );

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	$clean_map = tsi_sanitize_mapping( $mapping, count( $headers ) );

	$log      = array();
	$inserted = 0;
	$updated  = 0;
	$skipped  = 0;
	$errors   = 0;

	foreach ( $rows as $i => $row ) {
		$row_num = $offset + $i + 2; // +2: header is row 1, data starts at row 2.
		$result  = tsi_import_single_row( $row, $row_num, $post_type, $clean_map );

		$log[] = $result['message'];
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
				break;
		}
	}

	$next_offset = $offset + count( $rows );
	$done        = $next_offset >= $total;

	if ( $done ) {
		delete_transient( 'tsi_csv_data_' . $token );
	}

	wp_send_json_success( array(
		'offset'   => $next_offset,
		'total'    => $total,
		'done'     => $done,
		'log'      => $log,
		'inserted' => $inserted,
		'updated'  => $updated,
		'skipped'  => $skipped,
		'errors'   => $errors,
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
 * @param array  $row       The CSV row values.
 * @param int    $row_num   Human-readable row number (for logs).
 * @param string $post_type Target post type.
 * @param array  $mapping   Sanitized mapping array.
 * @return array { status: string, message: string }
 */
function tsi_import_single_row( $row, $row_num, $post_type, $mapping ) {
	$post_data = array( 'post_type' => $post_type );
	$meta_data = array();
	$tax_data  = array();
	$thumb_url = '';

	foreach ( $mapping as $field => $info ) {
		$value = ( 'custom' === $info['source'] )
			? $info['value']
			: ( isset( $row[ $info['col'] ] ) ? $row[ $info['col'] ] : '' );

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
		} elseif ( '_thumbnail_url' === $field ) {
			$thumb_url = esc_url_raw( $value );
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

	/* Determine insert vs. update */
	$is_update = false;
	if ( ! empty( $post_data['ID'] ) ) {
		$existing = get_post( $post_data['ID'] );
		if ( $existing && $existing->post_type === $post_type ) {
			$is_update = true;
		} elseif ( $existing ) {
			return array(
				'status'  => 'skipped',
				/* translators: 1: row number, 2: post ID */
				'message' => sprintf( __( 'Row %1$d: Skipped — ID %2$d belongs to a different post type.', 'the-simplest-importer' ), $row_num, $post_data['ID'] ),
			);
		} else {
			unset( $post_data['ID'] ); // ID not found; insert as new.
		}
	}

	if ( ! $is_update && empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = 'draft';
	}

	$result = $is_update
		? wp_update_post( $post_data, true )
		: wp_insert_post( $post_data, true );

	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			/* translators: 1: row number, 2: error message */
			'message' => sprintf( __( 'Row %1$d: Error — %2$s', 'the-simplest-importer' ), $row_num, $result->get_error_message() ),
		);
	}

	$post_id = $result;

	foreach ( $meta_data as $key => $val ) {
		update_post_meta( $post_id, $key, $val );
	}

	foreach ( $tax_data as $tax => $terms ) {
		if ( taxonomy_exists( $tax ) ) {
			wp_set_object_terms( $post_id, $terms, $tax );
		}
	}

	if ( $thumb_url ) {
		tsi_set_featured_image( $post_id, $thumb_url );
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
			/* translators: 1: row number, 2: post ID */
			'message' => sprintf( __( 'Row %1$d: Updated post #%2$d', 'the-simplest-importer' ), $row_num, $post_id ),
		);
	}

	return array(
		'status'  => 'inserted',
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
