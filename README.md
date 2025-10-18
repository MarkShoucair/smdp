# Square Menu Display Premium Deluxe Pro

**Version:** 3.0 (Security Hardened)
**Author:** Mark Shoucair (30%) & Claude AI & ChatGPT (70%)
**Requires WordPress:** 5.0 or higher
**Requires PHP:** 7.4 or higher
**License:** GPL-3.0

---

## 📖 Table of Contents

1. [Overview](#-overview)
2. [Features](#-features)
3. [Installation](#-installation)
4. [Initial Setup](#-initial-setup)
5. [Square Authentication](#-square-authentication)
6. [Shortcodes](#-shortcodes)
7. [Admin Features](#-admin-features)
8. [Webhook Configuration](#-webhook-configuration)
9. [PWA Features](#-pwa-features)
10. [Help & Bill Request System](#-help--bill-request-system)
11. [Troubleshooting](#-troubleshooting)
12. [Developer Documentation](#-developer-documentation)
13. [Security](#-security)
14. [FAQ](#-faq)
15. [Support & Resources](#-support--resources)
16. [Technical Specifications](#-technical-specifications)

---

## 🎯 Overview

**Square Menu Display Premium Deluxe Pro** is a comprehensive WordPress plugin that integrates Square POS with your WordPress website to display dynamic, real-time menu items. Perfect for restaurants, cafes, bars, and any business using Square for point-of-sale.

### What Makes It "Premium Deluxe Pro"?

- ✅ **Real-time catalog sync** from Square to WordPress
- ✅ **Automatic webhook integration** for instant updates
- ✅ **Progressive Web App (PWA)** support for offline access
- ✅ **Sold-out status tracking** with manual overrides
- ✅ **Drag-and-drop menu editor** with visual organization
- ✅ **Help & Bill request system** for table service
- ✅ **OAuth 2.0 authentication** with automatic token refresh
- ✅ **Category management** with hide/show toggle
- ✅ **Modifier lists support** with selective display
- ✅ **Responsive design** works on all devices
- ✅ **Enterprise-grade security** with encryption and rate limiting

### 🤖 AI-Powered Development

This plugin was developed with assistance from **Claude (Anthropic)** and **ChatGPT (OpenAI)**. The entire development leveraged AI to:

- Architect the plugin structure and file organization
- Implement complex features like OAuth 2.0, webhooks, and PWA
- Create security hardening with encryption and rate limiting
- Design responsive layouts and user interfaces
- Debug and refine functionality through iterative conversations
- Ensure WordPress coding standards and best practices

The collaboration between human creativity and AI technical capabilities resulted in a robust, production-ready plugin with enterprise-grade security.

---

## ✨ Features

### Core Features

#### 1. **Menu Display**
- Display Square catalog items on your WordPress site
- Automatic synchronization with Square inventory
- Real-time sold-out status updates
- Category-based filtering
- Responsive grid layout (3 columns by default)
- Item images, descriptions, and pricing
- Modifier lists (add-ons/customizations)

#### 2. **Admin Management**
- Visual drag-and-drop menu editor
- Category creation and organization
- Item ordering within categories
- Hide/show individual images
- Manual sold-out overrides
- Category visibility toggle
- Item-to-category assignment

#### 3. **Synchronization**
- Manual sync on-demand
- Scheduled automatic sync (hourly/daily/custom)
- Webhook-based instant sync
- Rate-limited to prevent abuse
- Error notifications for failed syncs
- API request/response logging

#### 4. **Authentication Options**
- **OAuth 2.0** - Merchant-specific tokens (recommended)
- **Personal Access Token** - Application-level access
- Automatic token refresh (OAuth)
- Encrypted token storage (AES-256-CBC)
- Token expiration monitoring

#### 5. **Progressive Web App (PWA)**
- Service worker caching
- Offline menu access
- Fast page loads
- App-like experience on tablets
- Auto-update detection
- Debug mode for development

#### 6. **Table Service Features**
- Customer help requests
- Bill requests
- View current bill
- Table number assignment
- Dual lookup methods (Customer ID or Table Item)

---

## 📦 Installation

### Method 1: Manual Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: FTP Installation

1. Unzip the plugin folder
2. Upload the `square-menu-display` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin > Plugins**
4. Find "Square Menu Display Premium Deluxe Pro" and click **Activate**

### Method 3: WP-CLI

```bash
wp plugin install /path/to/square-menu-display.zip --activate
```

---

## 🚀 Initial Setup

### Step 1: Access Plugin Settings

After activation, go to **WordPress Admin > Square Menu**

You'll see the following menu items:
- **Settings** - Main configuration page
- **Categories** - Manage menu categories
- **Menu Editor** - Visual drag-and-drop interface
- **Items** - List view with manual overrides
- **Help & Bill** - Table service configuration
- **Webhooks** - Automatic sync setup
- **Modifiers** - Control which add-ons appear
- **API Log** - View Square API communication

### Step 2: Choose Authentication Method

You have two options:

#### **Option A: OAuth 2.0** (Recommended for merchants)

✅ **Pros:**
- Merchant-specific access
- Automatic token refresh
- More secure for multi-user environments
- Easier to revoke access

❌ **Cons:**
- Webhooks not supported (OAuth tokens are merchant-level)
- Requires Square Developer account
- Multi-step setup

#### **Option B: Personal Access Token** (Recommended for webhooks)

✅ **Pros:**
- Full webhook support
- Application-level features
- Simpler setup
- Works with scheduled/manual sync

❌ **Cons:**
- Manual token management
- No automatic refresh
- Single token for all merchants

---

## 🔐 Square Authentication

### Method 1: OAuth 2.0 Setup

#### Prerequisites
- Square Developer account
- Registered Square Application

#### Steps

**1. Create Square Application**
- Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
- Click **Create App** or select existing app
- Note your **Application ID** (starts with `sq0idp-`)
- Generate a **Secret** (starts with `sq0csp-`)

**2. Configure OAuth Credentials**
- Go to **Square Menu > Settings**
- Scroll to "Square Connection" section
- Click **Update OAuth credentials** (if already configured)
- Enter your **Application ID**
- Enter your **Application Secret**
- Click **Save OAuth Credentials**

**3. Set Redirect URI in Square**
- Copy the redirect URI shown: `https://yoursite.com/wp-admin/admin.php?page=smdp_main&oauth_callback=1`
- Go to your Square app settings
- Add this URI to **Redirect URLs**
- Save in Square Developer Dashboard

**4. Connect Your Square Account**
- Click **Connect with Square** button
- Authorize the connection in Square
- You'll be redirected back to WordPress
- Success message will appear

**5. Verify Connection**
- Check for green checkmark: "✓ Connected to Square"
- Token status shows days remaining
- Test with **Sync Now** button

---

### Method 2: Personal Access Token Setup

#### Steps

**1. Generate Personal Access Token**
- Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
- Select your application (or create one)
- Go to **Credentials** tab
- Copy **Access Token** (Production or Sandbox)

**2. Enter Token in WordPress**
- Go to **Square Menu > Settings**
- Scroll to "Square Access Token" field
- Paste your access token
- Click **Save Settings**

**3. Configure Location ID**
- Go to **Square Menu > Help & Bill**
- Enter your Square **Location ID**
  - Find in Square Dashboard > Locations
  - Or use Square API Explorer
- Click **Save Help & Bill Settings**

**4. Test Connection**
- Click **Sync Now** on Settings page
- Check for success message
- Verify items appear in **Items** page

---

## 📋 Shortcodes

### Primary Shortcode: `[square_menu]`

Displays menu items from a specific category.

#### Basic Usage

```php
[square_menu category="lunch-menu"]
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category` | string | Yes | Category slug (lowercase, hyphenated) |

#### Examples

**Display Lunch Menu:**
```php
[square_menu category="lunch-menu"]
```

**Display Drinks Menu:**
```php
[square_menu category="drinks"]
```

**Display Dinner Specials:**
```php
[square_menu category="dinner-specials"]
```

#### How to Get Category Slugs

1. Go to **Square Menu > Categories**
2. Look at the "Slug" column
3. Use the slug in your shortcode

Example:
- **Category Name:** "Lunch Menu"
- **Slug:** `lunch-menu`
- **Shortcode:** `[square_menu category="lunch-menu"]`

---

### Help Request Shortcodes

Used for table service in restaurants.

#### `[smdp_request_help]`

Creates a "Request Help" button for customers to notify staff.

**Usage:**
```php
[smdp_request_help table="5"]
```

**Parameters:**
- `table` - Table number (required)

**What It Does:**
- Creates $0 order in Square with "Request Help" item
- Staff sees notification in Square POS
- Table number appears in order notes

---

#### `[smdp_request_bill]`

Creates a "Request Bill" button for customers.

**Usage:**
```php
[smdp_request_bill table="5"]
```

**Parameters:**
- `table` - Table number (required)

**What It Does:**
- Creates $0 order in Square with "Request Bill" item
- Staff sees bill request in Square POS
- Table number included in order

---

### Example: Complete Table Service Page

```html
<h1>Table 5 - Menu</h1>

<!-- Display the menu -->
[square_menu category="dinner-menu"]

<!-- Service buttons -->
<div style="margin-top: 20px;">
  [smdp_request_help table="5"]
  [smdp_request_bill table="5"]
</div>
```

---

### Menu App Builder Shortcode: `[smdp_menu_app]`

**Alternative menu display with enhanced tablet/PWA features.**

#### What is the Menu App?

The Menu App Builder creates a **tablet-optimized, app-like menu experience** with:
- Category navigation bar (top or left layout)
- Automatic category switching
- Promo screen with idle timeout
- PWA installation support
- Customizable button styles
- Full-screen idle promo images

#### Usage

```php
[smdp_menu_app id="default"]
```

#### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | `default` | Menu configuration ID |
| `layout` | string | `top` | Layout: `top` or `left` |

#### Examples

**Basic Usage:**
```php
[smdp_menu_app id="default"]
```

**Left Layout (for landscape tablets):**
```php
[smdp_menu_app id="default" layout="left"]
```

#### Key Differences from `[square_menu]`

| Feature | `[square_menu]` | `[smdp_menu_app]` |
|---------|-----------------|-------------------|
| **Purpose** | Display single category | Display all categories with tabs |
| **Navigation** | None (manual page switching) | Automatic category tabs |
| **Layout** | Simple grid | App-like with navigation bar |
| **PWA Support** | Basic | Full (icons, manifest, offline) |
| **Promo Screen** | No | Yes (idle timeout) |
| **Tablet Optimized** | No | Yes |
| **Best For** | Website pages | Dedicated tablet kiosks |

#### Configuration

All menu app settings are managed in **WordPress Admin > Menu App Builder**:

##### 1. App Layout Tab

**Category Layout:**
- **Top Layout** - Horizontal category buttons across the top (best for portrait tablets)
- **Left Layout** - Vertical category sidebar on the left (best for landscape tablets)

**Promo Screen Settings:**
- **Idle Timeout** - Number of seconds of inactivity before showing promo screen (default: 30)
- **Promo Images** - Upload multiple images for slideshow rotation
- **Image Upload** - Drag-and-drop or click to upload
- **Remove Images** - Click X to remove uploaded images

**PWA Configuration:**
- **PWA Name** - App name shown on home screen (e.g., "Our Restaurant Menu")
- **PWA Short Name** - Abbreviated name (e.g., "Menu")
- **Theme Color** - Browser toolbar color (hex code)
- **Background Color** - Splash screen background (hex code)
- **PWA Icon** - Upload 512x512px icon for home screen

**Custom CSS:**
- Add custom CSS to override default menu app styles
- Supports all standard CSS properties
- Applied only to menu app pages (not regular shortcode)

##### 2. Styles Tab

**Category Button Appearance:**
- **Background Color** - Button background (default: #333)
- **Text Color** - Button text color (default: #fff)
- **Active Background** - Selected button background (default: #007cba)
- **Active Text Color** - Selected button text (default: #fff)
- **Border Radius** - Button corner rounding in pixels
- **Padding** - Button internal spacing
- **Margin** - Space between buttons

**Typography:**
- **Font Family** - Choose from system fonts or web fonts
- **Font Size** - Category button text size
- **Font Weight** - Normal, bold, etc.

**Live Preview:**
- See changes in real-time as you adjust settings
- Preview shows actual button appearance
- No need to save to see preview

##### 3. Items Tab

**Functionality:**
- Embedded version of main Menu Editor
- Full drag-and-drop support
- Organize items within categories
- Hide/show item images
- Move items between categories
- All changes saved to main catalog

**Note:** Changes made here affect both `[square_menu]` and `[smdp_menu_app]` displays.

##### 4. Categories Tab

**Category Management:**
- **Reorder Categories** - Drag to reorder display sequence
- **Hide Categories** - Checkbox to exclude from menu app
- **Category Name** - Display name (editable)
- **Slug** - URL-friendly identifier (auto-generated)

**Embedded Manager:**
- Same functionality as main Categories page
- Add new categories
- Delete categories
- Changes sync across plugin

---

#### Setup Guide

**Complete setup walkthrough for Menu App Builder:**

**Step 1: Initial Configuration**
1. Go to **WordPress Admin > Menu App Builder**
2. Click **App Layout** tab
3. Choose layout (top or left)
4. Set idle timeout (e.g., 30 seconds)
5. Upload at least one promo image
6. Click **Save Settings**

**Step 2: Customize Appearance**
1. Click **Styles** tab
2. Set category button colors
3. Adjust font size and weight
4. Use live preview to verify appearance
5. Click **Save Settings**

**Step 3: Configure PWA**
1. Return to **App Layout** tab
2. Scroll to PWA Configuration
3. Enter PWA name (e.g., "Our Menu")
4. Enter short name (e.g., "Menu")
5. Set theme and background colors
6. Upload 512x512px icon (PNG format)
7. Click **Save Settings**

**Step 4: Organize Content**
1. Click **Items** tab
2. Drag items to reorder within categories
3. Hide unwanted item images
4. Click **Save**

**Step 5: Manage Categories**
1. Click **Categories** tab
2. Drag categories to desired order
3. Hide categories you don't want in menu app
4. Click **Save**

**Step 6: Add to Page**
1. Create new WordPress page (e.g., "Menu")
2. Add shortcode: `[smdp_menu_app id="default"]`
3. Publish page
4. Visit page to test

---

#### Use Cases

**1. Restaurant Tablet Kiosks**
```php
[smdp_menu_app id="default" layout="top"]
```
- Customers browse entire menu on table-mounted tablets
- Idle timeout shows promotional content
- PWA enables offline access
- Category tabs for easy navigation

**2. Bar Menu Display**
```php
[smdp_menu_app id="default" layout="left"]
```
- Landscape-oriented tablets behind bar
- Staff can quickly navigate categories
- No page reloads between sections
- Promo screen shows drink specials

**3. Coffee Shop Self-Service**
```php
[smdp_menu_app id="default"]
```
- Counter-top tablet for customer browsing
- Automatic return to promo screen after 30 seconds
- PWA installation on device home screen
- Works offline during internet outages

**4. Food Truck Menu Board**
```php
[smdp_menu_app id="default" layout="top"]
```
- Mounted tablet visible to customers
- Quick category switching
- Large, readable buttons
- Weather-resistant tablet enclosure

---

#### PWA Installation Guide

**For Customers (Adding to Home Screen):**

**On iOS (iPhone/iPad):**
1. Open menu page in Safari
2. Tap Share button (box with arrow)
3. Scroll and tap "Add to Home Screen"
4. Edit name if desired
5. Tap "Add"
6. Icon appears on home screen

**On Android:**
1. Open menu page in Chrome
2. Tap three-dot menu
3. Tap "Add to Home Screen"
4. Confirm app name
5. Tap "Add"
6. Icon appears on home screen

**On Desktop (Chrome/Edge):**
1. Visit menu page
2. Look for install icon in address bar
3. Click "Install"
4. App opens in standalone window

---

#### Advanced Customization

**Custom CSS Examples:**

**Make Category Buttons Larger:**
```css
.menu-app-category-button {
    font-size: 20px !important;
    padding: 15px 30px !important;
}
```

**Change Item Card Style:**
```css
.menu-app-item-card {
    border-radius: 12px !important;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
}
```

**Customize Promo Screen:**
```css
.menu-app-promo-screen {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}
```

**Hide Prices on Display:**
```css
.menu-app-item-price {
    display: none !important;
}
```

---

#### Troubleshooting Menu App

**Issue: Promo screen not appearing**
- **Solution:** Check idle timeout setting is > 0
- **Solution:** Verify promo image is uploaded
- **Solution:** Ensure JavaScript is enabled
- **Solution:** Clear browser cache

**Issue: Categories not showing**
- **Solution:** Go to Categories tab and verify categories aren't hidden
- **Solution:** Ensure categories have items assigned
- **Solution:** Check items are not all sold out
- **Solution:** Run catalog sync

**Issue: PWA won't install**
- **Solution:** Ensure site uses HTTPS
- **Solution:** Verify service worker is registered (check browser console)
- **Solution:** Upload valid PWA icon (512x512px PNG)
- **Solution:** Check manifest.json is accessible

**Issue: Styles not applying**
- **Solution:** Clear browser cache
- **Solution:** Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)
- **Solution:** Increment cache version in Settings
- **Solution:** Check custom CSS for syntax errors

**Issue: Items showing wrong order**
- **Solution:** Go to Items tab and reorganize
- **Solution:** Click Save after reordering
- **Solution:** Clear PWA cache (Settings > Increment Cache Version)
- **Solution:** Hard refresh browser

---

## 🎨 Admin Features

### 1. Settings Page (`Square Menu > Settings`)

#### Square Connection
- OAuth connection status
- Token expiration date
- Connect/Disconnect buttons
- Manual token entry (alternative to OAuth)

#### Sync Configuration
- **Sync Interval:** Choose hourly, 6 hours, 12 hours, or daily
- **Manual Sync Only:** Disable automatic syncing
- **Sync Now Button:** Trigger immediate sync

#### PWA Debug Mode
- **Enable Debug Mode:** Bypass caching during development
- **Cache Version:** Force clients to reload assets
- Shows debug panel on frontend
- Logs service worker activity

#### Actions
- **Sync Now** - Manually trigger catalog sync
- **Clear All Cached Data** - Remove all cached items (keeps token)

---

### 2. Categories Page (`Square Menu > Categories`)

Manage menu categories independently of Square.

#### Features
- **Add New Category:** Create custom categories
- **Delete Category:** Remove unused categories
- **Category List:** View name, slug, order

#### Usage

**Add Category:**
1. Enter category name (e.g., "Lunch Specials")
2. Click **Add Category**
3. Slug is auto-generated (`lunch-specials`)

**Delete Category:**
1. Click **Delete** next to category
2. Confirm deletion
3. Items remain but become "unassigned"

---

### 3. Menu Editor Page (`Square Menu > Menu Editor`)

Visual drag-and-drop interface for organizing items.

#### Features
- **Drag & Drop:** Reorder items within categories
- **Category Sections:** Collapsible category groups
- **Item Thumbnails:** Visual preview of items
- **Hide Image Toggle:** Show/hide specific item images
- **Bulk Actions:** Move multiple items at once

#### Usage

**Reorder Items:**
1. Drag item to new position
2. Items auto-sort within category
3. Click **Save** to persist order

**Hide Item Image:**
1. Click eye icon next to item
2. Image won't display on frontend
3. Item still shows name, description, price

**Move Item to Category:**
1. Drag item to different category section
2. Item immediately moves
3. Click **Save** to confirm

---

### 4. Items List Page (`Square Menu > Items`)

List view with advanced controls.

#### Features
- **Thumbnails:** See item images
- **Square Category:** View reporting category from Square
- **Menu Category:** Assign plugin category
- **Sold Out Status:** View Square status and plugin override
- **Bulk Actions:** Update multiple items at once

#### Actions

**Match Square Categories:**
- Copies reporting categories from Square to plugin
- Useful after Square catalog reorganization

**Sync Sold Out Status:**
- Copies sold-out status from Square
- Creates overrides in plugin
- Updates all items at once

**Manual Override:**
- Dropdown: "Sold Out" / "Available"
- Overrides Square status
- Persists across syncs

---

### 5. Help & Bill Page (`Square Menu > Help & Bill`)

Configure table service features.

#### Settings

**Help Item:**
- Square catalog item used for help requests
- Usually a $0 "Request Help" item
- Searchable dropdown with all items

**Bill Item:**
- Square catalog item used for bill requests
- Usually a $0 "Request Bill" item
- Separate from help item

**Location ID:**
- Your Square location ID
- Required for order creation

**Bill Lookup Method:**
- **Customer ID Method:** Match by Square customer
- **Table Item Method:** Match by catalog item on order

---

### 6. Webhooks Page (`Square Menu > Webhooks`)

Configure automatic catalog syncing.

#### Requirements
- ✅ Personal Access Token (not OAuth)
- ✅ Valid Square credentials
- ✅ HTTPS enabled on WordPress site
- ✅ REST API accessible publicly

#### Features

**Activate Webhooks:**
- One-time setup button
- Automatically creates webhook subscription in Square
- Syncs signature key securely

**Refresh Webhooks:**
- Updates signature keys without creating new subscriptions
- Safe to run multiple times

**Webhook List:**
- Shows all webhook subscriptions
- Displays event types and URLs

---

### 7. Modifiers Page (`Square Menu > Modifiers`)

Control which modifier lists appear on frontend.

#### Features
- **Checkbox List:** All modifier lists from Square
- **Show/Hide:** Toggle visibility
- **Bulk Update:** Save all changes at once

---

### 8. API Log Page (`Square Menu > API Log`)

View recent Square API communications.

#### Information Displayed
- **Timestamp:** When request was made
- **API Request:** Full URL and parameters
- **API Response:** Raw JSON response (formatted)
- **Last 10 Requests:** Most recent kept

---

## 🔔 Webhook Configuration

### Quick Setup (Recommended)

**1. Go to Webhooks Page**
```
WordPress Admin > Square Menu > Webhooks
```

**2. Click "Activate Webhooks"**
- Automatically creates webhook subscription
- Syncs signature key
- Configures event type: `catalog.version.updated`

**3. Verify Setup**
- Check "Existing Subscriptions" list
- Confirm signature key is stored
- Test by updating item in Square

### How It Works

1. Square sends webhook when catalog changes
2. Plugin verifies HMAC-SHA256 signature
3. If valid, triggers immediate catalog sync
4. Menu updates automatically on frontend
5. Invalid signatures are rejected (security)

---

## 📱 PWA Features

### What is PWA?

Progressive Web App technology allows your menu to:
- Work offline (cached data)
- Load instantly (service worker)
- Update automatically (background sync)
- Feel like a native app (no browser chrome)
- Install on device home screen

### Debug Mode

**Enable:**
1. Go to **Square Menu > Settings**
2. Check **Enable Debug Mode**
3. Click **Save Settings**

**What It Does:**
- Bypasses service worker caching
- Shows debug panel on frontend
- Logs all cache operations to console

**Disable in Production!**

### Cache Version Management

**When to Increment:**
- After plugin update
- After CSS/JavaScript changes
- When items appear outdated on tablets

**How:**
1. Go to **Settings** page
2. Find **Cache Version** field
3. Click **Increment Version** button
4. Click **Save Settings**

---

## 🍽️ Help & Bill Request System

### Setup Guide

#### Step 1: Create Square Items

**Help Request Item:**
1. Go to Square Dashboard > Items
2. Create new item: "Request Help"
3. Set price: $0.00

**Bill Request Item:**
1. Create another item: "Request Bill"
2. Set price: $0.00

#### Step 2: Configure in WordPress

1. Go to **Help & Bill** page
2. Search and select Help item
3. Search and select Bill item
4. Enter Location ID
5. Choose lookup method (Customer ID or Table Item)

#### Step 3: Create Table Pages

Example page for Table 5:

```php
<h1>Table 5</h1>

[square_menu category="dinner"]

<div class="service-buttons">
  [smdp_request_help table="5"]
  [smdp_request_bill table="5"]
</div>
```

---

## 🔧 Troubleshooting

### Common Issues

#### Issue: Menu Not Displaying

**Solutions:**
1. Check category slug matches shortcode
2. Run manual sync
3. Verify access token is valid
4. Assign items to categories

#### Issue: Items Not Syncing

**Solutions:**
1. Check sync mode is not "Manual Only"
2. Verify webhooks are active
3. Check API permissions
4. Force sync manually

#### Issue: Webhooks Not Working

**Solutions:**
1. Verify using Personal Access Token (not OAuth)
2. Ensure HTTPS is enabled
3. Check signature key is stored
4. Test webhook endpoint

#### Issue: OAuth Connection Fails

**Solutions:**
1. Verify credentials are correct
2. Check redirect URI matches exactly
3. Clear browser cache
4. Enable debug mode

---

## 👨‍💻 Developer Documentation

### Hooks & Filters

#### Actions

**`smdp_after_sync`**
```php
add_action('smdp_after_sync', function($items_count) {
    error_log("Synced {$items_count} items");
});
```

**`smdp_before_sync`**
```php
add_action('smdp_before_sync', function() {
    wp_cache_flush();
});
```

#### Filters

**`smdp_menu_items`**
```php
add_filter('smdp_menu_items', function($items, $category_id) {
    // Modify items before display
    return $items;
}, 10, 2);
```

**`smdp_item_price`**
```php
add_filter('smdp_item_price', function($price, $item_id) {
    return $price * 1.10; // Add 10% service charge
}, 10, 2);
```

### REST API Endpoints

**GET `/wp-json/smdp/v1/webhook`**
```bash
curl https://yoursite.com/wp-json/smdp/v1/webhook
# Returns: {"alive":true}
```

**POST `/wp-json/smdp/v1/webhook`**
Receives Square webhooks with signature verification.

### Encryption

**Encrypt Data:**
```php
$encrypted = smdp_encrypt('sensitive data');
update_option('my_encrypted_option', $encrypted);
```

**Decrypt Data:**
```php
$encrypted = get_option('my_encrypted_option');
$decrypted = smdp_decrypt($encrypted);
```

---

## 🔒 Security

### Security Features

- ✅ **AES-256-CBC encryption** for sensitive data
- ✅ **OAuth 2.0** with automatic token refresh
- ✅ **HMAC-SHA256** webhook signature verification
- ✅ **Multi-factor rate limiting** (IP + User-Agent + User ID)
- ✅ **Input validation** for Square IDs
- ✅ **CSRF protection** with nonces
- ✅ **SQL injection prevention** (Options API)
- ✅ **Output escaping** (esc_html, esc_attr, esc_url)
- ✅ **Capability checks** (manage_options)

### Security Best Practices

**For Administrators:**
1. Use HTTPS (required for webhooks/PWA)
2. Keep WordPress updated
3. Disable WP_DEBUG in production
4. Restrict plugin access to admins
5. Monitor logs regularly

**For Developers:**
1. Never hardcode credentials
2. Always validate input
3. Use nonces for forms
4. Check capabilities before actions
5. Rate limit custom endpoints

### Security Audit Checklist

- [ ] HTTPS enabled site-wide
- [ ] WordPress core is up-to-date
- [ ] PHP version is 7.4+
- [ ] WP_DEBUG disabled in production
- [ ] Access tokens are encrypted
- [ ] Webhook signatures verified
- [ ] Rate limiting active
- [ ] Admin access restricted

---

## ❓ FAQ

### General Questions

**Q: Does this work with Square Sandbox?**
A: Yes! Define in wp-config.php: `define('SMDP_ENVIRONMENT', 'sandbox');`

**Q: Can I use multiple locations?**
A: Plugin supports one location per installation. For multiple locations, use multisite.

**Q: Does it work with WooCommerce?**
A: This plugin displays menus only. It can coexist with WooCommerce.

**Q: Can customers order through the website?**
A: No. This displays menus. Orders must be placed through Square POS.

### Sync Questions

**Q: How often does it sync?**
A: Configurable: hourly, 6 hours, 12 hours, daily, or manual only.

**Q: Can I sync on demand?**
A: Yes. Settings > Sync Now button.

**Q: What if Square API is down?**
A: Plugin uses cached data. Menu continues to work.

### Authentication Questions

**Q: OAuth vs Personal Token - which is better?**
A:
- **OAuth:** Better security, auto-refresh, no webhooks
- **Personal Token:** Required for webhooks, simpler

### PWA Questions

**Q: Do I need PWA features?**
A: Only if you want offline access or app installation.

**Q: Does PWA work on iOS?**
A: Yes, iOS 11.3+ supports service workers.

---

## 📞 Support & Resources

### Documentation
- **Plugin README:** You're reading it!
- **Security Fixes:** See `SECURITY-FIXES-APPLIED.md`
- **Testing Guide:** See `TESTING-CHECKLIST.md`

### Square Resources
- [Square Developer Portal](https://developer.squareup.com/)
- [Square API Reference](https://developer.squareup.com/reference/square)
- [OAuth Guide](https://developer.squareup.com/docs/oauth-api/overview)
- [Webhooks Documentation](https://developer.squareup.com/docs/webhooks/overview)

### Contact
For issues, questions, or feature requests: **mark@shoucair.ca**

---

## 📄 Technical Specifications

### System Requirements

**WordPress:**
- Version 5.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.6+
- HTTPS required (webhooks/PWA)

**Square:**
- Square account
- API access enabled
- OAuth app or Personal token

**Server:**
- `allow_url_fopen` enabled
- `cURL` extension
- `OpenSSL` extension
- `JSON` extension
- 64MB+ PHP memory

**Browser Support:**
- Chrome 45+
- Firefox 40+
- Safari 11.1+
- Edge 17+
- iOS Safari 11.3+

### File Structure

```
square-menu-display/
├── Main.php                              # Plugin entry point & initialization
├── README.md                             # Complete user guide (this file)
├── SECURITY-FIXES-APPLIED.md             # Security audit & fixes documentation
├── TESTING-CHECKLIST.md                  # QA testing procedures
├── OAUTH_SETUP.md                        # OAuth 2.0 setup guide
├── admin-webhooks.php                    # Webhooks admin page UI
├── smdp-webhook.php                      # Webhook REST API handler
├── service-worker.js                     # PWA service worker template
├── test-encryption.php                   # Encryption testing utility
├── test-rewrite.php                      # URL rewrite testing utility
│
├── includes/                             # Core plugin classes
│   ├── constants.php                     # Constants, helpers, validation functions
│   ├── class-admin-assets.php            # Admin CSS/JS enqueue manager
│   ├── class-admin-pages.php             # Admin page renderers (Settings, Categories, etc.)
│   ├── class-admin-settings.php          # WordPress Settings API registration
│   ├── class-ajax-handler.php            # AJAX endpoint handlers (sync, refresh, etc.)
│   ├── class-appearance-manager.php      # Frontend appearance & styling controls
│   ├── class-debug-panel.php             # PWA debug panel for frontend
│   ├── class-help-request.php            # Help & Bill request system (shortcodes + AJAX)
│   ├── class-manifest-generator.php      # PWA manifest.json generator
│   ├── class-menu-app-builder.php        # Menu app builder & configuration
│   ├── class-oauth-handler.php           # OAuth 2.0 authentication flow
│   ├── class-plugin-activation.php       # Activation/deactivation hooks
│   ├── class-protection-settings.php     # Content protection & access control
│   ├── class-pwa-handler.php             # PWA service worker registration
│   ├── class-shortcode.php               # [square_menu] shortcode renderer
│   ├── class-standalone-menu-app.php     # Standalone menu app functionality
│   ├── class-sync-manager.php            # Square catalog sync manager
│   │
│   └── templates/                        # PHP templates for admin pages
│       └── admin-menu-editor.php         # Drag-and-drop menu editor UI
│
└── assets/                               # Frontend & admin assets
    │
    ├── css/                              # Stylesheets
    │   ├── item-detail.css               # Item detail modal styles
    │   ├── menu-app.css                  # Main menu app frontend styles
    │   ├── menu-app-admin.css            # Menu app builder admin styles
    │   └── smdp-structural.css           # Structural CSS (grid, layout)
    │
    └── js/                               # JavaScript files
        ├── help-admin.js                 # Help & Bill admin page interactions
        ├── help-request.js               # Help/Bill request frontend AJAX
        ├── item-detail.js                # Item detail modal functionality
        ├── menu-app-builder-admin.js     # Menu app builder admin interface
        ├── menu-app-frontend.js          # Menu app frontend functionality
        ├── pwa-install.js                # PWA installation prompt handler
        ├── refresh.js                    # Menu refresh functionality
        ├── table-setup.js                # Table setup for help/bill system
        └── view-bill.js                  # View bill functionality
```

**Total Files:** 40+ PHP, JS, CSS, and documentation files

**Key Directories:**
- `includes/` - All PHP classes and core logic (15 classes)
- `assets/css/` - All stylesheets (4 files)
- `assets/js/` - All JavaScript (9 files)
- `includes/templates/` - PHP template files (1 file)

**Documentation:**
- `README.md` - Complete user & developer guide
- `SECURITY-FIXES-APPLIED.md` - Security documentation
- `TESTING-CHECKLIST.md` - Testing procedures
- `OAUTH_SETUP.md` - OAuth setup reference

---

## 📝 Changelog

### Version 3.0 (Security Hardened) - 2025-01-17

**🔒 Security Enhancements:**
- Fixed critical webhook authentication bypass
- Added comprehensive Square ID validation
- Implemented multi-factor rate limiting
- Secured debug logging
- Enhanced CSRF protection

**✨ New Features:**
- Admin error notifications
- Real-time sync monitoring
- Token format validation
- Improved error handling

**📚 Documentation:**
- Complete comprehensive README
- Security fixes documentation
- Testing checklist

---

## 📜 License

**GNU General Public License v3.0 (GPL-3.0)**

Copyright (c) 2025 Mark Shoucair

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

**What this means:**
- ✅ You can use this plugin for free
- ✅ You can modify the code
- ✅ You can distribute it (original or modified)
- ✅ You can use it commercially
- ⚠️ You must keep it under GPL-3.0 license
- ⚠️ You must include the license and copyright notice
- ⚠️ You must disclose the source code when distributing

---

## 🙏 Credits

**Development Team:**
- **Mark Shoucair** - 30% (Project concept, requirements, testing)
- **Claude (Anthropic)** - 40% (Security hardening, documentation)
- **ChatGPT (OpenAI)** - 30% (Initial development, features)

**Technologies:**
- WordPress Plugin API
- Square REST API
- OAuth 2.0
- Progressive Web Apps
- Service Workers
- AES-256-CBC Encryption

---

**Last Updated:** January 17, 2025

**END OF README**
