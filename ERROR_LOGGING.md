# Error Logging and Debugging

## Overview

The Reseller Panel plugin now includes enhanced error logging and debugging capabilities to help diagnose API connection issues with NameCheap and OpenSRS providers.

## Features

### 1. Detailed Error Messages

When testing API connections, you will now receive:
- **Error Code**: A specific error code identifying the type of error
- **Error Message**: A detailed description of what went wrong
- **Debug Information**: Additional context including:
  - Provider name and key
  - Timestamp of the error
  - HTTP status codes
  - API endpoint information
  - Environment (sandbox/production)

### 2. Error Logging to File

All API errors are automatically logged to a file for later review and debugging.

#### Log File Location

Logs are stored in: `wp-content/uploads/reseller-panel-logs/errors.log`

#### Log File Security

- The log directory is protected with `.htaccess` to prevent direct web access
- An `index.php` file prevents directory listing
- Log files are stored in the WordPress uploads directory, which should already be secured

#### Log File Format

Each log entry includes:
```
[2026-01-19 00:06:03] Provider: NameCheap
Error: Invalid request IP
Context:
  error_code: api_error
  endpoint: https://api.sandbox.namecheap.com/api/xml.response
  http_code: 403
  environment: sandbox
  client_ip: 127.0.0.1
--------------------------------------------------------------------------------
```

### 3. UI Error Display

When a connection test fails, the admin interface will display:
1. A simple error message in the status area
2. An expandable "Connection Error Details" section with:
   - Full error message
   - Numbered list of debug information
   - Helpful suggestions for common issues

## Common Error Scenarios

### NameCheap

#### Invalid API Credentials
- **Error Code**: `api_error`
- **Message**: "API key is invalid"
- **Solution**: Verify your API User, API Key, and Username are correct

#### IP Not Whitelisted
- **Error Code**: `api_error`
- **Message**: "Invalid request IP"
- **Solution**: Add your server's IP address to the NameCheap API whitelist

#### Missing Credentials
- **Error Code**: `missing_credentials`
- **Message**: "NameCheap credentials are incomplete"
- **Solution**: Ensure all required fields (API User, API Key, Username) are filled in

### OpenSRS

#### Invalid API Key
- **Error Code**: `api_error`
- **Message**: Various authentication errors
- **Solution**: Verify your API Key (Private Key) and Reseller Username

#### Network Issues
- **Error Code**: `http_error`
- **Message**: "HTTP error XXX received from OpenSRS API"
- **Solution**: Check your server's network connectivity and firewall rules

## Accessing Logs

### Via FTP/SFTP

1. Connect to your WordPress installation
2. Navigate to `wp-content/uploads/reseller-panel-logs/`
3. Download `errors.log`

### Via WordPress Admin (Future Enhancement)

A log viewer interface may be added in a future update.

### Via Command Line

```bash
# View the last 50 lines of the log
tail -n 50 /path/to/wp-content/uploads/reseller-panel-logs/errors.log

# Watch the log in real-time
tail -f /path/to/wp-content/uploads/reseller-panel-logs/errors.log
```

## Troubleshooting

### Log File Not Created

If the log file is not being created:
1. Check that the WordPress uploads directory is writable
2. Verify PHP has permission to create directories
3. Check for any PHP errors in your server's error log

### Log File Too Large

The log file can be safely deleted - it will be recreated automatically:
```bash
rm /path/to/wp-content/uploads/reseller-panel-logs/errors.log
```

Or via FTP, simply delete the `errors.log` file.

## Privacy and Security

- **No Sensitive Data**: API keys and passwords are NOT logged
- **Protected Directory**: Logs are protected from direct web access
- **Server-Side Only**: Logs are only accessible to server administrators

## Support

When requesting support for API connection issues, please:
1. Take a screenshot of the error details from the admin interface
2. Include relevant log entries from the error log
3. Remove any sensitive information before sharing
