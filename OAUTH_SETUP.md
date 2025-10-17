# Square OAuth Setup Guide

## Security Model: wp-config.php Constants

Your Square App credentials (**App ID** and **App Secret**) are stored in `wp-config.php` instead of the plugin code. This ensures:

✅ **Secret never exposed** in distributed plugin code
✅ **Not committed to version control** (wp-config.php is always in .gitignore)
✅ **Protected by WordPress** security practices
✅ **Each customer uses YOUR credentials** (one-click experience)

---

## For Plugin Developers (You)

### Step 1: Register Your App with Square

1. Go to https://developer.squareup.com/apps
2. Click "+" or "Create App"
3. Enter your app details:
   - **App Name**: Square Menu Display Premium Deluxe Pro
   - **Description**: WordPress plugin for displaying Square menus
4. Save and note your credentials:
   - **Application ID**: `sq0idp-xxxxx...`
   - **Application Secret**: `sq0csp-xxxxx...`

### Step 2: Configure OAuth Redirect URL

1. In your Square app settings, find "OAuth" section
2. Add Redirect URL: `https://example.com/wp-admin/admin.php?page=smdp_main&oauth_callback=1`
   - Replace `example.com` with your test site
   - For marketplace: Use `https://yourdomain.com/...` (your main site)
3. Set OAuth scopes:
   - `ORDERS_READ`
   - `ORDERS_WRITE`
   - `ITEMS_READ`
   - `ITEMS_WRITE`
   - `MERCHANT_PROFILE_READ`

### Step 3: Add Credentials to wp-config.php

Edit your `wp-config.php` file and add:

```php
/**
 * Square Menu Display Pro - OAuth Configuration
 * Add these lines BEFORE "That's all, stop editing!"
 */
define( 'SMDP_SQUARE_APP_ID', 'sq0idp-YOUR_ACTUAL_APP_ID_HERE' );
define( 'SMDP_SQUARE_APP_SECRET', 'sq0csp-YOUR_ACTUAL_APP_SECRET_HERE' );
```

**Important**: Replace with your actual credentials from Step 1!

### Step 4: Test OAuth Flow

1. Go to your WordPress admin: **Square Menu → Settings**
2. Click **"Connect with Square"**
3. You'll be redirected to Square authorization page
4. Click "Allow" to authorize
5. You'll be redirected back with success message

---

## For Customers (Setup Instructions)

When distributing your plugin, customers need to add YOUR credentials to their wp-config.php.

### Option 1: Automatic Setup (Recommended)

Provide customers with YOUR App ID and Secret via:
- License key activation email
- Setup wizard in plugin
- Support documentation

### Option 2: Manual Setup

Customers add to their `wp-config.php`:

```php
// Square Menu Display Pro OAuth
define( 'SMDP_SQUARE_APP_ID', 'sq0idp-XXXX' );  // You provide this
define( 'SMDP_SQUARE_APP_SECRET', 'sq0csp-XXXX' );  // You provide this
```

Then click "Connect with Square" button.

---

## Distribution Models

### Model A: WordPress.org (Free Version)

**Setup**:
1. Plugin includes OAuth code ✅
2. Customers add YOUR credentials to wp-config.php
3. One-click "Connect with Square"

**Pros**: One-click OAuth experience
**Cons**: Customers need wp-config.php access

---

### Model B: Square App Marketplace

**Setup**:
1. Submit plugin to Square marketplace
2. Square handles OAuth completely
3. Customers click "Install" from marketplace
4. Square manages authorization

**Pros**: True one-click, Square manages everything
**Cons**: Must go through Square approval process

---

### Model C: Premium with License System

**Setup**:
1. Customer purchases license
2. License activation provides wp-config.php snippet
3. Customer adds to wp-config.php
4. One-click "Connect with Square"

**Pros**: Monetization built-in, secure credentials
**Cons**: Requires licensing system

---

## Security Best Practices

### ✅ DO:
- Store App Secret in wp-config.php constants
- Use HTTPS for all OAuth redirects
- Validate nonces and state parameters
- Encrypt tokens in database
- Log OAuth errors for debugging

### ❌ DON'T:
- Hardcode App Secret in plugin files
- Commit wp-config.php to Git
- Store App Secret in database (database backups are often shared)
- Use HTTP for OAuth (Square requires HTTPS)
- Share your App Secret publicly

---

## Testing

### Sandbox Mode

1. Create sandbox Square account: https://developer.squareup.com/sandbox
2. Create sandbox app credentials
3. Add sandbox credentials to wp-config.php:
   ```php
   define( 'SMDP_SQUARE_SANDBOX', true );  // Enable sandbox mode
   define( 'SMDP_SQUARE_APP_ID', 'sandbox-sq0idp-...' );
   define( 'SMDP_SQUARE_APP_SECRET', 'sandbox-sq0csp-...' );
   ```
4. Test complete OAuth flow
5. Verify API calls work with sandbox merchants

### Production Mode

1. Switch to production credentials in wp-config.php
2. Remove or set `SMDP_SQUARE_SANDBOX` to false
3. Test with real Square account
4. Monitor error logs for issues

---

## Troubleshooting

### "OAuth not configured" warning

**Problem**: Plugin shows warning about missing OAuth config
**Solution**: Add `SMDP_SQUARE_APP_ID` and `SMDP_SQUARE_APP_SECRET` to wp-config.php

### "Invalid state parameter" error

**Problem**: OAuth callback fails with state mismatch
**Solution**: Clear browser cookies and try again (state expires after 10 minutes)

### "Redirect URI mismatch" error

**Problem**: Square rejects OAuth request
**Solution**: Verify redirect URL in Square app settings matches exactly:
- Must be HTTPS (except localhost in sandbox)
- Must match: `https://yoursite.com/wp-admin/admin.php?page=smdp_main&oauth_callback=1`

### Token expires quickly

**Problem**: Access token expires after 30 days
**Solution**: Plugin automatically refreshes token when it expires in < 7 days (runs hourly check)

---

## For Square Marketplace Submission

When submitting to Square App Marketplace:

1. **Remove wp-config.php requirement** - Square handles OAuth
2. **Update plugin to detect marketplace mode**:
   ```php
   // In your plugin
   if ( defined( 'SQUARE_MARKETPLACE_MODE' ) && SQUARE_MARKETPLACE_MODE ) {
       // Square handles OAuth, skip credential checks
   }
   ```
3. **Provide Square with OAuth callback URL**:
   `https://yourdomain.com/wp-admin/admin.php?page=smdp_main&oauth_callback=1`
4. **Complete Square's security review**
5. **Test with Square's QA team**

---

## Support Resources

- **Square OAuth Docs**: https://developer.squareup.com/docs/oauth-api/overview
- **WordPress Security**: https://wordpress.org/documentation/article/hardening-wordpress/
- **wp-config.php Best Practices**: https://wordpress.org/documentation/article/editing-wp-config-php/

---

## Quick Reference

| What | Where | Security Level |
|------|-------|----------------|
| App ID | wp-config.php constant | Public (safe to share) |
| App Secret | wp-config.php constant | **CONFIDENTIAL** (never share) |
| Access Token | Database (encrypted) | Confidential |
| Refresh Token | Database (encrypted) | Confidential |

**Remember**: App Secret in wp-config.php = Secure ✅
**Never**: Hardcode App Secret in plugin code = Insecure ❌
