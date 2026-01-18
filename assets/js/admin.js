/**
 * Reseller Panel Admin Scripts
 */

(function($) {
	'use strict';

	// Wait for DOM ready to prevent layout forced errors
	$(document).ready(function() {
		// Test connection button handler
		$('.reseller-panel-test-connection').on('click', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var $message = $btn.siblings('.reseller-panel-test-message');
		var originalText = $btn.text();
		var provider = $btn.data('provider');
		var nonce = $('[name="_wpnonce"]').val();

		console.log('Test connection clicked for provider:', provider);
		console.log('Nonce value:', nonce);

		$btn.prop('disabled', true).text('Testing...');
		$message.hide().removeClass('success error');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'reseller_panel_test_connection',
				provider: provider,
				_wpnonce: nonce
			},
			success: function(response) {
				console.log('AJAX success response:', response);
				console.log('Debug info:', response.data);
				
				if (response.success) {
					var message = response.data.message || 'Connection successful!';
					$message.addClass('success').text('✓ ' + message).show();
					$btn.removeClass('button-secondary').addClass('button-success');
					
					// Hide error details section
					$('#reseller-panel-error-details').hide();
					
					// Enable import button for this provider
					var $importBtn = $('[data-provider="' + provider + '"].reseller-panel-import-domains');
					if ($importBtn.length) {
						$importBtn.prop('disabled', false);
						console.log('Import button enabled for provider:', provider);
					}
				} else {
					var errorMessage = response.data.message || 'Connection failed';
					console.log('Debug array:', response.data.debug);
					$message.addClass('error').text('✗ ' + errorMessage).show();
					$btn.removeClass('button-success').addClass('button-secondary');
					
					// Show detailed error information
					var errorDetails = '';
					if (response.data.message) {
						errorDetails += 'Error Message: ' + response.data.message + '\n\n';
					}
					if (response.data.debug && Array.isArray(response.data.debug)) {
						errorDetails += 'Debug Information:\n';
						response.data.debug.forEach(function(item, index) {
							errorDetails += (index + 1) + '. ' + item + '\n';
						});
					}
					if (errorDetails) {
						$('#reseller-panel-error-content').text(errorDetails);
						$('#reseller-panel-error-details').show();
					}
					
					// Disable import button if test fails
					var $importBtn = $('[data-provider="' + provider + '"].reseller-panel-import-domains');
					if ($importBtn.length) {
						$importBtn.prop('disabled', true);
					}
				}
			},
			error: function(xhr, status, error) {
				console.log('AJAX error:', xhr, status, error);
				console.log('Response text:', xhr.responseText);
				try {
					var errorResponse = JSON.parse(xhr.responseText);
					console.log('Parsed error response:', errorResponse);
				} catch(e) {
					console.log('Could not parse error response');
				}
				$message.addClass('error').text('✗ Error testing connection: ' + status).show();
			},
			complete: function() {
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});

	// Import domains button handler
	$('.reseller-panel-import-domains').on('click', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var $message = $btn.siblings('.reseller-panel-import-message');
		var originalText = $btn.text();
		var provider = $btn.data('provider');
		var nonce = $('[name="_wpnonce"]').val();

		console.log('Import domains clicked for provider:', provider);
		console.log('Nonce value:', nonce);

		$btn.prop('disabled', true).text('Importing...');
		$message.hide().removeClass('success error');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'reseller_panel_import_domains',
				provider: provider,
				_wpnonce: nonce
			},
			success: function(response) {
				console.log('Import AJAX success response:', response);
				console.log('Import response data:', response.data);
				
				if (response.success) {
					var message = response.data.message || 'Domains imported successfully!';
					$message.addClass('success').text('✓ ' + message).show();
					$btn.removeClass('button-secondary').addClass('button-success');
					console.log('Import summary:', response.data.summary);
					console.log('Import details:', response.data.details);
				} else {
					var errorMessage = response.data.message || 'Domain import failed';
					console.log('Import error details:', response.data);
					$message.addClass('error').text('✗ ' + errorMessage).show();
					$btn.removeClass('button-success').addClass('button-secondary');
				}
			},
			error: function(xhr, status, error) {
				console.log('Import AJAX error:', xhr, status, error);
				console.log('Import response text:', xhr.responseText);
				try {
					var errorResponse = JSON.parse(xhr.responseText);
					console.log('Parsed import error response:', errorResponse);
				} catch(e) {
					console.log('Could not parse import error response');
				}
				$message.addClass('error').text('✗ Error importing domains: ' + status).show();
			},
			complete: function() {
				$btn.prop('disabled', false).text(originalText);
			}
		});
	});
	$('.reseller-panel-form-container form').on('submit', function(e) {
		var $form = $(this);
		var hasErrors = false;

		// Check required fields
		$form.find('[required]').each(function() {
			if (!$(this).val()) {
				$(this).addClass('error');
				hasErrors = true;
			} else {
				$(this).removeClass('error');
			}
		});

		if (hasErrors) {
			e.preventDefault();
			alert('Please fill in all required fields');
		}
	});

	// Add visual feedback on form changes
	$('.reseller-panel-form-group input, .reseller-panel-form-group select, .reseller-panel-form-group textarea').on('change', function() {
		$(this).addClass('changed');
	});

	// Show unsaved changes warning
	var hasChanges = false;
	var isSubmitting = false;

	$('.reseller-panel-form-group input, .reseller-panel-form-group select, .reseller-panel-form-group textarea').on('change', function() {
		hasChanges = true;
	});

	// Handle both form submission via submit button and the submit event
	$('.reseller-panel-form-container form').on('submit', function() {
		isSubmitting = true;
		hasChanges = false;
	});

	// Also handle when submit button is clicked directly
	$('.reseller-panel-form-container input[type="submit"], .reseller-panel-form-container button[type="submit"]').on('click', function() {
		isSubmitting = true;
		hasChanges = false;
	});

	$(window).on('beforeunload', function() {
		if (hasChanges && !isSubmitting) {
			return 'You have unsaved changes';
		}
	});
	}); // End document ready

})(jQuery);