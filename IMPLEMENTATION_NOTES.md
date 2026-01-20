# Implementation Notes

## Overview
This document provides technical details about the three bug fixes applied to the Reseller Panel plugin.

---

## Fix 1: Database Service Seeding

### Location
`inc/class-reseller-panel.php` - Lines 515-695

### Implementation Details

#### Method: `populate_default_services()`
```php
private function populate_default_services() {
    // Check if service exists before inserting
    // Insert default 5 services if missing
    // Log success/failure for each service
}
```

#### Services Created
```php
$default_services = array(
    array('service_key' => 'domains', 'service_name' => 'Domains', ...),
    array('service_key' => 'ssl', 'service_name' => 'SSL Certificates', ...),
    array('service_key' => 'hosting', 'service_name' => 'Hosting', ...),
    array('service_key' => 'emails', 'service_name' => 'Email', ...),
    array('service_key' => 'marketing', 'service_name' => 'Marketing', ...),
);
```

#### Calling Code
```php
// In create_tables() method, after adding providers_order column:
$this->populate_default_services();
```

#### Key Features
- Checks if service already exists before inserting (idempotent)
- Uses prepared statements for security
- Logs each operation to WordPress error log
- All services default to disabled (enabled = 0)

#### When It Runs
- On plugin activation
- On any admin page load that calls `create_tables()`
- Safe to run multiple times (won't duplicate)

### Testing
```php
// After activation:
SELECT * FROM wp_reseller_panel_services;
// Should show 5 rows with different service_keys
```

---

## Fix 2: OpenSRS XML Parser Enhancement

### Location
`inc/providers/class-opensrs-provider.php` - Lines 350-525

### Problem Analysis

OpenSRS XML response example (GET_TLDLIST):
```xml
<?xml version="1.0" encoding="UTF-8"?>
<OPS_envelope>
  <body>
    <data_block>
      <dt_assoc>
        <item key="is_success">1</item>
        <item key="response_code">Command Successful</item>
        <item key="tld_list">
          <dt_array>
            <item key="0">
              <dt_assoc>
                <item key="tld">com</item>
                <item key="registration_price">8.99</item>
                <item key="renewal_price">8.99</item>
              </dt_assoc>
            </item>
            <item key="1">
              <dt_assoc>
                <item key="tld">net</item>
                <item key="registration_price">11.79</item>
                <item key="renewal_price">11.79</item>
              </dt_assoc>
            </item>
          </dt_array>
        </item>
      </dt_assoc>
    </data_block>
  </body>
</OPS_envelope>
```

**Original Parser Issue:** Only extracted `is_success`, `response_code`, `response_text`. Missed the nested `tld_list` array.

### Solution Architecture

#### Method: `extract_nested_xml_data(DOMXPath, array &$result)`
- Iterates through all items with `key` attribute
- Detects `dt_array` and `dt_assoc` elements
- Routes to appropriate parser method
- Populates result array with extracted data

#### Method: `parse_dt_array(DOMElement)`
- Extracts indexed array from `<dt_array>` XML element
- Recursively handles nested structures
- Returns PHP array

#### Method: `parse_dt_assoc(DOMElement)`
- Extracts associative array from `<dt_assoc>` XML element
- Recursively handles nested structures
- Returns PHP array

### Parser Flow
```
parse_xml_response()
├── Load XML into DOMDocument
├── Create DOMXPath for querying
├── Extract basic fields (is_success, response_code, response_text)
└── Call extract_nested_xml_data()
    └── Iterate through all items with key attribute
        ├── Check for dt_array → call parse_dt_array()
        ├── Check for dt_assoc → call parse_dt_assoc()
        └── Simple values → add directly to result
```

### Recursive Processing
Both `parse_dt_array()` and `parse_dt_assoc()` handle nesting:
```php
if ($child has dt_array) {
    $result[$key] = parse_dt_array($child);
} elseif ($child has dt_assoc) {
    $result[$key] = parse_dt_assoc($child);
} else {
    $result[$key] = $child->nodeValue;
}
```

### Result Format
After parsing the example above:
```php
$result = [
    'is_success' => 1,
    'response_code' => 'Command Successful',
    'tld_list' => [
        '0' => [
            'tld' => 'com',
            'registration_price' => '8.99',
            'renewal_price' => '8.99',
        ],
        '1' => [
            'tld' => 'net',
            'registration_price' => '11.79',
            'renewal_price' => '11.79',
        ],
    ]
];
```

### Backward Compatibility
- All top-level responses still work as before
- If API returns simple values, they're extracted as strings
- If API returns nested structures, they're properly parsed
- No breaking changes to existing API consumers

### Testing
```php
$response = $provider->make_request('DOMAIN', 'GET_TLDLIST');

if (!is_wp_error($response)) {
    // Check that tld_list is populated
    if (isset($response['tld_list']) && is_array($response['tld_list'])) {
        echo "TLD count: " . count($response['tld_list']);
        // Should show number of TLDs, not 0
    }
}
```

---

## Fix 3: Domain Fee Configuration

### Location
`inc/providers/class-opensrs-provider.php` - Lines 46-78 (config field), 605-620 (application)

### Configuration Field Definition
```php
'domain_fee' => array(
    'label' => __( 'Domain Markup Fee (Optional)', 'ultimate-multisite' ),
    'type' => 'text',
    'description' => __( 'Additional fee or markup to add to all domain prices...', 'ultimate-multisite' ),
)
```

### Storage
- Stored in: `wp_sitemeta` (network-wide option)
- Option name: `reseller_panel_opensrs_config`
- Field key: `domain_fee`
- Format: String representation of decimal number

### Application Logic
In `get_domains()` method:

```php
// Step 1: Load config from database
$this->load_config();

// Step 2: Get domain fee from config, default to 0
$domain_fee = floatval( $this->get_config_value( 'domain_fee', 0 ) );

// Step 3: For each TLD, add fee to all prices
foreach ( $response['tld_list'] as $tld => $data ) {
    $registration_price = floatval($data['registration_price']) + $domain_fee;
    $renewal_price = floatval($data['renewal_price']) + $domain_fee;
    $transfer_price = floatval($data['transfer_price']) + $domain_fee;
    
    // Step 4: Create domain entry with adjusted prices
    $domains[] = array(
        'registration_price' => $registration_price,
        'renewal_price' => $renewal_price,
        'transfer_price' => $transfer_price,
    );
}
```

### Numeric Handling
- Input validation: Uses `floatval()` to convert string to number
- Default: `0` (no markup) if field is empty
- Precision: PHP float precision (sufficient for currency)
- Application: Simple addition to all price types

### Examples
```
Input: "2.50"  → Price: 8.99 → Result: 11.49 ✓
Input: "0.99"  → Price: 8.99 → Result: 9.98 ✓
Input: ""      → Price: 8.99 → Result: 8.99 ✓ (no markup)
Input: "5"     → Price: 8.99 → Result: 13.99 ✓
```

### Database Storage Format
```sql
-- Config is stored as JSON in wp_sitemeta
UPDATE wp_sitemeta 
SET meta_value = '{"api_key":"...","username":"...","domain_fee":"2.50"}'
WHERE meta_key = 'reseller_panel_opensrs_config';
```

### Admin UI Integration
The domain_fee field appears in the OpenSRS settings form:
1. Provider Settings page loads OpenSRS tab
2. `get_config_fields()` returns all field definitions including domain_fee
3. Provider form renderer creates text input for domain_fee
4. On submit, value is sanitized and saved via `save_config()`

---

## Enhanced Logging

### File Modified
`inc/importers/class-domain-importer.php`

### Logging Points Added

#### 1. Import Start
```php
Logger::log_info(
    'Domain_Importer',
    'Starting domain import from provider: ' . $this->provider->get_name(),
    array( 'provider_key' => $this->provider->get_key() )
);
```

#### 2. Domains Retrieved
```php
Logger::log_info(
    'Domain_Importer',
    'Retrieved domains from provider',
    array(
        'provider_key' => $this->provider->get_key(),
        'domain_count' => count($domains),
    )
);
```

#### 3. Error During Retrieval
```php
Logger::log_error(
    'Domain_Importer',
    'Error getting domains from provider: ' . $error->get_error_message(),
    array(
        'provider_key' => $this->provider->get_key(),
        'error_code' => $error->get_error_code(),
    )
);
```

#### 4. No Domains Found
```php
Logger::log_warning(
    'Domain_Importer',
    'No domains found from provider - check provider API response',
    array( 'provider_key' => $this->provider->get_key() )
);
```

#### 5. Import Complete
```php
Logger::log_info(
    'Domain_Importer',
    'Domain import completed',
    array(
        'provider_key' => $this->provider->get_key(),
        'imported' => $this->results['imported'],
        'updated' => $this->results['updated'],
        'skipped' => $this->results['skipped'],
        'errors' => count($this->results['errors']),
    )
);
```

### Log Output Examples
```
[2025-01-20 12:34:56] Domain_Importer: Starting domain import from provider: OpenSRS
[2025-01-20 12:34:57] Domain_Importer: Retrieved domains from provider (domain_count: 285)
[2025-01-20 12:35:02] Domain_Importer: Domain import completed (imported: 285, updated: 0, skipped: 0, errors: 0)
```

---

## Testing Checklist

### Fix 1 - Services Seeding
- [ ] Plugin activates successfully
- [ ] Check WordPress error log for "Created default service" messages
- [ ] Run: `SELECT COUNT(*) FROM wp_reseller_panel_services;` → Should be 5
- [ ] Services Settings page shows all 5 services
- [ ] Deactivate/reactivate plugin → Services still there (no duplicates)

### Fix 2 - XML Parser
- [ ] Configure OpenSRS with valid credentials
- [ ] Run: `wp_remote_post()` test to GET_TLDLIST endpoint
- [ ] Parse response through `parse_xml_response()`
- [ ] Check that `$response['tld_list']` is populated with array
- [ ] Verify TLD count matches number in reseller account

### Fix 3 - Domain Fee
- [ ] Add domain fee: 2.50 in OpenSRS settings
- [ ] Import a domain with base price 8.99
- [ ] Check Ultimate Multisite product price → Should be 11.49
- [ ] Test with different fees: 0.99, 5.00, empty string
- [ ] Verify all price types (registration, renewal, transfer) include fee

### General
- [ ] No PHP errors in error_log
- [ ] Backward compatibility: Old configs still work
- [ ] Domain fee defaults to 0 if not set
- [ ] Import logging shows detailed progress

---

## Code Review Notes

### Security
✓ Uses prepared statements in database queries
✓ Sanitizes input via `sanitize_key()`, `sanitize_text_field()`
✓ Uses `floatval()` for numeric type safety
✓ Doesn't store sensitive data in logs

### Performance
✓ Idempotent operations (safe to run multiple times)
✓ Recursive parser handles nested structures efficiently
✓ No N+1 database queries
✓ Logging uses conditional checks to avoid unnecessary calls

### Maintainability
✓ Clear method names and docstrings
✓ Logical separation of concerns
✓ Commented code sections explaining complex logic
✓ Follows existing code style and patterns

### Backward Compatibility
✓ No database schema changes
✓ No breaking changes to APIs
✓ Optional features default safely
✓ Existing configurations unaffected

