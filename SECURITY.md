# Security Implementation - Square Menu Display Premium Deluxe Pro

## Overview
This document outlines the security measures implemented to protect sensitive credentials and API tokens.

---

## Sensitive Data Protected

The following sensitive options are encrypted and protected from unauthorized access:

1. **`square_menu_access_token`** - Manual Square API access token
2. **`smdp_access_token`** - Legacy access token (for backward compatibility)
3. **`smdp_oauth_access_token`** - OAuth access token
4. **`smdp_oauth_refresh_token`** - OAuth refresh token
5. **`smdp_oauth_app_secret`** - OAuth application secret
6. **`smdp_square_webhook_signature_key`** - Webhook signature verification key

---

## Security Layers

### Layer 1: Encryption at Rest ✅

**File:** `includes/constants.php`

All sensitive tokens and keys are encrypted using AES-256-CBC before being stored in the database.

**Functions:**
- `smdp_encrypt($data)` - Encrypts data using WordPress salts
- `smdp_decrypt($encrypted_data)` - Decrypts encrypted data
- `smdp_store_access_token($token)` - Stores encrypted access token
- `smdp_get_access_token()` - Retrieves and decrypts access token
- `smdp_store_webhook_key($key)` - Stores encrypted webhook key
- `smdp_get_webhook_key()` - Retrieves and decrypted webhook key

**Encryption Details:**
- **Algorithm:** AES-256-CBC (Advanced Encryption Standard)
- **Key Derivation:** SHA-256 hash of WordPress authentication salts
- **IV:** Randomly generated for each encryption operation
- **Storage Format:** Base64-encoded (IV + encrypted data)

**Fallback:** If OpenSSL is not available, data is stored unencrypted with error logging.

---

### Layer 2: Hidden from options.php ✅

**File:** `Main.php` (lines 29-136)

WordPress's `options.php` page displays ALL database options by default, including encrypted values. This poses a security risk as administrators could copy encrypted tokens and attempt to decrypt them.

**Protection Implemented:**

#### A. Prevent Updates via options.php
```php
add_filter( 'pre_update_option', ... )
```
- Detects if an update attempt is coming from `options.php`
- Blocks updates to sensitive options
- Logs security events
- Returns old value to prevent modification

#### B. Remove from Allowed Options
```php
add_filter( 'allowed_options', ... )
```
- Removes sensitive options from all option groups
- Prevents them from appearing in settings forms
- Applies globally across WordPress admin

#### C. Mask Values When Displayed
```php
add_filter( 'option_square_menu_access_token', ... )
add_filter( 'option_smdp_oauth_access_token', ... )
add_filter( 'option_smdp_oauth_app_secret', ... )
add_filter( 'option_smdp_square_webhook_signature_key', ... )
```
- Detects when options are accessed from `options.php`
- Returns masked value: `********** (encrypted - hidden for security)`
- Uses debug backtrace to identify calling context
- Does NOT interfere with legitimate plugin operations

---

### Layer 3: Access Control ✅

**Capability Required:** `manage_options`

Only WordPress administrators can access plugin settings pages where tokens are configured.

**Multisite Protection:**
```php
add_filter( 'option_page_capability_options', ... )
```
- In multisite environments, only super admins can access `options.php`
- Returns `do_not_allow` capability for non-super-admins

---

### Layer 4: Input Validation ✅

**File:** `includes/constants.php` (lines 311-426)

All access tokens are validated before storage:

**Function:** `smdp_validate_access_token($token)`
- Removes whitespace
- Checks length (50-500 characters)
- Validates character set (alphanumeric + `-_.` only)
- Logs validation failures
- Returns `false` for invalid tokens

---

## Security Best Practices

### ✅ Implemented

1. **Encryption at rest** - All tokens encrypted using AES-256-CBC
2. **Hidden from UI** - Tokens masked/hidden in WordPress admin
3. **Input validation** - Tokens validated before storage
4. **Access logging** - Security events logged to error log
5. **Capability checks** - Only admins can modify settings
6. **Nonce verification** - All forms use WordPress nonces
7. **SQL injection prevention** - Using WordPress APIs only
8. **XSS prevention** - All output escaped with `esc_attr()`, `esc_html()`, etc.

### 🔒 Additional Recommendations

1. **SSL/TLS Required** - Always use HTTPS in production
2. **Regular Key Rotation** - Rotate OAuth tokens periodically
3. **Monitor Logs** - Check error logs for `[SMDP SECURITY]` entries
4. **Restrict File Permissions** - Set `wp-config.php` to 0600
5. **Use Environment Variables** - Consider moving secrets to `.env` file
6. **Database Backups** - Encrypt database backups containing tokens

---

## Threat Model

### Protected Against:

✅ **Database Exposure** - Tokens encrypted, not plain text
✅ **options.php Leakage** - Tokens masked and hidden
✅ **Unauthorized Modification** - Updates blocked via filters
✅ **SQL Injection** - Using WordPress prepared statements
✅ **XSS Attacks** - All output escaped
✅ **CSRF Attacks** - Nonce verification on all forms

### Still Vulnerable To:

⚠️ **Server Compromise** - If server is hacked, encryption key (WordPress salts) may be accessible
⚠️ **Database Theft** - Encrypted tokens could be brute-forced if encryption key is known
⚠️ **Admin Account Compromise** - Admin users can still access/modify tokens via plugin settings
⚠️ **Memory Dumps** - Decrypted tokens exist in memory during API calls

### Mitigations for Remaining Risks:

1. **Use OAuth instead of manual tokens** - OAuth tokens can be revoked remotely
2. **Enable 2FA for WordPress admin** - Reduces account compromise risk
3. **Use Web Application Firewall (WAF)** - Cloudflare, Sucuri, etc.
4. **Regular Security Audits** - Scan for malware and vulnerabilities
5. **Principle of Least Privilege** - Grant admin access only when necessary

---

## Testing Security

### How to Verify Protection:

1. **Go to:** `wp-admin/options.php` in your browser
2. **Search for:** `square_menu_access_token` or `smdp_oauth_access_token`
3. **Expected Result:**
   - Option should NOT appear in the list, OR
   - Value should show: `********** (encrypted - hidden for security)`

4. **Try to Edit:** Attempt to modify a sensitive option via options.php
5. **Expected Result:**
   - Update should be blocked
   - Old value should be preserved
   - Security event logged in error log

### Check Error Logs:

Look for entries like:
```
[SMDP SECURITY] Blocked attempt to modify sensitive option "square_menu_access_token" via options.php
```

---

## Security Incident Response

If you suspect a security breach:

1. **Immediately Revoke Tokens**
   - Log into Square Developer Dashboard
   - Revoke all OAuth tokens
   - Generate new credentials

2. **Check Access Logs**
   - Review WordPress error logs
   - Check server access logs for suspicious IPs
   - Look for `[SMDP SECURITY]` log entries

3. **Change WordPress Credentials**
   - Update all admin passwords
   - Rotate WordPress salts in `wp-config.php`
   - This invalidates encryption keys (tokens will need to be re-entered)

4. **Audit Database**
   - Run security scan plugins (Wordfence, Sucuri)
   - Check for malicious database entries
   - Review user accounts for unauthorized access

5. **Update Plugin**
   - Ensure you have the latest version
   - Check for security patches

---

## Compliance Notes

### PCI DSS (if processing payments)
- ✅ Encryption of sensitive data at rest
- ✅ Access control to sensitive data
- ✅ Audit logging of security events
- ⚠️ Consider additional PCI-compliant hosting

### GDPR (if storing EU customer data)
- ✅ Encryption of personal data
- ✅ Access controls
- ⚠️ Ensure data processing agreements with Square

---

## Contact

For security concerns or to report vulnerabilities:
- **Email:** [Your Security Contact Email]
- **Response Time:** Within 24-48 hours

**Do NOT disclose security vulnerabilities publicly until patched.**

---

## Version History

### v3.0 (Current)
- ✅ AES-256-CBC encryption for all tokens
- ✅ Hidden from options.php
- ✅ Input validation for tokens
- ✅ Security event logging
- ✅ Access control filters

### Previous Versions
- ⚠️ Tokens stored in plain text (INSECURE - upgrade immediately!)

---

## Developer Notes

### Adding New Sensitive Options

If you add new sensitive options to the plugin:

1. **Add to constants.php** encryption functions
2. **Add to Main.php** filter lists:
   - `pre_update_option` filter
   - `allowed_options` filter
   - Add new `option_{name}` masking filter
3. **Update this SECURITY.md** documentation
4. **Test** that option is hidden from options.php

### Example:
```php
// In Main.php
$sensitive_options = array(
    // ... existing options ...
    'your_new_sensitive_option', // Add here
);

// Add masking filter
add_filter( 'option_your_new_sensitive_option', function( $value ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $backtrace as $trace ) {
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'options.php' ) !== false ) {
                return '********** (encrypted - hidden for security)';
            }
        }
    }
    return $value;
});
```

---

**Last Updated:** 2025-01-XX
**Plugin Version:** 3.0
**Security Review Date:** 2025-01-XX
