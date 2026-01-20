<?php
/**
 * Customer Domain Manager Template
 *
 * Displays customer-facing domain management interface with tabs for
 * managing domains, DNS, and transfers.
 *
 * @package Reseller_Panel
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get customer domains
$domains = isset( $domains ) ? $domains : array();
// Tab navigation is read-only and doesn't modify data, but we sanitize the input
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'domains';
// Whitelist valid tabs
$valid_tabs = array( 'domains', 'dns', 'transfers' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'domains';
}

?>

<div class="reseller-panel-customer-domain-manager">
	<div class="domain-manager-header">
		<h2><?php esc_html_e( 'My Domains', 'ultimate-multisite' ); ?></h2>
	</div>

	<!-- Tabs Navigation -->
	<nav class="domain-manager-tabs">
		<a href="?tab=domains" class="tab-link <?php echo 'domains' === $active_tab ? 'active' : ''; ?>">
			<?php esc_html_e( 'Domains', 'ultimate-multisite' ); ?>
		</a>
		<a href="?tab=dns" class="tab-link <?php echo 'dns' === $active_tab ? 'active' : ''; ?>">
			<?php esc_html_e( 'DNS Management', 'ultimate-multisite' ); ?>
		</a>
		<a href="?tab=transfers" class="tab-link <?php echo 'transfers' === $active_tab ? 'active' : ''; ?>">
			<?php esc_html_e( 'Transfers', 'ultimate-multisite' ); ?>
		</a>
	</nav>

	<!-- Tab Content -->
	<div class="domain-manager-content">
		<?php if ( 'domains' === $active_tab ) : ?>
			<!-- Domains Tab -->
			<div class="tab-panel" id="domains-panel">
				<?php if ( empty( $domains ) ) : ?>
					<div class="no-domains">
						<p><?php esc_html_e( 'You do not have any registered domains yet.', 'ultimate-multisite' ); ?></p>
						<a href="#" class="btn btn-primary"><?php esc_html_e( 'Register a Domain', 'ultimate-multisite' ); ?></a>
					</div>
				<?php else : ?>
					<table class="domains-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Domain Name', 'ultimate-multisite' ); ?></th>
								<th><?php esc_html_e( 'Status', 'ultimate-multisite' ); ?></th>
								<th><?php esc_html_e( 'Expiry Date', 'ultimate-multisite' ); ?></th>
								<th><?php esc_html_e( 'Auto-Renew', 'ultimate-multisite' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'ultimate-multisite' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $domains as $domain ) : ?>
								<tr class="domain-row" data-domain="<?php echo esc_attr( $domain['name'] ); ?>">
									<td class="domain-name">
										<strong><?php echo esc_html( $domain['name'] ); ?></strong>
									</td>
									<td class="domain-status">
										<span class="status-badge status-<?php echo esc_attr( $domain['status'] ); ?>">
											<?php echo esc_html( ucfirst( $domain['status'] ) ); ?>
										</span>
									</td>
									<td class="domain-expiry">
										<?php
										if ( isset( $domain['expiry_date'] ) ) {
											echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $domain['expiry_date'] ) ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td class="domain-auto-renew">
										<label class="toggle-switch">
											<input 
												type="checkbox" 
												class="auto-renew-toggle" 
												data-domain="<?php echo esc_attr( $domain['name'] ); ?>"
												<?php checked( ! empty( $domain['auto_renew'] ) ); ?>
											/>
											<span class="toggle-slider"></span>
										</label>
									</td>
									<td class="domain-actions">
										<div class="action-buttons">
											<button class="btn btn-sm btn-outline manage-dns-btn" data-domain="<?php echo esc_attr( $domain['name'] ); ?>">
												<?php esc_html_e( 'DNS', 'ultimate-multisite' ); ?>
											</button>
											<button class="btn btn-sm btn-outline renew-domain-btn" data-domain="<?php echo esc_attr( $domain['name'] ); ?>">
												<?php esc_html_e( 'Renew', 'ultimate-multisite' ); ?>
											</button>
											<button class="btn btn-sm btn-outline transfer-domain-btn" data-domain="<?php echo esc_attr( $domain['name'] ); ?>">
												<?php esc_html_e( 'Transfer', 'ultimate-multisite' ); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php elseif ( 'dns' === $active_tab ) : ?>
			<!-- DNS Management Tab -->
			<div class="tab-panel" id="dns-panel">
				<div class="dns-management-section">
					<div class="select-domain-section">
						<label for="dns-domain-select"><?php esc_html_e( 'Select Domain:', 'ultimate-multisite' ); ?></label>
						<select id="dns-domain-select" class="form-control">
							<option value=""><?php esc_html_e( 'Choose a domain...', 'ultimate-multisite' ); ?></option>
							<?php foreach ( $domains as $domain ) : ?>
								<option value="<?php echo esc_attr( $domain['name'] ); ?>">
									<?php echo esc_html( $domain['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div id="dns-records-container" style="display: none;">
						<div class="dns-records-header">
							<h3><?php esc_html_e( 'DNS Records', 'ultimate-multisite' ); ?></h3>
							<button class="btn btn-primary" id="add-dns-record">
								<?php esc_html_e( 'Add Record', 'ultimate-multisite' ); ?>
							</button>
						</div>

						<div id="dns-records-list">
							<!-- DNS records will be loaded here via AJAX -->
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'transfers' === $active_tab ) : ?>
			<!-- Transfers Tab -->
			<div class="tab-panel" id="transfers-panel">
				<div class="transfers-section">
					<h3><?php esc_html_e( 'Domain Transfers', 'ultimate-multisite' ); ?></h3>

					<!-- Transfer In -->
					<div class="transfer-in-section">
						<h4><?php esc_html_e( 'Transfer Domain In', 'ultimate-multisite' ); ?></h4>
						<p><?php esc_html_e( 'Transfer a domain from another registrar to your account.', 'ultimate-multisite' ); ?></p>

						<form id="transfer-in-form" class="transfer-form">
							<div class="form-group">
								<label for="transfer_domain_name"><?php esc_html_e( 'Domain Name', 'ultimate-multisite' ); ?></label>
								<input 
									type="text" 
									id="transfer_domain_name" 
									name="domain_name" 
									class="form-control" 
									placeholder="<?php esc_attr_e( 'example.com', 'ultimate-multisite' ); ?>"
									required
								/>
							</div>

							<div class="form-group">
								<label for="transfer_auth_code"><?php esc_html_e( 'Authorization Code', 'ultimate-multisite' ); ?></label>
								<input 
									type="text" 
									id="transfer_auth_code" 
									name="auth_code" 
									class="form-control" 
									placeholder="<?php esc_attr_e( 'EPP/Auth Code', 'ultimate-multisite' ); ?>"
									required
								/>
								<small class="form-text">
									<?php esc_html_e( 'Get this code from your current registrar', 'ultimate-multisite' ); ?>
								</small>
							</div>

							<button type="submit" class="btn btn-primary">
								<?php esc_html_e( 'Initiate Transfer', 'ultimate-multisite' ); ?>
							</button>
						</form>
					</div>

					<!-- Transfer Out -->
					<div class="transfer-out-section">
						<h4><?php esc_html_e( 'Transfer Domain Out', 'ultimate-multisite' ); ?></h4>
						<p><?php esc_html_e( 'Get an authorization code to transfer your domain to another registrar.', 'ultimate-multisite' ); ?></p>

						<div class="form-group">
							<label for="transfer_out_domain"><?php esc_html_e( 'Select Domain', 'ultimate-multisite' ); ?></label>
							<select id="transfer_out_domain" class="form-control">
								<option value=""><?php esc_html_e( 'Choose a domain...', 'ultimate-multisite' ); ?></option>
								<?php foreach ( $domains as $domain ) : ?>
									<option value="<?php echo esc_attr( $domain['name'] ); ?>">
										<?php echo esc_html( $domain['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<button type="button" id="get-auth-code" class="btn btn-secondary">
							<?php esc_html_e( 'Get Authorization Code', 'ultimate-multisite' ); ?>
						</button>

						<div id="auth-code-display" class="auth-code-result" style="display: none;">
							<!-- Auth code will be displayed here -->
						</div>
					</div>

					<!-- Transfer Status -->
					<div class="transfer-status-section">
						<h4><?php esc_html_e( 'Active Transfers', 'ultimate-multisite' ); ?></h4>
						<div id="active-transfers-list">
							<!-- Active transfers will be loaded here -->
							<p class="text-muted"><?php esc_html_e( 'No active transfers', 'ultimate-multisite' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
// Enqueue required scripts for the customer portal
wp_enqueue_script( 'reseller-panel-customer-portal' );
wp_localize_script(
	'reseller-panel-customer-portal',
	'resellerPanelCustomer',
	array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonces'   => array(
			'dns'      => wp_create_nonce( 'reseller_panel_dns_nonce' ),
			'transfer' => wp_create_nonce( 'reseller_panel_transfer_nonce' ),
			'renewal'  => wp_create_nonce( 'reseller_panel_renewal_nonce' ),
		),
		'messages' => array(
			'loading'        => __( 'Loading...', 'ultimate-multisite' ),
			'error'          => __( 'An error occurred', 'ultimate-multisite' ),
			'success'        => __( 'Operation completed successfully', 'ultimate-multisite' ),
			'confirmRenew'   => __( 'Are you sure you want to renew this domain?', 'ultimate-multisite' ),
			'confirmTransfer' => __( 'Are you sure you want to initiate this transfer?', 'ultimate-multisite' ),
		),
	)
);
?>
