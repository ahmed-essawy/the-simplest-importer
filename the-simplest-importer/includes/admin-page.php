<?php
/**
 * Admin page rendering and asset enqueueing.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
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

	wp_enqueue_style(
		'tsi-responsive',
		TSI_PLUGIN_URL . 'assets/css/responsive.css',
		array( 'tsi-admin' ),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/css/responsive.css' )
	);

	wp_enqueue_style(
		'tsi-features',
		TSI_PLUGIN_URL . 'assets/css/features.css',
		array( 'tsi-admin' ),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/css/features.css' )
	);

	wp_enqueue_script(
		'tsi-admin',
		TSI_PLUGIN_URL . 'assets/app.js',
		array( 'jquery' ),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/app.js' ),
		true
	);

	wp_localize_script( 'tsi-admin', 'tsiImporter', array(
		'ajax_url'         => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'tsi_nonce' ),
		'batch_size'       => absint( apply_filters( 'tsi_import_batch_size', 50 ) ),
		'profiles'         => tsi_get_mapping_profiles(),
		'history'          => tsi_get_import_history(),
		'schedules'        => tsi_get_scheduled_imports(),
		'export_schedules' => tsi_get_export_schedules(),
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
				<button type="button" class="tsi-dark-toggle" id="tsi-dark-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'the-simplest-importer' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
				</button>
			</h1>
			<p class="tsi-subtitle"><?php esc_html_e( 'Import, export, and manage your WordPress content using CSV, JSON, and XML files.', 'the-simplest-importer' ); ?></p>
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
				<p class="description tsi-entity-hint"><?php esc_html_e( 'Includes all registered post types with a UI.', 'the-simplest-importer' ); ?></p>
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
						<span class="tsi-action-desc"><?php esc_html_e( 'Upload a file to create or update posts', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-export" class="tsi-action-card">
						<span class="dashicons dashicons-download"></span>
						<strong><?php esc_html_e( 'Export', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Download posts as CSV or Excel', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-template" class="tsi-action-card">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<strong><?php esc_html_e( 'Template', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Get a blank CSV with correct headers', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-history" class="tsi-action-card">
						<span class="dashicons dashicons-backup"></span>
						<strong><?php esc_html_e( 'History', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'View past imports and rollback', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-schedule" class="tsi-action-card">
						<span class="dashicons dashicons-clock"></span>
						<strong><?php esc_html_e( 'Schedule', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Set up recurring imports from a URL', 'the-simplest-importer' ); ?></span>
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
					<select id="tsi-export-format" class="tsi-export-format">
						<option value="csv"><?php esc_html_e( 'CSV', 'the-simplest-importer' ); ?></option>
						<option value="xlsx"><?php esc_html_e( 'Excel (XLSX)', 'the-simplest-importer' ); ?></option>
					</select>
					<button type="button" id="tsi-btn-run-export" class="button button-primary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export', 'the-simplest-importer' ); ?>
					</button>
				</div>

				<details class="tsi-export-advanced">
					<summary><?php esc_html_e( 'Advanced Options', 'the-simplest-importer' ); ?></summary>
					<div class="tsi-export-advanced-body">
						<div class="tsi-export-section">
							<h4><?php esc_html_e( 'Filter by Status', 'the-simplest-importer' ); ?></h4>
							<div class="tsi-status-checks">
								<label><input type="checkbox" name="tsi-export-status" value="publish" checked> <?php esc_html_e( 'Published', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="draft" checked> <?php esc_html_e( 'Draft', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="pending" checked> <?php esc_html_e( 'Pending', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="private" checked> <?php esc_html_e( 'Private', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="future"> <?php esc_html_e( 'Scheduled', 'the-simplest-importer' ); ?></label>
							</div>
						</div>
						<div class="tsi-export-section">
							<h4><?php esc_html_e( 'Select Columns', 'the-simplest-importer' ); ?></h4>
							<p class="description"><?php esc_html_e( 'Leave all checked to export every field.', 'the-simplest-importer' ); ?></p>
							<div id="tsi-export-fields" class="tsi-export-fields-list"></div>
						</div>
					</div>
				</details>
			</div>
		</div>

		<!-- Step 3 — Provide CSV (Import) -->
		<div class="tsi-card" id="tsi-step-source" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Provide Data File', 'the-simplest-importer' ); ?></h2>
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
							<p class="tsi-dropzone-title"><?php esc_html_e( 'Drag & drop your file here', 'the-simplest-importer' ); ?></p>
							<p class="tsi-dropzone-or"><?php esc_html_e( 'or', 'the-simplest-importer' ); ?></p>
							<button type="button" class="button" id="tsi-browse-btn"><?php esc_html_e( 'Browse Files', 'the-simplest-importer' ); ?></button>
							<input type="file" id="tsi-csv-file" accept=".csv,.json,.xml">
							<p class="description"><?php esc_html_e( 'Accepts .csv, .json, and .xml files · UTF-8 recommended · first row/object must define columns', 'the-simplest-importer' ); ?></p>
						</div>
					</div>
				</div>

				<div id="tsi-source-url" class="tsi-source-panel" style="display:none">
					<div class="tsi-url-row">
						<input type="url" id="tsi-csv-url" class="regular-text" placeholder="https://example.com/data.csv">
						<button type="button" id="tsi-btn-fetch-url" class="button button-primary"><?php esc_html_e( 'Fetch', 'the-simplest-importer' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Enter a direct link to a publicly accessible CSV, JSON, or XML file. Google Sheets URLs are automatically converted.', 'the-simplest-importer' ); ?></p>
				</div>

				<div id="tsi-file-info" class="tsi-file-info" style="display:none"></div>
				<div id="tsi-file-queue" style="display:none"></div>
				<div id="tsi-preview" style="display:none"></div>
			</div>
		</div>

		<!-- Step 4 — Map columns -->
		<div class="tsi-card" id="tsi-step-mapping" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">4</span>
				<div>
					<h2><?php esc_html_e( 'Map Columns', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Match file columns to post fields. Uncheck fields you do not need.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-mapping-toolbar">
					<button type="button" id="tsi-select-all" class="button button-small"><?php esc_html_e( 'Select All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-deselect-all" class="button button-small"><?php esc_html_e( 'Deselect All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-reset-all" class="button button-small"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset Mappings', 'the-simplest-importer' ); ?></button>
					<select id="tsi-profile-select" class="tsi-profile-select">
						<option value=""><?php esc_html_e( '— Load Profile —', 'the-simplest-importer' ); ?></option>
					</select>
					<button type="button" id="tsi-save-profile" class="button button-small"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Save Profile', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-delete-profile" class="button button-small" style="display:none"><span class="dashicons dashicons-trash"></span></button>
					<span class="tsi-mapping-count" id="tsi-mapping-count"></span>
				</div>
				<div class="tsi-field-search">
					<span class="dashicons dashicons-search tsi-field-search-icon"></span>
					<input type="text" id="tsi-field-search" class="tsi-field-search-input" placeholder="<?php esc_attr_e( 'Search fields…', 'the-simplest-importer' ); ?>" />
					<span class="tsi-field-search-count" id="tsi-field-search-count"></span>
				</div>
				<div class="tsi-table-wrap">
					<table class="widefat tsi-mapping-table" id="tsi-mapping-table">
						<thead>
							<tr>
								<th class="tsi-col-check"><?php esc_html_e( 'Use', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-field"><?php esc_html_e( 'Post Field', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-map"><?php esc_html_e( 'Column / Value', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-transform"><?php esc_html_e( 'Transform', 'the-simplest-importer' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="tsi-mapping-preview" id="tsi-mapping-preview" style="display:none">
					<h4><?php esc_html_e( 'Sample Row Preview', 'the-simplest-importer' ); ?></h4>
					<div id="tsi-mapping-preview-content"></div>
				</div>
				<div class="tsi-mapping-actions">
					<button type="button" id="tsi-add-extra" class="button">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom Field', 'the-simplest-importer' ); ?>
					</button>

					<details class="tsi-import-settings" open>
						<summary><?php esc_html_e( 'Import Settings', 'the-simplest-importer' ); ?></summary>
						<div class="tsi-import-settings-body">
							<div class="tsi-import-options">
								<label class="tsi-option-label" title="<?php esc_attr_e( 'Process all rows without inserting or updating any posts', 'the-simplest-importer' ); ?>">
									<input type="checkbox" id="tsi-dry-run"> <?php esc_html_e( 'Dry Run', 'the-simplest-importer' ); ?>
								</label>
								<label class="tsi-option-label" title="<?php esc_attr_e( 'Check for existing posts before inserting', 'the-simplest-importer' ); ?>">
									<input type="checkbox" id="tsi-dup-check"> <?php esc_html_e( 'Duplicate Check', 'the-simplest-importer' ); ?>
								</label>
								<div id="tsi-dup-options">
									<select id="tsi-dup-field" class="tsi-dup-field-select">
										<option value="post_title"><?php esc_html_e( 'by Title', 'the-simplest-importer' ); ?></option>
										<option value="post_name"><?php esc_html_e( 'by Slug', 'the-simplest-importer' ); ?></option>
										<option value="meta_key"><?php esc_html_e( 'by Meta Key', 'the-simplest-importer' ); ?></option>
									</select>
									<span id="tsi-dup-meta-wrap">
										<input type="text" id="tsi-dup-meta-key" class="small-text" placeholder="<?php esc_attr_e( 'meta key', 'the-simplest-importer' ); ?>">
									</span>
								</div>
							</div>
							<div class="tsi-filter-section" id="tsi-filter-section" style="display:none">
								<h4><?php esc_html_e( 'Row Filters', 'the-simplest-importer' ); ?></h4>
								<p class="description"><?php esc_html_e( 'Only import rows matching all rules (AND logic).', 'the-simplest-importer' ); ?></p>
								<div id="tsi-filter-rules"></div>
								<button type="button" id="tsi-add-filter" class="button button-small">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e( 'Add Filter Rule', 'the-simplest-importer' ); ?>
								</button>
							</div>
						</div>
					</details>

					<div class="tsi-import-buttons">
						<button type="button" id="tsi-btn-validate" class="button tsi-btn-run">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Validate', 'the-simplest-importer' ); ?>
						</button>
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
					<div class="tsi-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Import progress', 'the-simplest-importer' ); ?>" id="tsi-progress-bar">
						<div class="tsi-progress-fill" id="tsi-progress-fill"></div>
					</div>
					<span class="tsi-progress-pct" id="tsi-progress-pct">0%</span>
				</div>
				<div class="tsi-live-log" id="tsi-live-log" aria-live="polite" aria-relevant="additions"></div>
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
					<button type="button" id="tsi-btn-download-log" class="button">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Log', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" id="tsi-btn-rollback" class="button" style="display:none">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Rollback Import', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" id="tsi-btn-retry-failed" class="button button-primary" style="display:none">
						<span class="dashicons dashicons-controls-repeat"></span>
						<?php esc_html_e( 'Retry Failed Rows', 'the-simplest-importer' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Validation Results -->
		<div class="tsi-card" id="tsi-step-validation" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-yes-alt"></span></span>
				<div>
					<h2><?php esc_html_e( 'Validation Results', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Review issues found in your data before importing.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div id="tsi-validation-results" role="alert"></div>
				<div class="tsi-results-actions">
					<button type="button" id="tsi-btn-proceed-import" class="button button-primary"><?php esc_html_e( 'Proceed with Import', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-btn-cancel-import" class="button"><?php esc_html_e( 'Cancel', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Import History -->
		<div class="tsi-card" id="tsi-step-history" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-backup"></span></span>
				<div>
					<h2><?php esc_html_e( 'Import History', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'View past imports and undo them if needed.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'I / U / S / E', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Posts', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-history-body">
						<tr><td colspan="6"><?php esc_html_e( 'Loading…', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Scheduled Imports -->
		<div class="tsi-card" id="tsi-step-schedule" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-clock"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Imports', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Automatically import from a URL on a recurring schedule.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled imports.', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
				<div class="tsi-schedule-form">
					<h4><?php esc_html_e( 'Add New Schedule', 'the-simplest-importer' ); ?></h4>
					<div class="tsi-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'the-simplest-importer' ); ?>
							<input type="text" id="tsi-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My daily import', 'the-simplest-importer' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'Data URL:', 'the-simplest-importer' ); ?>
							<input type="url" id="tsi-schedule-url" class="regular-text" placeholder="https://example.com/data.csv">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'the-simplest-importer' ); ?>
							<select id="tsi-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'the-simplest-importer' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'the-simplest-importer' ); ?></option>
								<option value="daily" selected><?php esc_html_e( 'Daily', 'the-simplest-importer' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Mapping Profile:', 'the-simplest-importer' ); ?>
							<select id="tsi-schedule-profile">
								<option value=""><?php esc_html_e( '— auto-match —', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'the-simplest-importer' ); ?>
							<input type="email" id="tsi-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'the-simplest-importer' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will use the currently selected post type.', 'the-simplest-importer' ); ?></p>
					<button type="button" id="tsi-btn-add-schedule" class="button button-primary"><?php esc_html_e( 'Add Schedule', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Scheduled Exports -->
		<div class="tsi-card" id="tsi-step-export-schedule" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-download"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Exports', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Automatically export posts on a recurring schedule and optionally receive the file by email.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-export-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled exports.', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
				<div class="tsi-schedule-form">
					<h4><?php esc_html_e( 'Add New Export Schedule', 'the-simplest-importer' ); ?></h4>
					<div class="tsi-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'the-simplest-importer' ); ?>
							<input type="text" id="tsi-export-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My weekly export', 'the-simplest-importer' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'the-simplest-importer' ); ?>
							<select id="tsi-export-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'the-simplest-importer' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'the-simplest-importer' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'the-simplest-importer' ); ?></option>
								<option value="weekly" selected><?php esc_html_e( 'Weekly', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'the-simplest-importer' ); ?>
							<input type="email" id="tsi-export-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'the-simplest-importer' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will export all posts of the currently selected post type. Files are stored in wp-content/uploads/tsi-exports/ and auto-deleted after 7 days.', 'the-simplest-importer' ); ?></p>
					<button type="button" id="tsi-btn-add-export-schedule" class="button button-primary"><?php esc_html_e( 'Add Export Schedule', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Overlay spinner (export / template) -->
		<div id="tsi-overlay" class="tsi-overlay" style="display:none" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Processing', 'the-simplest-importer' ); ?>">
			<div class="tsi-overlay-inner">
				<span class="spinner is-active"></span>
				<span id="tsi-overlay-text" aria-live="assertive"><?php esc_html_e( 'Processing…', 'the-simplest-importer' ); ?></span>
			</div>
		</div>

	</div>
	<?php
}

