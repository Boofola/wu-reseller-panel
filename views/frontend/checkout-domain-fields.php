<?php
/**
 * Checkout Domain Fields Template
 *
 * Displays domain registration fields in checkout form.
 *
 * @package Reseller_Panel
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="reseller-panel-domain-checkout">
	<h3><?php esc_html_e( 'Domain Registration', 'ultimate-multisite' ); ?></h3>

	<div class="domain-registration-toggle">
		<label>
			<input type="checkbox" name="register_domain" id="register_domain" value="1" />
			<?php esc_html_e( 'Register a domain name', 'ultimate-multisite' ); ?>
		</label>
	</div>

	<div class="domain-registration-fields" style="display: none;">
		<!-- Domain Search -->
		<div class="form-group domain-search-group">
			<label for="domain_name"><?php esc_html_e( 'Domain Name', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
			<div class="domain-search-wrapper">
				<input 
					type="text" 
					name="domain_name" 
					id="domain_name" 
					class="form-control" 
					placeholder="<?php esc_attr_e( 'example.com', 'ultimate-multisite' ); ?>"
					aria-describedby="domain-search-help"
				/>
				<button type="button" id="check_domain_availability" class="btn btn-secondary">
					<?php esc_html_e( 'Check Availability', 'ultimate-multisite' ); ?>
				</button>
			</div>
			<small id="domain-search-help" class="form-text text-muted">
				<?php esc_html_e( 'Enter your desired domain name and check if it\'s available', 'ultimate-multisite' ); ?>
			</small>
			<div id="domain_availability_result" class="domain-availability-result"></div>
		</div>

		<!-- Domain Pricing -->
		<div class="form-group domain-pricing-group" style="display: none;">
			<div id="domain_pricing_display" class="domain-pricing-display"></div>
		</div>

		<!-- Registrant Information -->
		<div class="registrant-info-section">
			<h4><?php esc_html_e( 'Registrant Information', 'ultimate-multisite' ); ?></h4>
			<p class="description">
				<?php esc_html_e( 'Please provide accurate contact information for domain registration. This information will be used for WHOIS records.', 'ultimate-multisite' ); ?>
			</p>

			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="registrant_first_name"><?php esc_html_e( 'First Name', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="text" 
						name="registrant_first_name" 
						id="registrant_first_name" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['first_name'] ); ?>"
						required
					/>
				</div>

				<div class="form-group col-md-6">
					<label for="registrant_last_name"><?php esc_html_e( 'Last Name', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="text" 
						name="registrant_last_name" 
						id="registrant_last_name" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['last_name'] ); ?>"
						required
					/>
				</div>
			</div>

			<div class="form-group">
				<label for="registrant_organization"><?php esc_html_e( 'Organization (Optional)', 'ultimate-multisite' ); ?></label>
				<input 
					type="text" 
					name="registrant_organization" 
					id="registrant_organization" 
					class="form-control" 
				/>
			</div>

			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="registrant_email"><?php esc_html_e( 'Email', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="email" 
						name="registrant_email" 
						id="registrant_email" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['email'] ); ?>"
						required
					/>
				</div>

				<div class="form-group col-md-6">
					<label for="registrant_phone"><?php esc_html_e( 'Phone', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="tel" 
						name="registrant_phone" 
						id="registrant_phone" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['phone'] ); ?>"
						required
					/>
				</div>
			</div>

			<div class="form-group">
				<label for="registrant_address"><?php esc_html_e( 'Address', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
				<input 
					type="text" 
					name="registrant_address" 
					id="registrant_address" 
					class="form-control" 
					value="<?php echo esc_attr( $customer_data['address'] ); ?>"
					required
				/>
			</div>

			<div class="form-row">
				<div class="form-group col-md-6">
					<label for="registrant_city"><?php esc_html_e( 'City', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="text" 
						name="registrant_city" 
						id="registrant_city" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['city'] ); ?>"
						required
					/>
				</div>

				<div class="form-group col-md-3">
					<label for="registrant_state"><?php esc_html_e( 'State/Province', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="text" 
						name="registrant_state" 
						id="registrant_state" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['state'] ); ?>"
						required
					/>
				</div>

				<div class="form-group col-md-3">
					<label for="registrant_zip"><?php esc_html_e( 'Zip/Postal Code', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
					<input 
						type="text" 
						name="registrant_zip" 
						id="registrant_zip" 
						class="form-control" 
						value="<?php echo esc_attr( $customer_data['zip'] ); ?>"
						required
					/>
				</div>
			</div>

			<div class="form-group">
				<label for="registrant_country"><?php esc_html_e( 'Country', 'ultimate-multisite' ); ?> <span class="required">*</span></label>
				<select name="registrant_country" id="registrant_country" class="form-control" required>
					<option value=""><?php esc_html_e( 'Select Country', 'ultimate-multisite' ); ?></option>
					<?php
					$countries = array(
						'US' => __( 'United States', 'ultimate-multisite' ),
						'CA' => __( 'Canada', 'ultimate-multisite' ),
						'GB' => __( 'United Kingdom', 'ultimate-multisite' ),
						'AU' => __( 'Australia', 'ultimate-multisite' ),
						'DE' => __( 'Germany', 'ultimate-multisite' ),
						'FR' => __( 'France', 'ultimate-multisite' ),
						'IT' => __( 'Italy', 'ultimate-multisite' ),
						'ES' => __( 'Spain', 'ultimate-multisite' ),
						'NL' => __( 'Netherlands', 'ultimate-multisite' ),
						'SE' => __( 'Sweden', 'ultimate-multisite' ),
						'NO' => __( 'Norway', 'ultimate-multisite' ),
						'DK' => __( 'Denmark', 'ultimate-multisite' ),
						'FI' => __( 'Finland', 'ultimate-multisite' ),
						'IE' => __( 'Ireland', 'ultimate-multisite' ),
						'NZ' => __( 'New Zealand', 'ultimate-multisite' ),
					);

					$countries = apply_filters( 'reseller_panel_checkout_countries', $countries );

					foreach ( $countries as $code => $name ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $code ),
							selected( $customer_data['country'], $code, false ),
							esc_html( $name )
						);
					}
					?>
				</select>
			</div>

			<div class="form-group">
				<label>
					<input type="checkbox" name="agree_domain_terms" id="agree_domain_terms" value="1" required />
					<?php
					printf(
						/* translators: %s: Link to domain registration terms */
						esc_html__( 'I agree to the %s', 'ultimate-multisite' ),
						'<a href="#" target="_blank">' . esc_html__( 'domain registration terms and conditions', 'ultimate-multisite' ) . '</a>'
					);
					?>
				</label>
			</div>
		</div>
	</div>
</div>
