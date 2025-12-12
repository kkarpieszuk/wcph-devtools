jQuery(document).ready(function($) {
	$('#wc-ph-import-form').on('submit', function(e) {
		e.preventDefault();

		var formData = new FormData();
		var fileInput = $('#import_file')[0];
		var targetProduct = $('#target_product').val();

		if (!targetProduct) {
			alert('Please select a target product');
			return;
		}

		if (!fileInput.files[0]) {
			alert('Please select a file to import');
			return;
		}

		var file = fileInput.files[0];
		var reader = new FileReader();

		reader.onload = function(e) {
			formData.append('action', 'wc_ph_import_data');
			formData.append('nonce', $('#wc_ph_import_nonce').val());
			formData.append('target_product', targetProduct);
			formData.append('import_data', e.target.result);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$('#import-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
					} else {
						$('#import-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
					}
				},
				error: function() {
					$('#import-result').html('<div class="notice notice-error"><p>An error occurred during import</p></div>').show();
				}
			});
		};

		reader.readAsText(file);
	});
});

