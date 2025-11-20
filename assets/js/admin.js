/**
 * Reseller Panel Admin Scripts
 */

(function($) {
	'use strict';

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
				} else {
					var errorMessage = response.data.message || 'Connection failed';
					console.log('Debug array:', response.data.debug);
					$message.addClass('error').text('✗ ' + errorMessage).show();
					$btn.removeClass('button-success').addClass('button-secondary');
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

	// Form validation
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

	$('.reseller-panel-form-container form').on('submit', function() {
		isSubmitting = true;
		hasChanges = false;
	});

	$(window).on('beforeunload', function() {
		if (hasChanges && !isSubmitting) {
			return 'You have unsaved changes';
		}
	});

})(jQuery);