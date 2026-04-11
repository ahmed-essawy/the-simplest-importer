<?php
/**
 * Plugin info for the Plugins page thickbox.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Plugin Row Meta — "View details" link on Plugins page
 * ------------------------------------------------------------------ */

add_filter( 'plugin_action_links_' . TSI_PLUGIN_BASENAME, 'tsi_plugin_action_links' );

/**
 * Add a "Use Me" link to the plugin action links on the Plugins page.
 *
 * @param array $links Existing action links.
 * @return array Modified links.
 */
function tsi_plugin_action_links( $links ) {
	$use_me_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'tools.php?page=the-simplest-importer' ) ),
		esc_html__( 'Use Me', 'the-simplest-importer' )
	);

	array_unshift( $links, $use_me_link );

	return $links;
}

add_filter( 'plugin_row_meta', 'tsi_plugin_row_meta', 10, 2 );

/**
 * Add a "View details" thickbox link to the plugin row on the Plugins page.
 *
 * @param array  $links Existing meta links.
 * @param string $file  Plugin file path.
 * @return array Modified links.
 */
function tsi_plugin_row_meta( $links, $file ) {
	if ( TSI_PLUGIN_BASENAME !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
		esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=the-simplest-importer&TB_iframe=true&width=600&height=550' ) ),
		esc_attr__( 'More information about The Simplest Importer', 'the-simplest-importer' ),
		esc_html__( 'View details', 'the-simplest-importer' )
	);

	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://www.paypal.com/paypalme/ahmessawy/10USD' ),
		esc_html__( 'Donate', 'the-simplest-importer' )
	);

	return $links;
}

add_filter( 'plugins_api', 'tsi_plugins_api_info', 10, 3 );

/**
 * Provide plugin details for the "View details" thickbox popup.
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string             $action The API action being performed.
 * @param object             $args   Plugin API arguments.
 * @return false|object
 */
function tsi_plugins_api_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}
	if ( ! isset( $args->slug ) || 'the-simplest-importer' !== $args->slug ) {
		return $result;
	}

	$info                 = new stdClass();
	$info->name           = 'The Simplest Importer';
	$info->slug           = 'the-simplest-importer';
	$info->version        = TSI_VERSION;
	$info->author         = '<a href="https://minicad.io/">Ahmed Essawy</a>';
	$info->author_profile = 'https://minicad.io/';
	$info->requires       = '6.3';
	$info->tested         = '7.0';
	$info->requires_php   = '7.4';
	$info->donate_link    = 'https://www.paypal.com/paypalme/ahmessawy/10USD';
	$info->homepage       = 'https://github.com/ahmed-essawy/the-simplest-importer';

	$info->sections = array(
		'description'  => '<p>' . esc_html__( 'Import, export, and manage posts and custom post types via CSV, JSON, and XML with visual column mapping and batch processing.', 'the-simplest-importer' ) . '</p>'
			. '<h4>' . esc_html__( 'Key Features', 'the-simplest-importer' ) . '</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'CSV, JSON, and XML import with auto-format detection', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Visual drag-and-drop column mapping with auto-match', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Export to CSV or Excel XLSX', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Batch processing with progress bar and ETA', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Scheduled imports and exports via WP-Cron', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Duplicate detection, dry run mode, and import rollback', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'ACF, Yoast SEO, and Rank Math meta support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Google Sheets import, field transforms, and conditional row filtering', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge, post parent-child, and mapping profiles', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode toggle and field search in mapping table', 'the-simplest-importer' ) . '</li>'
			. '</ul>',
		'installation' => '<h4>' . esc_html__( 'Installation from within WordPress', 'the-simplest-importer' ) . '</h4>'
			. '<ol>'
			. '<li>' . esc_html__( 'Visit Plugins > Add New.', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Search for The Simplest Importer.', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Install and activate the The Simplest Importer plugin.', 'the-simplest-importer' ) . '</li>'
			. '</ol>'
			. '<h4>' . esc_html__( 'Manual installation', 'the-simplest-importer' ) . '</h4>'
			. '<ol>'
			. '<li>' . esc_html__( 'Upload the entire the-simplest-importer folder to the /wp-content/plugins/ directory.', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Visit Plugins.', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Activate the The Simplest Importer plugin.', 'the-simplest-importer' ) . '</li>'
			. '</ol>',
		'changelog'    => '<h4>v1.4.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Full-width dashboard layout', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode with manual toggle', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Field search in mapping table', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Post parent-child import support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Extra transforms: find/replace, prepend, append, math, dates, URL encode', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge mapping', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dashboard statistics widget', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'View Details popup on Plugins page', 'the-simplest-importer' ) . '</li>'
			. '</ul>'
			. '<h4>v1.3.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'XML and JSON import support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Excel XLSX export', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'WooCommerce product gallery image import', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Hierarchical taxonomy import', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Error row retry', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Real-time mapping preview', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Import progress ETA', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Accessibility improvements', 'the-simplest-importer' ) . '</li>'
			. '</ul>'
			. '<h4>v1.2.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'ACF support with update_field()', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'SEO meta support for Yoast SEO and Rank Math', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Google Sheets import', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Conditional row filtering', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Scheduled exports with email attachment', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Single post export meta box', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Email notifications for scheduled imports', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( '8 new developer hooks', 'the-simplest-importer' ) . '</li>'
			. '</ul>'
			. '<h4>v1.1.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Scheduled imports via WP-Cron', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Import history and rollback', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Duplicate detection', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Field transforms', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Mapping profiles', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'CSV validation and dry run mode', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Multi-file upload', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Delimiter auto-detection', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Selective column export and status filter', 'the-simplest-importer' ) . '</li>'
			. '</ul>'
			. '<h4>v1.0.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Initial release', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'CSV import with visual column mapping', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Batch import with progress bar', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'CSV export with multiple modes', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Drag-and-drop upload and URL fetch', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Featured image and taxonomy support', 'the-simplest-importer' ) . '</li>'
			. '</ul>',
	);

	$info->last_updated = gmdate( 'Y-m-d' );

	return $info;
}
