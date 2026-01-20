# Domain Seller Features - Integration Documentation

## Overview

This document describes the advanced domain management features that have been integrated into the WU Reseller Panel, including DNS management, domain transfers, and automatic renewal capabilities.

## Core Components

### 1. DNS Manager (`inc/class-dns-manager.php`)

The DNS Manager provides comprehensive DNS record management capabilities for domains.

#### Features:
- **Supported DNS Record Types**: A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, PTR
- **CRUD Operations**: Create, Read, Update, Delete DNS records
- **DNS Zone Files**: Generate DNS zone files from records
- **Permission System**: Customer and admin permission checking
- **AJAX Integration**: Real-time DNS record management via AJAX

#### Key Methods:
```php
// Get DNS records for a domain
DNS_Manager::get_instance()->get_dns_records( $domain_name, $customer_id );

// Add a DNS record
DNS_Manager::get_instance()->add_dns_record( $domain_name, $record_data, $customer_id );

// Update a DNS record
DNS_Manager::get_instance()->update_dns_record( $domain_name, $record_id, $record_data, $customer_id );

// Delete a DNS record
DNS_Manager::get_instance()->delete_dns_record( $domain_name, $record_id, $customer_id );

// Get zone file
DNS_Manager::get_instance()->get_zone_file( $domain_name, $customer_id );
```

#### AJAX Endpoints:
- `wp_ajax_reseller_panel_get_dns_records` - Get DNS records (admin)
- `wp_ajax_reseller_panel_add_dns_record` - Add DNS record (admin)
- `wp_ajax_reseller_panel_update_dns_record` - Update DNS record (admin)
- `wp_ajax_reseller_panel_delete_dns_record` - Delete DNS record (admin)
- `wp_ajax_reseller_panel_customer_get_dns_records` - Get DNS records (customer)
- `wp_ajax_reseller_panel_customer_add_dns_record` - Add DNS record (customer)
- `wp_ajax_reseller_panel_customer_update_dns_record` - Update DNS record (customer)
- `wp_ajax_reseller_panel_customer_delete_dns_record` - Delete DNS record (customer)

#### DNS Record Validation:
The DNS Manager validates all DNS records before creation/update:
- **A records**: Must be valid IPv4 addresses
- **AAAA records**: Must be valid IPv6 addresses
- **MX records**: Require priority value
- **All records**: Require type, name, and value fields

---

### 2. Domain Transfer Manager (`inc/class-domain-transfer-manager.php`)

The Domain Transfer Manager handles domain transfers both into and out of your registrar.

#### Transfer Statuses:
- `pending` - Transfer initiated, waiting for approval
- `in_progress` - Transfer is being processed
- `completed` - Transfer successfully completed
- `failed` - Transfer failed
- `cancelled` - Transfer was cancelled
- `rejected` - Transfer was rejected

#### Features:
- **Transfer In**: Transfer domains from other registrars
- **Transfer Out**: Transfer domains to other registrars
- **Authorization Codes**: Generate and manage EPP/auth codes
- **Status Tracking**: Monitor transfer progress
- **Automatic Status Updates**: Cron job checks transfer status hourly

#### Key Methods:
```php
// Initiate transfer in
Domain_Transfer_Manager::get_instance()->initiate_transfer_in(
    $domain_name,
    $auth_code,
    $customer_id,
    $provider_id,
    $options
);

// Initiate transfer out (generates auth code)
Domain_Transfer_Manager::get_instance()->initiate_transfer_out(
    $domain_name,
    $customer_id,
    $new_registrar,
    $options
);

// Cancel transfer
Domain_Transfer_Manager::get_instance()->cancel_transfer( $domain_name, $customer_id );

// Get transfer metadata
Domain_Transfer_Manager::get_instance()->get_transfer_metadata( $domain_name );
```

#### AJAX Endpoints:
- `wp_ajax_reseller_panel_initiate_domain_transfer_in` - Start transfer in
- `wp_ajax_reseller_panel_initiate_domain_transfer_out` - Start transfer out
- `wp_ajax_reseller_panel_cancel_domain_transfer` - Cancel transfer
- `wp_ajax_reseller_panel_get_transfer_status` - Get transfer status

#### Cron Jobs:
- `reseller_panel_check_transfer_status` - Runs hourly to check pending transfers

---

### 3. Domain Renewal Manager (`inc/class-domain-renewal-manager.php`)

The Domain Renewal Manager handles automatic domain renewals and renewal notifications.

#### Features:
- **Automatic Renewal**: Automatically renew domains before expiration
- **Batch Processing**: Process multiple renewals efficiently
- **Payment Integration**: Integrate with WP Ultimo payment system
- **Renewal Notifications**: Send notifications for successful/failed renewals
- **Configurable Notice Period**: Set days before expiration to trigger renewal

#### Key Methods:
```php
// Check if a domain needs renewal
Domain_Renewal_Manager::get_instance()->check_domain_renewal( $payment_id, $domain_name );

// Process batch renewals (all domains due for renewal)
Domain_Renewal_Manager::get_instance()->process_batch_renewals();
```

#### WP Ultimo Integration Hooks:
```php
// When membership becomes active, renew domains
add_action( 'wu_membership_status_to_active', [$this, 'maybe_renew_domains'], 10, 2 );

// Handle expired memberships
add_action( 'wu_membership_status_to_expired', [$this, 'handle_expired_membership'], 10, 2 );

// Process renewal payments
add_action( 'wu_payment_status_to_completed', [$this, 'process_renewal_payment'], 10, 2 );
```

#### Cron Jobs:
- `reseller_panel_domain_batch_renewal_check` - Runs daily to process batch renewals

---

## Database Schema

### Domain Metadata Table

The domain metadata table stores additional information about domains.

```sql
CREATE TABLE wp_reseller_panel_domain_meta (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    domain_id bigint(20) UNSIGNED NOT NULL,
    meta_key varchar(255) NOT NULL,
    meta_value longtext,
    PRIMARY KEY (id),
    KEY domain_id (domain_id),
    KEY meta_key (meta_key)
);
```

#### Metadata Keys:
- `domain_data` - Stores comprehensive domain information (domain_name, customer_id, auto_renew, expiry_date, etc.)
- `transfer_data` - Stores transfer information (status, transfer_id, provider, direction, initiated_at, etc.)
- `auto_renew` - Boolean indicating if auto-renewal is enabled
- `expiry_date` - Domain expiration date (Y-m-d H:i:s format)
- `dns_managed` - Boolean indicating if DNS is managed by the system

---

## Provider Integration

### Base Service Provider Updates

The `Base_Service_Provider` abstract class has been extended with new methods:

```php
// DNS Methods
public function get_dns_records( $domain_name );
public function add_dns_record( $domain_name, $record_data );
public function update_dns_record( $domain_name, $record_id, $record_data );
public function delete_dns_record( $domain_name, $record_id );

// Transfer Methods
public function transfer_domain( $domain_name, $auth_code, $registrant_info, $options = [] );
public function check_transfer_status( $domain_name, $transfer_id );
public function get_auth_code( $domain_name );

// Renewal Method
public function renew_domain( $domain_name, $years = 1 );
```

**Note**: Base implementations return `WP_Error` with "not_implemented" code. Providers should override these methods with actual API implementations.

### Provider Implementation Status

#### OpenSRS Provider
- **DNS Methods**: Not yet implemented (returns WP_Error)
- **Transfer Methods**: Not yet implemented (returns WP_Error)
- **Renewal Methods**: Not yet implemented (returns WP_Error)

#### NameCheap Provider
- **DNS Methods**: Not yet implemented (returns WP_Error)
- **Transfer Methods**: Not yet implemented (returns WP_Error)
- **Renewal Methods**: Not yet implemented (returns WP_Error)

---

## Settings

New settings have been added to the Reseller Panel settings page:

### Transfer Settings
- **Enable Domain Transfers** (`reseller_panel_enable_transfers`)
  - Type: Toggle
  - Default: true
  - Description: Allow customers to transfer domains in and out

- **Transfer Lock Days** (`reseller_panel_transfer_lock_days`)
  - Type: Number
  - Default: 60
  - Range: 0-365
  - Description: Number of days to lock domain transfers after registration

### Renewal Settings
- **Renewal Notice Days** (`reseller_panel_renewal_notice_days`)
  - Type: Number
  - Default: 30
  - Range: 1-90
  - Description: Send renewal notifications this many days before expiration

- **Renewal Retry Days** (`reseller_panel_renewal_retry_days`)
  - Type: Number
  - Default: 7
  - Range: 0-30
  - Description: Retry failed renewals for this many days after expiration

### DNS Settings
- **Enable Customer DNS Management** (`reseller_panel_enable_customer_dns`)
  - Type: Toggle
  - Default: true
  - Description: Allow customers to manage DNS records directly

- **DNS Record Limit** (`reseller_panel_dns_record_limit`)
  - Type: Number
  - Default: 50
  - Range: 10-500
  - Description: Maximum number of DNS records per domain

---

## Usage Examples

### Example 1: Add a DNS Record

```php
$dns_manager = \Reseller_Panel\DNS_Manager::get_instance();

$result = $dns_manager->add_dns_record(
    'example.com',
    [
        'type' => 'A',
        'name' => 'www',
        'value' => '192.0.2.1',
        'ttl' => 3600,
    ],
    $customer_id
);

if ( $result['success'] ) {
    echo 'DNS record added successfully!';
} else {
    echo 'Error: ' . $result['message'];
}
```

### Example 2: Initiate Domain Transfer In

```php
$transfer_manager = \Reseller_Panel\Domain_Transfer_Manager::get_instance();

$result = $transfer_manager->initiate_transfer_in(
    'example.com',
    'EPP-AUTH-CODE-HERE',
    $customer_id,
    'opensrs'
);

if ( $result['success'] ) {
    echo 'Transfer initiated successfully!';
} else {
    echo 'Error: ' . $result['message'];
}
```

### Example 3: Enable Auto-Renewal

```php
// This would typically be done through domain metadata
global $wpdb;

$wpdb->replace(
    $wpdb->prefix . 'reseller_panel_domain_meta',
    [
        'domain_id' => 0,
        'meta_key' => 'domain_data',
        'meta_value' => maybe_serialize([
            'domain_name' => 'example.com',
            'customer_id' => 123,
            'auto_renew' => true,
            'expiry_date' => '2027-01-20 00:00:00',
        ]),
    ],
    [ '%d', '%s', '%s' ]
);
```

---

## Scheduled Tasks (Cron Jobs)

The plugin registers two cron jobs:

1. **Transfer Status Checker** (`reseller_panel_check_transfer_status`)
   - **Frequency**: Hourly
   - **Purpose**: Check status of pending domain transfers
   - **Action**: Updates transfer metadata based on provider response

2. **Batch Renewal Processor** (`reseller_panel_domain_batch_renewal_check`)
   - **Frequency**: Daily
   - **Purpose**: Process automatic domain renewals
   - **Action**: Renews domains that are within the renewal notice period

---

## Security Considerations

### Permission Checks
- All AJAX handlers verify nonces
- Customer operations check `is_user_logged_in()`
- Admin operations check `current_user_can( 'manage_network' )`
- DNS management checks `can_manage_domain_dns()` for customer permissions

### Input Sanitization
- All user inputs are sanitized using WordPress functions:
  - `sanitize_text_field()` for text inputs
  - `absint()` for integer inputs
  - `sanitize_email()` for email addresses
- DNS record data is validated before processing

### Output Escaping
- All output uses appropriate escaping functions:
  - `esc_html()` for HTML content
  - `esc_attr()` for attributes
  - `esc_url()` for URLs

---

## Next Steps for Full Implementation

### Phase 3: Provider API Implementation
Each provider (OpenSRS, NameCheap) needs to implement:
1. DNS record management API calls
2. Domain transfer API calls
3. Domain renewal API calls

### Phase 4: Customer-Facing Frontend
Create customer portal templates:
1. Domain management dashboard
2. DNS record management interface
3. Transfer management interface

### Phase 5: Admin Enhancements
Create admin management pages:
1. Domain management dashboard
2. DNS management panel
3. Transfer management panel

### Phase 6: JavaScript Assets
Create interactive JavaScript components:
1. Customer domain manager (AJAX-based)
2. Admin domain manager (bulk operations)
3. DNS record editor (validation & real-time updates)

---

## Troubleshooting

### DNS Records Not Saving
- **Check**: Provider implementation of `add_dns_record()`
- **Check**: DNS validation is passing
- **Check**: Provider API credentials are configured
- **Solution**: Review provider logs in Logger class

### Transfers Stuck in Pending
- **Check**: Cron jobs are running (`wp cron event list`)
- **Check**: Provider `check_transfer_status()` implementation
- **Solution**: Manually run `wp cron event run reseller_panel_check_transfer_status`

### Auto-Renewal Not Working
- **Check**: Cron jobs are running
- **Check**: Domain metadata has `auto_renew` = true
- **Check**: `expiry_date` is set correctly
- **Check**: Provider `renew_domain()` implementation
- **Solution**: Review renewal logs in Logger class

---

## Support

For issues, questions, or contributions:
- **Repository**: https://github.com/Boofola/wu-reseller-panel
- **Documentation**: See ARCHITECTURE.md for system design
- **Provider APIs**: 
  - OpenSRS: https://domains.opensrs.guide/
  - NameCheap: https://www.namecheap.com/support/api/intro/
