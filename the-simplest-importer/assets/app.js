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

	var csvHeaders      = [];
	var csvToken        = '';
	var csvRowCount     = 0;
	var extraFieldCount = 0;
	var postTypeData    = {};

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
	});

	$('#tsi-post-type').on('change', function () {
		var slug = $(this).val();
		if (slug) {
			var pt = postTypeData[slug];
			if (pt && pt.count === 0) {
				$('#tsi-btn-export').hide();
			} else {
				$('#tsi-btn-export').show();
			}
			$('#tsi-step-actions').slideDown(200);
		} else {
			$('#tsi-btn-export').show();
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
		var mode = $('input[name="tsi-export-mode"]:checked').val();
		var params = {
			action:      'tsi_export',
			nonce:       tsiImporter.nonce,
			post_type:   $('#tsi-post-type').val(),
			export_mode: mode
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
		$.post(tsiImporter.ajax_url, params, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Export failed.');
				return;
			}
			downloadBase64(res.data.csv, res.data.filename);
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
		if (files && files.length) {
			uploadFile(files[0]);
		}
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
		if (!file.name.toLowerCase().match(/\.csv$/)) {
			window.alert('Please select a .csv file.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'tsi_parse_csv');
		formData.append('nonce', tsiImporter.nonce);
		formData.append('csv_file', file);

		showOverlay('Parsing CSV\u2026');

		$.ajax({
			url:         tsiImporter.ajax_url,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function (res) {
				hideOverlay();
				if (!res.success) {
					window.alert(res.data || 'Error parsing CSV.');
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

		showOverlay('Fetching CSV\u2026');
		$.post(tsiImporter.ajax_url, {
			action:  'tsi_parse_csv_url',
			nonce:   tsiImporter.nonce,
			csv_url: url
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Error fetching CSV.');
				return;
			}
			onCsvParsed(res.data, url.split('/').pop() || 'remote.csv');
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

		/* File info badge */
		$('#tsi-file-info').html(
			'<span class="dashicons dashicons-yes-alt"></span> ' +
			'<strong>' + esc(filename) + '</strong> &mdash; ' +
			data.row_count + ' data rows, ' + csvHeaders.length + ' columns'
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
			$('#tsi-step-mapping').slideDown(200);
			scrollTo('#tsi-step-mapping');
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
		if (importMode === 'update') {
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

		var totals = { inserted: 0, updated: 0, skipped: 0, errors: 0 };
		var allLogs = [];

		processBatch(0, mapping, totals, allLogs);
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
	 * Process one batch, then recurse until done.
	 */
	function processBatch(offset, mapping, totals, allLogs) {
		$.post(tsiImporter.ajax_url, {
			action:     'tsi_import_batch',
			nonce:      tsiImporter.nonce,
			token:      csvToken,
			post_type:  $('#tsi-post-type').val(),
			mapping:    JSON.stringify(mapping),
			offset:     offset,
			batch_size: tsiImporter.batch_size
		}, function (res) {
			if (!res.success) {
				$('#tsi-progress-detail').text(res.data || 'Import failed.');
				return;
			}

			var d       = res.data;
			var percent = Math.min(100, Math.round((d.offset / d.total) * 100));

			/* Update progress bar */
			$('#tsi-progress-fill').css('width', percent + '%');
			$('#tsi-progress-pct').text(percent + '%');
			$('#tsi-progress-detail').text(
				'Processed ' + d.offset + ' of ' + d.total + ' rows\u2026'
			);

			/* Accumulate */
			totals.inserted += d.inserted;
			totals.updated  += d.updated;
			totals.skipped  += d.skipped;
			totals.errors   += d.errors;
			allLogs = allLogs.concat(d.log);

			/* Append to live log */
			var $liveLog = $('#tsi-live-log');
			$.each(d.log, function (idx, line) {
				var cls = 'tsi-log-ok';
				if (line.indexOf('Error') !== -1) {
					cls = 'tsi-log-error';
				} else if (line.indexOf('Skipped') !== -1) {
					cls = 'tsi-log-skip';
				}
				$liveLog.append('<div class="' + cls + '">' + esc(line) + '</div>');
			});
			$liveLog.scrollTop($liveLog[0].scrollHeight);

			if (!d.done) {
				processBatch(d.offset, mapping, totals, allLogs);
			} else {
				showResults(totals, allLogs);
			}

		}).fail(function () {
			$('#tsi-progress-detail').text('Network error. Import halted.');
		});
	}

	/* ================================================================
	 * Step 6 — Results
	 * ================================================================ */

	function showResults(totals, allLogs) {
		/* Complete progress */
		$('#tsi-progress-fill').css('width', '100%');
		$('#tsi-progress-pct').text('100%');
		$('#tsi-progress-title').text('Complete');
		$('#tsi-progress-detail').text('Import finished successfully.');

		/* Summary badges */
		$('#tsi-results-summary').html(
			'<span class="tsi-badge tsi-badge-inserted"><span class="dashicons dashicons-plus-alt"></span> ' + totals.inserted + ' Inserted</span>' +
			'<span class="tsi-badge tsi-badge-updated"><span class="dashicons dashicons-update"></span> ' + totals.updated + ' Updated</span>' +
			'<span class="tsi-badge tsi-badge-skipped"><span class="dashicons dashicons-minus"></span> ' + totals.skipped + ' Skipped</span>' +
			'<span class="tsi-badge tsi-badge-errors"><span class="dashicons dashicons-warning"></span> ' + totals.errors + ' Errors</span>'
		);

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

		$('#tsi-step-results').slideDown(200);
		scrollTo('#tsi-step-results');
	}

	/* Start New Import */
	$('#tsi-btn-new').on('click', function () {
		resetAll();
		scrollTo('#tsi-step-entity');
	});

	/* ================================================================
	 * Helpers
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
		$('#tsi-step-source, #tsi-step-mapping, #tsi-step-progress, #tsi-step-results').each(function () {
			if (this.id !== stepId) {
				/* handled above */
			}
		});
		if (stepId === 'tsi-step-source') {
			$('#tsi-step-export').hide();
			$('#tsi-step-source').hide();
			$('#tsi-step-mapping').hide();
			$('#tsi-step-progress').hide();
			$('#tsi-step-results').hide();
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
		csvHeaders  = [];
		csvToken    = '';
		csvRowCount = 0;
	}

	function resetProgress() {
		$('#tsi-progress-fill').css('width', '0');
		$('#tsi-progress-pct').text('0%');
		$('#tsi-progress-title').text('Importing\u2026');
		$('#tsi-progress-detail').text('Preparing your import\u2026');
		$('#tsi-live-log').empty();
		$('#tsi-results-summary').empty();
		$('#tsi-results-log').empty();
	}

	function showOverlay(text) {
		$('#tsi-overlay-text').text(text);
		$('#tsi-overlay').show();
	}

	function hideOverlay() {
		$('#tsi-overlay').hide();
	}

	function scrollTo(selector) {
		var $el = $(selector);
		if ($el.length) {
			$('html, body').animate({ scrollTop: $el.offset().top - 46 }, 300);
		}
	}

	/**
	 * Escape a string for safe HTML rendering.
	 */
	function esc(str) {
		if (typeof str !== 'string') {
			return str;
		}
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Trigger a file download from a base64-encoded CSV string.
	 */
	function downloadBase64(b64, filename) {
		var byteChars   = atob(b64);
		var byteNumbers = new Array(byteChars.length);
		var i;
		for (i = 0; i < byteChars.length; i++) {
			byteNumbers[i] = byteChars.charCodeAt(i);
		}
		var blob = new Blob([new Uint8Array(byteNumbers)], { type: 'text/csv' });
		var link = document.createElement('a');
		link.href     = URL.createObjectURL(blob);
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

})(jQuery);
