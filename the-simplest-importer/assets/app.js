/**
 * The Simplest Importer — Admin UI
 *
 * @package TheSimplestImporter
 */

/* global jQuery, tsiImporter */
(function ($) {
	'use strict';

	/* ----------------------------------------------------------------
	 * State
	 * ---------------------------------------------------------------- */

	var csvHeaders       = [];
	var csvToken         = '';
	var csvRowCount      = 0;
	var extraFieldCount  = 0;
	var postTypeData     = {};
	var lastHistoryId    = '';
	var lastAllLogs      = [];
	var fileQueue        = [];
	var csvDelimiter     = ',';
	var failedRowIndices = [];
	var lastMapping      = {};
	var lastImportMode   = 'insert';
	var lastDryRun       = false;
	var lastDupField     = '';
	var lastDupMeta      = '';
	var lastTransforms   = {};
	var lastFilters      = [];
	var firstPreviewRow  = [];

	/* ================================================================
	 * Step 1 — Load post types
	 * ================================================================ */

	$.post(tsiImporter.ajax_url, {
		action: 'tsi_get_post_types',
		nonce:  tsiImporter.nonce
	}, function (res) {
		if (!res.success) {
			return;
		}

		var $sel = $('#tsi-post-type');
		$.each(res.data, function (i, pt) {
			postTypeData[pt.slug] = pt;
			$sel.append(
				'<option value="' + esc(pt.slug) + '">' +
				esc(pt.label) + ' (' + pt.count + ')' +
				'</option>'
			);
		});

		/* Restore sticky post type from localStorage (#14) */
		var saved = localStorage.getItem('tsi_last_post_type');
		if (saved && postTypeData[saved]) {
			$sel.val(saved).trigger('change');
		}
	});

	$('#tsi-post-type').on('change', function () {
		var slug = $(this).val();
		if (slug) {
			/* Save sticky selection (#14) */
			localStorage.setItem('tsi_last_post_type', slug);

			var pt = postTypeData[slug];
			if (pt && pt.count === 0) {
				$('#tsi-btn-export').hide();
			} else {
				$('#tsi-btn-export').show();
				/* Show row count in export button (#15) */
				if (pt && pt.count) {
					$('#tsi-btn-export').find('.tsi-action-desc').text('Export ' + pt.count + ' rows');
				}
			}
			$('#tsi-step-actions').slideDown(200);
		} else {
			$('#tsi-btn-export').show().find('.tsi-action-desc').text('Export');
			$('#tsi-step-actions').slideUp(200);
		}
		resetFrom('tsi-step-source');
	});

	/* ================================================================
	 * Step 2 — Action buttons
	 * ================================================================ */

	$('#tsi-btn-import').on('click', function () {
		resetFrom('tsi-step-source');
		resetSource();
		$('#tsi-step-source').slideDown(200);
		scrollTo('#tsi-step-source');
	});

	$('#tsi-btn-export').on('click', function () {
		resetFrom('tsi-step-source');
		/* Reset export options to defaults */
		$('input[name="tsi-export-mode"][value="all"]').prop('checked', true);
		$('#tsi-export-range-fields').hide();
		$('#tsi-export-row-fields').hide();
		$('#tsi-export-date-fields').hide();
		$('#tsi-export-date-from, #tsi-export-date-to').val('');

		/* Set ID range min/max from selected post type */
		var pt = postTypeData[$('#tsi-post-type').val()];
		var maxId = (pt && pt.max_id) ? pt.max_id : '';
		var count = (pt && pt.count) ? pt.count : '';
		$('#tsi-export-id-from').val(1).attr({ min: 1, max: maxId || '' });
		$('#tsi-export-id-to').val(maxId || '').attr({ min: 1, max: maxId || '' });

		/* Set row range min/max from post count */
		$('#tsi-export-row-from').val(1).attr({ min: 1, max: count || '' });
		$('#tsi-export-row-to').val(count || '').attr({ min: 1, max: count || '' });

		/* Populate selective columns checklist (#9) */
		var ptSlug = $('#tsi-post-type').val();
		if (ptSlug) {
			$.post(tsiImporter.ajax_url, {
				action:    'tsi_get_fields',
				nonce:     tsiImporter.nonce,
				post_type: ptSlug
			}, function (res) {
				if (!res.success) {
					return;
				}
				var html = '';
				$.each(res.data, function (key, label) {
					html += '<label class="tsi-export-field-label">' +
						'<input type="checkbox" class="tsi-export-field" value="' + esc(key) + '" checked> ' +
						esc(label) +
						'</label> ';
				});
				$('#tsi-export-fields').html(html);
			});
		}

		$('#tsi-step-export').slideDown(200);
		scrollTo('#tsi-step-export');
	});

	/* Export mode — click anywhere on the option box */
	$('.tsi-export-option-wrap').on('click', function (e) {
		if ($(e.target).is('input[type="number"], input[type="date"]')) {
			return;
		}
		$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
	});

	/* Export mode radio toggles */
	$('input[name="tsi-export-mode"]').on('change', function () {
		var mode = $(this).val();
		$('#tsi-export-row-fields').toggle(mode === 'rows');
		$('#tsi-export-range-fields').toggle(mode === 'range');
		$('#tsi-export-date-fields').toggle(mode === 'dates');
	});

	/* Clamp ID range inputs on every keystroke / change */
	$('#tsi-export-id-from, #tsi-export-id-to, #tsi-export-row-from, #tsi-export-row-to').on('input change', function () {
		var val = parseInt($(this).val(), 10);
		if (isNaN(val)) {
			return;
		}
		var maxId = parseInt($(this).attr('max'), 10) || 0;
		if (val < 1) {
			$(this).val(1);
		} else if (maxId && val > maxId) {
			$(this).val(maxId);
		}
	});

	/* Run Export */
	$('#tsi-btn-run-export').on('click', function () {
		var mode   = $('input[name="tsi-export-mode"]:checked').val();
		var format = $('#tsi-export-format').val() || 'csv';
		var params = {
			action:        'tsi_export',
			nonce:         tsiImporter.nonce,
			post_type:     $('#tsi-post-type').val(),
			export_mode:   mode,
			export_format: format
		};

		if (mode === 'rows') {
			params.row_from = $('#tsi-export-row-from').val();
			params.row_to   = $('#tsi-export-row-to').val();
			var ptR       = postTypeData[$('#tsi-post-type').val()];
			var maxRows   = (ptR && ptR.count) ? ptR.count : 0;
			var rowFrom   = params.row_from ? parseInt(params.row_from, 10) : 0;
			var rowTo     = params.row_to ? parseInt(params.row_to, 10) : 0;

			if (!rowFrom && !rowTo) {
				window.alert('Please enter at least one row number.');
				return;
			}
			if (rowFrom < 1 || rowTo < 1) {
				window.alert('Row numbers must be at least 1.');
				return;
			}
			if (maxRows && rowFrom > maxRows) {
				window.alert('"From row" cannot exceed the total rows (' + maxRows + ').');
				return;
			}
			if (maxRows && rowTo > maxRows) {
				window.alert('"To row" cannot exceed the total rows (' + maxRows + ').');
				return;
			}
			if (rowFrom && rowTo && rowFrom > rowTo) {
				window.alert('"From row" cannot be greater than "To row".');
				return;
			}
		} else if (mode === 'range') {
			params.id_from = $('#tsi-export-id-from').val();
			params.id_to   = $('#tsi-export-id-to').val();
			var pt      = postTypeData[$('#tsi-post-type').val()];
			var maxId   = (pt && pt.max_id) ? pt.max_id : 0;
			var idFrom  = params.id_from ? parseInt(params.id_from, 10) : 0;
			var idTo    = params.id_to ? parseInt(params.id_to, 10) : 0;

			if (!idFrom && !idTo) {
				window.alert('Please enter at least one ID value.');
				return;
			}
			if (idFrom < 1 || idTo < 1) {
				window.alert('ID values must be at least 1.');
				return;
			}
			if (maxId && idFrom > maxId) {
				window.alert('"From ID" cannot exceed the maximum ID (' + maxId + ').');
				return;
			}
			if (maxId && idTo > maxId) {
				window.alert('"To ID" cannot exceed the maximum ID (' + maxId + ').');
				return;
			}
			if (idFrom && idTo && idFrom > idTo) {
				window.alert('"From ID" cannot be greater than "To ID".');
				return;
			}
		} else if (mode === 'dates') {
			params.date_from = $('#tsi-export-date-from').val();
			params.date_to   = $('#tsi-export-date-to').val();
			if (!params.date_from && !params.date_to) {
				window.alert('Please enter at least one date.');
				return;
			}
		}

		showOverlay('Exporting\u2026');

		/* Collect advanced export options (#5, #9) */
		var statuses = [];
		$('.tsi-export-status:checked').each(function () {
			statuses.push($(this).val());
		});
		if (statuses.length) {
			params['export_statuses[]'] = statuses;
		}
		var fields = [];
		$('.tsi-export-field:checked').each(function () {
			fields.push($(this).val());
		});
		if (fields.length) {
			params['export_fields[]'] = fields;
		}

		$.post(tsiImporter.ajax_url, params, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Export failed.');
				return;
			}
			downloadBase64(res.data.csv, res.data.filename, res.data.mime || 'text/csv');
		}).fail(function () {
			hideOverlay();
			window.alert('Export request failed.');
		});
	});

	$('#tsi-btn-template').on('click', function () {
		resetFrom('tsi-step-source');
		showOverlay('Generating template\u2026');
		$.post(tsiImporter.ajax_url, {
			action:    'tsi_template',
			nonce:     tsiImporter.nonce,
			post_type: $('#tsi-post-type').val()
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Template generation failed.');
				return;
			}
			downloadBase64(res.data.csv, res.data.filename);
		}).fail(function () {
			hideOverlay();
			window.alert('Template request failed.');
		});
	});

	/* History button (#2) */
	$('#tsi-btn-history').on('click', function () {
		resetFrom('tsi-step-source');
		loadHistory();
		$('#tsi-step-history').slideDown(200);
		scrollTo('#tsi-step-history');
	});

	/* Schedule button (#1) */
	$('#tsi-btn-schedule').on('click', function () {
		resetFrom('tsi-step-source');
		loadSchedules();
		loadExportSchedules();
		$('#tsi-step-schedule').slideDown(200);
		$('#tsi-step-export-schedule').slideDown(200);
		scrollTo('#tsi-step-schedule');
	});

	/* ================================================================
	 * Step 3 — Source tabs
	 * ================================================================ */

	$('.tsi-source-tab').on('click', function () {
		$('.tsi-source-tab').removeClass('tsi-source-tab--active');
		$(this).addClass('tsi-source-tab--active');
		var tab = $(this).data('tab');
		$('.tsi-source-panel').hide();
		$('#tsi-source-' + tab).show();
	});

	/* ---- Drag & Drop ---- */

	var $dropzone = $('#tsi-dropzone');

	$dropzone.on('dragover dragenter', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).addClass('tsi-drag-over');
	}).on('dragleave drop', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).removeClass('tsi-drag-over');
	}).on('drop', function (e) {
		var dt    = e.originalEvent.dataTransfer;
		var files = dt ? dt.files : null;
		if (!files || !files.length) {
			return;
		}
		/* Multi-file queue (#8) */
		if (files.length > 1) {
			fileQueue = [];
			var i;
			for (i = 0; i < files.length; i++) {
				if (files[i].name.toLowerCase().match(/\.(csv|json|xml)$/)) {
					fileQueue.push(files[i]);
				}
			}
			if (fileQueue.length > 1) {
				$('#tsi-file-queue').html(
					'<strong>' + fileQueue.length + ' files queued.</strong> ' +
					'Processing first file. Remaining files will be processed after each import completes.'
				).show();
				uploadFile(fileQueue.shift());
				return;
			}
		}
		uploadFile(files[0]);
	}).on('click', function (e) {
		if ($(e.target).closest('#tsi-browse-btn').length) {
			return;
		}
		$('#tsi-csv-file')[0].click();
	});

	$('#tsi-browse-btn').on('click', function (e) {
		e.stopPropagation();
		$('#tsi-csv-file')[0].click();
	});

	$('#tsi-csv-file').on('change', function () {
		if (this.files && this.files[0]) {
			uploadFile(this.files[0]);
		}
	});

	/**
	 * Upload a CSV file via AJAX and parse it.
	 */
	function uploadFile(file) {
		if (!file.name.toLowerCase().match(/\.(csv|json|xml)$/)) {
			window.alert('Please select a .csv, .json, or .xml file.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'tsi_parse_csv');
		formData.append('nonce', tsiImporter.nonce);
		formData.append('csv_file', file);

		showOverlay('Parsing file\u2026');

		$.ajax({
			url:         tsiImporter.ajax_url,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function (res) {
				hideOverlay();
				if (!res.success) {
					window.alert(res.data || 'Error parsing file.');
					return;
				}
				onCsvParsed(res.data, file.name);
			},
			error: function () {
				hideOverlay();
				window.alert('Upload request failed.');
			}
		});
	}

	/* ---- Fetch URL ---- */

	$('#tsi-btn-fetch-url').on('click', function () {
		var url = $('#tsi-csv-url').val().trim();
		if (!url) {
			window.alert('Please enter a URL.');
			return;
		}

		showOverlay('Fetching file\u2026');
		$.post(tsiImporter.ajax_url, {
			action:  'tsi_parse_csv_url',
			nonce:   tsiImporter.nonce,
			csv_url: url
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Error fetching file.');
				return;
			}
			onCsvParsed(res.data, url.split('/').pop() || 'remote-data');
		}).fail(function () {
			hideOverlay();
			window.alert('Fetch request failed.');
		});
	});

	/* ---- After CSV parsed ---- */

	function onCsvParsed(data, filename) {
		csvHeaders  = data.headers;
		csvToken    = data.token;
		csvRowCount = data.row_count;
		csvDelimiter = data.delimiter || ',';
		firstPreviewRow = (data.preview && data.preview.length) ? data.preview[0] : [];

		/* File info badge — include delimiter (#11) */
		var delimLabel = csvDelimiter === '\t' ? 'tab' : csvDelimiter;
		$('#tsi-file-info').html(
			'<span class="dashicons dashicons-yes-alt"></span> ' +
			'<strong>' + esc(filename) + '</strong> &mdash; ' +
			data.row_count + ' data rows, ' + csvHeaders.length + ' columns' +
			' <span class="tsi-delimiter-badge">delimiter: <code>' + esc(delimLabel) + '</code></span>'
		).show();

		/* Data preview table */
		var preview = data.preview || [];
		if (preview.length) {
			var html = '<h3>Data Preview <span class="tsi-preview-meta">(first ' + preview.length + ' of ' + data.row_count + ' rows)</span></h3>';
			html += '<div class="tsi-preview-scroll"><table class="widefat tsi-preview-table"><thead><tr>';
			var h;
			for (h = 0; h < csvHeaders.length; h++) {
				html += '<th>' + esc(csvHeaders[h]) + '</th>';
			}
			html += '</tr></thead><tbody>';
			var r, c, val;
			for (r = 0; r < preview.length; r++) {
				html += '<tr>';
				for (c = 0; c < csvHeaders.length; c++) {
					val = (preview[r] && preview[r][c]) ? String(preview[r][c]) : '';
					if (val.length > 60) {
						val = val.substring(0, 60) + '\u2026';
					}
					html += '<td>' + esc(val) + '</td>';
				}
				html += '</tr>';
			}
			html += '</tbody></table></div>';
			$('#tsi-preview').html(html).show();
		}

		/* Load fields and show mapping */
		showOverlay('Loading fields\u2026');
		$.post(tsiImporter.ajax_url, {
			action:    'tsi_get_fields',
			nonce:     tsiImporter.nonce,
			post_type: $('#tsi-post-type').val()
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				return;
			}
			extraFieldCount = 0;
			buildMappingTable(res.data);
			/* Show filter section if CSV headers are available */
			if (csvHeaders.length) {
				$('#tsi-filter-section').show();
				$('#tsi-filter-rules').empty();
			}
			$('#tsi-step-mapping').slideDown(200);
			scrollTo('#tsi-step-mapping');
			updateMappingPreview();
		});
	}

	/* ================================================================
	 * Step 4 — Mapping
	 * ================================================================ */

	function buildMappingTable(fields) {
		var $tbody  = $('#tsi-mapping-table tbody').empty();
		var csvNorm = csvHeaders.map(function (h) {
			return h.toLowerCase().replace(/[\s_\-]+/g, '');
		});

		$.each(fields, function (fieldKey, fieldLabel) {
			$tbody.append(buildMappingRow(fieldKey, fieldLabel, csvNorm, false));
		});

		bindMappingEvents();
		updateMappingCount();
	}

	function buildMappingRow(fieldKey, fieldLabel, csvNorm, isExtra) {
		var fieldNorm    = fieldKey.toLowerCase().replace(/[\s_\-]+/g, '');
		var labelNorm    = fieldLabel.toLowerCase().replace(/[\s_\-]+/g, '');
		var strippedNorm = fieldKey.replace(/^(meta__|tax__)/, '').toLowerCase().replace(/[\s_\-]+/g, '');

		/* Auto-match */
		var matchIdx = -1;
		$.each(csvNorm, function (idx, cn) {
			if (matchIdx === -1 && (cn === fieldNorm || cn === labelNorm || cn === strippedNorm)) {
				matchIdx = idx;
			}
		});

		/* Build <select> */
		var opts = '<option value="-1">\u2014 skip \u2014</option>';
		$.each(csvHeaders, function (idx, h) {
			var sel = (idx === matchIdx) ? ' selected' : '';
			opts += '<option value="' + idx + '"' + sel + '>' + esc(h) + '</option>';
		});
		opts += '<option value="__custom__">\u270E Custom value\u2026</option>';

		var checked = matchIdx !== -1 ? ' checked' : '';

		/* Field cell */
		var fieldHtml;
		if (isExtra) {
			fieldHtml = '<td><input type="text" class="tsi-extra-key regular-text" placeholder="meta_key_name" value="' + esc(fieldKey) + '"></td>';
		} else {
			fieldHtml = '<td><strong>' + esc(fieldLabel) + '</strong><br><code>' + esc(fieldKey) + '</code></td>';
		}

		var removeBtn = isExtra ? ' <button type="button" class="button button-small tsi-remove-extra" title="Remove">&times;</button>' : '';
		var resetBtn  = !isExtra ? ' <button type="button" class="button button-small tsi-reset-field" title="Reset to original mapping"><span class="dashicons dashicons-image-rotate"></span></button>' : '';

		/* Transform dropdown (#4) */
		var transformOpts = '<select class="tsi-transform-select">' +
			'<option value="">— none —</option>' +
			'<option value="uppercase">UPPERCASE</option>' +
			'<option value="lowercase">lowercase</option>' +
			'<option value="titlecase">Title Case</option>' +
			'<option value="trim">Trim whitespace</option>' +
			'<option value="strip_tags">Strip HTML tags</option>' +
			'<option value="slug">Slug (sanitize_title)</option>' +
			'<option value="date_ymd">Date → YYYY-MM-DD</option>' +
			'<option value="date_dmy">Date → DD/MM/YYYY</option>' +
			'</select>';

		return $(
			'<tr data-field="' + esc(fieldKey) + '" data-original-match="' + matchIdx + '"' + (isExtra ? ' class="tsi-extra-row"' : '') + '>' +
			'<td class="tsi-col-check"><input type="checkbox" class="tsi-field-check"' + checked + '></td>' +
			fieldHtml +
			'<td>' +
				'<div class="tsi-col-map-inner">' +
				'<select class="tsi-col-select">' + opts + '</select>' +
				resetBtn +
				removeBtn +
				'</div>' +
				'<input type="text" class="tsi-custom-value regular-text" placeholder="Enter static value\u2026">' +
			'</td>' +
			'<td>' + transformOpts + '</td>' +
			'</tr>'
		);
	}

	function bindMappingEvents() {
		var $tbody = $('#tsi-mapping-table tbody');
		$tbody.off('change', '.tsi-col-select').off('change', '.tsi-field-check').off('click', '.tsi-remove-extra').off('click', '.tsi-reset-field');

		$tbody.on('change', '.tsi-col-select', function () {
			var $row = $(this).closest('tr');
			var $cb  = $row.find('.tsi-field-check');
			var val  = $(this).val();

			if (val === '__custom__') {
				$row.find('.tsi-custom-value').show().trigger('focus');
				$cb.prop('checked', true);
			} else {
				$row.find('.tsi-custom-value').hide();
				$cb.prop('checked', val !== '-1');
			}
			updateMappingCount();
		});

		$tbody.on('change', '.tsi-field-check', function () {
			if (!$(this).is(':checked')) {
				var $row = $(this).closest('tr');
				$row.find('.tsi-col-select').val('-1');
				$row.find('.tsi-custom-value').hide();
			}
			updateMappingCount();
		});

		$tbody.on('click', '.tsi-remove-extra', function () {
			$(this).closest('tr').remove();
			updateMappingCount();
		});

		$tbody.on('click', '.tsi-reset-field', function () {
			var $row     = $(this).closest('tr');
			var origIdx  = $row.data('original-match');
			var $sel     = $row.find('.tsi-col-select');
			var $cb      = $row.find('.tsi-field-check');

			$sel.val(String(origIdx));
			$row.find('.tsi-custom-value').hide();

			if (parseInt(origIdx, 10) !== -1) {
				$cb.prop('checked', true);
			} else {
				$cb.prop('checked', false);
			}
			updateMappingCount();
		});
	}

	function updateMappingCount() {
		var total   = $('#tsi-mapping-table tbody tr').length;
		var checked = $('#tsi-mapping-table tbody .tsi-field-check:checked').length;
		$('#tsi-mapping-count').text(checked + ' / ' + total + ' fields mapped');

		/* Show/hide Update button based on ID field being mapped */
		var idMapped = false;
		$('#tsi-mapping-table tbody tr[data-field="ID"]').each(function () {
			var $cb  = $(this).find('.tsi-field-check');
			var $sel = $(this).find('.tsi-col-select');
			if ($cb.is(':checked') && $sel.val() !== '-1') {
				idMapped = true;
			}
		});
		$('#tsi-btn-update').toggle(idMapped);
		$('#tsi-btn-insert-update').toggle(idMapped);
	}

	/* Select / Deselect All */
	$('#tsi-select-all').on('click', function () {
		$('#tsi-mapping-table tbody tr').each(function () {
			$(this).find('.tsi-field-check').prop('checked', true);
		});
		updateMappingCount();
	});

	$('#tsi-deselect-all').on('click', function () {
		$('#tsi-mapping-table tbody tr').each(function () {
			$(this).find('.tsi-field-check').prop('checked', false);
			$(this).find('.tsi-col-select').val('-1');
			$(this).find('.tsi-custom-value').hide();
		});
		updateMappingCount();
	});

	/* Reset All Mappings (non-custom-field rows only) */
	$('#tsi-reset-all').on('click', function () {
		$('#tsi-mapping-table tbody tr').not('.tsi-extra-row').each(function () {
			var origIdx = $(this).data('original-match');
			var $sel    = $(this).find('.tsi-col-select');
			var $cb     = $(this).find('.tsi-field-check');

			$sel.val(String(origIdx));
			$(this).find('.tsi-custom-value').hide();

			if (parseInt(origIdx, 10) !== -1) {
				$cb.prop('checked', true);
			} else {
				$cb.prop('checked', false);
			}
		});
		updateMappingCount();
	});

	/* Add extra custom field */
	$('#tsi-add-extra').on('click', function () {
		extraFieldCount++;
		var key     = 'custom_field_' + extraFieldCount;
		var csvNorm = csvHeaders.map(function (h) {
			return h.toLowerCase().replace(/[\s_\-]+/g, '');
		});
		var $row = buildMappingRow(key, 'Custom Field', csvNorm, true);
		$('#tsi-mapping-table tbody').append($row);
		$row.find('.tsi-extra-key').trigger('focus');
		updateMappingCount();
	});

	/* ================================================================
	 * Step 5 — Run import (batch)
	 * ================================================================ */

	$('#tsi-btn-insert, #tsi-btn-update, #tsi-btn-insert-update').on('click', function () {
		var btnId      = $(this).attr('id');
		var importMode = btnId === 'tsi-btn-update' ? 'update' : (btnId === 'tsi-btn-insert-update' ? 'insert-update' : 'insert');
		var mapping    = buildMappingPayload();
		var transforms = buildTransformPayload();
		var isDryRun   = $('#tsi-dry-run').is(':checked');

		/* Duplicate detection (#3) */
		var dupField = '';
		var dupMeta  = '';
		if ($('#tsi-dup-check').is(':checked')) {
			dupField = $('#tsi-dup-field').val();
			dupMeta  = $('#tsi-dup-meta-key').val();
		}

		/* For insert mode, strip the ID mapping so all rows create new posts */
		if (importMode === 'insert' && mapping.hasOwnProperty('ID')) {
			delete mapping.ID;
		}

		var hasMapping = false;
		var k;
		for (k in mapping) {
			if (mapping.hasOwnProperty(k)) {
				hasMapping = true;
				break;
			}
		}

		if (!hasMapping) {
			window.alert('Please map at least one field before importing.');
			return;
		}

		var confirmMsg;
		if (isDryRun) {
			confirmMsg = 'Run a dry run (no changes will be made)?';
		} else if (importMode === 'update') {
			confirmMsg = 'This will update existing posts based on the ID column. Continue?';
		} else if (importMode === 'insert-update') {
			confirmMsg = 'This will insert rows with no ID or non-existing IDs, and update rows with existing IDs. Continue?';
		} else {
			confirmMsg = 'This will insert new posts. Continue?';
		}
		if (!window.confirm(confirmMsg)) {
			return;
		}

		/* Transition to progress view */
		$('#tsi-step-mapping').slideUp(200);
		$('#tsi-step-progress').slideDown(200);
		scrollTo('#tsi-step-progress');

		if (isDryRun) {
			$('#tsi-progress-title').text('Dry Run\u2026');
		}

		var totals = { inserted: 0, updated: 0, skipped: 0, errors: 0 };
		var allLogs = [];
		var filters = buildFilterPayload();
		lastHistoryId    = '';
		lastAllLogs      = [];
		failedRowIndices = [];
		lastMapping      = mapping;
		lastImportMode   = importMode;
		lastDryRun       = isDryRun;
		lastDupField     = dupField;
		lastDupMeta      = dupMeta;
		lastTransforms   = transforms;
		lastFilters      = filters;
		var batchStartTime = Date.now();

		processBatch(0, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, batchStartTime);
	});

	/**
	 * Build the mapping payload from the table.
	 */
	function buildMappingPayload() {
		var mapping = {};

		$('#tsi-mapping-table tbody tr').each(function () {
			var $cb  = $(this).find('.tsi-field-check');
			var $sel = $(this).find('.tsi-col-select');
			if (!$cb.is(':checked')) {
				return;
			}

			var selVal = $sel.val();
			if (selVal === '-1') {
				return;
			}

			var fieldKey;
			if ($(this).hasClass('tsi-extra-row')) {
				var raw = $(this).find('.tsi-extra-key').val().trim();
				if (!raw) {
					return;
				}
				fieldKey = 'meta__' + raw;
			} else {
				fieldKey = $(this).data('field');
			}

			if (selVal === '__custom__') {
				mapping[fieldKey] = {
					source: 'custom',
					value:  $(this).find('.tsi-custom-value').val()
				};
			} else {
				mapping[fieldKey] = {
					source: 'csv',
					col:    parseInt(selVal, 10)
				};
			}
		});

		return mapping;
	}

	/**
	 * Build the transforms payload from the table (#4).
	 */
	function buildTransformPayload() {
		var transforms = {};
		$('#tsi-mapping-table tbody tr').each(function () {
			var $cb  = $(this).find('.tsi-field-check');
			if (!$cb.is(':checked')) {
				return;
			}
			var field = $(this).data('field');
			if ($(this).hasClass('tsi-extra-row')) {
				var raw = $(this).find('.tsi-extra-key').val().trim();
				if (raw) {
					field = 'meta__' + raw;
				}
			}
			var transform = $(this).find('.tsi-transform-select').val();
			if (transform) {
				transforms[field] = transform;
			}
		});
		return transforms;
	}

	/**
	 * Update the mapping preview panel using the first CSV row.
	 */
	function updateMappingPreview() {
		if (!firstPreviewRow.length) {
			$('#tsi-mapping-preview').hide();
			return;
		}

		var items = [];
		$('#tsi-mapping-table tbody tr').each(function () {
			var $cb = $(this).find('.tsi-field-check');
			if (!$cb.is(':checked')) {
				return;
			}
			var label = $(this).find('.tsi-field-label').text() || $(this).find('.tsi-extra-key').val() || '?';
			var $sel  = $(this).find('.tsi-col-select');
			var val   = '';
			if ($sel.val() === '__custom__') {
				val = $(this).find('.tsi-custom-value').val() || '';
			} else {
				var colIdx = parseInt($sel.val(), 10);
				if (!isNaN(colIdx) && colIdx >= 0 && colIdx < firstPreviewRow.length) {
					val = firstPreviewRow[colIdx];
				}
			}
			items.push({ label: label, value: val });
		});

		if (!items.length) {
			$('#tsi-mapping-preview').hide();
			return;
		}

		var html = '<table class="widefat tsi-preview-mini"><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
		$.each(items, function (i, it) {
			html += '<tr><td>' + esc(it.label) + '</td><td><code>' + esc(it.value || '—') + '</code></td></tr>';
		});
		html += '</tbody></table>';
		$('#tsi-mapping-preview-content').html(html);
		$('#tsi-mapping-preview').show();
	}

	/* Update preview on any mapping change */
	$(document).on('change', '.tsi-col-select, .tsi-field-check, .tsi-custom-value', updateMappingPreview);
	$(document).on('input', '.tsi-custom-value', updateMappingPreview);

	/**
	 * Process one batch, then recurse until done.
	 */
	function processBatch(offset, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, startTime, retryRows) {
		var postData = {
			action:       'tsi_import_batch',
			nonce:        tsiImporter.nonce,
			token:        csvToken,
			post_type:    $('#tsi-post-type').val(),
			mapping:      JSON.stringify(mapping),
			offset:       offset,
			batch_size:   tsiImporter.batch_size,
			import_mode:  importMode,
			dry_run:      isDryRun ? '1' : '',
			dup_field:    dupField,
			dup_meta_key: dupMeta,
			transforms:   JSON.stringify(transforms),
			filters:      JSON.stringify(filters || []),
			history_id:   lastHistoryId
		};
		if (retryRows && retryRows.length) {
			postData.retry_rows = JSON.stringify(retryRows);
		}
		$.post(tsiImporter.ajax_url, postData, function (res) {
			if (!res.success) {
				$('#tsi-progress-detail').text(res.data || 'Import failed.');
				return;
			}

			var d       = res.data;
			var percent = Math.min(100, Math.round((d.offset / d.total) * 100));

			/* Update progress bar */
			$('#tsi-progress-fill').css('width', percent + '%');
			$('#tsi-progress-pct').text(percent + '%');
			$('#tsi-progress-bar').attr('aria-valuenow', percent);

			/* Calculate ETA */
			var elapsed = (Date.now() - startTime) / 1000;
			var etaText = '';
			if (d.offset > 0 && !d.done) {
				var remaining = Math.round(elapsed * (d.total - d.offset) / d.offset);
				if (remaining >= 60) {
					etaText = ' \u2014 ~' + Math.ceil(remaining / 60) + 'm remaining';
				} else {
					etaText = ' \u2014 ~' + remaining + 's remaining';
				}
			}

			$('#tsi-progress-detail').text(
				'Processed ' + d.offset + ' of ' + d.total + ' rows' + etaText
			);

			/* Accumulate */
			totals.inserted += d.inserted;
			totals.updated  += d.updated;
			totals.skipped  += d.skipped;
			totals.errors   += d.errors;
			if (d.failed_rows && d.failed_rows.length) {
				failedRowIndices = failedRowIndices.concat(d.failed_rows);
			}
			allLogs = allLogs.concat(d.log);

			/* Capture history ID for rollback */
			if (d.history_id) {
				lastHistoryId = d.history_id;
			}

			/* Append to live log */
			var $liveLog = $('#tsi-live-log');
			$.each(d.log, function (idx, line) {
				var cls = 'tsi-log-ok';
				if (line.indexOf('Error') !== -1) {
					cls = 'tsi-log-error';
				} else if (line.indexOf('Skipped') !== -1) {
					cls = 'tsi-log-skip';
				} else if (line.indexOf('DRY RUN') !== -1) {
					cls = 'tsi-log-dry';
				}
				$liveLog.append('<div class="' + cls + '">' + esc(line) + '</div>');
			});
			$liveLog.scrollTop($liveLog[0].scrollHeight);

			if (!d.done) {
				processBatch(d.offset, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, startTime, retryRows);
			} else {
				lastAllLogs = allLogs;
				showResults(totals, allLogs, isDryRun);
			}

		}).fail(function () {
			$('#tsi-progress-detail').text('Network error. Import halted.');
		});
	}

	/* ================================================================
	 * Step 6 — Results
	 * ================================================================ */

	function showResults(totals, allLogs, isDryRun) {
		/* Complete progress */
		$('#tsi-progress-fill').css('width', '100%');
		$('#tsi-progress-pct').text('100%');
		$('#tsi-progress-title').text(isDryRun ? 'Dry Run Complete' : 'Complete');
		$('#tsi-progress-detail').text(isDryRun ? 'No changes were made.' : 'Import finished successfully.');

		/* Summary badges */
		var badgeHtml =
			'<span class="tsi-badge tsi-badge-inserted"><span class="dashicons dashicons-plus-alt"></span> ' + totals.inserted + ' Inserted</span>' +
			'<span class="tsi-badge tsi-badge-updated"><span class="dashicons dashicons-update"></span> ' + totals.updated + ' Updated</span>' +
			'<span class="tsi-badge tsi-badge-skipped"><span class="dashicons dashicons-minus"></span> ' + totals.skipped + ' Skipped</span>' +
			'<span class="tsi-badge tsi-badge-errors"><span class="dashicons dashicons-warning"></span> ' + totals.errors + ' Errors</span>';
		if (isDryRun) {
			badgeHtml = '<span class="tsi-badge tsi-badge-dry">[DRY RUN]</span> ' + badgeHtml;
		}
		$('#tsi-results-summary').html(badgeHtml);

		/* Full log */
		if (allLogs.length) {
			var html = '<div class="tsi-log">';
			$.each(allLogs, function (idx, line) {
				var cls = 'tsi-log-ok';
				if (line.indexOf('Error') !== -1) {
					cls = 'tsi-log-error';
				} else if (line.indexOf('Skipped') !== -1) {
					cls = 'tsi-log-skip';
				}
				html += '<div class="' + cls + '">' + esc(line) + '</div>';
			});
			html += '</div>';
			$('#tsi-results-log').html(html);
		}

		/* Show download log button (#12) */
		$('#tsi-btn-download-log').toggle(allLogs.length > 0);

		/* Show rollback button only for non-dry-run imports with a history ID */
		$('#tsi-btn-rollback').toggle(!isDryRun && !!lastHistoryId);

		/* Show retry button when there are failed rows */
		$('#tsi-btn-retry-failed').toggle(!isDryRun && failedRowIndices.length > 0);

		$('#tsi-step-results').slideDown(200);
		scrollTo('#tsi-step-results');
	}

	/* Download log as CSV (#12) */
	$('#tsi-btn-download-log').on('click', function () {
		if (!lastAllLogs.length) {
			return;
		}
		var csv = 'Row,Status,Message\n';
		$.each(lastAllLogs, function (idx, line) {
			csv += '"' + (idx + 1) + '","' + line.replace(/"/g, '""') + '"\n';
		});
		var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		link.href     = URL.createObjectURL(blob);
		link.download = 'import-log-' + new Date().toISOString().slice(0, 10) + '.csv';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	});

	/* Rollback import */
	$('#tsi-btn-rollback').on('click', function () {
		if (!lastHistoryId) {
			return;
		}
		if (!window.confirm('This will move all imported posts to trash. Continue?')) {
			return;
		}
		showOverlay('Rolling back\u2026');
		$.post(tsiImporter.ajax_url, {
			action:     'tsi_rollback',
			nonce:      tsiImporter.nonce,
			history_id: lastHistoryId
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Rollback failed.');
				return;
			}
			window.alert(res.data.message);
			$('#tsi-btn-rollback').hide();
		}).fail(function () {
			hideOverlay();
			window.alert('Rollback request failed.');
		});
	});

	/* Retry Failed Rows */
	$('#tsi-btn-retry-failed').on('click', function () {
		if (!failedRowIndices.length) {
			return;
		}

		/* Hide results and show progress */
		$('#tsi-step-results').hide();
		$('#tsi-step-progress').slideDown(200);
		$('#tsi-progress-title').text('Retrying failed rows\u2026');
		$('#tsi-progress-fill').css('width', '0%');
		$('#tsi-progress-pct').text('0%');
		$('#tsi-progress-detail').text('');
		$('#tsi-progress-bar').attr('aria-valuenow', 0);
		$('#tsi-live-log').html('');

		var totals      = { inserted: 0, updated: 0, skipped: 0, errors: 0 };
		var allLogs     = [];
		var retryIndices = failedRowIndices.slice();
		failedRowIndices = [];
		lastAllLogs      = [];
		var startTime    = Date.now();

		processBatch(0, lastMapping, totals, allLogs, lastImportMode, lastDryRun, lastDupField, lastDupMeta, lastTransforms, lastFilters, startTime, retryIndices);
	});

	/* Start New Import */
	$('#tsi-btn-new').on('click', function () {
		resetAll();
		scrollTo('#tsi-step-entity');
	});

	/* ================================================================
	 * Validate CSV (#7)
	 * ================================================================ */

	$('#tsi-btn-validate').on('click', function () {
		var mapping = buildMappingPayload();
		var hasMapping = false;
		var k;
		for (k in mapping) {
			if (mapping.hasOwnProperty(k)) {
				hasMapping = true;
				break;
			}
		}
		if (!hasMapping) {
			window.alert('Please map at least one field before validating.');
			return;
		}

		showOverlay('Validating\u2026');
		$.post(tsiImporter.ajax_url, {
			action:    'tsi_validate_csv',
			nonce:     tsiImporter.nonce,
			token:     csvToken,
			post_type: $('#tsi-post-type').val(),
			mapping:   JSON.stringify(mapping)
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Validation failed.');
				return;
			}
			var d = res.data;
			var html = '<h3>' + esc(d.message) + '</h3>';
			if (d.errors.length) {
				html += '<div class="tsi-validation-errors"><h4>Errors (' + d.errors.length + ')</h4>';
				$.each(d.errors, function (i, e) {
					html += '<div class="tsi-log-error">' + esc(e) + '</div>';
				});
				html += '</div>';
			}
			if (d.warnings.length) {
				html += '<div class="tsi-validation-warnings"><h4>Warnings (' + d.warnings.length + ')</h4>';
				$.each(d.warnings, function (i, w) {
					html += '<div class="tsi-log-skip">' + esc(w) + '</div>';
				});
				html += '</div>';
			}
			if (!d.errors.length && !d.warnings.length) {
				html += '<p class="tsi-validation-ok"><span class="dashicons dashicons-yes-alt"></span> No issues found.</p>';
			}
			$('#tsi-validation-results').html(html);
			$('#tsi-step-validation').slideDown(200);
			scrollTo('#tsi-step-validation');
		}).fail(function () {
			hideOverlay();
			window.alert('Validation request failed.');
		});
	});

	/* Proceed with Import after validation */
	$('#tsi-btn-proceed-import').on('click', function () {
		$('#tsi-step-validation').slideUp(200);
		$('#tsi-step-mapping').slideDown(200);
		scrollTo('#tsi-step-mapping');
	});

	/* Cancel from validation — hide validation and stay on mapping */
	$('#tsi-btn-cancel-import').on('click', function () {
		$('#tsi-step-validation').slideUp(200);
		scrollTo('#tsi-step-mapping');
	});

	/* ================================================================
	 * Duplicate check toggle (#3)
	 * ================================================================ */

	$('#tsi-dup-check').on('change', function () {
		$('#tsi-dup-options').toggle($(this).is(':checked'));
	});

	$('#tsi-dup-field').on('change', function () {
		$('#tsi-dup-meta-wrap').toggle($(this).val() === 'meta_key');
	});

	/* ================================================================
	 * Conditional Row Filters
	 * ================================================================ */

	$('#tsi-add-filter').on('click', function () {
		var $rules = $('#tsi-filter-rules');
		var idx    = $rules.children().length;
		var html   = '<div class="tsi-filter-rule" data-idx="' + idx + '">';
		html      += '<select class="tsi-filter-col">';
		$.each(csvHeaders, function (i, h) {
			html += '<option value="' + i + '">' + esc(h) + '</option>';
		});
		html += '</select>';
		html += '<select class="tsi-filter-op">';
		html += '<option value="equals">equals</option>';
		html += '<option value="not_equals">not equals</option>';
		html += '<option value="contains">contains</option>';
		html += '<option value="not_contains">not contains</option>';
		html += '<option value="gt">greater than</option>';
		html += '<option value="lt">less than</option>';
		html += '<option value="empty">is empty</option>';
		html += '<option value="not_empty">is not empty</option>';
		html += '</select>';
		html += '<input type="text" class="tsi-filter-value small-text" placeholder="value">';
		html += '<button type="button" class="button button-small tsi-remove-filter" aria-label="Remove filter rule"><span class="dashicons dashicons-no-alt"></span></button>';
		html += '</div>';
		$rules.append(html);
	});

	$(document).on('click', '.tsi-remove-filter', function () {
		$(this).closest('.tsi-filter-rule').remove();
	});

	$(document).on('change', '.tsi-filter-op', function () {
		var op = $(this).val();
		$(this).closest('.tsi-filter-rule').find('.tsi-filter-value').toggle(op !== 'empty' && op !== 'not_empty');
	});

	/**
	 * Build the filter rules payload.
	 */
	function buildFilterPayload() {
		var filters = [];
		$('#tsi-filter-rules .tsi-filter-rule').each(function () {
			filters.push({
				col:   parseInt($(this).find('.tsi-filter-col').val(), 10),
				op:    $(this).find('.tsi-filter-op').val(),
				value: $(this).find('.tsi-filter-value').val() || ''
			});
		});
		return filters;
	}

	/* ================================================================
	 * Mapping Profiles (#6)
	 * ================================================================ */

	$('#tsi-btn-save-profile').on('click', function () {
		var name = window.prompt('Enter a name for this mapping profile:');
		if (!name) {
			return;
		}
		var mapping = buildMappingPayload();
		showOverlay('Saving profile\u2026');
		$.post(tsiImporter.ajax_url, {
			action:       'tsi_save_profile',
			nonce:        tsiImporter.nonce,
			profile_name: name,
			post_type:    $('#tsi-post-type').val(),
			mapping:      JSON.stringify(mapping)
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Save failed.');
				return;
			}
			updateProfileDropdown(res.data.profiles);
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Save profile request failed.');
		});
	});

	$('#tsi-btn-delete-profile').on('click', function () {
		var id = $('#tsi-profile-select').val();
		if (!id) {
			window.alert('Please select a profile to delete.');
			return;
		}
		if (!window.confirm('Delete this mapping profile?')) {
			return;
		}
		$.post(tsiImporter.ajax_url, {
			action:     'tsi_delete_profile',
			nonce:      tsiImporter.nonce,
			profile_id: id
		}, function (res) {
			if (res.success) {
				updateProfileDropdown(res.data.profiles);
			}
		});
	});

	$('#tsi-profile-select').on('change', function () {
		var id = $(this).val();
		if (!id) {
			return;
		}
		var profiles = tsiImporter.profiles || {};
		if (profiles[id] && profiles[id].mapping) {
			applyProfileMapping(profiles[id].mapping);
		}
	});

	function updateProfileDropdown(profiles) {
		tsiImporter.profiles = profiles;
		var $sel = $('#tsi-profile-select').empty().append('<option value="">— select profile —</option>');
		var pt   = $('#tsi-post-type').val();
		$.each(profiles, function (id, p) {
			if (!pt || p.post_type === pt) {
				$sel.append('<option value="' + esc(id) + '">' + esc(p.name) + '</option>');
			}
		});
	}

	function applyProfileMapping(profileMapping) {
		$('#tsi-mapping-table tbody tr').each(function () {
			var field = $(this).data('field');
			var pm    = profileMapping[field];
			var $cb   = $(this).find('.tsi-field-check');
			var $sel  = $(this).find('.tsi-col-select');

			if (pm) {
				if (pm.source === 'custom') {
					$sel.val('__custom__');
					$(this).find('.tsi-custom-value').val(pm.value || '').show();
				} else {
					$sel.val(String(pm.col));
					$(this).find('.tsi-custom-value').hide();
				}
				$cb.prop('checked', true);
			} else {
				$sel.val('-1');
				$cb.prop('checked', false);
				$(this).find('.tsi-custom-value').hide();
			}
		});
		updateMappingCount();
	}

	/* ================================================================
	 * Import History (#2)
	 * ================================================================ */

	function loadHistory() {
		$('#tsi-history-body').html('<tr><td colspan="6">Loading\u2026</td></tr>');
		$.post(tsiImporter.ajax_url, {
			action: 'tsi_get_history',
			nonce:  tsiImporter.nonce
		}, function (res) {
			if (!res.success) {
				return;
			}
			var history = res.data.history;
			var html    = '';
			if (!history || !history.length) {
				html = '<tr><td colspan="6">No imports recorded yet.</td></tr>';
			} else {
				$.each(history, function (i, h) {
					var statusClass = h.rolled_back ? 'tsi-history-rolled-back' : '';
					html += '<tr class="' + statusClass + '">' +
						'<td>' + esc(h.date) + '</td>' +
						'<td>' + esc(h.post_type) + '</td>' +
						'<td>' + esc(h.mode) + '</td>' +
						'<td>' + h.inserted + ' / ' + h.updated + ' / ' + (h.skipped || 0) + ' / ' + (h.errors || 0) + '</td>' +
						'<td>' + (h.post_ids ? h.post_ids.length : 0) + ' posts</td>' +
						'<td>' + (h.rolled_back ? '<em>Rolled back</em>' : '<button type="button" class="button button-small tsi-rollback-history" data-id="' + esc(h.id) + '">Rollback</button>') + '</td>' +
						'</tr>';
				});
			}
			$('#tsi-history-body').html(html);
		});
	}

	$(document).on('click', '.tsi-rollback-history', function () {
		var hid = $(this).data('id');
		if (!window.confirm('Move all posts from this import to trash?')) {
			return;
		}
		var $btn = $(this);
		showOverlay('Rolling back\u2026');
		$.post(tsiImporter.ajax_url, {
			action:     'tsi_rollback',
			nonce:      tsiImporter.nonce,
			history_id: hid
		}, function (res) {
			hideOverlay();
			if (res.success) {
				$btn.replaceWith('<em>Rolled back</em>');
				window.alert(res.data.message);
			} else {
				window.alert(res.data || 'Rollback failed.');
			}
		}).fail(function () {
			hideOverlay();
			window.alert('Rollback request failed.');
		});
	});

	/* ================================================================
	 * Scheduled Imports (#1)
	 * ================================================================ */

	function loadSchedules() {
		var schedules = tsiImporter.schedules || {};
		var html = '';
		$.each(schedules, function (id, s) {
			var statusText = esc(s.last_status || 'N/A');
			var statusAttr = '';
			if (s.last_error) {
				statusAttr = ' title="' + $('<span>').text(s.last_error).html() + '" class="tsi-schedule-error"';
			}
			html += '<tr>' +
				'<td>' + esc(s.name) + '</td>' +
				'<td>' + esc(s.post_type) + '</td>' +
				'<td>' + esc(s.frequency) + '</td>' +
				'<td>' + esc(s.email || '—') + '</td>' +
				'<td>' + esc(s.last_run || 'Never') + '</td>' +
				'<td' + statusAttr + '>' + statusText + '</td>' +
				'<td><button type="button" class="button button-small tsi-delete-schedule" data-id="' + esc(id) + '">Delete</button></td>' +
				'</tr>';
		});
		if (!html) {
			html = '<tr><td colspan="7">No scheduled imports.</td></tr>';
		}
		$('#tsi-schedule-body').html(html);

		/* Populate profile dropdown in schedule form */
		var $psel = $('#tsi-schedule-profile').empty().append('<option value="">— auto-match —</option>');
		var profiles = tsiImporter.profiles || {};
		$.each(profiles, function (id, p) {
			$psel.append('<option value="' + esc(id) + '">' + esc(p.name) + '</option>');
		});
	}

	$('#tsi-btn-add-schedule').on('click', function () {
		var name  = $('#tsi-schedule-name').val().trim();
		var url   = $('#tsi-schedule-url').val().trim();
		var freq  = $('#tsi-schedule-freq').val();
		var prof  = $('#tsi-schedule-profile').val();
		var email = $('#tsi-schedule-email').val().trim();

		if (!name || !url) {
			window.alert('Please enter a name and CSV URL.');
			return;
		}

		showOverlay('Creating schedule\u2026');
		$.post(tsiImporter.ajax_url, {
			action:        'tsi_add_schedule',
			nonce:         tsiImporter.nonce,
			schedule_name: name,
			csv_url:       url,
			post_type:     $('#tsi-post-type').val(),
			frequency:     freq,
			profile_id:    prof,
			email:         email
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Failed to create schedule.');
				return;
			}
			tsiImporter.schedules = res.data.schedules;
			loadSchedules();
			$('#tsi-schedule-name, #tsi-schedule-url, #tsi-schedule-email').val('');
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Schedule request failed.');
		});
	});

	$(document).on('click', '.tsi-delete-schedule', function () {
		var sid = $(this).data('id');
		if (!window.confirm('Delete this schedule?')) {
			return;
		}
		$.post(tsiImporter.ajax_url, {
			action:      'tsi_delete_schedule',
			nonce:       tsiImporter.nonce,
			schedule_id: sid
		}, function (res) {
			if (res.success) {
				tsiImporter.schedules = res.data.schedules;
				loadSchedules();
			}
		});
	});

	/* ================================================================
	 * Scheduled Exports
	 * ================================================================ */

	function loadExportSchedules() {
		var schedules = tsiImporter.export_schedules || {};
		var html = '';
		$.each(schedules, function (id, s) {
			html += '<tr>' +
				'<td>' + esc(s.name) + '</td>' +
				'<td>' + esc(s.post_type) + '</td>' +
				'<td>' + esc(s.frequency) + '</td>' +
				'<td>' + esc(s.email || '\u2014') + '</td>' +
				'<td>' + esc(s.last_run || 'Never') + '</td>' +
				'<td>' + esc(s.last_status || 'N/A') + '</td>' +
				'<td><button type="button" class="button button-small tsi-delete-export-schedule" data-id="' + esc(id) + '">Delete</button></td>' +
				'</tr>';
		});
		if (!html) {
			html = '<tr><td colspan="7">No scheduled exports.</td></tr>';
		}
		$('#tsi-export-schedule-body').html(html);
	}

	$('#tsi-btn-add-export-schedule').on('click', function () {
		var name  = $('#tsi-export-schedule-name').val().trim();
		var freq  = $('#tsi-export-schedule-freq').val();
		var email = $('#tsi-export-schedule-email').val().trim();

		if (!name) {
			window.alert('Please enter a name for the export schedule.');
			return;
		}

		showOverlay('Creating export schedule\u2026');
		$.post(tsiImporter.ajax_url, {
			action:        'tsi_add_export_schedule',
			nonce:         tsiImporter.nonce,
			schedule_name: name,
			post_type:     $('#tsi-post-type').val(),
			frequency:     freq,
			email:         email
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Failed to create export schedule.');
				return;
			}
			tsiImporter.export_schedules = res.data.export_schedules;
			loadExportSchedules();
			$('#tsi-export-schedule-name, #tsi-export-schedule-email').val('');
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Export schedule request failed.');
		});
	});

	$(document).on('click', '.tsi-delete-export-schedule', function () {
		var sid = $(this).data('id');
		if (!window.confirm('Delete this export schedule?')) {
			return;
		}
		$.post(tsiImporter.ajax_url, {
			action:      'tsi_delete_export_schedule',
			nonce:       tsiImporter.nonce,
			schedule_id: sid
		}, function (res) {
			if (res.success) {
				tsiImporter.export_schedules = res.data.export_schedules;
				loadExportSchedules();
			}
		});
	});

	/* ================================================================
	 * Helpers — History & Schedule step resets
	 * ================================================================ */

	function resetFrom(stepId) {
		var found = false;
		$('.tsi-card').each(function () {
			if (found) {
				$(this).hide();
			}
			if (this.id === stepId) {
				found = true;
			}
		});
		if (stepId === 'tsi-step-source') {
			$('#tsi-step-export').hide();
			$('#tsi-step-source').hide();
			$('#tsi-step-mapping').hide();
			$('#tsi-step-progress').hide();
			$('#tsi-step-results').hide();
			$('#tsi-step-validation').hide();
			$('#tsi-step-history').hide();
			$('#tsi-step-schedule').hide();
		}
	}

	function resetAll() {
		$('#tsi-post-type').val('');
		$('#tsi-step-actions').hide();
		resetFrom('tsi-step-source');
		resetSource();
		resetProgress();
	}

	function resetSource() {
		$('#tsi-csv-file').val('');
		$('#tsi-csv-url').val('');
		$('#tsi-file-info').hide().empty();
		$('#tsi-preview').hide().empty();
		$('#tsi-file-queue').hide().empty();
		csvHeaders  = [];
		csvToken    = '';
		csvRowCount = 0;
		fileQueue   = [];
	}

	function resetProgress() {
		$('#tsi-progress-fill').css('width', '0');
		$('#tsi-progress-pct').text('0%');
		$('#tsi-progress-title').text('Importing\u2026');
		$('#tsi-progress-detail').text('Preparing your import\u2026');
		$('#tsi-live-log').empty();
		$('#tsi-results-summary').empty();
		$('#tsi-results-log').empty();
		lastHistoryId = '';
		lastAllLogs   = [];
	}

	function showOverlay(text) {
		$('#tsi-overlay-text').text(text);
		$('#tsi-overlay').show();
	}

	function hideOverlay() {
		$('#tsi-overlay').hide();
	}

	/* Close overlay on ESC key */
	$(document).on('keydown', function (e) {
		if (27 === e.keyCode && $('#tsi-overlay').is(':visible')) {
			hideOverlay();
		}
	});

	function scrollTo(selector) {
		var $el = $(selector);
		if ($el.length) {
			$('html, body').animate({ scrollTop: $el.offset().top - 46 }, 300);
		}
	}

	function esc(str) {
		if (typeof str !== 'string') {
			return str;
		}
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	function downloadBase64(b64, filename, mime) {
		var byteChars   = atob(b64);
		var byteNumbers = new Array(byteChars.length);
		var i;
		for (i = 0; i < byteChars.length; i++) {
			byteNumbers[i] = byteChars.charCodeAt(i);
		}
		var blobType = mime || 'text/csv';
		var blob = new Blob([new Uint8Array(byteNumbers)], { type: blobType });
		var link = document.createElement('a');
		link.href     = URL.createObjectURL(blob);
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

})(jQuery);
