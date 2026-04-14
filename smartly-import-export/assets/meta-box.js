(function() {
	'use strict';

	function downloadSinglePostExport(button) {
		var postId = button.getAttribute('data-post-id');
		var nonceField = document.getElementById('smie_meta_box_nonce');
		var data;

		if (!postId || !nonceField || !window.ajaxurl) {
			return;
		}

		data = new FormData();
		data.append('action', 'smie_export_single_post');
		data.append('smie_meta_box_nonce', nonceField.value);
		data.append('post_id', postId);

		fetch(window.ajaxurl, {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		})
			.then(function(response) {
				return response.json();
			})
			.then(function(result) {
				var raw;
				var bytes;
				var blob;
				var url;
				var link;
				var index;

				if (!result.success) {
					window.alert(result.data || 'Export failed.');
					return;
				}

				raw = atob(result.data.csv);
				bytes = new Uint8Array(raw.length);
				for (index = 0; index < raw.length; index++) {
					bytes[index] = raw.charCodeAt(index);
				}

				blob = new Blob([bytes], { type: 'text/csv;charset=utf-8' });
				url = URL.createObjectURL(blob);
				link = document.createElement('a');

				link.href = url;
				link.download = result.data.filename;
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				URL.revokeObjectURL(url);
			})
			.catch(function() {
				window.alert('Export request failed.');
			});
	}

	function initSingleExport() {
		var button = document.getElementById('smie-export-single');

		if (!button) {
			return;
		}

		button.addEventListener('click', function() {
			downloadSinglePostExport(button);
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initSingleExport);
	} else {
		initSingleExport();
	}
})();