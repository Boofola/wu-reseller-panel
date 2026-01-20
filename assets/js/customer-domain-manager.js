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
	 * Show notification message
	 */
	function showNotification(message, type) {
		type = type || 'info';
		var $notification = $('<div class="domain-notification notification-' + type + '">' + 
			'<span class="notification-message">' + escapeHtml(message) + '</span>' + 
			'<button class="notification-close">&times;</button>' + 
			'</div>');
		
		$('.domain-manager-content').prepend($notification);
		
		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$notification.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
		
		// Close button handler
		$notification.find('.notification-close').on('click', function() {
			$notification.fadeOut(function() {
				$(this).remove();
			});
		});
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	/**
	 * Manage DNS for a domain
	 */
	function manageDNS(domain) {
		// Switch to DNS tab
		$('.tab-link[href="?tab=dns"]').click();

		// Select the domain
		$('#dns-domain-select').val(domain).trigger('change');
	}

	/**
	 * Renew a domain
	 */
	function renewDomain(domain) {
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
					showNotification(response.data.message || window.resellerPanelCustomer.messages.success, 'success');
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					showNotification(response.data.message || window.resellerPanelCustomer.messages.error, 'error');
				}
			},
			error: function() {
				showNotification(window.resellerPanelCustomer.messages.error, 'error');
			}
		});
	}

	/**
	 * Transfer a domain
	 */
	function transferDomain(domain) {
		// Switch to transfers tab
		$('.tab-link[href="?tab=transfers"]').click();

		// Fill in domain name
		$('#transfer_out_domain').val(domain);
	}

	/**
	 * Initialize
	 */
	$(document).ready(function() {
		// Attach event listeners to domain action buttons
		$(document).on('click', '.manage-dns-btn', function(e) {
			e.preventDefault();
			var domain = $(this).data('domain');
			if (domain) {
				manageDNS(domain);
			}
		});

		$(document).on('click', '.renew-domain-btn', function(e) {
			e.preventDefault();
			var domain = $(this).data('domain');
			if (domain) {
				renewDomain(domain);
			}
		});

		$(document).on('click', '.transfer-domain-btn', function(e) {
			e.preventDefault();
			var domain = $(this).data('domain');
			if (domain) {
				transferDomain(domain);
			}
		});

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
						showNotification(response.data.message || window.resellerPanelCustomer.messages.error, 'error');
						// Revert toggle
						$toggle.prop('checked', !enabled);
					}
				},
				error: function() {
					showNotification(window.resellerPanelCustomer.messages.error, 'error');
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
				showNotification('Please select a domain', 'error');
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
						$('#auth-code-display')
							.html('<strong>Authorization Code:</strong> <code></code>')
							.find('code')
							.text(authCode)
							.end()
							.show();
					} else {
						showNotification(response.data.message || window.resellerPanelCustomer.messages.error, 'error');
					}
				},
				error: function() {
					showNotification(window.resellerPanelCustomer.messages.error, 'error');
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
						showNotification(response.data.message || window.resellerPanelCustomer.messages.success, 'success');
						$form[0].reset();
					} else {
						showNotification(response.data.message || window.resellerPanelCustomer.messages.error, 'error');
					}
				},
				error: function() {
					showNotification(window.resellerPanelCustomer.messages.error, 'error');
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
	 * Display DNS records with proper escaping
	 */
	function displayDNSRecords(records) {
		var $list = $('#dns-records-list');
		var $table = $('<table class="dns-records-table"></table>');
		var $thead = $('<thead><tr>' + 
			'<th>Type</th>' + 
			'<th>Name</th>' + 
			'<th>Value</th>' + 
			'<th>TTL</th>' + 
			'<th>Actions</th>' + 
			'</tr></thead>');
		var $tbody = $('<tbody></tbody>');

		if (records.length === 0) {
			$tbody.append('<tr><td colspan="5">No DNS records found</td></tr>');
		} else {
			records.forEach(function(record) {
				var $row = $('<tr></tr>');
				$row.append($('<td></td>').text(record.type || ''));
				$row.append($('<td></td>').text(record.name || ''));
				$row.append($('<td></td>').text(record.value || ''));
				$row.append($('<td></td>').text(record.ttl || ''));
				$row.append($('<td></td>').html('<button class="btn btn-sm btn-outline">Edit</button>'));
				$tbody.append($row);
			});
		}

		$table.append($thead).append($tbody);
		$list.html('').append($table);
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

})(jQuery);
