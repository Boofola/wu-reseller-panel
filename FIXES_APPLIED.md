# Reseller Panel - Fixes Applied

## Summary

Three critical issues have been identified and resolved:

1. **Services Settings page showing empty** - No services were seeded in the database
2. **OpenSRS domain import failing** - XML parser wasn't extracting nested data structures
3. **OpenSRS pricing markup needed** - New domain fee feature added for custom pricing

---

## Fix 1: Services Settings Page Empty

### Problem
The "Services Settings" admin page was showing no services to configure, even though providers were active and API testing was successful. This was because the `reseller_panel_services` table was created but never populated with default services.

### Root Cause
The plugin's `create_tables()` function created the database schema but did not insert the default services (domains, ssl, hosting, emails, marketing).

### Solution Implemented
Added a new `populate_default_services()` method to the `Reseller_Panel` class that automatically creates the five standard services on plugin activation:

- **Domains** - Register, manage, and renew domain names
- **SSL Certificates** - SSL certificates for domain security  
- **Hosting** - Web hosting services
- **Email** - Email services
- **Marketing** - Marketing services

**File Modified:** `inc/class-reseller-panel.php`

**Changes:**
- Added call to `$this->populate_default_services()` in the `create_tables()` method
- Added `populate_default_services()` method that checks if services exist before inserting them
- All services are disabled by default, allowing admins to enable only those they need

### How to Use
1. After updating the plugin code, deactivate and reactivate the plugin
2. OR run the plugin activation hook programmatically
3. Navigate to "Reseller Panel → Services Settings"
4. Services should now be visible in the table
5. Configure which providers to use for each service

---

## Fix 2: OpenSRS Domain Import - "No Domains Found"

### Problem
When attempting to import domains from OpenSRS, the importer would fail with the error:
```
✗ No domains found from provider
```

This occurred even though:
- Provider credentials were correct
- API test connection was successful
- OpenSRS has domains available in the reseller account

### Root Cause
The XML response parser (`parse_xml_response()`) only extracted top-level response fields (`is_success`, `response_code`, `response_text`) but did not handle nested XML structures (`dt_array` and `dt_assoc`). 

OpenSRS API responses for operations like `GET_TLDLIST` return nested data structures that require recursive parsing. The TLD list data was present in the response but not being extracted.

### Solution Implemented
Enhanced the XML response parser to recursively extract nested data structures:

1. **Added `extract_nested_xml_data()` method** - Iterates through all items with keys and detects nested structures
2. **Added `parse_dt_array()` method** - Recursively parses OpenSRS `dt_array` elements (indexed arrays)
3. **Added `parse_dt_assoc()` method** - Recursively parses OpenSRS `dt_assoc` elements (associative arrays)

**File Modified:** `inc/providers/class-opensrs-provider.php`

**What This Fixes:**
- GET_TLDLIST responses now properly extract the `tld_list` array
- Any future OpenSRS API calls returning nested structures will work correctly
- Maintains backward compatibility with simple responses

### How It Works
The parser now:
1. Extracts top-level response metadata (is_success, response_code, response_text)
2. Iterates through all items in the response
3. For each item, checks if it contains nested `dt_array` or `dt_assoc` structures
4. Recursively parses nested structures to extract all data
5. Returns a complete, flattened PHP array

### Enhanced Logging
The domain importer now logs detailed information about the import process:

**File Modified:** `inc/importers/class-domain-importer.php`

**Logging Added:**
- When import starts (provider name, key)
- When domains are retrieved (count of domains returned)
- If errors occur (detailed error messages)
- When import completes (counts of imported/updated/skipped/errors)

This makes it easy to debug import issues using WordPress error logs.

---

## Fix 3: OpenSRS Domain Pricing Markup

### Problem
OpenSRS changed their pricing model and no longer provides pricing in the reseller panel. The plugin needed a way for resellers to add a custom markup/fee to the base OpenSRS pricing.

### Solution Implemented
Added a new optional `domain_fee` configuration field to the OpenSRS provider:

**File Modified:** `inc/providers/class-opensrs-provider.php`

**New Configuration Field:**
- **Field Name:** Domain Markup Fee (Optional)
- **Type:** Text input (accepts decimal values)
- **Description:** Additional fee or markup to add to all domain prices
- **Example:** Enter "2.50" to add $2.50 to every domain price

### How It Works
1. Admin configures the OpenSRS provider
2. Admin enters the desired markup in "Domain Markup Fee" field (e.g., 2.50)
3. When domains are imported via `get_domains()`:
   - Base prices are retrieved from OpenSRS
   - The markup fee is added to **all** pricing types:
     - Registration price
     - Renewal price
     - Transfer price
4. Domains are created with the marked-up prices in Ultimate Multisite

### Example
If OpenSRS has:
- .COM registration: $8.99/year
- .COM renewal: $8.99/year
- .COM transfer: $8.99/year

And admin sets domain fee to 2.50:
- .COM registration: $11.49/year
- .COM renewal: $11.49/year
- .COM transfer: $11.49/year

### How to Configure
1. Go to **Reseller Panel → Provider Settings → OpenSRS**
2. Scroll to "Domain Markup Fee (Optional)"
3. Enter the fee amount (e.g., 2.50, 0.99, 5.00)
4. Leave blank for no markup
5. Click "Save OpenSRS Settings"
6. Import or sync domains - they will include the markup

### Configuration Storage
The domain fee is stored in the provider config alongside other settings:
- Database: `wp_sitemeta` (or network options)
- Option key: `reseller_panel_opensrs_config`
- Field key: `domain_fee`

---

## Testing the Fixes

### Test Fix 1 - Services Settings
1. Deactivate and reactivate the plugin
2. Go to "Reseller Panel → Services Settings"
3. ✓ You should see 5 services listed:
   - Domains
   - SSL Certificates
   - Hosting
   - Email
   - Marketing
4. ✓ All should be disabled by default

### Test Fix 2 - Domain Import
1. Configure OpenSRS provider with valid credentials
2. Test the connection (should succeed)
3. Go to an admin page with domain import functionality
4. Click "Import Domains from OpenSRS"
5. ✓ Should see domains being imported instead of "No domains found" error
6. ✓ Check WordPress error logs for detailed import progress

### Test Fix 3 - Domain Fee
1. Go to "Reseller Panel → Provider Settings → OpenSRS"
2. In "Domain Markup Fee" field, enter: 2.50
3. Save settings
4. Import domains
5. ✓ Check domain prices - they should be $2.50 higher than OpenSRS base prices
6. ✓ All three price types (registration, renewal, transfer) should include the fee

---

## Database Changes

### New Data
The `reseller_panel_services` table is now seeded with:

```
service_key | service_name      | enabled | default_provider | fallback_provider
------------|-------------------|---------|------------------|-------------------
domains     | Domains           | 0       | NULL             | NULL
ssl         | SSL Certificates  | 0       | NULL             | NULL
hosting     | Hosting           | 0       | NULL             | NULL
emails      | Email             | 0       | NULL             | NULL
marketing   | Marketing         | 0       | NULL             | NULL
```

### Modified Fields
No existing fields were changed. The OpenSRS provider's `config` field now stores:
```json
{
  "api_key": "...",
  "username": "...",
  "environment": "live",
  "enabled": 1,
  "domain_fee": "2.50"
}
```

---

## Backward Compatibility

All changes are fully backward compatible:

- Existing provider configurations continue to work
- `domain_fee` is optional and defaults to 0 (no markup)
- XML parser changes only add functionality, don't remove it
- Services table seeding only creates missing services

---

## Troubleshooting

### Services still not showing
- Clear browser cache
- Check plugin activation logs in `wp-content/debug.log`
- Verify database table exists: `wp_reseller_panel_services`

### Domains still not importing
- Check error logs for XML parsing errors
- Verify OpenSRS API credentials are correct
- Run test connection - should show balance success
- Enable WordPress debug logging to see detailed import logs

### Domain fee not applying
- Verify fee is entered in OpenSRS settings (not NameCheap)
- Use decimal format (e.g., 2.50, not 2,50)
- Reimport domains after changing fee
- Check domain product prices in Ultimate Multisite

---

## Code Quality

All modified files have been:
- ✓ Checked for PHP syntax errors
- ✓ Tested for logical flow
- ✓ Enhanced with detailed logging
- ✓ Documented with inline comments
- ✓ Maintained backward compatibility

