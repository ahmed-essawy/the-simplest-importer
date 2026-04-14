/**
 * Smartly Import Export — Admin UI
 *
 * @package SmartlyImportExport
 */

/* global jQuery, smieImporter */
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
	var currentSourceName = '';
	var pendingImportMode = 'insert';
	var queueContext      = null;

	/* ================================================================
	 * Dark Mode Toggle
	 * ================================================================ */

	(function initDarkMode() {
		var $wrap   = $('.smie-wrap');
		var $toggle = $('#smie-dark-toggle');
		var $icon   = $toggle.find('.dashicons');
		var stored  = localStorage.getItem('smie_dark_mode');

		if (stored === '1') {
			$wrap.addClass('smie-wrap--dark');
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		}

		$toggle.on('click', function () {
			var isDark = $wrap.toggleClass('smie-wrap--dark').hasClass('smie-wrap--dark');
			localStorage.setItem('smie_dark_mode', isDark ? '1' : '0');
			$icon.toggleClass('dashicons-visibility', !isDark)
			     .toggleClass('dashicons-hidden', isDark);
		});
	})();

	/* ================================================================
	 * Field Search in Mapping Table
	 * ================================================================ */

	$('#smie-field-search').on('keyup', function () {
		var term   = $(this).val().toLowerCase();
		var $rows  = $('#smie-mapping-table tbody tr');
		var total  = $rows.length;
		var shown  = 0;

		$rows.each(function () {
			var label = $(this).find('.smie-col-field').text().toLowerCase();
			var match = !term || label.indexOf(term) !== -1;
			$(this).toggle(match);
			if (match) {
				shown++;
			}
		});

		var $count = $('#smie-field-search-count');
		if (term) {
			$count.text(shown + ' / ' + total).show();
		} else {
			$count.hide();
		}
	});

	/* ================================================================
	 * Step 1 — Load post types
	 * ================================================================ */

	$.post(smieImporter.ajax_url, {
		action: 'smie_get_post_types',
		nonce:  smieImporter.nonce
	}, function (res) {
		if (!res.success) {
			return;
		}

		var $sel = $('#smie-post-type');
		$.each(res.data, function (i, pt) {
			postTypeData[pt.slug] = pt;
			$sel.append(
				'<option value="' + esc(pt.slug) + '">' +
				esc(pt.label) + ' (' + pt.count + ')' +
				'</option>'
			);
		});

		/* Restore sticky post type from localStorage (#14) */
		var saved = localStorage.getItem('smie_last_post_type');
		if (saved && postTypeData[saved]) {
			$sel.val(saved).trigger('change');
		}
	});

	$('#smie-post-type').on('change', function () {
		var slug = $(this).val();
		if (slug) {
			/* Save sticky selection (#14) */
			localStorage.setItem('smie_last_post_type', slug);

			var pt = postTypeData[slug];
			if (pt && pt.count === 0) {
				$('#smie-btn-export').hide();
			} else {
				$('#smie-btn-export').show();
				/* Show row count in export button (#15) */
				if (pt && pt.count) {
					$('#smie-btn-export').find('.smie-action-desc').text('Export ' + pt.count + ' rows');
				}
			}
			$('#smie-step-actions').slideDown(200);
		} else {
			$('#smie-btn-export').show().find('.smie-action-desc').text('Export');
			$('#smie-step-actions').slideUp(200);
		}
		resetFrom('smie-step-source');
	});

	/* ================================================================
	 * Step 2 — Action buttons
	 * ================================================================ */

	$('#smie-btn-import').on('click', function () {
		resetFrom('smie-step-source');
		resetSource();
		$('#smie-step-source').slideDown(200);
		scrollTo('#smie-step-source');
	});

	$('#smie-btn-export').on('click', function () {
		resetFrom('smie-step-source');
		/* Reset export options to defaults */
		$('input[name="smie-export-mode"][value="all"]').prop('checked', true);
		$('#smie-export-range-fields').hide();
		$('#smie-export-row-fields').hide();
		$('#smie-export-date-fields').hide();
		$('#smie-export-date-from, #smie-export-date-to').val('');

		/* Set ID range min/max from selected post type */
		var pt = postTypeData[$('#smie-post-type').val()];
		var maxId = (pt && pt.max_id) ? pt.max_id : '';
		var count = (pt && pt.count) ? pt.count : '';
		$('#smie-export-id-from').val(1).attr({ min: 1, max: maxId || '' });
		$('#smie-export-id-to').val(maxId || '').attr({ min: 1, max: maxId || '' });

		/* Set row range min/max from post count */
		$('#smie-export-row-from').val(1).attr({ min: 1, max: count || '' });
		$('#smie-export-row-to').val(count || '').attr({ min: 1, max: count || '' });

		/* Populate selective columns checklist (#9) */
		var ptSlug = $('#smie-post-type').val();
		if (ptSlug) {
			$.post(smieImporter.ajax_url, {
				action:    'smie_get_fields',
				nonce:     smieImporter.nonce,
				post_type: ptSlug
			}, function (res) {
				if (!res.success) {
					return;
				}
				var html = '';
				$.each(res.data, function (key, label) {
					html += '<label class="smie-export-field-label">' +
						'<input type="checkbox" class="smie-export-field" value="' + esc(key) + '" checked> ' +
						esc(label) +
						'</label> ';
				});
				$('#smie-export-fields').html(html);
			});
		}

		$('#smie-step-export').slideDown(200);
		scrollTo('#smie-step-export');
	});

	/* Export mode — click anywhere on the option box */
	$('.smie-export-option-wrap').on('click', function (e) {
		if ($(e.target).is('input[type="number"], input[type="date"]')) {
			return;
		}
		$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
	});

	/* Export mode radio toggles */
	$('input[name="smie-export-mode"]').on('change', function () {
		var mode = $(this).val();
		$('#smie-export-row-fields').toggle(mode === 'rows');
		$('#smie-export-range-fields').toggle(mode === 'range');
		$('#smie-export-date-fields').toggle(mode === 'dates');
	});

	/* Clamp ID range inputs on every keystroke / change */
	$('#smie-export-id-from, #smie-export-id-to, #smie-export-row-from, #smie-export-row-to').on('input change', function () {
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
	$('#smie-btn-run-export').on('click', function () {
		var mode   = $('input[name="smie-export-mode"]:checked').val();
		var format = $('#smie-export-format').val() || 'csv';
		var params = {
			action:        'smie_export',
			nonce:         smieImporter.nonce,
			post_type:     $('#smie-post-type').val(),
			export_mode:   mode,
			export_format: format
		};

		if (mode === 'rows') {
			params.row_from = $('#smie-export-row-from').val();
			params.row_to   = $('#smie-export-row-to').val();
			var ptR       = postTypeData[$('#smie-post-type').val()];
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
			params.id_from = $('#smie-export-id-from').val();
			params.id_to   = $('#smie-export-id-to').val();
			var pt      = postTypeData[$('#smie-post-type').val()];
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
			params.date_from = $('#smie-export-date-from').val();
			params.date_to   = $('#smie-export-date-to').val();
			if (!params.date_from && !params.date_to) {
				window.alert('Please enter at least one date.');
				return;
			}
		}

		showOverlay('Exporting\u2026');

		/* Collect advanced export options (#5, #9) */
		var statuses = [];
		$('.smie-export-status:checked').each(function () {
			statuses.push($(this).val());
		});
		if (statuses.length) {
			params['export_statuses[]'] = statuses;
		}
		var fields = [];
		$('.smie-export-field:checked').each(function () {
			fields.push($(this).val());
		});
		if (fields.length) {
			params['export_fields[]'] = fields;
		}

		$.post(smieImporter.ajax_url, params, function (res) {
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

	$('#smie-btn-template').on('click', function () {
		resetFrom('smie-step-source');
		showOverlay('Generating template\u2026');
		$.post(smieImporter.ajax_url, {
			action:    'smie_template',
			nonce:     smieImporter.nonce,
			post_type: $('#smie-post-type').val()
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
	$('#smie-btn-history').on('click', function () {
		resetFrom('smie-step-source');
		loadHistory();
		$('#smie-step-history').slideDown(200);
		scrollTo('#smie-step-history');
	});

	/* Schedule button (#1) */
	$('#smie-btn-schedule').on('click', function () {
		resetFrom('smie-step-source');
		loadSchedules();
		loadExportSchedules();
		$('#smie-step-schedule').slideDown(200);
		$('#smie-step-export-schedule').slideDown(200);
		scrollTo('#smie-step-schedule');
	});

	/* ================================================================
	 * Step 3 — Source tabs
	 * ================================================================ */

	$('.smie-source-tab').on('click', function () {
		$('.smie-source-tab').removeClass('smie-source-tab--active');
		$(this).addClass('smie-source-tab--active');
		var tab = $(this).data('tab');
		$('.smie-source-panel').hide();
		$('#smie-source-' + tab).show();
	});

	/* ---- Drag & Drop ---- */

	var $dropzone = $('#smie-dropzone');

	$dropzone.on('dragover dragenter', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).addClass('smie-drag-over');
	}).on('dragleave drop', function (e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).removeClass('smie-drag-over');
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
				$('#smie-file-queue').html(
					'<strong>' + fileQueue.length + ' files queued.</strong> ' +
					'Processing first file. Remaining files will be processed after each import completes.'
				).show();
				uploadFile(fileQueue.shift());
				return;
			}
			if (fileQueue.length === 1) {
				uploadFile(fileQueue.shift());
				return;
			}
		}
		uploadFile(files[0]);
	}).on('click', function (e) {
		if ($(e.target).closest('#smie-browse-btn').length) {
			return;
		}
		$('#smie-csv-file')[0].click();
	});

	$('#smie-browse-btn').on('click', function (e) {
		e.stopPropagation();
		$('#smie-csv-file')[0].click();
	});

	$('#smie-csv-file').on('change', function () {
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
		formData.append('action', 'smie_parse_csv');
		formData.append('nonce', smieImporter.nonce);
		formData.append('csv_file', file);

		showOverlay('Parsing file\u2026');

		$.ajax({
			url:         smieImporter.ajax_url,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function (res) {
				hideOverlay();
				if (!res.success) {
					queueContext = null;
					fileQueue = [];
					updateQueueNotice();
					window.alert(res.data || 'Error parsing file.');
					return;
				}
				onCsvParsed(res.data, file.name);
			},
			error: function () {
				hideOverlay();
				queueContext = null;
				fileQueue = [];
				updateQueueNotice();
				window.alert('Upload request failed.');
			}
		});
	}

	/* ---- Fetch URL ---- */

	$('#smie-btn-fetch-url').on('click', function () {
		var url = $('#smie-csv-url').val().trim();
		if (!url) {
			window.alert('Please enter a URL.');
			return;
		}

		showOverlay('Fetching file\u2026');
		$.post(smieImporter.ajax_url, {
			action:  'smie_parse_csv_url',
			nonce:   smieImporter.nonce,
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
		currentSourceName = filename;
		csvHeaders  = data.headers;
		csvToken    = data.token;
		csvRowCount = data.row_count;
		csvDelimiter = data.delimiter || ',';
		firstPreviewRow = (data.preview && data.preview.length) ? data.preview[0] : [];

		/* File info badge — include delimiter (#11) */
		var delimLabel = csvDelimiter === '\t' ? 'tab' : csvDelimiter;
		$('#smie-file-info').html(
			'<span class="dashicons dashicons-yes-alt"></span> ' +
			'<strong>' + esc(filename) + '</strong> &mdash; ' +
			data.row_count + ' data rows, ' + csvHeaders.length + ' columns' +
			' <span class="smie-delimiter-badge">delimiter: <code>' + esc(delimLabel) + '</code></span>'
		).show();

		/* Data preview table */
		var preview = data.preview || [];
		if (preview.length) {
			var html = '<h3>Data Preview <span class="smie-preview-meta">(first ' + preview.length + ' of ' + data.row_count + ' rows)</span></h3>';
			html += '<div class="smie-preview-scroll"><table class="widefat smie-preview-table"><thead><tr>';
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
			$('#smie-preview').html(html).show();
		}

		/* Load fields and show mapping */
		showOverlay('Loading fields\u2026');
		$.post(smieImporter.ajax_url, {
			action:    'smie_get_fields',
			nonce:     smieImporter.nonce,
			post_type: $('#smie-post-type').val()
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				return;
			}
			extraFieldCount = 0;
			buildMappingTable(res.data);
			updateProfileDropdown(smieImporter.profiles || {});
			/* Show filter section if CSV headers are available */
			if (csvHeaders.length) {
				$('#smie-filter-section').show();
				$('#smie-filter-rules').empty();
			}
			$('#smie-step-mapping').slideDown(200);
			scrollTo('#smie-step-mapping');
			updateMappingPreview();

			if (queueContext) {
				var remapResult = remapQueuedMappingForHeaders(queueContext.mapping, queueContext.sourceHeaders, csvHeaders);
				if (remapResult.missing.length) {
					queueContext = null;
					fileQueue = [];
					updateQueueNotice();
					window.alert('Queued import paused. The next file is missing expected columns: ' + remapResult.missing.join(', '));
					return;
				}

				applyProfileMapping(remapResult.mapping);
				startImport(queueContext.importMode, remapResult.mapping, queueContext.transforms, queueContext.isDryRun, queueContext.dupField, queueContext.dupMeta, queueContext.filters, true);
			}
		});
	}

	/* ================================================================
	 * Step 4 — Mapping
	 * ================================================================ */

	function buildMappingTable(fields) {
		var $tbody  = $('#smie-mapping-table tbody').empty();
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
		opts += '<option value="__merge__">\u2702 Merge columns\u2026</option>';

		var checked = matchIdx !== -1 ? ' checked' : '';

		/* Field cell */
		var fieldHtml;
		if (isExtra) {
			fieldHtml = '<td class="smie-col-field"><input type="text" class="smie-extra-key regular-text" placeholder="meta_key_name" value="' + esc(fieldKey) + '"></td>';
		} else {
			fieldHtml = '<td class="smie-col-field"><strong>' + esc(fieldLabel) + '</strong><br><code>' + esc(fieldKey) + '</code></td>';
		}

		var removeBtn = isExtra ? ' <button type="button" class="button button-small smie-remove-extra" title="Remove">&times;</button>' : '';
		var resetBtn  = !isExtra ? ' <button type="button" class="button button-small smie-reset-field" title="Reset to original mapping"><span class="dashicons dashicons-image-rotate"></span></button>' : '';

		/* Transform dropdown */
		var transformOpts = '<select class="smie-transform-select">' +
			'<option value="">— none —</option>' +
			'<optgroup label="Text">' +
			'<option value="uppercase">UPPERCASE</option>' +
			'<option value="lowercase">lowercase</option>' +
			'<option value="titlecase">Title Case</option>' +
			'<option value="trim">Trim whitespace</option>' +
			'<option value="strip_tags">Strip HTML tags</option>' +
			'<option value="slug">Slug (sanitize_title)</option>' +
			'<option value="url_encode">URL encode</option>' +
			'</optgroup>' +
			'<optgroup label="Find &amp; Replace">' +
			'<option value="find_replace">Find &amp; Replace…</option>' +
			'<option value="prepend">Prepend text…</option>' +
			'<option value="append">Append text…</option>' +
			'</optgroup>' +
			'<optgroup label="Date">' +
			'<option value="date_ymd">Date → YYYY-MM-DD</option>' +
			'<option value="date_dmy">Date → DD/MM/YYYY</option>' +
			'<option value="date_mdy">Date → MM/DD/YYYY</option>' +
			'<option value="date_iso">Date → ISO 8601</option>' +
			'</optgroup>' +
			'<optgroup label="Math">' +
			'<option value="math_multiply">Multiply by…</option>' +
			'<option value="math_add">Add number…</option>' +
			'<option value="number_format">Format number (2 dec)</option>' +
			'</optgroup>' +
			'</select>' +
			'<input type="text" class="smie-transform-param" placeholder="" style="display:none">';

		return $(
			'<tr data-field="' + esc(fieldKey) + '" data-original-match="' + matchIdx + '"' + (isExtra ? ' class="smie-extra-row"' : '') + '>' +
			'<td class="smie-col-check"><input type="checkbox" class="smie-field-check"' + checked + '></td>' +
			fieldHtml +
			'<td>' +
				'<div class="smie-col-map-inner">' +
				'<select class="smie-col-select">' + opts + '</select>' +
				resetBtn +
				removeBtn +
				'</div>' +
			'<input type="text" class="smie-merge-template regular-text" placeholder="{first_name} {last_name}" style="display:none">' +
				'<input type="text" class="smie-custom-value regular-text" placeholder="Enter static value\u2026" style="display:none">' +
			'</td>' +
			'<td>' + transformOpts + '</td>' +
			'</tr>'
		);
	}

	function bindMappingEvents() {
		var $tbody = $('#smie-mapping-table tbody');
		$tbody.off('change', '.smie-col-select').off('change', '.smie-field-check').off('click', '.smie-remove-extra').off('click', '.smie-reset-field').off('change', '.smie-transform-select');

		var paramTransforms = {
			find_replace:   'find|replace',
			prepend:        'text to prepend',
			append:         'text to append',
			math_multiply:  'multiplier',
			math_add:       'number to add'
		};

		$tbody.on('change', '.smie-transform-select', function () {
			var val    = $(this).val();
			var $param = $(this).siblings('.smie-transform-param');
			if (paramTransforms[val]) {
				$param.attr('placeholder', paramTransforms[val]).show().trigger('focus');
			} else {
				$param.val('').hide();
			}
		});

		$tbody.on('change', '.smie-col-select', function () {
			var $row = $(this).closest('tr');
			var $cb  = $row.find('.smie-field-check');
			var val  = $(this).val();

			if (val === '__custom__') {
				$row.find('.smie-custom-value').show().trigger('focus');
				$row.find('.smie-merge-template').hide();
				$cb.prop('checked', true);
			} else if (val === '__merge__') {
				$row.find('.smie-merge-template').show().trigger('focus');
				$row.find('.smie-custom-value').hide();
				$cb.prop('checked', true);
			} else {
				$row.find('.smie-custom-value').hide();
				$row.find('.smie-merge-template').hide();
				$cb.prop('checked', val !== '-1');
			}
			updateMappingCount();
		});

		$tbody.on('change', '.smie-field-check', function () {
			if (!$(this).is(':checked')) {
				var $row = $(this).closest('tr');
				$row.find('.smie-col-select').val('-1');
				$row.find('.smie-custom-value').hide();
			}
			updateMappingCount();
		});

		$tbody.on('click', '.smie-remove-extra', function () {
			$(this).closest('tr').remove();
			updateMappingCount();
		});

		$tbody.on('click', '.smie-reset-field', function () {
			var $row     = $(this).closest('tr');
			var origIdx  = $row.data('original-match');
			var $sel     = $row.find('.smie-col-select');
			var $cb      = $row.find('.smie-field-check');

			$sel.val(String(origIdx));
			$row.find('.smie-custom-value').hide();
			$row.find('.smie-merge-template').hide();

			if (parseInt(origIdx, 10) !== -1) {
				$cb.prop('checked', true);
			} else {
				$cb.prop('checked', false);
			}
			updateMappingCount();
		});
	}

	function updateMappingCount() {
		var total   = $('#smie-mapping-table tbody tr').length;
		var checked = $('#smie-mapping-table tbody .smie-field-check:checked').length;
		$('#smie-mapping-count').text(checked + ' / ' + total + ' fields mapped');

		/* Show/hide Update button based on ID field being mapped */
		var idMapped = false;
		$('#smie-mapping-table tbody tr[data-field="ID"]').each(function () {
			var $cb  = $(this).find('.smie-field-check');
			var $sel = $(this).find('.smie-col-select');
			if ($cb.is(':checked') && $sel.val() !== '-1') {
				idMapped = true;
			}
		});
		$('#smie-btn-update').toggle(idMapped);
		$('#smie-btn-insert-update').toggle(idMapped);
	}

	/* Select / Deselect All */
	$('#smie-select-all').on('click', function () {
		$('#smie-mapping-table tbody tr').each(function () {
			$(this).find('.smie-field-check').prop('checked', true);
		});
		updateMappingCount();
	});

	$('#smie-deselect-all').on('click', function () {
		$('#smie-mapping-table tbody tr').each(function () {
			$(this).find('.smie-field-check').prop('checked', false);
			$(this).find('.smie-col-select').val('-1');
			$(this).find('.smie-custom-value').hide();
			$(this).find('.smie-merge-template').hide();
		});
		updateMappingCount();
	});

	/* Reset All Mappings (non-custom-field rows only) */
	$('#smie-reset-all').on('click', function () {
		$('#smie-mapping-table tbody tr').not('.smie-extra-row').each(function () {
			var origIdx = $(this).data('original-match');
			var $sel    = $(this).find('.smie-col-select');
			var $cb     = $(this).find('.smie-field-check');

			$sel.val(String(origIdx));
			$(this).find('.smie-custom-value').hide();
			$(this).find('.smie-merge-template').hide();

			if (parseInt(origIdx, 10) !== -1) {
				$cb.prop('checked', true);
			} else {
				$cb.prop('checked', false);
			}
		});
		updateMappingCount();
	});

	/* Add extra custom field */
	$('#smie-add-extra').on('click', function () {
		extraFieldCount++;
		var key     = 'custom_field_' + extraFieldCount;
		var csvNorm = csvHeaders.map(function (h) {
			return h.toLowerCase().replace(/[\s_\-]+/g, '');
		});
		var $row = buildMappingRow(key, 'Custom Field', csvNorm, true);
		$('#smie-mapping-table tbody').append($row);
		$row.find('.smie-extra-key').trigger('focus');
		updateMappingCount();
	});

	/* ================================================================
	 * Step 5 — Run import (batch)
	 * ================================================================ */

	$('#smie-btn-insert, #smie-btn-update, #smie-btn-insert-update').on('click', function () {
		var btnId      = $(this).attr('id');
		var importMode = btnId === 'smie-btn-update' ? 'update' : (btnId === 'smie-btn-insert-update' ? 'insert-update' : 'insert');
		pendingImportMode = importMode;
		startImportFromCurrentState(importMode, false);
	});

	function startImportFromCurrentState(importMode, isQueuedContinuation) {
		var mapping    = buildMappingPayload();
		var transforms = buildTransformPayload();
		var isDryRun   = $('#smie-dry-run').is(':checked');
		var dupField   = '';
		var dupMeta    = '';

		if ($('#smie-dup-check').is(':checked')) {
			dupField = $('#smie-dup-field').val();
			dupMeta  = $('#smie-dup-meta-key').val();
		}

		startImport(importMode, mapping, transforms, isDryRun, dupField, dupMeta, buildFilterPayload(), isQueuedContinuation);
	}

	function startImport(importMode, mapping, transforms, isDryRun, dupField, dupMeta, filters, isQueuedContinuation) {
		if (importMode === 'insert' && mapping.hasOwnProperty('ID')) {
			delete mapping.ID;
		}

		var hasMapping = false;
		var key;
		for (key in mapping) {
			if (mapping.hasOwnProperty(key)) {
				hasMapping = true;
				break;
			}
		}

		if (!hasMapping) {
			window.alert('Please map at least one field before importing.');
			return;
		}

		if (!isQueuedContinuation) {
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

			if (fileQueue.length > 0) {
				queueContext = {
					sourceHeaders: cloneData(csvHeaders),
					mapping: cloneData(mapping),
					transforms: cloneData(transforms),
					importMode: importMode,
					isDryRun: isDryRun,
					dupField: dupField,
					dupMeta: dupMeta,
					filters: cloneData(filters),
					totals: { inserted: 0, updated: 0, skipped: 0, errors: 0 },
					logs: [],
					processedCount: 0,
					totalCount: fileQueue.length + 1
				};
			} else {
				queueContext = null;
			}

			updateQueueNotice();
		}

		resetProgress();
		$('#smie-step-results, #smie-step-validation').hide();
		$('#smie-step-mapping').slideUp(200);
		$('#smie-step-progress').slideDown(200);
		scrollTo('#smie-step-progress');

		if (isDryRun) {
			$('#smie-progress-title').text('Dry Run\u2026');
		}

		var totals = { inserted: 0, updated: 0, skipped: 0, errors: 0 };
		var allLogs = [];
		lastHistoryId    = '';
		lastAllLogs      = [];
		failedRowIndices = [];
		lastMapping      = cloneData(mapping);
		lastImportMode   = importMode;
		lastDryRun       = isDryRun;
		lastDupField     = dupField;
		lastDupMeta      = dupMeta;
		lastTransforms   = cloneData(transforms);
		lastFilters      = cloneData(filters);
		processBatch(0, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, Date.now());
	}

	function getSuggestedImportMode(mapping) {
		return mapping.hasOwnProperty('ID') ? 'insert-update' : 'insert';
	}

	function cloneData(data) {
		return JSON.parse(JSON.stringify(data));
	}

	function findHeaderIndex(headerName, headers) {
		var normalizedHeader = String(headerName).toLowerCase().replace(/[\s_\-]+/g, '');
		var index = -1;

		$.each(headers, function (i, candidate) {
			var normalizedCandidate = String(candidate).toLowerCase().replace(/[\s_\-]+/g, '');
			if (headerName === candidate || normalizedHeader === normalizedCandidate) {
				index = i;
				return false;
			}
		});

		return index;
	}

	function remapQueuedMappingForHeaders(mapping, sourceHeaders, newHeaders) {
		var remapped = cloneData(mapping);
		var missing  = [];

		$.each(remapped, function (fieldKey, info) {
			if (!info || info.source !== 'csv') {
				return;
			}

			var sourceHeader = sourceHeaders[info.col];
			var newIndex = findHeaderIndex(sourceHeader, newHeaders);
			if (newIndex === -1) {
				missing.push(sourceHeader);
				return;
			}

			info.col = newIndex;
		});

		return {
			mapping: remapped,
			missing: missing
		};
	}

	function updateQueueNotice() {
		if (fileQueue.length > 0) {
			var message = '<strong>' + fileQueue.length + ' queued files remaining.</strong>';
			if (queueContext) {
				message += ' They will continue automatically when the next file headers match.';
			}
			$('#smie-file-queue').html(message).show();
			return;
		}

		$('#smie-file-queue').hide().empty();
	}

	/**
	 * Build the mapping payload from the table.
	 */
	function buildMappingPayload() {
		var mapping = {};

		$('#smie-mapping-table tbody tr').each(function () {
			var $cb  = $(this).find('.smie-field-check');
			var $sel = $(this).find('.smie-col-select');
			if (!$cb.is(':checked')) {
				return;
			}

			var selVal = $sel.val();
			if (selVal === '-1') {
				return;
			}

			var fieldKey;
			if ($(this).hasClass('smie-extra-row')) {
				var raw = $(this).find('.smie-extra-key').val().trim();
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
					value:  $(this).find('.smie-custom-value').val()
				};
			} else if (selVal === '__merge__') {
				mapping[fieldKey] = {
					source:   'merge',
					template: $(this).find('.smie-merge-template').val()
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
	 * Build the transform payload from the table.
	 */
	function buildTransformPayload() {
		var transforms = {};
		var paramTransforms = ['find_replace', 'prepend', 'append', 'math_multiply', 'math_add'];

		$('#smie-mapping-table tbody tr').each(function () {
			var $cb = $(this).find('.smie-field-check');
			if (!$cb.is(':checked')) {
				return;
			}
			var field = $(this).data('field');
			if ($(this).hasClass('smie-extra-row')) {
				var raw = $(this).find('.smie-extra-key').val().trim();
				if (raw) {
					field = 'meta__' + raw;
				}
			}
			var transform = $(this).find('.smie-transform-select').val();
			if (transform) {
				if ($.inArray(transform, paramTransforms) !== -1) {
					var param = $(this).find('.smie-transform-param').val() || '';
					transforms[field] = { transform: transform, param: param };
				} else {
					transforms[field] = transform;
				}
			}
		});

		return transforms;
	}

	/**
	 * Update the mapping preview panel using the first CSV row.
	 */
	function updateMappingPreview() {
		if (!firstPreviewRow.length) {
			$('#smie-mapping-preview').hide();
			return;
		}

		var items = [];
		$('#smie-mapping-table tbody tr').each(function () {
			var $cb = $(this).find('.smie-field-check');
			if (!$cb.is(':checked')) {
				return;
			}
			var label = $(this).find('strong').text() || $(this).find('.smie-extra-key').val() || '?';
			var $sel  = $(this).find('.smie-col-select');
			var val   = '';
			if ($sel.val() === '__custom__') {
				val = $(this).find('.smie-custom-value').val() || '';
			} else if ($sel.val() === '__merge__') {
				val = $(this).find('.smie-merge-template').val() || '';
			} else {
				var colIdx = parseInt($sel.val(), 10);
				if (!isNaN(colIdx) && colIdx >= 0 && colIdx < firstPreviewRow.length) {
					val = firstPreviewRow[colIdx];
				}
			}
			items.push({ label: label, value: val });
		});

		if (!items.length) {
			$('#smie-mapping-preview').hide();
			return;
		}

		var html = '<table class="widefat smie-preview-mini"><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
		$.each(items, function (i, it) {
			html += '<tr><td>' + esc(it.label) + '</td><td><code>' + esc(it.value || '—') + '</code></td></tr>';
		});
		html += '</tbody></table>';
		$('#smie-mapping-preview-content').html(html);
		$('#smie-mapping-preview').show();
	}

	/* Update preview on any mapping change */
	$(document).on('change', '.smie-col-select, .smie-field-check, .smie-custom-value', updateMappingPreview);
	$(document).on('input', '.smie-custom-value', updateMappingPreview);

	/**
	 * Process one batch, then recurse until done.
	 */
	function processBatch(offset, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, startTime, retryRows) {
		var postData = {
			action:       'smie_import_batch',
			nonce:        smieImporter.nonce,
			token:        csvToken,
			post_type:    $('#smie-post-type').val(),
			mapping:      JSON.stringify(mapping),
			offset:       offset,
			batch_size:   smieImporter.batch_size,
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
		$.post(smieImporter.ajax_url, postData, function (res) {
			if (!res.success) {
				$('#smie-progress-detail').text(res.data || 'Import failed.');
				return;
			}

			var d       = res.data;
			var percent = Math.min(100, Math.round((d.offset / d.total) * 100));

			/* Update progress bar */
			$('#smie-progress-fill').css('width', percent + '%');
			$('#smie-progress-pct').text(percent + '%');
			$('#smie-progress-bar').attr('aria-valuenow', percent);

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

			$('#smie-progress-detail').text(
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
			var $liveLog = $('#smie-live-log');
			$.each(d.log, function (idx, line) {
				var cls = 'smie-log-ok';
				if (line.indexOf('Error') !== -1) {
					cls = 'smie-log-error';
				} else if (line.indexOf('Skipped') !== -1) {
					cls = 'smie-log-skip';
				} else if (line.indexOf('DRY RUN') !== -1) {
					cls = 'smie-log-dry';
				}
				$liveLog.append('<div class="' + cls + '">' + esc(line) + '</div>');
			});
			$liveLog.scrollTop($liveLog[0].scrollHeight);

			if (!d.done) {
				processBatch(d.offset, mapping, totals, allLogs, importMode, isDryRun, dupField, dupMeta, transforms, filters, startTime, retryRows);
			} else {
				handleCompletedImport(totals, allLogs, isDryRun);
			}

		}).fail(function () {
			queueContext = null;
			fileQueue = [];
			updateQueueNotice();
			$('#smie-progress-detail').text('Network error. Import halted.');
		});
	}

	function handleCompletedImport(totals, allLogs, isDryRun) {
		if (queueContext) {
			queueContext.processedCount += 1;
			queueContext.totals.inserted += totals.inserted;
			queueContext.totals.updated  += totals.updated;
			queueContext.totals.skipped  += totals.skipped;
			queueContext.totals.errors   += totals.errors;
			queueContext.logs.push('=== ' + currentSourceName + ' ===');
			queueContext.logs = queueContext.logs.concat(allLogs);
			lastAllLogs = queueContext.logs.slice();

			if (fileQueue.length > 0) {
				updateQueueNotice();
				$('#smie-progress-fill').css('width', '100%');
				$('#smie-progress-pct').text('100%');
				$('#smie-progress-title').text('Preparing next queued file\u2026');
				$('#smie-progress-detail').text('Completed ' + queueContext.processedCount + ' of ' + queueContext.totalCount + ' files. Loading the next file\u2026');
				uploadFile(fileQueue.shift(), true);
				return;
			}

			var queueTotals = cloneData(queueContext.totals);
			var queueLogs   = queueContext.logs.slice();
			queueContext = null;
			lastHistoryId = '';
			updateQueueNotice();
			showResults(queueTotals, queueLogs, isDryRun, false, true);
			return;
		}

		lastAllLogs = allLogs;
		showResults(totals, allLogs, isDryRun, !!lastHistoryId, false);
	}

	/* ================================================================
	 * Step 6 — Results
	 * ================================================================ */

	function showResults(totals, allLogs, isDryRun, allowRollback, isQueueSummary) {
		/* Complete progress */
		$('#smie-progress-fill').css('width', '100%');
		$('#smie-progress-pct').text('100%');
		$('#smie-progress-title').text(isDryRun ? 'Dry Run Complete' : (isQueueSummary ? 'Queued Import Complete' : 'Complete'));
		$('#smie-progress-detail').text(isDryRun ? 'No changes were made.' : 'Import finished successfully.');

		/* Summary badges */
		var badgeHtml =
			'<span class="smie-badge smie-badge-inserted"><span class="dashicons dashicons-plus-alt"></span> ' + totals.inserted + ' Inserted</span>' +
			'<span class="smie-badge smie-badge-updated"><span class="dashicons dashicons-update"></span> ' + totals.updated + ' Updated</span>' +
			'<span class="smie-badge smie-badge-skipped"><span class="dashicons dashicons-minus"></span> ' + totals.skipped + ' Skipped</span>' +
			'<span class="smie-badge smie-badge-errors"><span class="dashicons dashicons-warning"></span> ' + totals.errors + ' Errors</span>';
		if (isDryRun) {
			badgeHtml = '<span class="smie-badge smie-badge-dry">[DRY RUN]</span> ' + badgeHtml;
		}
		if (isQueueSummary) {
			badgeHtml = '<p class="description">Queued files were processed sequentially with the same import settings.</p>' + badgeHtml;
		}
		$('#smie-results-summary').html(badgeHtml);

		/* Full log */
		if (allLogs.length) {
			var html = '<div class="smie-log">';
			$.each(allLogs, function (idx, line) {
				var cls = 'smie-log-ok';
				if (line.indexOf('Error') !== -1) {
					cls = 'smie-log-error';
				} else if (line.indexOf('Skipped') !== -1) {
					cls = 'smie-log-skip';
				}
				html += '<div class="' + cls + '">' + esc(line) + '</div>';
			});
			html += '</div>';
			$('#smie-results-log').html(html);
		}

		/* Show download log button (#12) */
		$('#smie-btn-download-log').toggle(allLogs.length > 0);

		/* Show rollback button only for non-dry-run imports with a history ID */
		$('#smie-btn-rollback').toggle(!isDryRun && allowRollback);

		/* Show retry button when there are failed rows */
		$('#smie-btn-retry-failed').toggle(!isDryRun && failedRowIndices.length > 0);

		$('#smie-step-results').slideDown(200);
		scrollTo('#smie-step-results');
	}

	/* Download log as CSV (#12) */
	$('#smie-btn-download-log').on('click', function () {
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
	$('#smie-btn-rollback').on('click', function () {
		if (!lastHistoryId) {
			return;
		}
		if (!window.confirm('This will move all imported posts to trash. Continue?')) {
			return;
		}
		showOverlay('Rolling back\u2026');
		$.post(smieImporter.ajax_url, {
			action:     'smie_rollback',
			nonce:      smieImporter.nonce,
			history_id: lastHistoryId
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Rollback failed.');
				return;
			}
			window.alert(res.data.message);
			$('#smie-btn-rollback').hide();
		}).fail(function () {
			hideOverlay();
			window.alert('Rollback request failed.');
		});
	});

	/* Retry Failed Rows */
	$('#smie-btn-retry-failed').on('click', function () {
		if (!failedRowIndices.length) {
			return;
		}

		/* Hide results and show progress */
		$('#smie-step-results').hide();
		$('#smie-step-progress').slideDown(200);
		$('#smie-progress-title').text('Retrying failed rows\u2026');
		$('#smie-progress-fill').css('width', '0%');
		$('#smie-progress-pct').text('0%');
		$('#smie-progress-detail').text('');
		$('#smie-progress-bar').attr('aria-valuenow', 0);
		$('#smie-live-log').html('');

		var totals      = { inserted: 0, updated: 0, skipped: 0, errors: 0 };
		var allLogs     = [];
		var retryIndices = failedRowIndices.slice();
		failedRowIndices = [];
		lastAllLogs      = [];
		var startTime    = Date.now();

		processBatch(0, lastMapping, totals, allLogs, lastImportMode, lastDryRun, lastDupField, lastDupMeta, lastTransforms, lastFilters, startTime, retryIndices);
	});

	/* Start New Import */
	$('#smie-btn-new').on('click', function () {
		resetAll();
		scrollTo('#smie-step-entity');
	});

	/* ================================================================
	 * Validate CSV (#7)
	 * ================================================================ */

	$('#smie-btn-validate').on('click', function () {
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

		pendingImportMode = getSuggestedImportMode(mapping);

		showOverlay('Validating\u2026');
		$.post(smieImporter.ajax_url, {
			action:    'smie_validate_csv',
			nonce:     smieImporter.nonce,
			token:     csvToken,
			post_type: $('#smie-post-type').val(),
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
				html += '<div class="smie-validation-errors"><h4>Errors (' + d.errors.length + ')</h4>';
				$.each(d.errors, function (i, e) {
					html += '<div class="smie-log-error">' + esc(e) + '</div>';
				});
				html += '</div>';
			}
			if (d.warnings.length) {
				html += '<div class="smie-validation-warnings"><h4>Warnings (' + d.warnings.length + ')</h4>';
				$.each(d.warnings, function (i, w) {
					html += '<div class="smie-log-skip">' + esc(w) + '</div>';
				});
				html += '</div>';
			}
			if (!d.errors.length && !d.warnings.length) {
				html += '<p class="smie-validation-ok"><span class="dashicons dashicons-yes-alt"></span> No issues found.</p>';
			}
			$('#smie-validation-results').html(html);
			$('#smie-step-validation').slideDown(200);
			scrollTo('#smie-step-validation');
		}).fail(function () {
			hideOverlay();
			window.alert('Validation request failed.');
		});
	});

	/* Proceed with Import after validation */
	$('#smie-btn-proceed-import').on('click', function () {
		$('#smie-step-validation').slideUp(200);
		startImportFromCurrentState(pendingImportMode, false);
	});

	/* Cancel from validation — hide validation and stay on mapping */
	$('#smie-btn-cancel-import').on('click', function () {
		$('#smie-step-validation').slideUp(200);
		scrollTo('#smie-step-mapping');
	});

	/* ================================================================
	 * Duplicate check toggle (#3)
	 * ================================================================ */

	$('#smie-dup-check').on('change', function () {
		$('#smie-dup-options').toggle($(this).is(':checked'));
	});

	$('#smie-dup-field').on('change', function () {
		$('#smie-dup-meta-wrap').toggle($(this).val() === 'meta_key');
	});

	/* ================================================================
	 * Conditional Row Filters
	 * ================================================================ */

	$('#smie-add-filter').on('click', function () {
		var $rules = $('#smie-filter-rules');
		var idx    = $rules.children().length;
		var html   = '<div class="smie-filter-rule" data-idx="' + idx + '">';
		html      += '<select class="smie-filter-col">';
		$.each(csvHeaders, function (i, h) {
			html += '<option value="' + i + '">' + esc(h) + '</option>';
		});
		html += '</select>';
		html += '<select class="smie-filter-op">';
		html += '<option value="equals">equals</option>';
		html += '<option value="not_equals">not equals</option>';
		html += '<option value="contains">contains</option>';
		html += '<option value="not_contains">not contains</option>';
		html += '<option value="gt">greater than</option>';
		html += '<option value="lt">less than</option>';
		html += '<option value="empty">is empty</option>';
		html += '<option value="not_empty">is not empty</option>';
		html += '</select>';
		html += '<input type="text" class="smie-filter-value small-text" placeholder="value">';
		html += '<button type="button" class="button button-small smie-remove-filter" aria-label="Remove filter rule"><span class="dashicons dashicons-no-alt"></span></button>';
		html += '</div>';
		$rules.append(html);
	});

	$(document).on('click', '.smie-remove-filter', function () {
		$(this).closest('.smie-filter-rule').remove();
	});

	$(document).on('change', '.smie-filter-op', function () {
		var op = $(this).val();
		$(this).closest('.smie-filter-rule').find('.smie-filter-value').toggle(op !== 'empty' && op !== 'not_empty');
	});

	/**
	 * Build the filter rules payload.
	 */
	function buildFilterPayload() {
		var filters = [];
		$('#smie-filter-rules .smie-filter-rule').each(function () {
			filters.push({
				col:   parseInt($(this).find('.smie-filter-col').val(), 10),
				op:    $(this).find('.smie-filter-op').val(),
				value: $(this).find('.smie-filter-value').val() || ''
			});
		});
		return filters;
	}

	/* ================================================================
	 * Mapping Profiles (#6)
	 * ================================================================ */

	$('#smie-save-profile').on('click', function () {
		var name = window.prompt('Enter a name for this mapping profile:');
		if (!name) {
			return;
		}
		var mapping = buildMappingPayload();
		showOverlay('Saving profile\u2026');
		$.post(smieImporter.ajax_url, {
			action:       'smie_save_profile',
			nonce:        smieImporter.nonce,
			profile_name: name,
			post_type:    $('#smie-post-type').val(),
			mapping:      JSON.stringify(mapping)
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Save failed.');
				return;
			}
			updateProfileDropdown(res.data.profiles, res.data.profile_id || '');
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Save profile request failed.');
		});
	});

	$('#smie-delete-profile').on('click', function () {
		var id = $('#smie-profile-select').val();
		if (!id) {
			window.alert('Please select a profile to delete.');
			return;
		}
		if (!window.confirm('Delete this mapping profile?')) {
			return;
		}
		$.post(smieImporter.ajax_url, {
			action:     'smie_delete_profile',
			nonce:      smieImporter.nonce,
			profile_id: id
		}, function (res) {
			if (res.success) {
				updateProfileDropdown(res.data.profiles);
			}
		});
	});

	$('#smie-profile-select').on('change', function () {
		var id = $(this).val();
		$('#smie-delete-profile').toggle(!!id);
		if (!id) {
			return;
		}
		var profiles = smieImporter.profiles || {};
		if (profiles[id] && profiles[id].mapping) {
			applyProfileMapping(profiles[id].mapping);
		}
	});

	function updateProfileDropdown(profiles, selectedId) {
		smieImporter.profiles = profiles;
		var $sel = $('#smie-profile-select').empty().append('<option value="">— select profile —</option>');
		var pt   = $('#smie-post-type').val();
		$.each(profiles, function (id, p) {
			if (!pt || p.post_type === pt) {
				$sel.append('<option value="' + esc(id) + '">' + esc(p.name) + '</option>');
			}
		});
		if (selectedId) {
			$sel.val(selectedId);
		}
		$('#smie-delete-profile').toggle(!!$sel.val());
	}

	function applyProfileMapping(profileMapping) {
		$('#smie-mapping-table tbody tr').each(function () {
			var field = $(this).data('field');
			var pm    = profileMapping[field];
			var $cb   = $(this).find('.smie-field-check');
			var $sel  = $(this).find('.smie-col-select');

			if (pm) {
				if (pm.source === 'custom') {
					$sel.val('__custom__');
					$(this).find('.smie-custom-value').val(pm.value || '').show();
					$(this).find('.smie-merge-template').hide();
				} else if (pm.source === 'merge') {
					$sel.val('__merge__');
					$(this).find('.smie-merge-template').val(pm.template || '').show();
					$(this).find('.smie-custom-value').hide();
				} else {
					$sel.val(String(pm.col));
					$(this).find('.smie-custom-value').hide();
					$(this).find('.smie-merge-template').hide();
				}
				$cb.prop('checked', true);
			} else {
				$sel.val('-1');
				$cb.prop('checked', false);
				$(this).find('.smie-custom-value').hide();
				$(this).find('.smie-merge-template').hide();
			}
		});
		updateMappingCount();
	}

	/* ================================================================
	 * Import History (#2)
	 * ================================================================ */

	function loadHistory() {
		$('#smie-history-body').html('<tr><td colspan="6">Loading\u2026</td></tr>');
		$.post(smieImporter.ajax_url, {
			action: 'smie_get_history',
			nonce:  smieImporter.nonce
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
					var statusClass = h.rolled_back ? 'smie-history-rolled-back' : '';
					html += '<tr class="' + statusClass + '">' +
						'<td>' + esc(h.date) + '</td>' +
						'<td>' + esc(h.post_type) + '</td>' +
						'<td>' + esc(h.mode) + '</td>' +
						'<td>' + h.inserted + ' / ' + h.updated + ' / ' + (h.skipped || 0) + ' / ' + (h.errors || 0) + '</td>' +
						'<td>' + (h.post_ids ? h.post_ids.length : 0) + ' posts</td>' +
						'<td>' + (h.rolled_back ? '<em>Rolled back</em>' : '<button type="button" class="button button-small smie-rollback-history" data-id="' + esc(h.id) + '">Rollback</button>') + '</td>' +
						'</tr>';
				});
			}
			$('#smie-history-body').html(html);
		});
	}

	$(document).on('click', '.smie-rollback-history', function () {
		var hid = $(this).data('id');
		if (!window.confirm('Move all posts from this import to trash?')) {
			return;
		}
		var $btn = $(this);
		showOverlay('Rolling back\u2026');
		$.post(smieImporter.ajax_url, {
			action:     'smie_rollback',
			nonce:      smieImporter.nonce,
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
		var schedules = smieImporter.schedules || {};
		var html = '';
		$.each(schedules, function (id, s) {
			var statusText = esc(s.last_status || 'N/A');
			var statusAttr = '';
			if (s.last_error) {
				statusAttr = ' title="' + $('<span>').text(s.last_error).html() + '" class="smie-schedule-error"';
			}
			html += '<tr>' +
				'<td>' + esc(s.name) + '</td>' +
				'<td>' + esc(s.post_type) + '</td>' +
				'<td>' + esc(s.frequency) + '</td>' +
				'<td>' + esc(s.email || '—') + '</td>' +
				'<td>' + esc(s.last_run || 'Never') + '</td>' +
				'<td' + statusAttr + '>' + statusText + '</td>' +
				'<td><button type="button" class="button button-small smie-delete-schedule" data-id="' + esc(id) + '">Delete</button></td>' +
				'</tr>';
		});
		if (!html) {
			html = '<tr><td colspan="7">No scheduled imports.</td></tr>';
		}
		$('#smie-schedule-body').html(html);

		/* Populate profile dropdown in schedule form */
		var $psel = $('#smie-schedule-profile').empty().append('<option value="">— auto-match —</option>');
		var profiles = smieImporter.profiles || {};
		$.each(profiles, function (id, p) {
			$psel.append('<option value="' + esc(id) + '">' + esc(p.name) + '</option>');
		});
	}

	$('#smie-btn-add-schedule').on('click', function () {
		var name  = $('#smie-schedule-name').val().trim();
		var url   = $('#smie-schedule-url').val().trim();
		var freq  = $('#smie-schedule-freq').val();
		var prof  = $('#smie-schedule-profile').val();
		var email = $('#smie-schedule-email').val().trim();

		if (!name || !url) {
			window.alert('Please enter a name and CSV URL.');
			return;
		}

		showOverlay('Creating schedule\u2026');
		$.post(smieImporter.ajax_url, {
			action:        'smie_add_schedule',
			nonce:         smieImporter.nonce,
			schedule_name: name,
			csv_url:       url,
			post_type:     $('#smie-post-type').val(),
			frequency:     freq,
			profile_id:    prof,
			email:         email
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Failed to create schedule.');
				return;
			}
			smieImporter.schedules = res.data.schedules;
			loadSchedules();
			$('#smie-schedule-name, #smie-schedule-url, #smie-schedule-email').val('');
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Schedule request failed.');
		});
	});

	$(document).on('click', '.smie-delete-schedule', function () {
		var sid = $(this).data('id');
		if (!window.confirm('Delete this schedule?')) {
			return;
		}
		$.post(smieImporter.ajax_url, {
			action:      'smie_delete_schedule',
			nonce:       smieImporter.nonce,
			schedule_id: sid
		}, function (res) {
			if (res.success) {
				smieImporter.schedules = res.data.schedules;
				loadSchedules();
			}
		});
	});

	/* ================================================================
	 * Scheduled Exports
	 * ================================================================ */

	function loadExportSchedules() {
		var schedules = smieImporter.export_schedules || {};
		var html = '';
		$.each(schedules, function (id, s) {
			html += '<tr>' +
				'<td>' + esc(s.name) + '</td>' +
				'<td>' + esc(s.post_type) + '</td>' +
				'<td>' + esc(s.frequency) + '</td>' +
				'<td>' + esc(s.email || '\u2014') + '</td>' +
				'<td>' + esc(s.last_run || 'Never') + '</td>' +
				'<td>' + esc(s.last_status || 'N/A') + '</td>' +
				'<td><button type="button" class="button button-small smie-delete-export-schedule" data-id="' + esc(id) + '">Delete</button></td>' +
				'</tr>';
		});
		if (!html) {
			html = '<tr><td colspan="7">No scheduled exports.</td></tr>';
		}
		$('#smie-export-schedule-body').html(html);
	}

	$('#smie-btn-add-export-schedule').on('click', function () {
		var name  = $('#smie-export-schedule-name').val().trim();
		var freq  = $('#smie-export-schedule-freq').val();
		var email = $('#smie-export-schedule-email').val().trim();

		if (!name) {
			window.alert('Please enter a name for the export schedule.');
			return;
		}

		showOverlay('Creating export schedule\u2026');
		$.post(smieImporter.ajax_url, {
			action:        'smie_add_export_schedule',
			nonce:         smieImporter.nonce,
			schedule_name: name,
			post_type:     $('#smie-post-type').val(),
			frequency:     freq,
			email:         email
		}, function (res) {
			hideOverlay();
			if (!res.success) {
				window.alert(res.data || 'Failed to create export schedule.');
				return;
			}
			smieImporter.export_schedules = res.data.export_schedules;
			loadExportSchedules();
			$('#smie-export-schedule-name, #smie-export-schedule-email').val('');
			window.alert(res.data.message);
		}).fail(function () {
			hideOverlay();
			window.alert('Export schedule request failed.');
		});
	});

	$(document).on('click', '.smie-delete-export-schedule', function () {
		var sid = $(this).data('id');
		if (!window.confirm('Delete this export schedule?')) {
			return;
		}
		$.post(smieImporter.ajax_url, {
			action:      'smie_delete_export_schedule',
			nonce:       smieImporter.nonce,
			schedule_id: sid
		}, function (res) {
			if (res.success) {
				smieImporter.export_schedules = res.data.export_schedules;
				loadExportSchedules();
			}
		});
	});

	/* ================================================================
	 * Helpers — History & Schedule step resets
	 * ================================================================ */

	function resetFrom(stepId) {
		var found = false;
		$('.smie-card').each(function () {
			if (found) {
				$(this).hide();
			}
			if (this.id === stepId) {
				found = true;
			}
		});
		if (stepId === 'smie-step-source') {
			$('#smie-step-export').hide();
			$('#smie-step-source').hide();
			$('#smie-step-mapping').hide();
			$('#smie-step-progress').hide();
			$('#smie-step-results').hide();
			$('#smie-step-validation').hide();
			$('#smie-step-history').hide();
			$('#smie-step-schedule').hide();
			$('#smie-step-export-schedule').hide();
		}
	}

	function resetAll() {
		$('#smie-post-type').val('');
		$('#smie-step-actions').hide();
		resetFrom('smie-step-source');
		resetSource();
		resetProgress();
	}

	function resetSource() {
		$('#smie-csv-file').val('');
		$('#smie-csv-url').val('');
		$('#smie-file-info').hide().empty();
		$('#smie-preview').hide().empty();
		$('#smie-file-queue').hide().empty();
		csvHeaders  = [];
		csvToken    = '';
		csvRowCount = 0;
		currentSourceName = '';
		pendingImportMode = 'insert';
		queueContext = null;
		fileQueue   = [];
	}

	function resetProgress() {
		$('#smie-progress-fill').css('width', '0');
		$('#smie-progress-pct').text('0%');
		$('#smie-progress-title').text('Importing\u2026');
		$('#smie-progress-detail').text('Preparing your import\u2026');
		$('#smie-live-log').empty();
		$('#smie-results-summary').empty();
		$('#smie-results-log').empty();
		lastHistoryId = '';
		lastAllLogs   = [];
	}

	function showOverlay(text) {
		$('#smie-overlay-text').text(text);
		$('#smie-overlay').show();
	}

	function hideOverlay() {
		$('#smie-overlay').hide();
	}

	/* Close overlay on ESC key */
	$(document).on('keydown', function (e) {
		if (27 === e.keyCode && $('#smie-overlay').is(':visible')) {
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
