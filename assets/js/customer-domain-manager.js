/**
 * Customer Domain Manager JavaScript
 *
 * Handles customer portal interactions for domain management.
 *
 * @package Reseller_Panel
 */

(function($) {
	'use strict';

	/**
	 * Manage DNS for a domain
	 */
	window.manageDNS = function(domain) {
		// Switch to DNS tab
		$('.tab-link[href="?tab=dns"]').click();

		// Select the domain
		$('#dns-domain-select').val(domain).trigger('change');
	};

	/**
	 * Renew a domain
	 */
	window.renewDomain = function(domain) {
		if (!confirm(window.resellerPanelCustomer.messages.confirmRenew)) {
			return;
		}

		// Make AJAX request to renew domain
		$.ajax({
			url: window.resellerPanelCustomer.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'reseller_panel_renew_domain',
				nonce: window.resellerPanelCustomer.nonces.renewal,
				domain: domain,
				years: 1
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message || window.resellerPanelCustomer.messages.success);
					location.reload();
				} else {
					alert(response.data.message || window.resellerPanelCustomer.messages.error);
				}
			},
			error: function() {
				alert(window.resellerPanelCustomer.messages.error);
			}
		});
	};

	/**
	 * Transfer a domain
	 */
	window.transferDomain = function(domain) {
		// Switch to transfers tab
		$('.tab-link[href="?tab=transfers"]').click();

		// Fill in domain name
		$('#transfer_out_domain').val(domain);
	};

	/**
	 * Initialize
	 */
	$(document).ready(function() {
		// Auto-renewal toggle
		$('.auto-renew-toggle').on('change', function() {
			var $toggle = $(this);
			var domain = $toggle.data('domain');
			var enabled = $toggle.is(':checked');

			$.ajax({
				url: window.resellerPanelCustomer.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'reseller_panel_toggle_auto_renew',
					nonce: window.resellerPanelCustomer.nonces.renewal,
					domain: domain,
					enabled: enabled ? '1' : '0'
				},
				success: function(response) {
					if (!response.success) {
						alert(response.data.message || window.resellerPanelCustomer.messages.error);
						// Revert toggle
						$toggle.prop('checked', !enabled);
					}
				},
				error: function() {
					alert(window.resellerPanelCustomer.messages.error);
					// Revert toggle
					$toggle.prop('checked', !enabled);
				}
			});
		});

		// DNS domain selector
		$('#dns-domain-select').on('change', function() {
			var domain = $(this).val();
			if (domain) {
				loadDNSRecords(domain);
				$('#dns-records-container').show();
			} else {
				$('#dns-records-container').hide();
			}
		});

		// Get authorization code
		$('#get-auth-code').on('click', function() {
			var domain = $('#transfer_out_domain').val();
			if (!domain) {
				alert('Please select a domain');
				return;
			}

			var $button = $(this);
			$button.prop('disabled', true).text('Loading...');

			$.ajax({
				url: window.resellerPanelCustomer.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'reseller_panel_get_auth_code',
					nonce: window.resellerPanelCustomer.nonces.transfer,
					domain: domain
				},
				success: function(response) {
					if (response.success) {
						var authCode = response.data.auth_code || response.data.code;
						$('#auth-code-display').html(
							'<strong>Authorization Code:</strong> <code>' + authCode + '</code>'
						).show();
					} else {
						alert(response.data.message || window.resellerPanelCustomer.messages.error);
					}
				},
				error: function() {
					alert(window.resellerPanelCustomer.messages.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Get Authorization Code');
				}
			});
		});

		// Transfer in form submission
		$('#transfer-in-form').on('submit', function(e) {
			e.preventDefault();

			if (!confirm(window.resellerPanelCustomer.messages.confirmTransfer)) {
				return;
			}

			var $form = $(this);
			var $button = $form.find('button[type="submit"]');
			var domain = $('#transfer_domain_name').val();
			var authCode = $('#transfer_auth_code').val();

			$button.prop('disabled', true).text('Processing...');

			$.ajax({
				url: window.resellerPanelCustomer.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'reseller_panel_transfer_domain',
					nonce: window.resellerPanelCustomer.nonces.transfer,
					domain: domain,
					auth_code: authCode
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message || window.resellerPanelCustomer.messages.success);
						$form[0].reset();
					} else {
						alert(response.data.message || window.resellerPanelCustomer.messages.error);
					}
				},
				error: function() {
					alert(window.resellerPanelCustomer.messages.error);
				},
				complete: function() {
					$button.prop('disabled', false).text('Initiate Transfer');
				}
			});
		});
	});

	/**
	 * Load DNS records for a domain
	 */
	function loadDNSRecords(domain) {
		var $list = $('#dns-records-list');
		$list.html('<p>' + window.resellerPanelCustomer.messages.loading + '</p>');

		$.ajax({
			url: window.resellerPanelCustomer.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'reseller_panel_get_dns_records',
				nonce: window.resellerPanelCustomer.nonces.dns,
				domain: domain
			},
			success: function(response) {
				if (response.success && response.data.records) {
					displayDNSRecords(response.data.records);
				} else {
					$list.html('<p>No DNS records found or error loading records.</p>');
				}
			},
			error: function() {
				$list.html('<p>' + window.resellerPanelCustomer.messages.error + '</p>');
			}
		});
	}

	/**
	 * Display DNS records
	 */
	function displayDNSRecords(records) {
		var $list = $('#dns-records-list');
		var html = '<table class="dns-records-table">';
		html += '<thead><tr>';
		html += '<th>Type</th>';
		html += '<th>Name</th>';
		html += '<th>Value</th>';
		html += '<th>TTL</th>';
		html += '<th>Actions</th>';
		html += '</tr></thead><tbody>';

		if (records.length === 0) {
			html += '<tr><td colspan="5">No DNS records found</td></tr>';
		} else {
			records.forEach(function(record) {
				html += '<tr>';
				html += '<td>' + record.type + '</td>';
				html += '<td>' + record.name + '</td>';
				html += '<td>' + record.value + '</td>';
				html += '<td>' + record.ttl + '</td>';
				html += '<td><button class="btn btn-sm btn-outline">Edit</button></td>';
				html += '</tr>';
			});
		}

		html += '</tbody></table>';
		$list.html(html);
	}

})(jQuery);
