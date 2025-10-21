# Square Menu Display Premium Deluxe Pro

**Version:** 3.0
**Author:** Mark Shoucair (30%) & ChatGPT & Claude (70%)
**Requires WordPress:** 5.0+
**Tested up to:** 6.4
**License:** GPLv2 or later
**Tags:** square, menu, restaurant, pos, pwa, mobile app, qr menu

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Getting Started](#getting-started)
  - [Step 1: Square Account Setup](#step-1-square-account-setup)
  - [Step 2: Connect to Square](#step-2-connect-to-square)
  - [Step 3: Initial Sync](#step-3-initial-sync)
- [User Guide](#user-guide)
  - [Menu Management](#menu-management)
  - [Menu App Builder](#menu-app-builder)
  - [PWA Setup](#pwa-setup)
  - [Help & Bill System](#help--bill-system)
  - [Shortcodes](#shortcodes)
- [Admin Interface](#admin-interface)
- [Advanced Features](#advanced-features)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Developer Documentation](#developer-documentation)
- [Support](#support)

---

## 🎯 Overview

**Square Menu Display Premium Deluxe Pro** is a comprehensive WordPress plugin that integrates your Square catalog directly into your website, creating beautiful, interactive menu displays that stay synchronized with your Square POS system in real-time.

Perfect for restaurants, bars, cafes, and retail establishments, this plugin transforms your WordPress site into a powerful digital menu system with Progressive Web App (PWA) capabilities, allowing customers to install your menu as a mobile app.

### What Makes This Plugin Special?

- **Real-time synchronization** with Square POS
- **Automatic sold-out status updates** from Square
- **Progressive Web App (PWA)** - Install as a mobile app
- **Offline functionality** - Menu works without internet
- **Table service features** - Request help & view bills
- **OAuth 2.0 security** - Enterprise-grade authentication
- **Webhook integration** - Instant updates from Square
- **Drag-and-drop menu builder** - No coding required
- **Customizable appearance** - Match your brand

---

## ✨ Features

### Core Features

- ✅ **Square Catalog Integration** - Automatic sync of items, prices, images, and categories
- ✅ **Real-time Updates** - Webhook support for instant menu changes
- ✅ **Sold-Out Management** - Three-tier system (manual override, Square location overrides, item status)
- ✅ **Category Management** - Create custom categories, hide/show, reorder
- ✅ **Item Ordering** - Drag-and-drop ordering within categories
- ✅ **Image Management** - Sync images from Square, option to hide per item
- ✅ **Modifier Support** - Display item modifiers with prices
- ✅ **Responsive Design** - Beautiful on desktop, tablet, and mobile

### Menu App Builder

- ✅ **Interactive Menu App** - Full-featured digital menu system
- ✅ **Layout Options** - Top navigation or left sidebar layouts
- ✅ **Promo Screen** - Idle timeout slideshow for promotions
- ✅ **Custom Styling** - Button colors, fonts, spacing
- ✅ **Category Filtering** - Quick navigation between menu sections
- ✅ **Item Detail Modal** - Click items for full details and modifiers

### PWA Features

- ✅ **Install as App** - One-click installation to home screen
- ✅ **Offline Support** - Service worker caching for offline access
- ✅ **Push Notifications** - Alert customers of menu changes (future)
- ✅ **Fullscreen Mode** - App-like experience
- ✅ **Auto-Updates** - Smart cache management and version control

### Table Service Features

- ✅ **Request Help** - Customers can request server assistance
- ✅ **View Bill** - Display itemized bill from Square
- ✅ **Table Management** - Configure table numbers and locations
- ✅ **Customer Lookup** - Link bills to Square customer IDs

### Security & Performance

- ✅ **OAuth 2.0 Authentication** - Secure, token-based Square connection
- ✅ **AES-256 Encryption** - Sensitive data encrypted at rest
- ✅ **Rate Limiting** - Multi-factor rate limiting prevents abuse
- ✅ **HMAC Signature Verification** - Webhook security
- ✅ **Automatic Token Refresh** - No interruption in service
- ✅ **Smart Caching** - Optimized performance with version control

---

## 📥 Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Go to **Plugins → Add New**
4. Click **Upload Plugin**
5. Choose the ZIP file and click **Install Now**
6. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download and unzip the plugin
2. Upload the `square-menu-display-premium-deluxe-pro` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### Method 3: WP-CLI

```bash
wp plugin install square-menu-display-premium-deluxe-pro.zip --activate
```

---

## 🚀 Getting Started

### Step 1: Square Account Setup

Before using this plugin, you need:

1. **A Square account** - Sign up at [squareup.com](https://squareup.com)
2. **Catalog items in Square** - Add your menu items, prices, and images to Square
3. **Square API credentials** - Choose one of two methods:

#### Option A: Manual Access Token (Easiest)

1. Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
2. Create a new application or select existing one
3. Go to **Credentials** tab
4. Copy your **Access Token** (Production or Sandbox)
5. Keep this secure - you'll need it in Step 2

#### Option B: OAuth 2.0 (Recommended for Production)

1. Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
2. Create a new application
3. Go to **OAuth** tab
4. Add your WordPress site URL as a **Redirect URL**:
   ```
   https://yoursite.com/wp-admin/admin.php?page=smdp_main&oauth_callback=1
   ```
5. Copy your **Application ID** and **Application Secret**
6. Keep these secure - you'll need them in Step 2

---

### Step 2: Connect to Square

#### Using Manual Access Token

1. In WordPress admin, go to **Square Menu → Settings**
2. Paste your **Square Access Token** in the field
3. Click **Save Settings**
4. You should see a green success message

#### Using OAuth 2.0

1. In WordPress admin, go to **Square Menu → Settings**
2. Scroll to **OAuth Connection** section
3. Enter your **Application ID**
4. Enter your **Application Secret**
5. Click **Save OAuth Settings**
6. Click **Connect with Square** button
7. You'll be redirected to Square to authorize the connection
8. Click **Allow** on the Square authorization page
9. You'll be redirected back to your WordPress site
10. You should see "OAuth connection successful!"

**Token Status Display:**
- 🟢 **Connected** - Shows merchant ID and token expiration date
- 🟡 **Expires Soon** - Token will auto-refresh in the next 7 days
- 🔴 **Disconnected** - Need to reconnect

---

### Step 3: Initial Sync

1. After connecting to Square, go to **Square Menu → Settings**
2. Scroll to **Catalog Sync** section
3. Click **Sync Now** button
4. Wait for the sync to complete (usually 5-30 seconds)
5. You should see a success message showing number of items synced

**What gets synced:**
- ✅ All menu items from your Square catalog
- ✅ Item images
- ✅ Categories (from Square reporting categories)
- ✅ Modifier lists and modifiers
- ✅ Prices and variations
- ✅ Sold-out status

**Sync Options:**
- **Manual Sync Only** - You click "Sync Now" when you want to update
- **Automatic Sync** - Syncs every X hours (default: 1 hour)
- **Webhook Sync** - Real-time updates when you change items in Square

---

## 📖 User Guide

### Menu Management

Go to **Square Menu → Menu Management** to organize your menu display.

#### Managing Categories

**View Categories:**
- All categories from Square are listed with their names and item counts
- Custom categories (created by you) are marked with "Custom"

**Hide/Show Categories:**
1. Find the category you want to hide
2. Click the **Eye icon** to toggle visibility
3. Hidden categories won't appear on your menu

**Reorder Categories:**
1. Click **Reorder Categories** button
2. Drag categories up or down
3. Click **Save Order**
4. New order will be reflected on your menu immediately

**Create Custom Category:**
1. Click **Create Custom Category** button
2. Enter a name (e.g., "Daily Specials", "Chef's Recommendations")
3. Click **Create**
4. The new category appears in your list
5. Add items to it by assigning them to this category

**Delete Custom Category:**
1. Find the custom category
2. Click the **Trash icon**
3. Confirm deletion
4. All items in this category will be unassigned

**Important:** You cannot delete Square-synced categories. Only custom categories can be deleted.

---

#### Managing Items

**View Items by Category:**
1. Select a category from the dropdown
2. All items in that category are displayed
3. Each item shows:
   - Name and description
   - Price
   - Image (if available)
   - Sold-out status
   - Category assignment

**Reorder Items:**
1. Select a category
2. Items are displayed in current order
3. Drag items up or down
4. Changes save automatically
5. Order is reflected immediately on your menu

**Change Item Category:**
1. Find the item
2. Click the **Category** dropdown
3. Select a new category
4. Item moves to the new category

**Hide Item Image:**
1. Find the item
2. Click **Hide Image** checkbox
3. Image won't display on your menu for this item
4. Useful for items with generic or placeholder images

**Manage Sold-Out Status:**

You have three levels of control:

1. **Auto (Default)** - Uses Square's sold-out status
   - Syncs from Square location overrides
   - Updates automatically
   - Recommended for most users

2. **Manual Override: Sold Out**
   - Forces item to show as sold out
   - Overrides Square status
   - Useful for temporary out-of-stock items

3. **Manual Override: Available**
   - Forces item to show as available
   - Overrides Square status
   - Useful if Square status is wrong

To change status:
1. Find the item
2. Click the **Sold Out Status** dropdown
3. Select desired status
4. Changes save automatically

**Sync Sold-Out from Square:**
1. Click **Sync Sold-Out Status** button
2. Resets all manual overrides to Auto
3. Syncs current status from Square
4. Use this to refresh all statuses at once

---

#### Modifier Management

Modifiers are options customers can add to items (e.g., "Extra Cheese", "No Onions", "Spicy").

**View Modifiers:**
- All modifier lists from Square are displayed
- Shows which items use each modifier list

**Disable Modifiers:**
1. Find the modifier list you want to hide
2. Click **Disable** checkbox
3. This modifier won't appear on your menu
4. Useful for internal modifiers or POS-only options

**Why disable modifiers?**
- Kitchen prep notes not relevant to customers
- Internal tracking modifiers
- Complicated options better explained in person

---

### Menu App Builder

Go to **Square Menu → Menu App Builder** to create an interactive menu application.

#### What is the Menu App?

The Menu App is a full-featured digital menu system with:
- Category navigation
- Item filtering
- Detail modals
- Promo screen
- PWA installation
- Offline support

Perfect for:
- QR code menus for table service
- Self-service kiosks
- Customer-facing displays
- Mobile ordering preparation

---

#### Layout Selection

**Top Navigation Layout:**
- Categories displayed as buttons across the top
- Content area below
- Best for: Desktop displays, wide screens, 3-8 categories

**Left Sidebar Layout:**
- Categories in a left column
- Content area on the right
- Best for: Mobile devices, many categories (8+), portrait orientation

To change layout:
1. Select **Layout** dropdown
2. Choose "Top Navigation" or "Left Sidebar"
3. Click **Save Settings**
4. Preview updates automatically

---

#### Category Management

**Select Active Categories:**
1. In the **Categories** section, you'll see all available categories
2. Check the categories you want in your Menu App
3. Uncheck categories you want to hide
4. Click **Save Settings**

**Reorder Categories:**
1. Drag categories up or down in the list
2. This determines the order they appear in navigation
3. First category is the default view
4. Changes save automatically

**Category Display Options:**
- **Show All** - Display every category
- **Hide Empty** - Automatically hide categories with no items
- **Custom Selection** - Choose specific categories

---

#### Promo Screen Setup

The promo screen shows a fullscreen image after a period of inactivity, perfect for promoting specials or branding.

**Enable Promo Screen:**
1. Check **Enable Promo Screen** checkbox
2. Set **Idle Timeout** (seconds before promo appears)
   - Default: 600 seconds (10 minutes)
   - Recommended: 300-900 seconds (5-15 minutes)
3. Upload **Promo Image**:
   - Click **Upload Image** button
   - Select image from media library or upload new
   - Recommended size: 1920x1080px (landscape) or 1080x1920px (portrait)
   - Format: JPG, PNG, or GIF

**Promo Screen Behavior:**
- Activates after idle timeout period
- Slides down from top with animation
- Enters fullscreen mode automatically
- Any touch/click dismisses it
- Returns to first menu category
- Menu refreshes when dismissed

**Best Practices:**
- Use high-quality, professionally designed images
- Match orientation to your typical display device
- Include compelling calls-to-action
- Update regularly for seasonal promotions
- Test on actual devices before deploying

---

#### Button Styling

Customize the appearance of category buttons to match your brand.

**Color Settings:**
- **Button Background Color** - Default button color
- **Button Text Color** - Text color on buttons
- **Active Button Color** - Color when category is selected
- **Active Text Color** - Text color on active button
- **Button Hover Color** - Color when hovering over button

**Size & Spacing:**
- **Button Padding** - Internal spacing (e.g., "12px 24px")
- **Button Border Radius** - Rounded corners (e.g., "8px")
- **Button Font Size** - Text size (e.g., "16px")
- **Button Margin** - Space between buttons (e.g., "8px")

**Advanced Styling:**
- **Custom CSS** - Add your own CSS rules
- Targets: `.smdp-cat-btn`, `.smdp-cat-btn.active`, `.smdp-app-section`
- Example:
  ```css
  .smdp-cat-btn {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: bold;
  }

  .smdp-cat-btn.active {
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  }
  ```

---

#### Item Detail Modal

Control how item details are displayed when clicked.

**Enable/Disable Modal:**
- **Enabled** - Clicking items opens detail overlay
- **Disabled** - Items are display-only, no interaction

**Modal Features (when enabled):**
- Full-size item image
- Complete description
- All variations with prices
- All modifiers with prices
- Sold-out status indicator
- Close button or click outside to dismiss

**Why disable?**
- Display-only menus
- Simplified user interface
- Touch-free COVID considerations
- Information kiosks

---

### PWA Setup

Progressive Web App (PWA) features allow customers to install your menu as a mobile app.

Go to **Square Menu → Menu App Builder → PWA Settings**

#### Basic Settings

**App Name:**
- Full name of your app
- Appears under icon on home screen
- Example: "Joe's Pizza Menu"
- Max 45 characters

**App Short Name:**
- Abbreviated name
- Used when space is limited
- Example: "Joe's Menu"
- Max 12 characters

**App Description:**
- Brief description of your menu app
- Used in app stores and installation prompts
- Example: "Browse our full menu, see daily specials, and place orders"
- Max 140 characters

---

#### Visual Settings

**Theme Color:**
- Primary color for browser UI
- Used in address bar on mobile
- Should match your brand
- Example: `#e74c3c` (red)

**Background Color:**
- Color shown during app launch
- Splash screen background
- Should match your design
- Example: `#ffffff` (white)

**App Icons:**

Upload icons in these sizes:
- **192x192px** - Required, standard icon size
- **512x512px** - Required, high-resolution icon
- **Apple Touch Icon (180x180px)** - For iOS devices

**Icon Requirements:**
- Format: PNG (recommended) or JPG
- No transparency for Apple Touch Icon
- Square aspect ratio
- Include padding/margin for optical balance
- Should be recognizable at small sizes

**Quick Start:**
1. Design a square logo for your restaurant
2. Export at 512x512px
3. Upload as 512px icon
4. Upload same image as 192px icon
5. Upload solid background version as Apple Touch Icon

---

#### Installation Instructions for Customers

**Android (Chrome):**
1. Open your menu website in Chrome
2. Tap the menu icon (three dots)
3. Select "Add to Home screen"
4. Tap "Add"
5. App icon appears on home screen

**iOS (Safari):**
1. Open your menu website in Safari
2. Tap the Share button (box with arrow)
3. Scroll and tap "Add to Home Screen"
4. Tap "Add"
5. App icon appears on home screen

**Desktop (Chrome/Edge):**
1. Open your menu website
2. Look for install icon in address bar
3. Click "Install"
4. App opens in its own window

---

#### PWA Features for Customers

Once installed, customers get:
- **App icon on home screen** - Like any native app
- **Offline access** - View menu without internet
- **Faster loading** - Cached assets load instantly
- **Fullscreen mode** - No browser UI clutter
- **Home screen badge** - Show notifications (future)
- **App-like experience** - Smooth animations and gestures

---

#### Testing PWA Installation

**Development Testing:**
1. Enable PWA Debug Mode in settings
2. Visit your menu app URL: `/menu-app/`
3. Open browser DevTools (F12)
4. Go to "Application" tab
5. Check "Manifest" section for errors
6. Check "Service Workers" for registration
7. Test offline mode by toggling "Offline" checkbox

**Production Testing:**
1. Disable PWA Debug Mode
2. Visit menu app on actual mobile device
3. Attempt installation process
4. Verify icon appears correctly
5. Test offline by enabling airplane mode
6. Verify menu loads from cache

**Common Issues:**
- **No install prompt** - Site must be HTTPS (not localhost)
- **Icon not showing** - Check icon URLs and file sizes
- **Offline not working** - Check service worker registration
- **Old content cached** - Increment cache version

---

### Help & Bill System

The Help & Bill system enables table service features for restaurants and bars.

Go to **Square Menu → Help & Bill**

#### Overview

This system allows customers to:
- Request help/service from their table
- View their itemized bill from Square
- Link to table numbers for targeted service

All actions create $0 orders in Square for tracking purposes.

---

#### Initial Setup

**1. Sync Square Locations:**
- Click **Sync Locations** button
- Select your primary location from dropdown
- Click **Save Location**

**2. Create Catalog Items in Square:**

You need to create two special items in your Square catalog:

**"Request Help" Item:**
- Name: "Request Help" or "Call Server"
- Price: $0.00
- Category: "Service Requests" (hidden from main menu)
- Purpose: Tracks help requests

**"Request Bill" Item:**
- Name: "Request Bill" or "View Check"
- Price: $0.00
- Category: "Service Requests"
- Purpose: Tracks bill views

**3. Sync Catalog:**
- Go to **Square Menu → Settings**
- Click **Sync Now**
- Return to **Help & Bill** settings

**4. Assign Catalog Items:**
- Select "Request Help" item from **Help Item** dropdown
- Select "Request Bill" item from **Bill Item** dropdown
- Click **Save Settings**

---

#### Table Management

**Add Tables:**
1. Click **Add Table** button
2. Enter table number (e.g., "1", "A1", "Patio-5")
3. Click **Save**
4. Repeat for all tables

**Edit Tables:**
1. Find the table in the list
2. Click **Edit** button
3. Change table number
4. Click **Save**

**Delete Tables:**
1. Find the table in the list
2. Click **Delete** button
3. Confirm deletion

**Best Practices:**
- Use clear, simple table numbers
- Match your physical table numbering system
- Include section names if needed (e.g., "Bar-1", "Patio-3")
- Keep numbers short for easy customer input

---

#### Bill Lookup Method

Choose how customers look up their bills:

**Method 1: Customer ID (Recommended)**
- Customers enter their Square Customer ID
- Most accurate method
- Retrieves exact order for that customer
- Best for: Loyalty program members, known customers

**Method 2: Table Number**
- Customers enter their table number
- Retrieves orders linked to that table's item
- Less accurate (multiple parties at same table)
- Best for: Walk-in customers, quick lookup

**Setting the Method:**
1. Go to **Help & Bill** settings
2. Select **Bill Lookup Method** dropdown
3. Choose "Customer ID" or "Table Number"
4. Click **Save Settings**

---

#### Using Shortcodes

**Request Help Button:**

Add to any page/post:
```
[smdp_request_help]
```

**With table number parameter:**
```
[smdp_request_help table="5"]
```

**What it does:**
- Displays a "Request Help" button
- Customer clicks button
- Creates $0 order in Square with help item
- Server notified via Square dashboard/app

---

**Request Bill/View Bill:**

Add to any page/post:
```
[smdp_request_bill]
```

**With table number parameter:**
```
[smdp_request_bill table="5"]
```

**What it does:**
- Displays customer ID or table number input
- Customer enters their info
- System retrieves bill from Square
- Displays itemized bill with totals
- Shows all items, prices, taxes, tips

---

#### Table-Specific URLs

Create direct links for QR codes on tables:

**Menu App with Table Number:**
```
https://yoursite.com/menu-app/table/5/
```

**What it does:**
- Opens menu app
- Pre-fills table number (5)
- Help & Bill buttons automatically know table number
- One QR code per table

**How to Create QR Codes:**
1. Generate URL for each table: `/menu-app/table/1/`, `/menu-app/table/2/`, etc.
2. Use QR code generator (qr-code-generator.com)
3. Create QR code for each URL
4. Print and laminate QR codes
5. Place on corresponding tables
6. Customers scan to access menu with table info

---

#### Customer Experience Flow

**Scenario 1: Customer Requests Help**
1. Customer scans QR code on table
2. Menu app opens with table number pre-filled
3. Customer browses menu
4. Customer clicks "Request Help" button
5. System creates $0 order in Square with "Request Help" item and table number
6. Server sees notification in Square app
7. Server assists customer

**Scenario 2: Customer Views Bill**
1. Customer scans QR code on table
2. Menu app opens with table number pre-filled
3. Customer clicks "View Bill" button
4. System looks up orders for that table
5. Displays itemized bill with all items, prices, taxes
6. Customer can review bill before paying
7. Customer can flag server for payment

---

### Shortcodes

#### Display Menu by Category

**Basic Usage:**
```
[square_menu category="appetizers"]
```

**Parameters:**
- `category` - Category slug (required)
  - Find slug in **Menu Management**
  - Usually lowercase category name with hyphens
  - Example: "appetizers", "entrees", "daily-specials"

**Example:**
```
[square_menu category="appetizers"]
[square_menu category="main-courses"]
[square_menu category="desserts"]
```

**What it displays:**
- Grid of items in that category
- Item images (if available and not hidden)
- Item names and descriptions
- Prices (from first variation)
- Sold-out badges (if applicable)
- Click for detail modal (if enabled)

---

#### Display Full Menu App

**Basic Usage:**
```
[smdp_menu_app]
```

**No parameters needed** - Uses settings from Menu App Builder.

**What it displays:**
- Full menu app with category navigation
- Layout as configured (top or left)
- All active categories
- Promo screen (if enabled)
- PWA installation prompt
- Item detail modals

**Best used on:**
- Dedicated menu page
- Full-width page template
- Mobile-optimized pages

---

#### Request Help Button

**Basic Usage:**
```
[smdp_request_help]
```

**With Pre-filled Table:**
```
[smdp_request_help table="5"]
```

**Parameters:**
- `table` - Table number (optional)
  - Pre-fills table number
  - Useful for direct links
  - Example: `table="5"`, `table="A1"`

**What it displays:**
- Button labeled "Request Help" or similar
- Click triggers $0 order in Square
- Notification to staff

---

#### Request/View Bill

**Basic Usage:**
```
[smdp_request_bill]
```

**With Pre-filled Table:**
```
[smdp_request_bill table="5"]
```

**Parameters:**
- `table` - Table number (optional)

**What it displays:**
- Input field for customer ID or table number (based on settings)
- "View Bill" button
- Itemized bill with all items, prices, taxes, tips
- Total amount

---

#### Combined Example Page

Create a comprehensive menu page:

```
<h1>Our Menu</h1>

<!-- Full Menu App -->
[smdp_menu_app]

<hr>

<h2>Quick Access</h2>

<!-- Request Help -->
<p>Need assistance?</p>
[smdp_request_help]

<!-- View Bill -->
<p>Ready to pay?</p>
[smdp_request_bill]
```

---

## 🎛️ Admin Interface

### Settings Page

**Square Menu → Settings**

Main configuration hub for the plugin.

#### Square API Connection

**Manual Token:**
- Paste access token from Square Developer Dashboard
- Click **Save Settings**
- Status shows "Connected" or "Disconnected"

**OAuth Connection:**
- Enter Application ID and Secret
- Click **Save OAuth Settings**
- Click **Connect with Square**
- Authorize in Square
- Status shows merchant ID and expiration date

**Token Management:**
- **Refresh Token** - Manually refresh OAuth token
- **Disconnect** - Revoke authorization and clear tokens
- **Token Expiration** - Shows days remaining (auto-refreshes at 7 days)

---

#### Catalog Sync Settings

**Sync Mode:**
- **Manual Only** - You trigger syncs by clicking "Sync Now"
- **Automatic** - Syncs on a schedule (every X hours)

**Sync Interval:**
- Set hours between automatic syncs
- Default: 1 hour
- Minimum: 1 hour
- Maximum: 24 hours

**Sync Now Button:**
- Triggers immediate catalog sync
- Shows progress and results
- Displays number of items synced

**Last Sync:**
- Shows timestamp of most recent successful sync
- Helps troubleshoot sync issues

---

#### Webhook Settings

**Enable Webhooks:**
- Real-time updates from Square
- Automatically syncs when you change items in Square
- More efficient than polling

**Webhook Setup:**
1. Click **Activate Webhooks** button
2. System creates webhook subscription in Square
3. Stores signature key securely
4. Status shows "Active" when working

**Webhook Status:**
- **Active** - Receiving updates from Square
- **Inactive** - Not configured or connection failed
- **Signature Key** - Shows if key is stored (encrypted)

**Refresh Webhook:**
- Updates signature key
- Use if webhook stops working
- Doesn't create new webhook, just refreshes credentials

**Important:** Webhooks only work with manual access tokens, not OAuth (Square limitation).

---

#### API Log

**View Recent Requests:**
- Shows last 10 API requests/responses
- Useful for troubleshooting connection issues
- Includes timestamps and response codes

**What's Logged:**
- Request URL
- Request method
- Response status code
- Response body (truncated)
- Timestamp

---

### Menu Management Page

**Square Menu → Menu Management**

Organize and customize your menu display.

**Main Sections:**
- **Categories** - List of all categories with visibility toggles
- **Items** - Items in selected category with ordering
- **Sold-Out Management** - Bulk and individual status controls
- **Modifiers** - Enable/disable modifier lists

**Quick Actions:**
- Reorder categories
- Create custom categories
- Delete custom categories
- Sync sold-out status
- Match Square categories

---

### Menu App Builder Page

**Square Menu → Menu App Builder**

Build your interactive menu application.

**Sections:**
- **Layout** - Choose top or left navigation
- **Categories** - Select and order active categories
- **Promo Screen** - Configure idle timeout slideshow
- **Button Styling** - Customize button appearance
- **PWA Settings** - Configure app name, icons, colors
- **Preview** - Live preview of your menu app

**Save Behavior:**
- Changes save immediately
- Preview updates in real-time
- Cache invalidates automatically

---

### Help & Bill Page

**Square Menu → Help & Bill**

Configure table service features.

**Sections:**
- **Location Settings** - Select Square location
- **Item Assignment** - Assign help/bill catalog items
- **Bill Lookup Method** - Choose customer ID or table number
- **Table Management** - Add, edit, delete tables

**Testing:**
- Test help request button
- Test bill lookup
- View sample bills

---

## 🔧 Advanced Features

### Webhook Integration

#### What are Webhooks?

Webhooks provide real-time notifications from Square when your catalog changes. Instead of polling every hour, your menu updates instantly when you:
- Add new items in Square
- Change prices
- Update descriptions
- Upload new images
- Mark items as sold out

#### Setting Up Webhooks

**Prerequisites:**
- Manual access token (not OAuth)
- HTTPS website (required by Square)
- WordPress REST API enabled

**Steps:**
1. Go to **Square Menu → Settings**
2. Scroll to **Webhook Settings**
3. Click **Activate Webhooks**
4. System automatically:
   - Creates webhook subscription in Square
   - Stores signature key securely
   - Verifies connection

**Verification:**
- Check webhook status shows "Active"
- Make a change in Square (edit item description)
- Wait 5-10 seconds
- Check menu page - change should appear

#### Webhook Security

**HMAC-SHA256 Signature Verification:**
- Every webhook request is signed by Square
- Plugin verifies signature before processing
- Prevents spoofed requests
- Signature key stored encrypted in database

**How it Works:**
1. Square sends webhook POST request
2. Plugin receives request with signature header
3. Plugin computes expected signature
4. Compares with received signature
5. Only processes if signatures match

#### Troubleshooting Webhooks

**Webhook Not Working:**
1. Check webhook status in settings
2. Verify signature key is stored
3. Check PHP error logs for verification failures
4. Try refreshing webhook
5. Deactivate and reactivate webhooks

**Signature Verification Failures:**
- Check URL matches exactly (with/without trailing slash)
- Verify HTTPS is working
- Check for reverse proxy or CDN modifications
- Review error logs: `[SMDP] Webhook rejected: Signature verification failed`

**Rate Limiting:**
- Plugin limits webhook processing to prevent abuse
- Multiple rapid webhooks are throttled
- Use manual sync if immediate update needed

---

### Cache Management

#### How Caching Works

**Three-Level Caching:**
1. **Database Cache** - WordPress options table
2. **Browser Cache** - Service worker caches assets
3. **Version Control** - Cache versioning for updates

**What's Cached:**
- Full Square catalog
- Item images
- Category data
- Menu HTML
- JavaScript/CSS assets

#### Cache Invalidation

**Automatic Invalidation:**
- Catalog sync (manual or automatic)
- Webhook receives update
- Menu settings change
- PWA debug mode toggled

**Manual Invalidation:**
1. Increment cache version
2. Clear browser cache
3. Unregister service workers
4. Force reload

**Cache Version:**
- Integer stored in database
- Incremented on each cache-busting event
- Sent to browser on every page load
- Browser detects version change and reloads

#### Browser Cache Management

**Service Worker Caching:**
- Caches menu assets for offline use
- Automatically updates when cache version changes
- Preserves table number across cache clears

**Clear Client Cache:**
1. Enable **PWA Debug Mode**
2. Visit menu page
3. System automatically clears cache
4. Disable **PWA Debug Mode**

**Debug Interface:**

Open browser console and type:
```javascript
// View cache status
smdpRefreshDebug.status()

// Manually refresh menu
smdpRefreshDebug.refresh()

// Check version from server
smdpRefreshDebug.fetchAndCheckVersion()

// Force cache clear
smdpRefreshDebug.forceClear()

// View debug logs
smdpRefreshDebug.getLogs()

// Clear debug logs
smdpRefreshDebug.clearLogs()
```

---

### Rate Limiting

#### Why Rate Limiting?

Prevents abuse of AJAX endpoints and API calls:
- Malicious automated requests
- Accidental infinite loops
- Resource exhaustion attacks
- Square API rate limit violations

#### How It Works

**Multi-Factor Identification:**
- User ID (if logged in)
- IP address (validated)
- User-Agent fingerprint (hashed)

**Composite Keys:**
- Logged-in users: `user_{ID}_{UA_HASH}`
- Anonymous users: `ip_{IP}_{UA_HASH}`

**Per-Action Limits:**
- `sync_sold_out` - 3 requests per 60 seconds
- `check_sync` - 20 requests per 60 seconds
- `refresh_menu` - 10 requests per 30 seconds

**Exponential Backoff:**
- After limit exceeded, timeout doubles
- Deters persistent abusers
- Logged for monitoring

#### Rate Limit Configuration

**Defaults (in constants.php):**
```php
smdp_is_rate_limited( $action, $limit, $period )
```

**Example:**
```php
if ( smdp_is_rate_limited( 'sync_sold_out', 3, 60 ) ) {
    wp_send_json_error( 'Too many requests' );
}
```

**Reset Rate Limit:**
```php
smdp_reset_rate_limit( 'sync_sold_out' );
```

**Monitor Rate Limits:**
- Check error logs: `[SMDP Rate Limit] Action '{action}' blocked`
- Shows identifier and attempt count

---

### Encryption

#### Data Encryption

**AES-256-CBC Encryption:**
- Industry-standard symmetric encryption
- 256-bit key strength
- CBC (Cipher Block Chaining) mode

**What's Encrypted:**
- Square access tokens (OAuth and manual)
- Square app secrets (OAuth)
- Webhook signature keys
- Refresh tokens

**Encryption Key Derivation:**
```php
$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
```

**Uses WordPress salts:**
- `AUTH_KEY`
- `SECURE_AUTH_KEY`
- Unique per WordPress installation
- Never changes (unless you rotate salts)

**Encryption Process:**
1. Generate random Initialization Vector (IV)
2. Encrypt data with AES-256-CBC
3. Combine IV + encrypted data
4. Base64 encode result
5. Store in database

**Decryption Process:**
1. Base64 decode stored value
2. Extract IV (first 16 bytes)
3. Extract encrypted data (remaining bytes)
4. Decrypt with AES-256-CBC
5. Return plaintext

#### Security Considerations

**Fallback Behavior:**
- If OpenSSL unavailable, stores plain text
- Logs warning to error log
- Rare scenario (OpenSSL standard on modern PHP)

**Key Rotation:**
- To rotate encryption keys:
  1. Disconnect from Square (clears old tokens)
  2. Rotate WordPress salts in wp-config.php
  3. Reconnect to Square (encrypts with new keys)

**Data at Rest:**
- All sensitive data encrypted in database
- No plain text tokens in SQL dumps
- Secure against database breaches

**Data in Transit:**
- All Square API calls over HTTPS
- TLS 1.2+ required
- Certificate verification enabled

---

### OAuth Token Management

#### Token Lifecycle

**Initial Authorization:**
1. User clicks "Connect with Square"
2. Redirected to Square authorization page
3. User grants permissions
4. Square redirects back with authorization code
5. Plugin exchanges code for access + refresh tokens
6. Tokens stored encrypted
7. Token expiration time stored

**Token Expiration:**
- Access tokens expire after 30 days
- Refresh tokens don't expire (but can be revoked)
- Plugin checks expiration daily

**Automatic Refresh:**
- Triggered when token expires in < 7 days
- Uses refresh token to get new access token
- New expiration time updated
- Happens silently in background
- Logged to error log

**Refresh Process:**
```php
// Check every hour
if ( time_until_expiry < 7 days ) {
    refresh_access_token();
}
```

**Token Revocation:**
- User clicks "Disconnect" button
- Plugin calls Square revoke endpoint
- Deletes stored tokens from database
- User must reauthorize to reconnect

#### OAuth Scopes

**Requested Permissions:**
- `MERCHANT_PROFILE_READ` - Read merchant information
- `ITEMS_READ` - Read catalog items
- `ITEMS_WRITE` - Update catalog items
- `ORDERS_READ` - Read orders (for bills)
- `ORDERS_WRITE` - Create orders (for help requests)
- `PAYMENTS_WRITE` - Process payments (future feature)

**Why These Scopes?**
- Read catalog to display menu
- Create orders for help/bill tracking
- Future: Accept orders and payments

**Scope Limitations:**
- Cannot access payment card data
- Cannot modify merchant settings
- Cannot access other merchants' data
- Scoped to specific merchant only

#### CSRF Protection

**State Parameter:**
- Generated using WordPress nonce
- Stored in transient (10-minute expiry)
- Verified on callback
- Prevents cross-site request forgery

**How It Works:**
1. Generate state: `wp_create_nonce( 'smdp_oauth_state' )`
2. Store in transient: `set_transient( 'smdp_oauth_state_{user_id}', $state, 600 )`
3. Include in authorization URL
4. Square includes state in redirect
5. Verify state matches stored value
6. Delete transient after verification

---

## 🛠️ Troubleshooting

### Common Issues

#### "No Access Token" Error

**Symptoms:**
- Sync fails
- Error message: "No Square access token configured"

**Solutions:**
1. Go to **Square Menu → Settings**
2. Verify access token is entered (manual) or OAuth is connected
3. If using OAuth, check token expiration status
4. Try disconnecting and reconnecting
5. Check if token has correct permissions

---

#### Items Not Syncing

**Symptoms:**
- Sync completes but items missing
- Zero items synced

**Possible Causes:**
1. **No items in Square catalog**
   - Add items in Square Dashboard first
   - Verify items are not deleted

2. **Wrong access token environment**
   - Sandbox token used on production
   - Production token used on sandbox
   - Check Square Developer Dashboard

3. **API permissions insufficient**
   - Token needs ITEMS_READ scope
   - Regenerate token with correct scopes

**Debug Steps:**
1. Go to **Square Menu → Settings**
2. Scroll to **API Log**
3. Click **Sync Now**
4. Check API log for errors
5. Look for HTTP error codes:
   - `401` - Authentication failed
   - `403` - Permission denied
   - `404` - Invalid endpoint
   - `500` - Square server error

---

#### Images Not Displaying

**Symptoms:**
- Items sync but no images shown
- Broken image icons

**Possible Causes:**
1. **No images in Square**
   - Upload images in Square Dashboard
   - Associate images with items

2. **Image permissions**
   - Images must be public in Square
   - Check image URLs are accessible

3. **HTTPS/Mixed Content**
   - WordPress site must be HTTPS
   - Mixed content blocks HTTP images

**Solutions:**
1. Verify images in Square Dashboard
2. Check image URLs in API log
3. Test image URL in browser directly
4. Enable HTTPS on WordPress site
5. Re-sync catalog after fixing

---

#### Webhook Not Working

**Symptoms:**
- Manual sync works, but automatic updates don't
- Webhook status shows inactive

**Requirements:**
- HTTPS website (required by Square)
- Manual access token (not OAuth)
- WordPress REST API enabled
- Incoming requests not blocked by firewall

**Debug Steps:**
1. **Verify HTTPS:**
   - Visit your site, check for padlock icon
   - Certificate must be valid

2. **Test REST API:**
   - Visit: `https://yoursite.com/wp-json/smdp/v1/webhook`
   - Should return: `{"alive":true}`

3. **Check Firewall:**
   - Whitelist Square webhook IPs
   - Check Cloudflare, Sucuri, Wordfence settings
   - Temporarily disable security plugins

4. **Check Error Logs:**
   - Enable WordPress debug logging
   - Look for: `[SMDP] Webhook rejected`
   - Review signature verification errors

5. **Refresh Webhook:**
   - Go to **Settings → Webhook**
   - Click **Refresh Webhook**
   - Check status changes to Active

---

#### PWA Not Installing

**Symptoms:**
- No install prompt appears
- Install button not visible

**Requirements:**
- HTTPS website (mandatory for PWA)
- Valid manifest.json file
- Service worker registered
- User hasn't dismissed prompt recently

**Debug Steps:**
1. **Check HTTPS:**
   - Must have valid SSL certificate
   - No mixed content warnings

2. **Verify Manifest:**
   - Open DevTools (F12)
   - Go to Application tab
   - Check Manifest section
   - Look for errors

3. **Check Service Worker:**
   - In Application tab, go to Service Workers
   - Should show registered and activated
   - Check for errors in console

4. **Test in Incognito:**
   - Open in incognito/private window
   - Dismissals are forgotten
   - Fresh install prompt

5. **Clear Browser Data:**
   - Clear site data in DevTools
   - Go to Application → Clear storage
   - Click "Clear site data"

6. **Check PWA Settings:**
   - Verify app name is set
   - Verify icons are uploaded
   - Verify theme colors are set

**Browser-Specific:**
- **iOS Safari** - Use Share button, "Add to Home Screen"
- **Android Chrome** - Install banner appears automatically
- **Desktop Chrome** - Install icon in address bar

---

#### Sold-Out Status Not Updating

**Symptoms:**
- Items show available but are sold out in Square
- Status doesn't change after sync

**Sold-Out Priority:**
1. Manual override (highest)
2. Square location overrides
3. Item sold_out flag (lowest)

**Debug Steps:**
1. **Check Manual Override:**
   - Go to **Menu Management**
   - Find item
   - Check Sold-Out Status dropdown
   - Set to "Auto" if manually overridden

2. **Sync Sold-Out Status:**
   - In **Menu Management**
   - Click **Sync Sold-Out Status**
   - Resets all manual overrides
   - Syncs from Square location overrides

3. **Check Square:**
   - Open Square Dashboard
   - Go to Items & Orders → Items
   - Find item
   - Check "Sold out at" location settings

4. **Manual Sync:**
   - Go to **Settings**
   - Click **Sync Now**
   - Wait for completion
   - Check item status again

---

#### Rate Limit Errors

**Symptoms:**
- Error: "Too many requests"
- AJAX calls failing

**Rate Limits (Default):**
- Sold-out sync: 3 per minute
- Check sync: 20 per minute
- Menu refresh: 10 per 30 seconds

**Solutions:**
1. **Wait:** Rate limits reset after timeout period
2. **Check for loops:** Ensure no JavaScript infinite loops
3. **Check logs:** `[SMDP Rate Limit]` entries in error log
4. **Identify source:** IP and user agent logged
5. **Clear transient:** Manually delete rate limit transient

**Manual Reset (via WP-CLI):**
```bash
wp transient delete --all
```

---

#### Menu Not Refreshing

**Symptoms:**
- Changes in Square not appearing on site
- Old data still showing

**Solutions:**
1. **Manual Sync:**
   - Go to **Settings**
   - Click **Sync Now**

2. **Check Last Sync Time:**
   - View last sync timestamp
   - If stuck, something is wrong

3. **Clear Cache:**
   - Enable PWA Debug Mode
   - Visit menu page
   - Cache clears automatically
   - Disable Debug Mode

4. **Check Automatic Sync:**
   - Verify sync mode is "Automatic"
   - Check sync interval setting
   - Verify WP-Cron is running

5. **Test WP-Cron:**
   ```bash
   wp cron event list
   wp cron event run square_menu_cron_sync
   ```

---

### Debug Mode

Enable debug logging for detailed troubleshooting.

**Enable WordPress Debug:**

Edit `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

**Enable PWA Debug Mode:**
1. Go to **Menu Management** (or any SMDP page)
2. Look for "PWA Debug Mode" toggle
3. Enable it
4. Cache automatically clears
5. Service worker caching disabled

**View Debug Logs:**
- Check `/wp-content/debug.log`
- Look for lines starting with `[SMDP]`
- Filter by component: `[SMDP OAuth]`, `[SMDP Sync]`, etc.

**Browser Console Debugging:**
```javascript
// View debug status
smdpRefreshDebug.status()

// View full log
smdpRefreshDebug.getLogs()

// Check specific issues
localStorage.getItem('smdp_debug_logs')
```

---

### Getting Support

**Before Requesting Support:**
1. Check this troubleshooting section
2. Enable debug logging
3. Check error logs
4. Try clearing cache
5. Test in incognito mode
6. Verify Square dashboard settings

**Information to Provide:**
- WordPress version
- PHP version
- Plugin version
- Error messages (exact text)
- Screenshots of issue
- Relevant error log entries
- Steps to reproduce

**Support Channels:**
- GitHub Issues: [Link to repository]
- Support Email: [Your email]
- Documentation: This README

---

## ❓ FAQ

### General Questions

**Q: Do I need a Square account?**
A: Yes, you need an active Square merchant account with items in your catalog.

**Q: Does this work with Square for Restaurants?**
A: Yes, fully compatible with Square for Restaurants, Retail, and all Square products.

**Q: Can I use this with WooCommerce?**
A: This plugin displays your Square menu but doesn't process orders. For e-commerce, consider Square's official WooCommerce plugin alongside this one.

**Q: Is this plugin free?**
A: Yes, the plugin itself is free. However, Square charges for payment processing if you add ordering features.

**Q: Does Square charge for API usage?**
A: No, Square API access is free with no per-request charges. You only pay standard processing fees for actual transactions.

---

### Technical Questions

**Q: Do I need HTTPS?**
A: Yes, for PWA features and webhooks. Square requires HTTPS for production API access.

**Q: What happens if Square is down?**
A: Your cached menu continues working. Offline PWA mode ensures customers can still view the menu.

**Q: How often does the menu sync?**
A: Default is every hour. With webhooks enabled, updates happen within seconds of changes in Square.

**Q: Can I customize the menu design?**
A: Yes, through Menu App Builder settings and Custom CSS. The plugin also uses your theme's styles.

**Q: Will this slow down my website?**
A: No, the plugin uses caching extensively. Initial sync takes 5-30 seconds, but menu loads from cache afterward.

**Q: Can I translate the plugin?**
A: The plugin is localization-ready. You can translate strings using WordPress .po/.mo files.

---

### Feature Questions

**Q: Can customers order through the menu?**
A: Not currently. This plugin displays menus. Online ordering requires additional Square integrations or third-party services.

**Q: Can I hide prices?**
A: Not directly, but you can use CSS to hide prices: `.smdp-item-price { display: none; }`

**Q: Can I show nutrition information?**
A: If nutrition data is in item descriptions in Square, it will display. There's no dedicated nutrition field.

**Q: Can I have multiple menus (lunch/dinner)?**
A: Yes, create custom categories in Square or the plugin, then use multiple shortcodes with different categories.

**Q: Can I use this for retail products?**
A: Yes, works for any Square catalog items—restaurant menus, retail products, services, etc.

**Q: Does this support variations (sizes, colors)?**
A: Yes, item variations from Square (Small/Medium/Large, Red/Blue) are displayed with individual prices.

**Q: Can I add items not in Square?**
A: No, all items must exist in your Square catalog. This ensures inventory and pricing stay synchronized.

---

### Security Questions

**Q: Is my Square access token secure?**
A: Yes, tokens are encrypted using AES-256 before storage. The plugin follows WordPress security best practices.

**Q: Can anyone trigger a sync?**
A: No, syncs require admin capabilities. Public endpoints (like menu display) are rate-limited.

**Q: What data is stored in my database?**
A: Catalog items, categories, images, settings, and encrypted credentials. No customer payment data.

**Q: Is Square notified of menu views?**
A: No, viewing your menu doesn't create any transactions or notifications in Square.

**Q: Can I revoke access?**
A: Yes, disconnect OAuth in plugin settings, or revoke app access in your Square Dashboard.

---

### PWA Questions

**Q: What browsers support PWA?**
A: Chrome, Edge, Safari (iOS 11.3+), Firefox, Opera. Most modern browsers support PWA features.

**Q: Do customers need to install the app?**
A: No, the menu works in browsers too. Installation is optional for enhanced experience.

**Q: How much storage does the PWA use?**
A: Typically 1-5 MB depending on number of items and images. Cached assets are cleaned automatically.

**Q: Can I update the PWA without users reinstalling?**
A: Yes, updates deploy automatically. Service worker detects changes and updates cache.

**Q: Will the app work completely offline?**
A: Menu items and images cached during online use will work offline. New items require internet once to cache.

---

## 👨‍💻 Developer Documentation

### Hooks & Filters

#### Actions

**`smdp_after_sync`**

Fires after a successful catalog sync.

```php
do_action( 'smdp_after_sync', int $item_count, array $categories, array $mapping );
```

**Parameters:**
- `$item_count` (int) - Number of items synced
- `$categories` (array) - Array of category data
- `$mapping` (array) - Item mapping data

**Example:**
```php
add_action( 'smdp_after_sync', function( $item_count, $categories, $mapping ) {
    // Send notification
    wp_mail( 'admin@example.com', 'Menu Synced', "$item_count items synced" );
}, 10, 3 );
```

---

**`smdp_before_sync`**

Fires before catalog sync begins.

```php
do_action( 'smdp_before_sync' );
```

**Example:**
```php
add_action( 'smdp_before_sync', function() {
    // Log sync start
    error_log( 'Starting menu sync at ' . date( 'Y-m-d H:i:s' ) );
} );
```

---

**`smdp_webhook_received`**

Fires when webhook is received from Square.

```php
do_action( 'smdp_webhook_received', string $event_type, array $payload );
```

**Parameters:**
- `$event_type` (string) - Event type (e.g., "catalog.version.updated")
- `$payload` (array) - Full webhook payload

**Example:**
```php
add_action( 'smdp_webhook_received', function( $event_type, $payload ) {
    if ( $event_type === 'catalog.version.updated' ) {
        // Invalidate external cache
        wp_cache_delete( 'custom_menu_cache' );
    }
}, 10, 2 );
```

---

#### Filters

**`smdp_item_output`**

Filters HTML output for a single menu item.

```php
apply_filters( 'smdp_item_output', string $html, array $item, array $mapping );
```

**Parameters:**
- `$html` (string) - Item HTML markup
- `$item` (array) - Square item data
- `$mapping` (array) - Item mapping data

**Example:**
```php
add_filter( 'smdp_item_output', function( $html, $item, $mapping ) {
    // Add custom badge for featured items
    if ( isset( $item['custom_attribute_values']['featured'] ) ) {
        $html = '<div class="featured-badge">Featured!</div>' . $html;
    }
    return $html;
}, 10, 3 );
```

---

**`smdp_category_name`**

Filters category display name.

```php
apply_filters( 'smdp_category_name', string $name, string $category_id, array $category_data );
```

**Parameters:**
- `$name` (string) - Category name
- `$category_id` (string) - Category ID
- `$category_data` (array) - Full category data

**Example:**
```php
add_filter( 'smdp_category_name', function( $name, $category_id, $category_data ) {
    // Add emoji to category names
    $emoji_map = [
        'appetizers' => '🥗',
        'entrees' => '🍝',
        'desserts' => '🍰',
    ];

    $slug = $category_data['slug'] ?? '';
    if ( isset( $emoji_map[ $slug ] ) ) {
        return $emoji_map[ $slug ] . ' ' . $name;
    }

    return $name;
}, 10, 3 );
```

---

**`smdp_sold_out_text`**

Filters sold-out badge text.

```php
apply_filters( 'smdp_sold_out_text', string $text, array $item );
```

**Parameters:**
- `$text` (string) - Badge text (default: "Sold Out")
- `$item` (array) - Square item data

**Example:**
```php
add_filter( 'smdp_sold_out_text', function( $text, $item ) {
    return '⛔ Currently Unavailable';
}, 10, 2 );
```

---

**`smdp_price_format`**

Filters price display format.

```php
apply_filters( 'smdp_price_format', string $formatted_price, int $amount, string $currency );
```

**Parameters:**
- `$formatted_price` (string) - Formatted price string
- `$amount` (int) - Price in cents
- `$currency` (string) - Currency code (e.g., "USD")

**Example:**
```php
add_filter( 'smdp_price_format', function( $formatted_price, $amount, $currency ) {
    // Show prices in euros
    if ( $currency === 'EUR' ) {
        return number_format( $amount / 100, 2 ) . '€';
    }
    return $formatted_price;
}, 10, 3 );
```

---

**`smdp_sync_interval`**

Filters automatic sync interval.

```php
apply_filters( 'smdp_sync_interval', int $seconds );
```

**Parameters:**
- `$seconds` (int) - Sync interval in seconds

**Example:**
```php
add_filter( 'smdp_sync_interval', function( $seconds ) {
    // Sync every 30 minutes during business hours
    $hour = (int) date( 'G' );
    if ( $hour >= 11 && $hour <= 22 ) {
        return 1800; // 30 minutes
    }
    return 3600; // 1 hour otherwise
} );
```

---

### JavaScript Events

**`smdp:menu-refreshed`**

Triggered when menu content is refreshed.

```javascript
document.addEventListener('smdp:menu-refreshed', function(e) {
    console.log('Menu refreshed:', e.detail);
    // e.detail.category - Category that was refreshed
    // e.detail.timestamp - Timestamp of refresh
});
```

---

**`smdp:item-clicked`**

Triggered when menu item is clicked (before modal opens).

```javascript
document.addEventListener('smdp:item-clicked', function(e) {
    console.log('Item clicked:', e.detail);
    // e.detail.itemId - Square item ID
    // e.detail.itemName - Item name
    // Prevent modal: e.preventDefault()
});
```

---

**`smdp:promo-shown`**

Triggered when promo screen is displayed.

```javascript
document.addEventListener('smdp:promo-shown', function(e) {
    console.log('Promo shown');
    // Track analytics
    gtag('event', 'promo_shown');
});
```

---

**`smdp:promo-dismissed`**

Triggered when promo screen is dismissed.

```javascript
document.addEventListener('smdp:promo-dismissed', function(e) {
    console.log('Promo dismissed');
    // Track user interaction
});
```

---

### REST API Endpoints

**Webhook Endpoint**

```
POST /wp-json/smdp/v1/webhook
```

Receives webhook notifications from Square.

**Headers:**
- `x-square-hmacsha256-signature` - HMAC signature for verification

**Response:**
```json
{
    "success": true,
    "type": "catalog.version.updated"
}
```

---

**Version Check**

```
POST /wp-json/smdp/v1/version
```

Returns current cache version and debug mode status.

**Parameters:**
- `nonce` - WordPress nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "cache_version": 5,
        "debug_mode": 0
    }
}
```

---

### Database Schema

**Options Table**

| Option Name | Type | Description |
|------------|------|-------------|
| `square_menu_access_token` | string (encrypted) | Square API access token |
| `square_menu_items` | array (serialized) | Cached catalog objects |
| `square_menu_item_mapping` | array (serialized) | Item-to-category mappings |
| `square_menu_categories` | array (serialized) | Category data with ordering |
| `square_menu_sync_interval` | integer | Sync interval in seconds |
| `square_menu_sync_mode` | integer | 0=automatic, 1=manual |
| `smdp_last_sync_timestamp` | integer | Unix timestamp of last sync |
| `smdp_oauth_access_token` | string (encrypted) | OAuth access token |
| `smdp_oauth_refresh_token` | string (encrypted) | OAuth refresh token |
| `smdp_oauth_token_expires` | string | ISO 8601 expiration datetime |
| `smdp_cache_version` | integer | Current cache version number |
| `smdp_pwa_debug_mode` | integer | 0=off, 1=on |

---

### Custom Development

**Create Custom Shortcode:**

```php
function custom_menu_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'category' => '',
        'limit' => -1,
        'layout' => 'grid',
    ], $atts );

    // Get items
    $items = get_option( SMDP_ITEMS_OPTION, [] );
    $mapping = get_option( SMDP_MAPPING_OPTION, [] );

    // Filter by category
    $filtered_items = array_filter( $items, function( $item ) use ( $atts, $mapping ) {
        $item_id = $item['id'];
        return isset( $mapping[ $item_id ] ) &&
               $mapping[ $item_id ]['category'] === $atts['category'];
    });

    // Limit
    if ( $atts['limit'] > 0 ) {
        $filtered_items = array_slice( $filtered_items, 0, $atts['limit'] );
    }

    // Build output
    ob_start();
    ?>
    <div class="custom-menu-<?php echo esc_attr( $atts['layout'] ); ?>">
        <?php foreach ( $filtered_items as $item ): ?>
            <div class="custom-menu-item">
                <h3><?php echo esc_html( $item['item_data']['name'] ); ?></h3>
                <!-- Add your custom HTML -->
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_menu', 'custom_menu_shortcode' );
```

**Usage:**
```
[custom_menu category="appetizers" limit="5" layout="list"]
```

---

**Add Custom Item Field:**

```php
// Store custom field during sync
add_action( 'smdp_after_sync', function( $item_count, $categories, $mapping ) {
    $items = get_option( SMDP_ITEMS_OPTION, [] );

    foreach ( $items as &$item ) {
        // Check for custom attribute in Square
        if ( isset( $item['custom_attribute_values']['featured'] ) ) {
            $item['is_featured'] = true;
        }
    }

    update_option( SMDP_ITEMS_OPTION, $items );
}, 10, 3 );

// Display custom field
add_filter( 'smdp_item_output', function( $html, $item, $mapping ) {
    if ( ! empty( $item['is_featured'] ) ) {
        $html = '<div class="featured-star">★</div>' . $html;
    }
    return $html;
}, 10, 3 );
```

---

**Integrate with WooCommerce:**

```php
// Sync WooCommerce stock with Square sold-out status
add_action( 'smdp_after_sync', function() {
    $items = get_option( SMDP_ITEMS_OPTION, [] );

    foreach ( $items as $item ) {
        // Find matching WooCommerce product by SKU
        $sku = $item['item_data']['variations'][0]['item_variation_data']['sku'] ?? '';

        if ( $sku ) {
            $product_id = wc_get_product_id_by_sku( $sku );

            if ( $product_id ) {
                $product = wc_get_product( $product_id );

                // Check if sold out in Square
                $is_sold_out = false; // Check logic here

                if ( $is_sold_out ) {
                    $product->set_stock_status( 'outofstock' );
                } else {
                    $product->set_stock_status( 'instock' );
                }

                $product->save();
            }
        }
    }
} );
```

---

## 📞 Support

### Community Support

- **Documentation:** You're reading it!
- **GitHub Issues:** [Link to repository issues]
- **WordPress Forums:** [Link if published on WordPress.org]

### Commercial Support

For priority support, custom development, or consultation:
- **Email:** [Your support email]
- **Website:** [Your website]

### Contributing

Contributions are welcome!

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write/update tests
5. Submit a pull request

**Coding Standards:**
- Follow WordPress Coding Standards
- PHPDoc blocks for all functions
- Sanitize inputs, escape outputs
- Use WordPress APIs exclusively

---

## 📜 License

This plugin is licensed under the GNU General Public License v2.0 or later.

```
Copyright (C) 2024 Mark Shoucair

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

---

## 🙏 Credits

**Developed by:**
- Mark Shoucair (30%)
- ChatGPT & Claude AI (70%)

**Built with:**
- WordPress
- Square API
- Service Workers API
- Progressive Web App standards

**Special Thanks:**
- Square, Inc. for excellent API documentation
- WordPress community for plugin development standards
- All beta testers and early adopters

---

## 📝 Changelog

### Version 3.0 - 2024-XX-XX

**Major Features:**
- Complete rewrite with modern architecture
- OAuth 2.0 authentication support
- Progressive Web App (PWA) capabilities
- Real-time webhook integration
- Menu App Builder with drag-and-drop
- Help & Bill table service system
- AES-256 encryption for sensitive data
- Multi-factor rate limiting
- Smart cache management with versioning

**Security Enhancements:**
- Encrypted credential storage
- HMAC webhook signature verification
- CSRF protection on all endpoints
- Input validation and output escaping
- Rate limiting on public endpoints

**Performance Improvements:**
- Service worker caching
- Optimized database queries
- Reduced API calls with smart caching
- Async menu refresh

**Developer Features:**
- Comprehensive hook system
- REST API endpoints
- Custom shortcode support
- Debug mode and logging
- Extensive documentation

---

## 🚀 Roadmap

### Planned Features

**Version 3.1:**
- [ ] Online ordering integration
- [ ] Customer feedback system
- [ ] Analytics dashboard
- [ ] Multi-location support
- [ ] Dietary filters (vegan, gluten-free, etc.)

**Version 3.2:**
- [ ] Push notifications for specials
- [ ] Customer loyalty integration
- [ ] Gift card display
- [ ] Reservation integration
- [ ] Social media sharing

**Version 4.0:**
- [ ] Headless CMS mode
- [ ] GraphQL API
- [ ] React-based admin interface
- [ ] Multi-language support
- [ ] Accessibility improvements (WCAG 2.1 AAA)

---

## 🤝 Sponsor This Project

If you find this plugin valuable, consider supporting its development:

- ⭐ Star the repository on GitHub
- 📢 Share with other restaurant owners
- 🐛 Report bugs and suggest features
- 💻 Contribute code improvements
- ☕ Buy us a coffee [Link to donation page]

---

**Thank you for using Square Menu Display Premium Deluxe Pro!**

We hope this plugin helps you create amazing menu experiences for your customers.

For questions, suggestions, or feedback, please don't hesitate to reach out.

*Last updated: 2024-XX-XX*
