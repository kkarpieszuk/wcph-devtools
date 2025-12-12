jQuery(document).ready(function($) {
	let allProductsPriceDetails = [];

	$('#wc-ph-export-all-button').on('click', function(e) {
		e.preventDefault();

		var button = $(this);
		button.prop('disabled', true).text('Loading...');
		allProductsPriceDetails = []; // Reset array

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wc_ph_get_all_products_ids',
				nonce: $('#wc_ph_export_nonce').val()
			},
			success: function(response) {
				if (response.success) {
					const allProductsIds = response.data.product_ids;
					console.log('Total products:', allProductsIds.length);

					// Start fetching product details
					fetchProductsPriceDetails(allProductsIds, 0);
				} else {
					button.prop('disabled', false).text('Start Exporting');
					$('#export-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
				}
			},
			error: function() {
				button.prop('disabled', false).text('Start Exporting');
				$('#export-result').html('<div class="notice notice-error"><p>An error occurred</p></div>').show();
			}
		});
	});

	function fetchProductsPriceDetails(allProductsIds, currentIndex) {
		var batchSize = 10;
		var batch = allProductsIds.slice(currentIndex, currentIndex + batchSize);
		var total = allProductsIds.length;
		var processed = currentIndex;
		var progress = Math.round((processed / total) * 100);

		// Update progress bar
		updateProgressBar(progress, processed, total);

		if (batch.length === 0) {
			// All products processed - export to CSV
			$('#export-progress').html('<div style="margin-top: 20px;"><p>Exporting to CSV...</p></div>');
			exportToCSV(allProductsPriceDetails);
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wc_ph_get_products_price_details',
				nonce: $('#wc_ph_export_nonce').val(),
				product_ids: batch
			},
			success: function(response) {
				if (response.success) {
					// Add products to the array (don't replace)
					allProductsPriceDetails = allProductsPriceDetails.concat(response.data.products);

					// Continue with next batch
					fetchProductsPriceDetails(allProductsIds, currentIndex + batchSize);
				} else {
					$('#wc-ph-export-all-button').prop('disabled', false).text('Start Exporting');
					$('#export-progress').hide();
					$('#export-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
				}
			},
			error: function() {
				$('#wc-ph-export-all-button').prop('disabled', false).text('Start Exporting');
				$('#export-progress').hide();
				$('#export-result').html('<div class="notice notice-error"><p>An error occurred while fetching product details</p></div>').show();
			}
		});
	}

	function updateProgressBar(progress, processed, total) {
		var progressHtml = '<div id="export-progress" style="margin-top: 20px;">' +
			'<div style="background: #f0f0f0; border-radius: 4px; height: 20px; overflow: hidden;">' +
			'<div style="background: #2271b1; height: 100%; width: ' + progress + '%; transition: width 0.3s;"></div>' +
			'</div>' +
			'<p style="margin-top: 10px;">Processing: ' + processed + ' / ' + total + ' (' + progress + '%)</p>' +
			'</div>';

		if ($('#export-progress').length) {
			$('#export-progress').replaceWith(progressHtml);
		} else {
			$('#export-result').html(progressHtml).show();
		}
	}

	function exportToCSV(productsData) {
		// Create a form and submit it to trigger CSV download
		var form = $('<form>', {
			'method': 'POST',
			'action': ajaxurl,
			'target': '_blank'
		});

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'action',
			'value': 'wc_ph_export_csv'
		}));

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'nonce',
			'value': $('#wc_ph_export_nonce').val()
		}));

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'products_data',
			'value': JSON.stringify(productsData)
		}));

		$('body').append(form);
		form.submit();
		form.remove();

		// Update UI after a short delay
		setTimeout(function() {
			$('#wc-ph-export-all-button').prop('disabled', false).text('Start Exporting');
			$('#export-progress').hide();
			$('#export-result').html('<div class="notice notice-success"><p>Successfully exported ' + productsData.length + ' products to CSV. Download started automatically.</p></div>').show();
		}, 500);
	}
});

