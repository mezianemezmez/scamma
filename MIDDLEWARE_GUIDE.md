# Middleware Integration Guide

## Overview
You now have two security middlewares integrated into your Laravel application:

1. **ReferrerAuth** - Validates request referrers and blocks malicious domains
2. **ClientAuth** - Validates client access based on antibots settings

## Middleware Registration
Both middlewares are registered in `bootstrap/app.php`:
- `referrer.auth` → `\App\Http\Middleware\ReferrerAuth::class`
- `client.auth` → `\App\Http\Middleware\ClientAuth::class`

## Features Added

### ReferrerAuth Middleware
- ✅ Blocks requests from 200+ malicious/security analysis domains
- ✅ Validates referrer headers against blocked domain list
- ✅ Skips validation for API routes with Bearer tokens (your React app)
- ✅ Skips validation for CSRF cookie requests
- ✅ Returns JSON responses for API requests, redirects for web requests
- ✅ Comprehensive logging of blocked requests

### ClientAuth Middleware
- ✅ Validates client access based on Antibots settings
- ✅ Checks for banned clients
- ✅ Detects and blocks crawlers using CrawlerDetect
- ✅ Validates allowed countries and operators
- ✅ Blocks blacklisted operators
- ✅ Enforces operator whitelists
- ✅ Returns appropriate responses for API vs web requests
- ✅ Enhanced logging with client details

## Database Changes
Added new fields to `clients` table:
- `country_code` (string, 2 chars) - For country validation
- `isp` (string) - For operator/ISP validation
- `why` (string) - Reason for banning

## Usage Examples

### Apply to specific routes:
```php
Route::middleware(['referrer.auth'])->group(function () {
    // Routes protected by referrer validation
});

Route::middleware(['client.auth'])->group(function () {
    // Routes protected by client validation
});

Route::middleware(['referrer.auth', 'client.auth'])->group(function () {
    // Routes protected by both middlewares
});
```

### Test Routes Created:
- `GET /client/{unique_id}` - Test client access (both middlewares)
- `POST /client/{unique_id}/action` - Test client action (both middlewares)
- `GET /404` - Error page for blocked requests

## Configuration

### Antibots Settings
Configure in your database `antibots` table:
- `antibots_protection` - Enable/disable client auth middleware
- `allowed_countries` - JSON array of allowed country codes
- `allowed_operators` - JSON array of allowed ISP names
- `blocker_operators` - JSON array of blocked ISP names

### Example Antibots Configuration:
```json
{
  "antibots_protection": true,
  "allowed_countries": ["US", "UK", "CA"],
  "allowed_operators": ["Verizon", "AT&T", "T-Mobile"],
  "blocker_operators": ["Suspicious ISP", "VPN Provider"]
}
```

## Testing
1. **Test with valid referrer**: Should pass through
2. **Test with blocked domain referrer**: Should return 403/redirect to 404
3. **Test with banned client**: Should return 403/redirect to 404
4. **Test API requests with Bearer token**: Should skip middleware validation

## API Integration
Your React app API calls are automatically excluded from these middlewares when using Bearer tokens, so your authenticated users won't be affected by these security measures.

## Logs
Both middlewares log security events to Laravel's log system:
- Invalid referrers
- Crawler detection
- Client banning events
- Country/operator violations

Check `storage/logs/laravel.log` for security events.
