# Square Menu Display PWA

A powerful WordPress plugin that transforms your Square POS menu into a Progressive Web App (PWA) with built-in protection features and customizable styling.

---

## 🤖 AI-Powered Development

This plugin was developed with assistance from **Claude** (Anthropic) and **ChatGPT** (OpenAI). The entire development process leveraged AI to:

- Architect the plugin structure and file organization
- Implement complex PWA manifest generation
- Create hard-coded protection features for iOS and web browsers
- Design responsive CSS layouts with mobile-first approach
- Debug and refine functionality through iterative conversations
- Ensure WordPress coding standards and best practices

The collaboration between human creativity and AI technical capabilities resulted in a robust, production-ready plugin that would have taken significantly longer to develop manually.

---

## Features

### 📱 Progressive Web App (PWA)
- **Installable**: Users can install your menu as a standalone app on iOS, Android, and desktop
- **Offline Capable**: Menu data is cached for offline viewing
- **App-like Experience**: Full-screen display without browser chrome
- **Custom Icons**: Upload custom app icons (192x192, 512x512, Apple Touch Icon)
- **App Identity**: Customize app name, short name, and description
- **Display Options**: Configure display mode (standalone, fullscreen, minimal-ui) and orientation

### 🔒 Built-in Protection Features
All protection features are **hard-coded** and automatically enabled on menu app pages:

- **Pinch Zoom Prevention**: Blocks pinch-to-zoom gestures on iOS and iPad
- **Text Selection Blocking**: Prevents text selection across the menu app
- **Right-Click Prevention**: Disables context menu access
- **Copy/Paste/Cut Blocking**: Prevents content copying via keyboard or menu
- **Long-Press Menu Prevention**: Blocks iOS long-press menus on images and text
- **Keyboard Shortcut Blocking**: Disables Ctrl+C, Ctrl+S, Ctrl+U, Ctrl+A, Ctrl+X, Ctrl+P

These features protect your menu content while maintaining usability for legitimate interactions like form inputs.

### 🎨 Hard-Coded Styling
Professional, responsive design with:

- **Help & Bill Button Styles**: Rounded buttons with custom colors (#f5fdfc background, #00649d text)
- **Category Navigation**: Optimized padding (10px 14px) and gap spacing (8px)
- **Full-Width Layout**: Menu items utilize full available width
- **Responsive Design**: Mobile-first approach with tablet and desktop breakpoints
- **Left Sidebar Support**: Special styling for left-aligned category navigation
- **Smooth Scrolling**: Enhanced scroll behavior for better UX

### 🔄 Square POS Integration
- **Real-time Sync**: Automatically fetches menu data from Square API
- **Category Management**: Organize items by Square catalog categories
- **Item Details**: Display descriptions, prices, variations, and modifiers
- **Image Support**: Show product images from Square catalog
- **Inventory Tracking**: Display stock status and availability

### ⚙️ Admin Features
- **Layout Customization**: Choose between different menu layouts
- **Category Button Styles**: Customize category navigation appearance
- **Custom CSS Editor**: Add your own CSS for advanced customization
- **Menu Items Editor**: Manage which items appear in the menu
- **Promo Screen**: Optional promotional content before menu display

---

## Installation

### Manual Installation
1. Download the plugin ZIP file or clone this repository
2. Upload the `square-menu-display-pwa` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Square Menu Display** in the admin sidebar

### Fresh Install Consistency
This plugin is designed to work identically across all WordPress installations:
- All protection features are hard-coded
- All structural CSS is hard-coded
- No additional configuration needed for core functionality
- Every fresh install will have the same layout and protection

---

## Configuration

### 1. Square API Setup
1. Go to **Square Menu Display > Settings**
2. Enter your Square Access Token
3. Select your Square Location
4. Save settings

### 2. PWA Customization
1. Navigate to **Square Menu Display > App Layout**
2. Under **PWA Settings**:
   - Upload App Icon (192x192)
   - Upload Large App Icon (512x512)
   - Upload Apple Touch Icon (180x180)
   - Set App Name and Short Name
   - Add App Description
   - Choose Display Mode
   - Set Orientation Lock

### 3. Display Your Menu
Add the shortcode to any page or post:

```
[smdp_menu_app]
```

---

## Technical Architecture

### File Structure
```
square-menu-display-pwa/
├── Main.php                              # Main plugin file
├── includes/
│   ├── class-menu-app-builder.php        # Admin UI and settings
│   ├── class-manifest-generator.php      # PWA manifest generation
│   ├── class-protection-settings.php     # Hard-coded protection features
│   ├── class-appearance-manager.php      # CSS loading
│   ├── class-square-api.php              # Square API integration
│   └── ...
├── assets/
│   ├── css/
│   │   ├── smdp-structural.css           # Hard-coded styles
│   │   ├── menu-app.css                  # Menu app styles
│   │   └── menu-app-admin.css            # Admin UI styles
│   └── js/
│       └── ...
└── README.md                             # This file
```

### Key Classes

#### `SMDP_Protection_Settings`
- Automatically injects protection scripts on menu app pages
- No settings or toggles - always enabled
- Detects menu app pages via `SMDP_MENU_APP_RENDERED` constant
- Methods:
  - `inject_protection()` - Main injection point
  - `inject_protection_styles()` - Injects CSS for protection
  - `inject_protection_scripts()` - Injects JavaScript for protection

#### `SMDP_Appearance_Manager`
- Enqueues structural CSS file
- No dynamic CSS generation - all styles are hard-coded
- Methods:
  - `enqueue_structural_css()` - Loads CSS file on menu app pages

#### `SMDP_Manifest_Generator`
- Generates PWA manifest.json dynamically
- Uses custom settings from admin panel
- Injects manifest link and Apple Touch Icon
- Handles icon fallbacks to default assets

---

## Development Notes

### AI Collaboration Process
The development of this plugin involved iterative conversations with Claude and ChatGPT:

1. **Initial Planning**: AI helped architect the plugin structure and identify necessary WordPress hooks
2. **Implementation**: AI generated initial code implementations following WordPress standards
3. **Debugging**: AI assisted in identifying and fixing issues like:
   - Shortcode name mismatches
   - Page detection timing problems
   - CSS specificity conflicts
4. **Refactoring**: AI helped transition from dynamic settings to hard-coded values based on user feedback
5. **Optimization**: AI suggested performance improvements and code organization

### Design Decisions

#### Hard-Coded vs. Customizable
Initially, protection and appearance features were designed to be customizable via admin toggles. However, the final implementation hard-codes everything to ensure:
- **Consistency**: Every installation looks and behaves identically
- **Simplicity**: No configuration needed for core features
- **Performance**: No database lookups for settings
- **Reliability**: No risk of misconfiguration

#### Protection Implementation
Protection features only apply to menu app pages (not entire site) to:
- Avoid interfering with WordPress admin
- Allow normal browsing on other pages
- Focus protection on menu content

#### CSS Organization
Structural CSS is separated from menu app CSS to:
- Make updates easier
- Distinguish core layout from theme styling
- Allow custom CSS additions without conflicts

---

## Browser Compatibility

### Tested Browsers
- ✅ Chrome (Desktop & Mobile)
- ✅ Safari (Desktop, iOS, iPadOS)
- ✅ Firefox (Desktop & Mobile)
- ✅ Edge (Desktop & Mobile)
- ✅ Samsung Internet

### PWA Support
- ✅ iOS/iPadOS 16.4+ (Add to Home Screen)
- ✅ Android (Chrome, Samsung Internet)
- ✅ Desktop Chrome/Edge (Install App)
- ✅ Desktop Safari 17+ (Add to Dock)

### Protection Features
- iOS/iPadOS: All protection features work
- Android: All protection features work
- Desktop: Most protection features work (pinch zoom N/A)

---

## Frequently Asked Questions

### Can I customize the protection features?
No, protection features are hard-coded and always enabled on menu app pages. This ensures consistent behavior across all installations.

### Can I change the Help/Bill button colors?
The button colors are hard-coded in `assets/css/smdp-structural.css`. You can modify the CSS file directly if needed, but changes will be overwritten on plugin updates.

### Does this work with other page builders?
Yes, as long as you can add the `[smdp_menu_app]` shortcode, it will work with any page builder (Elementor, Divi, WPBakery, Gutenberg, etc.).

### Will protection features break my site?
No, protection features only apply to pages containing the `[smdp_menu_app]` shortcode. Other pages function normally.

### Can I use this for multiple locations?
Currently, the plugin is designed for single-location use. Multi-location support may be added in future versions.

### What happens if I don't upload custom PWA icons?
The plugin will fall back to default icons included with the plugin.

---

## Changelog

### Version 3.0
- ✨ Complete rewrite with AI assistance
- ✨ Added PWA manifest generation
- ✨ Added custom icon upload functionality
- ✨ Hard-coded protection features
- ✨ Hard-coded appearance styling
- ✨ Simplified admin interface
- ✨ Improved Square API integration
- 🐛 Fixed shortcode detection issues
- 🐛 Fixed page detection timing problems
- ⚡ Performance improvements

### Version 2.4.4
- Previous stable version

---

## Credits

### Development Team
- **Developer**: Mark (Human)
- **AI Assistants**: Claude (Anthropic), ChatGPT (OpenAI)

### Technologies Used
- WordPress 5.8+
- PHP 7.4+
- Square API v2
- Progressive Web App APIs
- Service Workers
- Web Manifest Specification

### AI Tools
- **Claude** (Anthropic): Primary development assistant for code generation, debugging, and architecture
- **ChatGPT** (OpenAI): Additional development support and code review

---

## License

This plugin is proprietary software. All rights reserved.

---

## Support

For issues, questions, or feature requests, please contact the plugin developer  mark@shoucair.ca
---

## Future Enhancements

Potential features for future versions:
- Multi-location support
- Order integration with Square
- Customer accounts and order history
- Enhanced analytics
- A/B testing for menu layouts
- Customizable protection toggles (optional)
- Theme presets for styling
- Integration with other POS systems
