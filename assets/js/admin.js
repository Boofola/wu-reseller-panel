/**
 * Reseller Panel Admin Scripts
 */

(function($) {
	'use strict';

	// Test connection button handler
	$('.reseller-panel-test-connection').on('click', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var originalText = $btn.text();
		var provider = $btn.data('provider');

		$btn.prop('disabled', true).text('Testing...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'reseller_panel_test_connection',
				provider: provider,
				_wpnonce: $('[name="reseller_panel_provider_nonce"]').val()
			},
			success: function(response) {
				if (response.success) {
					alert('Connection successful!');
					$btn.removeClass('button-secondary').addClass('button-success');
				} else {
					alert('Connection failed: ' + response.data);
					$btn.removeClass('button-success').addClass('button-secondary');
				}
			},
			error: function() {
				alert('Error testing connection');
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

	$('.reseller-panel-form-group input, .reseller-panel-form-group select, .reseller-panel-form-group textarea').on('change', function() {
		hasChanges = true;
	});

	$('.reseller-panel-form-container form').on('submit', function() {
		hasChanges = false;
	});

	$(window).on('beforeunload', function() {
		if (hasChanges) {
			return 'You have unsaved changes';
		}
	});

})(jQuery);