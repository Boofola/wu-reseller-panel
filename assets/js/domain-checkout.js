/**
 * Domain Checkout JavaScript
 *
 * Handles domain search, availability checking, and pricing display.
 *
 * @package Reseller_Panel
 */

(function($) {
	'use strict';

	var ResellerPanelCheckout = {
		
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind DOM events
		 */
		bindEvents: function() {
			// Toggle domain registration fields
			$('#register_domain').on('change', this.toggleDomainFields);

			// Check domain availability
			$('#check_domain_availability').on('click', this.checkAvailability);

			// Domain name field Enter key
			$('#domain_name').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					ResellerPanelCheckout.checkAvailability();
				}
			});

			// Auto-populate registrant info from customer data
			$('#use_customer_info').on('click', this.populateRegistrantInfo);
		},

		/**
		 * Toggle domain registration fields
		 */
		toggleDomainFields: function() {
			var $checkbox = $(this);
			var $fields = $('.domain-registration-fields');

			if ($checkbox.is(':checked')) {
				$fields.slideDown();
				// Make fields required
				$fields.find('[required]').prop('disabled', false);
			} else {
				$fields.slideUp();
				// Remove required state
				$fields.find('[required]').prop('disabled', true);
				// Clear results
				$('#domain_availability_result').empty();
				$('.domain-pricing-group').hide();
			}
		},

		/**
		 * Check domain availability
		 */
		checkAvailability: function() {
			var domainName = $('#domain_name').val().trim();
			var $resultDiv = $('#domain_availability_result');
			var $button = $('#check_domain_availability');

			// Clear previous results
			$resultDiv.empty();

			// Validate domain name
			if (!domainName) {
				$resultDiv.html(
					'<div class="alert alert-warning">' +
					ResellerPanelCheckout.escapeHtml(window.resellerPanelCheckout.errorText) + ': ' +
					'Domain name is required' +
					'</div>'
				);
				return;
			}

			// Show loading state
			$button.prop('disabled', true).text(window.resellerPanelCheckout.searchingText);
			$resultDiv.html('<div class="spinner"></div>');

			// Make AJAX request
			$.ajax({
				url: window.resellerPanelCheckout.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'reseller_panel_search_domain',
					nonce: window.resellerPanelCheckout.searchNonce,
					domain: domainName
				},
				success: function(response) {
					if (response.success) {
						ResellerPanelCheckout.displayAvailability(response.data);
					} else {
						$resultDiv.html(
							'<div class="alert alert-danger">' +
							ResellerPanelCheckout.escapeHtml(response.data.message || 'Error checking availability') +
							'</div>'
						);
					}
				},
				error: function() {
					$resultDiv.html(
						'<div class="alert alert-danger">' +
						ResellerPanelCheckout.escapeHtml(window.resellerPanelCheckout.errorText) +
						'</div>'
					);
				},
				complete: function() {
					$button.prop('disabled', false).text('Check Availability');
				}
			});
		},

		/**
		 * Display availability results
		 */
		displayAvailability: function(data) {
			var $resultDiv = $('#domain_availability_result');
			var isAvailable = data.available === true || data.available === 1;

			if (isAvailable) {
				var $alert = $('<div class="alert alert-success"></div>');
				$alert.append($('<strong></strong>').text(window.resellerPanelCheckout.availableText + ' - '));
				$alert.append(document.createTextNode(ResellerPanelCheckout.escapeHtml($('#domain_name').val()) + ' is available for registration!'));
				$resultDiv.html('').append($alert);

				// Get and display pricing
				ResellerPanelCheckout.getDomainPricing($('#domain_name').val());
			} else {
				var $alert = $('<div class="alert alert-danger"></div>');
				$alert.append($('<strong></strong>').text(window.resellerPanelCheckout.unavailableText + ' - '));
				$alert.append(document.createTextNode(ResellerPanelCheckout.escapeHtml($('#domain_name').val()) + ' is already registered.'));
				$resultDiv.html('').append($alert);

				// Hide pricing
				$('.domain-pricing-group').hide();
			}
		},

		/**
		 * Get domain pricing
		 */
		getDomainPricing: function(domainName) {
			var $pricingDiv = $('#domain_pricing_display');
			var $pricingGroup = $('.domain-pricing-group');

			$pricingDiv.html('<div class="spinner"></div>');
			$pricingGroup.show();

			$.ajax({
				url: window.resellerPanelCheckout.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'reseller_panel_get_domain_price',
					nonce: window.resellerPanelCheckout.searchNonce,
					domain: domainName
				},
				success: function(response) {
					if (response.success) {
						ResellerPanelCheckout.displayPricing(response.data);
					} else {
						$pricingDiv.html(
							'<div class="alert alert-warning">' +
							'Pricing information not available' +
							'</div>'
						);
					}
				},
				error: function() {
					$pricingDiv.html(
						'<div class="alert alert-warning">' +
						'Error loading pricing' +
						'</div>'
					);
				}
			});
		},

		/**
		 * Display pricing information
		 */
		displayPricing: function(data) {
			var $pricingDiv = $('#domain_pricing_display');
			var html = '<div class="domain-pricing">';

			html += '<h5>Domain Pricing</h5>';
			html += '<table class="pricing-table">';

			if (data.registration_price) {
				html += '<tr>';
				html += '<td>Registration (1 year):</td>';
				html += '<td><strong>' + ResellerPanelCheckout.escapeHtml(data.registration_price) + '</strong></td>';
				html += '</tr>';
			}

			if (data.renewal_price) {
				html += '<tr>';
				html += '<td>Renewal:</td>';
				html += '<td>' + ResellerPanelCheckout.escapeHtml(data.renewal_price) + '</td>';
				html += '</tr>';
			}

			if (data.transfer_price) {
				html += '<tr>';
				html += '<td>Transfer:</td>';
				html += '<td>' + ResellerPanelCheckout.escapeHtml(data.transfer_price) + '</td>';
				html += '</tr>';
			}

			html += '</table>';
			html += '</div>';

			$pricingDiv.html(html);
		},

		/**
		 * Populate registrant info from customer data
		 */
		populateRegistrantInfo: function(e) {
			e.preventDefault();
			
			// This would be populated from the localized data
			if (window.resellerPanelCheckout.customerData) {
				var data = window.resellerPanelCheckout.customerData;
				
				$('#registrant_first_name').val(data.first_name || '');
				$('#registrant_last_name').val(data.last_name || '');
				$('#registrant_email').val(data.email || '');
				$('#registrant_phone').val(data.phone || '');
				$('#registrant_address').val(data.address || '');
				$('#registrant_city').val(data.city || '');
				$('#registrant_state').val(data.state || '');
				$('#registrant_zip').val(data.zip || '');
				$('#registrant_country').val(data.country || '');
			}
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		ResellerPanelCheckout.init();
	});

})(jQuery);
