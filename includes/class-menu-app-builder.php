<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMDP_Menu_App_Builder {
  const OPT_MENUS    = 'smdp_app_menus';
  const OPT_CSS      = 'smdp_app_custom_css';
  const OPT_CATALOG  = 'smdp_app_catalog';
  const OPT_SETTINGS = 'smdp_app_settings'; // ['layout' => 'top'|'left', 'promo_image' => url, 'promo_timeout' => seconds]
  const OPT_STYLES   = 'smdp_app_button_styles'; // Button styles

  public static function init() {
    add_action('admin_menu', array(__CLASS__, 'admin_menu'));
    add_action('admin_init', array(__CLASS__, 'register_settings'));
    add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin'));
    add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend'));
    add_action('wp_head', array(__CLASS__, 'inject_custom_styles'));
    add_action('admin_post_smdp_save_pwa_settings', array(__CLASS__, 'handle_pwa_settings_save'));
    add_action('admin_init', array(__CLASS__, 'handle_flush_rewrite_rules'));
    add_shortcode('smdp_menu_app', array(__CLASS__, 'shortcode_render'));

    add_action('rest_api_init', function() {
      register_rest_route('smdp/v1', '/app-catalog', array(
        'methods'  => 'GET',
        'callback' => array(__CLASS__, 'rest_get_catalog'),
        'permission_callback' => function(){ return current_user_can('manage_options'); },
      ));
      register_rest_route('smdp/v1', '/bootstrap', array(
        'methods'  => 'POST',
        'callback' => array(__CLASS__, 'rest_bootstrap_from_cache'),
        'permission_callback' => function(){ return current_user_can('manage_options'); },
      ));
      register_rest_route('smdp/v1', '/catalog-search', array(
        'methods'  => 'GET',
        'callback' => array(__CLASS__, 'rest_catalog_search_local'),
        'permission_callback' => function(){ return current_user_can('manage_options'); },
        'args' => array( 'q' => array('required' => true) )
      ));
      register_rest_route('smdp/v1', '/categories-order', array(
        'methods'  => 'POST',
        'callback' => array(__CLASS__, 'rest_save_categories_order'),
        'permission_callback' => function(){ return current_user_can('manage_options'); },
      ));
    });
  }

  /** ---------------- Admin ---------------- */

  public static function admin_menu() {
    // Add as submenu under Square Menu instead of top-level menu
    add_submenu_page(
      'smdp_main',                          // Parent slug (Square Menu)
      'Menu App Builder',                   // Page title
      'Menu App Builder',                   // Menu title
      'manage_options',                     // Capability
      'smdp_menu_app_builder',              // Menu slug
      array(__CLASS__, 'render_admin_page') // Callback
    );
  }

  public static function register_settings() {
    // Sanitize callback for styles
    $sanitize_styles = function($input) {
        if (!is_array($input)) return array();
        
        $sanitized = array();
        $sanitized['bg_color'] = !empty($input['bg_color']) ? sanitize_hex_color($input['bg_color']) : '#ffffff';
        $sanitized['text_color'] = !empty($input['text_color']) ? sanitize_hex_color($input['text_color']) : '#333333';
        $sanitized['border_color'] = !empty($input['border_color']) ? sanitize_hex_color($input['border_color']) : '#e5e5e5';
        $sanitized['active_bg_color'] = !empty($input['active_bg_color']) ? sanitize_hex_color($input['active_bg_color']) : '#f3f4f6';
        $sanitized['active_text_color'] = !empty($input['active_text_color']) ? sanitize_hex_color($input['active_text_color']) : '#333333';
        $sanitized['active_border_color'] = !empty($input['active_border_color']) ? sanitize_hex_color($input['active_border_color']) : '#333333';
        $sanitized['font_size'] = !empty($input['font_size']) ? intval($input['font_size']) : 16;
        $sanitized['padding_vertical'] = !empty($input['padding_vertical']) ? intval($input['padding_vertical']) : 10;
        $sanitized['padding_horizontal'] = !empty($input['padding_horizontal']) ? intval($input['padding_horizontal']) : 14;
        $sanitized['border_radius'] = !empty($input['border_radius']) ? intval($input['border_radius']) : 999;
        $sanitized['border_width'] = !empty($input['border_width']) ? intval($input['border_width']) : 1;
        $sanitized['font_weight'] = !empty($input['font_weight']) ? sanitize_text_field($input['font_weight']) : 'normal';
        $sanitized['font_family'] = !empty($input['font_family']) ? sanitize_text_field($input['font_family']) : '';
        
        return $sanitized;
    };
    
    // Sanitize callback for settings (including promo images)
    $sanitize_settings = function($input) {
        if (!is_array($input)) return array();

        // IMPORTANT: Merge with existing settings to preserve values from other forms
        $existing = get_option(self::OPT_SETTINGS, array());
        if (!is_array($existing)) {
            $existing = array();
        }

        // Start with existing settings, then overlay new values
        $sanitized = $existing;

        // Layout - only update if present in input
        if (isset($input['layout'])) {
            $sanitized['layout'] = sanitize_text_field($input['layout']);
        }
        
        // Promo timeout - only update if present
        if (isset($input['promo_timeout'])) {
            $sanitized['promo_timeout'] = intval($input['promo_timeout']);
        }

        // PWA theme colors - only update if present
        if (isset($input['theme_color'])) {
            $sanitized['theme_color'] = ! empty( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : '#5C7BA6';
        }
        if (isset($input['background_color'])) {
            $sanitized['background_color'] = ! empty( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '#ffffff';
        }

        // PWA icons - only update if present and not empty (preserve existing if empty)
        if (isset($input['icon_192']) && ! empty( $input['icon_192'] )) {
            $sanitized['icon_192'] = esc_url_raw( $input['icon_192'] );
        }
        if (isset($input['icon_512']) && ! empty( $input['icon_512'] )) {
            $sanitized['icon_512'] = esc_url_raw( $input['icon_512'] );
        }
        if (isset($input['apple_touch_icon']) && ! empty( $input['apple_touch_icon'] )) {
            $sanitized['apple_touch_icon'] = esc_url_raw( $input['apple_touch_icon'] );
        }

        // PWA identity - only update if present (can be empty strings)
        if (isset($input['app_name'])) {
            $sanitized['app_name'] = sanitize_text_field( $input['app_name'] );
        }
        if (isset($input['app_short_name'])) {
            $sanitized['app_short_name'] = sanitize_text_field( $input['app_short_name'] );
        }
        if (isset($input['app_description'])) {
            $sanitized['app_description'] = sanitize_text_field( $input['app_description'] );
        }

        // PWA display options - only update if present
        if (isset($input['display_mode'])) {
            $sanitized['display_mode'] = sanitize_text_field( $input['display_mode'] );
        }
        if (isset($input['orientation'])) {
            $sanitized['orientation'] = sanitize_text_field( $input['orientation'] );
        }

        // Promo image (single image only) - only update if present
        if (isset($input['promo_images'])) {
            if (is_string($input['promo_images'])) {
                $decoded = json_decode(stripslashes($input['promo_images']), true);
                if (is_array($decoded)) {
                    $sanitized['promo_images'] = array_map('esc_url_raw', $decoded);
                } else {
                    $sanitized['promo_images'] = array();
                }
            } elseif (is_array($input['promo_images'])) {
                $sanitized['promo_images'] = array_map('esc_url_raw', $input['promo_images']);
            } else {
                $sanitized['promo_images'] = array();
            }
        } else {
            $sanitized['promo_images'] = array();
        }
        
        return $sanitized;
    };

    // Layout & CSS settings (App Layout tab) - SEPARATE GROUP
    register_setting('smdp_menu_app_layout_group', self::OPT_CSS);
    register_setting('smdp_menu_app_layout_group', self::OPT_SETTINGS, array(
        'sanitize_callback' => $sanitize_settings
    ));
    
    // Button styles settings (Styles tab) - SEPARATE GROUP
    register_setting('smdp_menu_app_styles_group', self::OPT_STYLES, array(
        'sanitize_callback' => $sanitize_styles,
        'default' => array()
    ));

    add_settings_section('smdp_menu_app_section', '', '__return_false', 'smdp_menu_app_builder');
    add_settings_field(self::OPT_SETTINGS, 'App Layout', array(__CLASS__, 'field_settings'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
    add_settings_field('promo_settings', 'Promo Screen', array(__CLASS__, 'field_promo_settings'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
    add_settings_field(self::OPT_CSS, 'Custom CSS', array(__CLASS__, 'field_css'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
  }

  public static function enqueue_admin($hook) {
    if ($hook !== 'square-menu_page_smdp_menu_app_builder') return;

    // Enqueue media uploader for promo image
    wp_enqueue_media();

    $plugin_main = dirname(dirname(__FILE__)) . '/Main.php';
    $base_url = plugins_url('', $plugin_main);
    $ver = '1.8.3';

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('smdp-menu-app-admin', $base_url . '/assets/css/menu-app-admin.css', array(), $ver);
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('smdp-menu-app-admin', $base_url . '/assets/js/menu-app-builder-admin.js', array('jquery','jquery-ui-sortable','wp-color-picker'), $ver, true);

    $catalog = get_option(self::OPT_CATALOG, array());
    if (is_string($catalog)) { $catalog = json_decode($catalog, true); if (!is_array($catalog)) $catalog = array(); }

    wp_localize_script('smdp-menu-app-admin', 'SMDP_MENU_APP', array(
      'catalog' => $catalog,
      'rest'    => array(
        'base'     => esc_url_raw( rest_url( 'smdp/v1/' ) ),
        'catalog'  => esc_url_raw( rest_url('smdp/v1/app-catalog') ),
        'bootstrap'=> esc_url_raw( rest_url('smdp/v1/bootstrap') ),
        'search'   => esc_url_raw( rest_url('smdp/v1/catalog-search') ),
        'nonce'    => wp_create_nonce('wp_rest'),
      ),
    ));
  }

  public static function enqueue_frontend() {
    $plugin_main = dirname(dirname(__FILE__)) . '/Main.php';
    $base_url = plugins_url('', $plugin_main);
    $ver = '1.8';

    // Register structural CSS (hardcoded layout & structure)
    wp_register_style('smdp-structural', $base_url . '/assets/css/smdp-structural.css', array(), $ver);

    // Register menu app CSS (hardcoded visual styles)
    wp_register_style('smdp-menu-app',  $base_url . '/assets/css/menu-app.css', array('smdp-structural'), $ver);

    wp_register_script('smdp-menu-app', $base_url . '/assets/js/menu-app-frontend.js', array(), $ver, true);
    wp_register_script('smdp-pwa-install', $base_url . '/assets/js/pwa-install.js', array(), $ver, true);

    // Hook to output user's custom CSS (if any)
    add_action('wp_footer', array(__CLASS__, 'output_custom_css'), 100);
  }

  /**
   * Output user's custom CSS from admin textarea
   * This runs in wp_footer to allow users to override hardcoded styles
   */
  public static function output_custom_css() {
    // Only output if menu app CSS is enqueued (menu app is on the page)
    if (!wp_style_is('smdp-menu-app', 'enqueued')) {
      return;
    }

    // Get custom CSS from settings
    $custom_css = get_option(self::OPT_CSS, '');

    // Don't output if empty or just the default placeholder
    if (empty($custom_css) || trim($custom_css) === '' || trim($custom_css) === '/* Button & card style overrides here */') {
      return;
    }

    // Sanitize and output custom CSS
    $sanitized_css = wp_strip_all_tags($custom_css);

    if (!empty($sanitized_css)) {
      echo "\n<style id=\"smdp-user-custom-css\">\n/* User Custom CSS */\n" . $sanitized_css . "\n</style>\n";
    }
  }

  /**
   * Inject custom category button styles into wp_head
   * This outputs dynamic CSS based on the button styles saved in the admin
   */
  public static function inject_custom_styles() {
    // Only output if on a page that might have the menu app
    if (is_admin()) {
      return;
    }

    // Get saved button styles
    $styles = get_option(self::OPT_STYLES, array());

    // If no custom styles are saved, don't output anything
    if (empty($styles)) {
      return;
    }

    // Default values
    $defaults = array(
      'bg_color' => '#5C7BA6',
      'text_color' => '#ffffff',
      'border_color' => '#5C7BA6',
      'active_bg_color' => '#4A6288',
      'active_text_color' => '#ffffff',
      'active_border_color' => '#4A6288',
      'font_size' => 18,
      'padding_vertical' => 12,
      'padding_horizontal' => 20,
      'border_radius' => 8,
      'border_width' => 1,
      'font_weight' => '600',
      'font_family' => '',
    );

    $styles = array_merge($defaults, $styles);

    // Generate CSS
    ?>
<style id="smdp-custom-button-styles">
/* Custom Category Button Styles from Menu App Builder */
.smdp-cat-btn {
  background-color: <?php echo esc_attr($styles['bg_color']); ?> !important;
  color: <?php echo esc_attr($styles['text_color']); ?> !important;
  border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['border_color']); ?> !important;
  font-size: <?php echo esc_attr($styles['font_size']); ?>px !important;
  padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px !important;
  border-radius: <?php echo esc_attr($styles['border_radius']); ?>px !important;
  font-weight: <?php echo esc_attr($styles['font_weight']); ?> !important;
  <?php if (!empty($styles['font_family'])): ?>
  font-family: <?php echo esc_attr($styles['font_family']); ?> !important;
  <?php endif; ?>
}

.smdp-cat-btn.active,
.smdp-cat-btn:hover {
  background-color: <?php echo esc_attr($styles['active_bg_color']); ?> !important;
  color: <?php echo esc_attr($styles['active_text_color']); ?> !important;
  border-color: <?php echo esc_attr($styles['active_border_color']); ?> !important;
}

/* Left layout - keep custom styles but maintain layout-specific sizing */
.smdp-menu-app-fe.layout-left .smdp-cat-btn {
  font-size: <?php echo max(14, esc_attr($styles['font_size']) - 7); ?>px !important;
  padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo max(20, esc_attr($styles['padding_horizontal']) - 8); ?>px !important;
}
</style>
<?php
  }

  public static function render_admin_page() {
    ?>
    <div class="wrap smdp-menu-app">
      <h1>Menu App Builder</h1>
      <p>Create an app-like, tablet-friendly menu layout. <strong>Categories and items come from your Menu Editor</strong>. Edit there to see changes live.</p>

      <div style="margin:10px 0 18px;">
        <button type="button" class="button" id="smdp-bootstrap-btn">Rebuild Catalog Cache (from Menu Editor)</button>
        <span id="smdp-bootstrap-status" style="margin-left:8px;"></span>
      </div>

      <h2 class="nav-tab-wrapper">
        <a href="#tab-layout" class="nav-tab nav-tab-active">Configuration</a>
        <a href="#tab-styles" class="nav-tab">Styles</a>
        <a href="#tab-advanced" class="nav-tab">Advanced</a>
      </h2>

      <div id="tab-layout" class="smdp-tab active">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">App Configuration</h2>
        <p>Configure the menu app layout, promo screen, and Progressive Web App settings.</p>

        <?php
        // Get all settings needed for subtabs
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        $cache_version = get_option( 'smdp_cache_version', 1 );
        $settings = get_option(self::OPT_SETTINGS, array());
        if (!is_array($settings)) $settings = array();

        $theme_color = isset($settings['theme_color']) ? $settings['theme_color'] : '#5C7BA6';
        $background_color = isset($settings['background_color']) ? $settings['background_color'] : '#ffffff';
        $icon_192 = isset($settings['icon_192']) ? $settings['icon_192'] : '';
        $icon_512 = isset($settings['icon_512']) ? $settings['icon_512'] : '';
        $apple_touch_icon = isset($settings['apple_touch_icon']) ? $settings['apple_touch_icon'] : '';
        $app_name = isset($settings['app_name']) ? $settings['app_name'] : '';
        $app_short_name = isset($settings['app_short_name']) ? $settings['app_short_name'] : '';
        $app_description = isset($settings['app_description']) ? $settings['app_description'] : '';
        $display_mode = isset($settings['display_mode']) ? $settings['display_mode'] : 'standalone';
        $orientation = isset($settings['orientation']) ? $settings['orientation'] : 'any';
        ?>

        <!-- Subtabs for Configuration -->
        <div class="smdp-config-subtabs" style="margin:20px 0; border-bottom:1px solid #ccc;">
          <a href="#config-main" class="smdp-config-subtab active" style="display:inline-block; padding:10px 20px; text-decoration:none; border-bottom:2px solid #2271b1; margin-bottom:-1px;">Main</a>
          <a href="#config-promo" class="smdp-config-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Promo Screen</a>
          <a href="#config-pwa" class="smdp-config-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">PWA</a>
        </div>

        <!-- Subtab: Main -->
        <div id="config-main" class="smdp-config-subtab-content active" style="margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_layout_group'); ?>
            <h3>App Layout</h3>
            <?php self::field_settings(); ?>
            <?php submit_button('Save Layout'); ?>
          </form>

          <hr style="margin:30px 0; border:none; border-top:1px solid #ddd;">

          <h3>📱 Display Menu App</h3>
          <div style="background:#f9f9f9; padding:15px; border-left:4px solid #2271b1; margin-bottom:20px;">
            <p style="margin:0 0 10px 0;"><strong>Standalone URL:</strong></p>
            <p style="margin:0 0 15px 0;">
              <code style="background:#fff; padding:6px 10px; display:inline-block;"><?php echo home_url('/menu-app/'); ?></code>
              <a href="<?php echo home_url('/menu-app/'); ?>" target="_blank" class="button button-secondary" style="margin-left:10px;">Preview</a>
            </p>

            <p style="margin:0 0 10px 0;"><strong>Shortcode:</strong></p>
            <p style="margin:0;">
              <code style="background:#fff; padding:6px 10px; display:inline-block;">[smdp_menu_app]</code>
              <span style="margin-left:10px; color:#666;">Optional: <code style="background:#fff; padding:2px 6px;">layout="left"</code></span>
            </p>
          </div>

          <h3>🔗 Category-Specific URLs</h3>
          <p class="description">Each category has its own standalone URL. Find the "Copy Link" button next to each category in the Menu Editor.</p>
          <p class="description">Example: <code style="background:#f9f9f9; padding:2px 6px;"><?php echo home_url('/menu-app/category/appetizers/'); ?></code></p>
        </div>

        <!-- Subtab: Promo Screen -->
        <div id="config-promo" class="smdp-config-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_layout_group'); ?>
            <h3>Promo Screen Settings</h3>
            <?php self::field_promo_settings(); ?>
            <?php submit_button('Save Promo Settings'); ?>
          </form>
        </div>

        <!-- Subtab: PWA -->
        <div id="config-pwa" class="smdp-config-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="smdp_save_pwa_settings">
            <?php wp_nonce_field( 'smdp_pwa_settings_save', 'smdp_pwa_nonce' ); ?>

            <h3>🐛 Debug Mode</h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
              <div>
                <label style="display:block; margin-bottom:8px;">
                  <input type="checkbox" name="smdp_pwa_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?>>
                  <strong>Enable Debug Mode</strong>
                </label>
                <p class="description">Bypass caching and show debug panel on tablets.</p>
              </div>
              <div>
                <label style="display:block; margin-bottom:8px;"><strong>Cache Version</strong></label>
                <input type="number" name="smdp_cache_version" id="smdp_cache_version_pwa" value="<?php echo esc_attr($cache_version); ?>" min="1" style="width:80px;">
                <button type="button" class="button" id="smdp-increment-version-pwa">Increment</button>
                <p class="description">Current: v<?php echo $cache_version; ?>. Increment to force tablet reload.</p>
                <script>
                  jQuery(document).ready(function($){
                    $('#smdp-increment-version-pwa').click(function(){
                      var $input = $('#smdp_cache_version_pwa');
                      $input.val(parseInt($input.val()) + 1);
                    });
                  });
                </script>
              </div>
            </div>

            <h3>🎨 PWA Theme Colors</h3>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
              <div>
                <label style="display:block; margin-bottom:8px;"><strong>Theme Color</strong></label>
                <input type="text" name="smdp_pwa_theme_color" value="<?php echo esc_attr($theme_color); ?>" class="smdp-pwa-color-picker" />
                <p class="description">Browser address bar & splash screen color</p>
              </div>
              <div>
                <label style="display:block; margin-bottom:8px;"><strong>Background Color</strong></label>
                <input type="text" name="smdp_pwa_background_color" value="<?php echo esc_attr($background_color); ?>" class="smdp-pwa-color-picker" />
                <p class="description">Splash screen background when launching</p>
              </div>
            </div>

            <h3>📱 PWA App Icons</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:30px;">
              <!-- Icon 192x192 -->
              <div>
                <label><strong>App Icon 192x192</strong></label><br>
                <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[icon_192]" id="smdp-icon-192" value="<?php echo esc_attr($icon_192); ?>">
                <button type="button" class="button smdp-upload-icon" data-target="smdp-icon-192" data-preview="smdp-icon-192-preview">Upload</button>
                <?php if ($icon_192): ?>
                  <button type="button" class="button smdp-clear-icon" data-target="smdp-icon-192" data-preview="smdp-icon-192-preview">Remove</button>
                <?php endif; ?>
                <div id="smdp-icon-192-preview" style="margin-top:8px;">
                  <?php if ($icon_192): ?>
                    <img src="<?php echo esc_url($icon_192); ?>" style="max-width:120px; border:1px solid #ddd; padding:4px; display:block;">
                  <?php endif; ?>
                </div>
                <p class="description" style="margin-top:8px;">Android home screen (192x192)</p>
              </div>

              <!-- Icon 512x512 -->
              <div>
                <label><strong>App Icon 512x512</strong></label><br>
                <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[icon_512]" id="smdp-icon-512" value="<?php echo esc_attr($icon_512); ?>">
                <button type="button" class="button smdp-upload-icon" data-target="smdp-icon-512" data-preview="smdp-icon-512-preview">Upload</button>
                <?php if ($icon_512): ?>
                  <button type="button" class="button smdp-clear-icon" data-target="smdp-icon-512" data-preview="smdp-icon-512-preview">Remove</button>
                <?php endif; ?>
                <div id="smdp-icon-512-preview" style="margin-top:8px;">
                  <?php if ($icon_512): ?>
                    <img src="<?php echo esc_url($icon_512); ?>" style="max-width:120px; border:1px solid #ddd; padding:4px; display:block;">
                  <?php endif; ?>
                </div>
                <p class="description" style="margin-top:8px;">Splash screen (512x512)</p>
              </div>

              <!-- Apple Touch Icon -->
              <div>
                <label><strong>Apple Touch Icon</strong></label><br>
                <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[apple_touch_icon]" id="smdp-apple-icon" value="<?php echo esc_attr($apple_touch_icon); ?>">
                <button type="button" class="button smdp-upload-icon" data-target="smdp-apple-icon" data-preview="smdp-apple-icon-preview">Upload</button>
                <?php if ($apple_touch_icon): ?>
                  <button type="button" class="button smdp-clear-icon" data-target="smdp-apple-icon" data-preview="smdp-apple-icon-preview">Remove</button>
                <?php endif; ?>
                <div id="smdp-apple-icon-preview" style="margin-top:8px;">
                  <?php if ($apple_touch_icon): ?>
                    <img src="<?php echo esc_url($apple_touch_icon); ?>" style="max-width:120px; border:1px solid #ddd; padding:4px; display:block;">
                  <?php endif; ?>
                </div>
                <p class="description" style="margin-top:8px;">iOS home screen (180x180)</p>
              </div>
            </div>

            <h3>✏️ PWA App Identity</h3>
            <div style="margin-bottom:12px;">
              <label><strong>App Name</strong></label><br>
              <input type="text" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[app_name]" value="<?php echo esc_attr($app_name); ?>" placeholder="e.g., Joe's Restaurant Menu" style="width:100%; max-width:400px;">
              <p class="description">Full app name (leave empty to use "[Site Name] Menu")</p>
            </div>

            <div style="margin-bottom:12px;">
              <label><strong>App Short Name</strong></label><br>
              <input type="text" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[app_short_name]" value="<?php echo esc_attr($app_short_name); ?>" placeholder="e.g., Menu" style="width:100%; max-width:200px;" maxlength="12">
              <p class="description">Short name for home screen label (12 characters max, leave empty to use "Menu")</p>
            </div>

            <div style="margin-bottom:20px;">
              <label><strong>App Description</strong></label><br>
              <textarea name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[app_description]" rows="2" style="width:100%; max-width:500px;" placeholder="e.g., Browse our menu and order from your table"><?php echo esc_textarea($app_description); ?></textarea>
              <p class="description">Description shown in install dialog</p>
            </div>

            <h3>🖥️ PWA Display Options</h3>
            <div style="margin-bottom:12px;">
              <label><strong>Display Mode</strong></label><br>
              <select name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[display_mode]">
                <option value="standalone" <?php selected($display_mode, 'standalone'); ?>>Standalone (recommended - looks like native app)</option>
                <option value="fullscreen" <?php selected($display_mode, 'fullscreen'); ?>>Fullscreen (hides status bar too)</option>
                <option value="minimal-ui" <?php selected($display_mode, 'minimal-ui'); ?>>Minimal UI (shows minimal browser UI)</option>
                <option value="browser" <?php selected($display_mode, 'browser'); ?>>Browser (regular browser tab)</option>
              </select>
              <p class="description">How the app looks when installed</p>
            </div>

            <div style="margin-bottom:20px;">
              <label><strong>Orientation Lock</strong></label><br>
              <select name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[orientation]">
                <option value="any" <?php selected($orientation, 'any'); ?>>Any (recommended - allows rotation)</option>
                <option value="portrait" <?php selected($orientation, 'portrait'); ?>>Portrait only</option>
                <option value="landscape" <?php selected($orientation, 'landscape'); ?>>Landscape only</option>
                <option value="portrait-primary" <?php selected($orientation, 'portrait-primary'); ?>>Portrait Primary</option>
                <option value="landscape-primary" <?php selected($orientation, 'landscape-primary'); ?>>Landscape Primary</option>
              </select>
              <p class="description">Lock screen orientation</p>
            </div>

            <?php submit_button( 'Save PWA Settings', 'primary', 'smdp_save_pwa' ); ?>
          </form>

          <script>
          jQuery(document).ready(function($){
            // Icon upload handlers
            var iconUploaders = {};

            $('.smdp-upload-icon').on('click', function(e){
              e.preventDefault();
              var button = $(this);
              var targetId = button.data('target');
              var previewId = button.data('preview');

              if (iconUploaders[targetId]) {
                iconUploaders[targetId].open();
                return;
              }

              iconUploaders[targetId] = wp.media({
                title: 'Select Icon',
                button: { text: 'Use this icon' },
                multiple: false,
                library: { type: 'image' }
              });

              iconUploaders[targetId].on('select', function(){
                var attachment = iconUploaders[targetId].state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
                $('#' + previewId).html('<img src="' + attachment.url + '" style="max-width:120px; border:1px solid #ddd; padding:4px; display:block;">');
                button.next('.smdp-clear-icon').show();
              });

              iconUploaders[targetId].open();
            });

            $('.smdp-clear-icon').on('click', function(e){
              e.preventDefault();
              var button = $(this);
              var targetId = button.data('target');
              var previewId = button.data('preview');
              $('#' + targetId).val('');
              $('#' + previewId).html('');
              button.hide();
            });
          });
          </script>
        </div>

        </div>
      </div>

      <div id="tab-styles" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">Styling & Customization</h2>
        <p>Customize colors, fonts, and appearance of the menu app.</p>

        <!-- Subtabs for Styles -->
        <div class="smdp-style-subtabs" style="margin:20px 0; border-bottom:1px solid #ccc;">
          <a href="#style-category-buttons" class="smdp-style-subtab active" style="display:inline-block; padding:10px 20px; text-decoration:none; border-bottom:2px solid #2271b1; margin-bottom:-1px;">Category Buttons</a>
          <a href="#style-help-buttons" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Help Buttons</a>
          <a href="#style-background" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Background Colors</a>
          <a href="#style-item-cards" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Item Cards</a>
          <a href="#style-custom-css" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Custom CSS</a>
        </div>

        <!-- Subtab: Category Buttons -->
        <div id="style-category-buttons" class="smdp-style-subtab-content active" style="margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_styles_group'); ?>
            <h3>Category Button Styles</h3>
            <?php self::field_styles(); ?>
            <?php submit_button('Save Category Button Styles'); ?>
          </form>
        </div>

        <!-- Subtab: Help Buttons -->
        <div id="style-help-buttons" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <h3>Help & Bill Button Styles</h3>
          <p class="description">Customize the Help and Bill request buttons (coming soon in Phase 2)</p>
        </div>

        <!-- Subtab: Background Colors -->
        <div id="style-background" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <h3>Background Colors</h3>
          <p class="description">Customize background colors for different sections (coming soon in Phase 2)</p>
        </div>

        <!-- Subtab: Item Cards -->
        <div id="style-item-cards" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <h3>Item Card Styles</h3>
          <p class="description">Customize menu item card appearance (coming soon in Phase 2)</p>
        </div>

        <!-- Subtab: Custom CSS -->
        <div id="style-custom-css" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_layout_group'); ?>
            <h3>Custom CSS</h3>
            <p class="description">Add your own CSS to override any default styling. This CSS loads after all other styles.</p>
            <?php self::field_css(); ?>
            <?php submit_button('Save Custom CSS'); ?>
          </form>
        </div>
        </div>
      </div>

      <!-- Items and Categories tabs moved to Menu Management → Menu Editor -->

      <!-- Tab: Advanced -->
      <div id="tab-advanced" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">Advanced Settings</h2>
        <p>Advanced configuration and troubleshooting tools for the menu app.</p>

          <h3>Menu App URL</h3>
          <?php
          $menu_app_url = home_url( '/menu-app/' );
          $flushed = get_transient( 'smdp_rewrite_rules_flushed' );
          ?>

          <p class="description">Standalone menu app URL: <strong><a href="<?php echo esc_url( $menu_app_url ); ?>" target="_blank"><?php echo esc_html( $menu_app_url ); ?></a></strong></p>

          <?php if ( $flushed ): ?>
            <div class="notice notice-success inline" style="margin: 10px 0; padding: 10px;">
              <p><strong>Success!</strong> Rewrite rules have been flushed. The menu app URL should now work.</p>
            </div>
          <?php endif; ?>

          <p class="description">If the menu app URL returns a 404 error, click the button below to fix it:</p>
          <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smdp_menu_app_builder&action=flush_rewrite_rules' ), 'smdp_flush_rewrite_rules' ) ); ?>" class="button button-secondary">Fix Menu App URL (Flush Rewrite Rules)</a>
          <p class="description" style="margin-top: 10px;"><em>This re-registers WordPress URL routing rules. Safe to click anytime the menu app URL isn't working.</em></p>
        </div>
      </div>

    </div>
    <style>
      .smdp-tab { display:none !important; }
      .smdp-tab.active { display:block !important; }
      .nav-tab-wrapper { margin-top: 12px; }

      /* Configuration subtabs */
      .smdp-config-subtab { cursor: pointer; transition: all 0.2s; }
      .smdp-config-subtab:hover { color: #2271b1; }
      .smdp-config-subtab.active { color: #2271b1; font-weight: 600; border-bottom: 2px solid #2271b1; }
      .smdp-config-subtab-content { display: none !important; }
      .smdp-config-subtab-content.active { display: block !important; }

      /* Style subtabs */
      .smdp-style-subtab { cursor: pointer; transition: all 0.2s; }
      .smdp-style-subtab:hover { color: #2271b1; }
      .smdp-style-subtab.active { color: #2271b1; font-weight: 600; border-bottom: 2px solid #2271b1; }
      .smdp-style-subtab-content { display: none !important; }
      .smdp-style-subtab-content.active { display: block !important; }
    </style>
    <script>
      (function(){
        // Bootstrap button handler
        var btn = document.getElementById('smdp-bootstrap-btn');
        var status = document.getElementById('smdp-bootstrap-status');
        if (btn) {
          btn.addEventListener('click', function(){
            status.textContent = 'Building…';
            fetch('<?php echo esc_js( rest_url('smdp/v1/bootstrap') ); ?>', {
              method: 'POST',
              headers: {'X-WP-Nonce':'<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>'}
            }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
            .then(function(res){
              if (!res.ok) { console.error('Bootstrap error:', res.j); }
              status.textContent = (res.ok && res.j && res.j.ok) ? 'Done.' : 'Failed.';
            }).catch(function(err){
              console.error(err);
              status.textContent = 'Failed.';
            });
          });
        }
      })();
    </script>
    <script>
    // Enhanced tab switching with session state persistence
    jQuery(document).ready(function($){
        console.log('Menu App Builder: Tabs initializing');

        var $tabs = $('.nav-tab-wrapper .nav-tab');
        var $panes = $('.smdp-tab');

        console.log('Found tabs:', $tabs.length, 'Found panes:', $panes.length);

        $tabs.on('click', function(e){
            e.preventDefault();
            var target = $(this).attr('href');
            console.log('Tab clicked:', target);

            // Remove all active states
            $tabs.removeClass('nav-tab-active');
            $panes.removeClass('active');

            // Add active to clicked tab
            $(this).addClass('nav-tab-active');

            // Show corresponding pane
            var $targetPane = $(target);

            if ($targetPane.length) {
                $targetPane.addClass('active');
                console.log('Activated pane:', target);
                // Save to session storage
                sessionStorage.setItem('smdp_active_tab', target);
            } else {
                console.error('Pane not found:', target);
            }
        });

        // Check if we just saved settings (WordPress adds settings-updated parameter)
        var urlParams = new URLSearchParams(window.location.search);
        var settingsUpdated = urlParams.get('settings-updated');

        // Restore tab state from session or use first tab
        var savedTab = sessionStorage.getItem('smdp_active_tab');

        // First, remove all active states to prevent multiple tabs showing
        $tabs.removeClass('nav-tab-active');
        $panes.removeClass('active');

        // If settings were just updated, stay on the Styles tab
        if (settingsUpdated === 'true' && savedTab === '#tab-styles') {
            $tabs.filter('[href="#tab-styles"]').addClass('nav-tab-active');
            $('#tab-styles').addClass('active');
            console.log('Settings saved, staying on Styles tab');
        } else if (savedTab && $(savedTab).length) {
            $tabs.filter('[href="' + savedTab + '"]').addClass('nav-tab-active');
            $(savedTab).addClass('active');
            console.log('Restored tab:', savedTab);
        } else {
            // Default to first tab if no saved state
            $tabs.first().addClass('nav-tab-active');
            $panes.first().addClass('active');
        }

        // Configuration subtabs switching
        var $configSubtabs = $('.smdp-config-subtab');
        var $configSubtabPanes = $('.smdp-config-subtab-content');

        $configSubtabs.on('click', function(e){
            e.preventDefault();
            var target = $(this).attr('href');
            console.log('Config subtab clicked:', target);

            // Remove all active states
            $configSubtabs.removeClass('active').css({'color': '#666', 'border-bottom': 'none'});
            $configSubtabPanes.removeClass('active').hide();

            // Add active to clicked subtab
            $(this).addClass('active').css({'color': '#2271b1', 'border-bottom': '2px solid #2271b1', 'font-weight': '600'});

            // Show corresponding pane
            $(target).addClass('active').show();

            // Save to session storage
            sessionStorage.setItem('smdp_active_config_subtab', target);

            console.log('Activated config subtab:', target);
        });

        // Restore config subtab state
        var savedConfigSubtab = sessionStorage.getItem('smdp_active_config_subtab');
        if (savedConfigSubtab && $(savedConfigSubtab).length) {
            $configSubtabs.removeClass('active').css({'color': '#666', 'border-bottom': 'none'});
            $configSubtabPanes.removeClass('active').hide();
            $configSubtabs.filter('[href="' + savedConfigSubtab + '"]').addClass('active').css({'color': '#2271b1', 'border-bottom': '2px solid #2271b1', 'font-weight': '600'});
            $(savedConfigSubtab).addClass('active').show();
            console.log('Restored config subtab:', savedConfigSubtab);
        }

        // Style subtabs switching
        var $styleSubtabs = $('.smdp-style-subtab');
        var $styleSubtabPanes = $('.smdp-style-subtab-content');

        $styleSubtabs.on('click', function(e){
            e.preventDefault();
            var target = $(this).attr('href');
            console.log('Style subtab clicked:', target);

            // Remove all active states
            $styleSubtabs.removeClass('active').css({'color': '#666', 'border-bottom': 'none'});
            $styleSubtabPanes.removeClass('active').hide();

            // Add active to clicked subtab
            $(this).addClass('active').css({'color': '#2271b1', 'border-bottom': '2px solid #2271b1', 'font-weight': '600'});

            // Show corresponding pane
            $(target).addClass('active').show();

            // Save to session storage
            sessionStorage.setItem('smdp_active_style_subtab', target);

            console.log('Activated style subtab:', target);
        });

        // Restore style subtab state
        var savedStyleSubtab = sessionStorage.getItem('smdp_active_style_subtab');
        if (savedStyleSubtab && $(savedStyleSubtab).length) {
            $styleSubtabs.removeClass('active').css({'color': '#666', 'border-bottom': 'none'});
            $styleSubtabPanes.removeClass('active').hide();
            $styleSubtabs.filter('[href="' + savedStyleSubtab + '"]').addClass('active').css({'color': '#2271b1', 'border-bottom': '2px solid #2271b1', 'font-weight': '600'});
            $(savedStyleSubtab).addClass('active').show();
            console.log('Restored style subtab:', savedStyleSubtab);
        }

        // Save current tab/subtab state before any form submission
        $('form').on('submit', function() {
            // Save which main tab is currently active
            var activeTab = $tabs.filter('.nav-tab-active').attr('href');
            if (activeTab) {
                sessionStorage.setItem('smdp_active_tab', activeTab);
                console.log('Saving tab state before submit:', activeTab);
            }

            // Save which config subtab is currently active (if any)
            var activeConfigSubtab = $configSubtabs.filter('.active').attr('href');
            if (activeConfigSubtab) {
                sessionStorage.setItem('smdp_active_config_subtab', activeConfigSubtab);
                console.log('Saving config subtab state before submit:', activeConfigSubtab);
            }

            // Save which style subtab is currently active (if any)
            var activeStyleSubtab = $styleSubtabs.filter('.active').attr('href');
            if (activeStyleSubtab) {
                sessionStorage.setItem('smdp_active_style_subtab', activeStyleSubtab);
                console.log('Saving style subtab state before submit:', activeStyleSubtab);
            }
        });
    });
    </script>
    <?php
  }

  public static function field_css() {
    $css = get_option(self::OPT_CSS, "/* Add your custom CSS here to override default styles */");
    ?>
    <textarea name="<?php echo esc_attr(self::OPT_CSS); ?>" rows="20" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea($css); ?></textarea>

    <details style="margin-top:12px;border:1px solid #ddd;padding:12px;border-radius:4px;">
      <summary style="cursor:pointer;font-weight:600;font-size:14px;">📖 Complete CSS Selector Reference (Click to expand)</summary>

      <div style="margin-top:12px;line-height:1.8;">
        <p><strong>🎨 Custom CSS Override:</strong> Add your own CSS here to customize the menu app appearance.<br>
        This CSS will load <em>after</em> the hardcoded styles, allowing you to override any default styling.</p>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">🔘 Category Buttons</h4>
        <code>.smdp-cat-btn</code> - All category buttons<br>
        <code>.smdp-cat-btn.active</code> - Active/selected category button<br>
        <code>.smdp-cat-bar</code> - Category button container/rail (background, padding, gap)<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">🔴 Action Buttons (Bottom-Right)</h4>
        <code>.smdp-help-btn</code> - "Request Help" button<br>
        <code>.smdp-help-btn.smdp-btn-disabled</code> - Disabled help button state<br>
        <code>.smdp-bill-btn</code> - "Request Bill" button<br>
        <code>.smdp-bill-btn.smdp-bill-disabled</code> - Disabled bill button state<br>
        <code>.smdp-view-bill-btn</code> - "View Bill" button<br>
        <code>#smdp-table-badge</code> - Table number badge<br>
        <code>.smdp-action-buttons</code> - Container for Help + Bill buttons<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">📦 Layout & Containers</h4>
        <code>.smdp-menu-app-fe</code> - Main menu app container<br>
        <code>.smdp-menu-app-fe.layout-left</code> - When using left sidebar layout<br>
        <code>.smdp-menu-app-fe.layout-top</code> - When using top category bar layout<br>
        <code>.smdp-app-header</code> - Header area (contains category bar)<br>
        <code>.smdp-app-sections</code> - Content area (contains menu items)<br>
        <code>.smdp-app-section</code> - Individual category section<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">🍽️ Menu Items</h4>
        <code>.smdp-menu-container</code> - Menu items wrapper<br>
        <code>.smdp-menu-grid</code> - Menu items grid layout<br>
        <code>.smdp-menu-item</code> - Individual menu item card<br>
        <code>.smdp-item-tile</code> - Menu item tile/card<br>
        <code>.sold-out-item</code> - Sold out menu item<br>
        <code>.sold-out-banner</code> - "SOLD OUT" banner on items<br>
        <code>.smdp-menu-image</code> - Menu item images<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">🔍 Search Features</h4>
        <code>.smdp-search-container</code> - Search bar wrapper<br>
        <code>.smdp-search-bar</code> - Search input field<br>
        <code>.smdp-search-icon</code> - Search magnifying glass icon<br>
        <code>.smdp-search-clear</code> - Clear search button<br>
        <code>.smdp-search-results</code> - Search results container<br>
        <code>.smdp-no-results</code> - No results message<br>
        <code>.smdp-highlight</code> - Highlighted search text<br>
        <code>.smdp-menu-app-fe.searching</code> - When search is active<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">🖼️ Promo Screen</h4>
        <code>#smdp-promo-screen</code> - Promo/screensaver overlay<br>
        <code>#smdp-slides-container</code> - Promo slides container<br>
        <code>.smdp-promo-slide</code> - Individual promo slide image<br>

        <h4 style="margin-top:16px;margin-bottom:8px;border-bottom:2px solid #0073aa;padding-bottom:4px;">💡 Examples</h4>
        <pre style="background:#f5f5f5;padding:12px;border-radius:4px;overflow-x:auto;"><code>/* Change category button colors */
.smdp-cat-btn {
  background: #ff0000;
  color: white;
  border-radius: 25px;
}

.smdp-cat-btn.active {
  background: #cc0000;
}

/* Change Request Help button color */
.smdp-help-btn {
  background: #ff9800;
  font-size: 1.1rem;
}

/* Change category rail background */
.smdp-cat-bar {
  background: linear-gradient(to right, #f5f5f5, #e0e0e0);
  padding: 12px 0;
}

/* Change table badge color */
#smdp-table-badge {
  background: #9c27b0;
  font-size: 1rem;
}

/* Customize menu item cards */
.smdp-menu-item {
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Change sold out banner */
.sold-out-banner {
  background: #ff5722;
  font-weight: bold;
}

/* Custom search bar styling */
.smdp-search-bar {
  border: 2px solid #3498db;
  border-radius: 30px;
}
</code></pre>
      </div>
    </details>
    <?php
  }

  public static function field_settings() {
    $settings = get_option(self::OPT_SETTINGS, array());
    if (!is_array($settings)) $settings = array();
    $layout = isset($settings['layout']) ? $settings['layout'] : 'top';
    ?>
    <fieldset>
      <label><input type="radio" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[layout]" value="top" <?php checked($layout, 'top'); ?>> Category bar on <strong>top</strong> (default)</label><br>
      <label><input type="radio" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[layout]" value="left" <?php checked($layout, 'left'); ?>> Category rail on <strong>left</strong> (tablet landscape)</label>
      <p class="description">This only changes the placement of category tiles. Cards remain exactly the same as your existing menu output.</p>
    </fieldset>
    <?php
  }

  public static function field_promo_settings() {
    $settings = get_option(self::OPT_SETTINGS, array());
    if (!is_array($settings)) $settings = array();

    // Get promo images and ensure it's an array
    $promo_images = isset($settings['promo_images']) ? $settings['promo_images'] : array();

    // Backwards compatibility
    if (empty($promo_images) && !empty($settings['promo_image'])) {
      $promo_images = array($settings['promo_image']);
    }

    // Force to array if it's somehow a string
    if (!is_array($promo_images)) {
      if (is_string($promo_images)) {
        $decoded = json_decode($promo_images, true);
        $promo_images = is_array($decoded) ? $decoded : array();
      } else {
        $promo_images = array();
      }
    }

    $promo_timeout = isset($settings['promo_timeout']) ? intval($settings['promo_timeout']) : 600;
    ?>
    <fieldset>
      <p class="description">Configure the promotional screensaver that appears after a period of inactivity on the menu app.</p>

      <div style="margin-bottom:20px;">
        <label><strong>Idle Timeout (seconds)</strong></label><br>
        <input type="number" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[promo_timeout]" value="<?php echo esc_attr($promo_timeout); ?>" min="30" style="width:100px;">
        <p class="description">Show promo screen after this many seconds of inactivity (default: 600 = 10 minutes)</p>
      </div>

      <div style="margin-bottom:20px;">
        <label><strong>Promo Image</strong></label><br>
        <input type="hidden" id="smdp-promo-images" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[promo_images]" value="<?php echo esc_attr(json_encode($promo_images)); ?>">
        <button type="button" class="button" id="smdp-upload-promo">Upload Image</button>
        <button type="button" class="button" id="smdp-clear-promo" <?php echo empty($promo_images) ? 'style="display:none;"' : ''; ?>>Clear Image</button>
        <div id="smdp-promo-preview" style="margin-top:10px;">
          <?php if (!empty($promo_images) && isset($promo_images[0])): ?>
            <div style="position:relative; border:1px solid #ddd; padding:5px; display:inline-block;">
              <img src="<?php echo esc_url($promo_images[0]); ?>" style="max-width:300px; height:auto; display:block;">
            </div>
          <?php endif; ?>
        </div>
        <p class="description">Upload a single promo image (recommended: landscape orientation, 1920x1080px). Leave empty to disable promo screen.</p>
      </div>
    </fieldset>
    
    <script>
    jQuery(document).ready(function($){
      var mediaUploader;

      // Parse promo images safely
      var promoImagesData = <?php echo wp_json_encode($promo_images); ?>;
      var promoImages = [];
      
      // Ensure we have an array
      if (Array.isArray(promoImagesData)) {
        promoImages = promoImagesData;
      } else if (typeof promoImagesData === 'string') {
        try {
          var parsed = JSON.parse(promoImagesData);
          if (Array.isArray(parsed)) {
            promoImages = parsed;
          }
        } catch(e) {
          console.error('Failed to parse promo images:', e);
        }
      }
      
      console.log('Promo Images on load:', promoImages);
      console.log('Type check - is array:', Array.isArray(promoImages));
      
      function updatePreview() {
        console.log('Updating preview with images:', promoImages);
        if (!Array.isArray(promoImages)) {
          console.error('promoImages is not an array!', typeof promoImages, promoImages);
          promoImages = [];
        }

        // Show single image preview or empty state
        var html = '';
        if (promoImages.length > 0 && promoImages[0]) {
          html = '<div style="position:relative; border:1px solid #ddd; padding:5px; display:inline-block;">';
          html += '<img src="' + promoImages[0] + '" style="max-width:300px; height:auto; display:block;">';
          html += '</div>';
        }
        $('#smdp-promo-preview').html(html);

        var jsonString = JSON.stringify(promoImages);
        console.log('Setting hidden field to:', jsonString);
        $('#smdp-promo-images').val(jsonString);
        $('#smdp-clear-promo').toggle(promoImages.length > 0);
      }
      
      // Initial preview update
      updatePreview();
      
      $('#smdp-upload-promo').on('click', function(e) {
        e.preventDefault();
        console.log('Upload button clicked');
        
        if (mediaUploader) {
          mediaUploader.open();
          return;
        }
        
        mediaUploader = wp.media({
          title: 'Select Promo Image',
          button: { text: 'Use this image' },
          multiple: false
        });

        mediaUploader.on('select', function() {
          console.log('Media selected');
          var attachment = mediaUploader.state().get('selection').first().toJSON();
          var url = attachment.url;
          console.log('Selected image:', url);

          // Replace with single image (not push)
          promoImages = [url];

          console.log('Image set:', promoImages);
          updatePreview();
        });
        
        mediaUploader.open();
      });

      $('#smdp-clear-promo').on('click', function(e) {
        e.preventDefault();
        if (confirm('Remove promo image?')) {
          console.log('Clearing promo image');
          promoImages = [];
          updatePreview();
        }
      });

      // Log form submission
      $('form').on('submit', function() {
        var hiddenVal = $('#smdp-promo-images').val();
        console.log('Form submitting with hidden field value:', hiddenVal);
      });
    });
    </script>
    <?php
  }

  // Button styles form fields
  public static function field_styles() {
    $styles = get_option(self::OPT_STYLES, array());
    $name = self::OPT_STYLES;
    
    // Default values
    $defaults = array(
      'bg_color' => '#5C7BA6',
      'text_color' => '#ffffff',
      'border_color' => '#5C7BA6',
      'active_bg_color' => '#4A6288',
      'active_text_color' => '#ffffff',
      'active_border_color' => '#4A6288',
      'font_size' => 18,
      'padding_vertical' => 12,
      'padding_horizontal' => 20,
      'border_radius' => 8,
      'border_width' => 1,
      'font_weight' => '600',
      'font_family' => '',
    );    
    $styles = array_merge($defaults, $styles);
    ?>
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 15px; align-items: start;">
      <!-- Left Column: Form Controls -->
      <div class="smdp-style-controls">

        <h3>Default Button Style</h3>
        <table class="form-table">
        <tr>
          <th>Background Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[bg_color]" value="<?php echo esc_attr($styles['bg_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
        <tr>
          <th>Text Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[text_color]" value="<?php echo esc_attr($styles['text_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
        <tr>
          <th>Border Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[border_color]" value="<?php echo esc_attr($styles['border_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
      </table>

      <h3>Active Button Style</h3>
      <table class="form-table">
        <tr>
          <th>Active Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[active_bg_color]" value="<?php echo esc_attr($styles['active_bg_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
        <tr>
          <th>Active Text Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[active_text_color]" value="<?php echo esc_attr($styles['active_text_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
        <tr>
          <th>Active Border Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[active_border_color]" value="<?php echo esc_attr($styles['active_border_color']); ?>" class="smdp-color-picker" />
          </td>
        </tr>
      </table>

      <h3>Typography & Spacing</h3>
      <table class="form-table">
        <tr>
          <th>Font Size (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[font_size]" value="<?php echo esc_attr($styles['font_size']); ?>" min="10" max="32" style="width: 80px;" />
          </td>
        </tr>
        <tr>
          <th>Font Weight</th>
          <td>
            <select name="<?php echo esc_attr($name); ?>[font_weight]">
              <option value="normal" <?php selected($styles['font_weight'], 'normal'); ?>>Normal</option>
              <option value="bold" <?php selected($styles['font_weight'], 'bold'); ?>>Bold</option>
              <option value="600" <?php selected($styles['font_weight'], '600'); ?>>Semi-Bold (600)</option>
              <option value="500" <?php selected($styles['font_weight'], '500'); ?>>Medium (500)</option>
            </select>
          </td>
        </tr>
        <tr>
          <th>Font Family</th>
          <td>
            <select name="<?php echo esc_attr($name); ?>[font_family]" style="width: 250px;">
              <option value="">Inherit from theme</option>
              <optgroup label="System Fonts">
                <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" <?php selected($styles['font_family'], "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"); ?>>System Default</option>
                <option value="Arial, sans-serif" <?php selected($styles['font_family'], 'Arial, sans-serif'); ?>>Arial</option>
                <option value="'Helvetica Neue', Helvetica, Arial, sans-serif" <?php selected($styles['font_family'], "'Helvetica Neue', Helvetica, Arial, sans-serif"); ?>>Helvetica</option>
                <option value="Georgia, serif" <?php selected($styles['font_family'], 'Georgia, serif'); ?>>Georgia</option>
                <option value="'Times New Roman', Times, serif" <?php selected($styles['font_family'], "'Times New Roman', Times, serif"); ?>>Times New Roman</option>
                <option value="'Courier New', Courier, monospace" <?php selected($styles['font_family'], "'Courier New', Courier, monospace"); ?>>Courier New</option>
                <option value="Verdana, sans-serif" <?php selected($styles['font_family'], 'Verdana, sans-serif'); ?>>Verdana</option>
                <option value="Tahoma, sans-serif" <?php selected($styles['font_family'], 'Tahoma, sans-serif'); ?>>Tahoma</option>
              </optgroup>
              <optgroup label="Google Fonts (Common)">
                <option value="'Roboto', sans-serif" <?php selected($styles['font_family'], "'Roboto', sans-serif"); ?>>Roboto</option>
                <option value="'Open Sans', sans-serif" <?php selected($styles['font_family'], "'Open Sans', sans-serif"); ?>>Open Sans</option>
                <option value="'Lato', sans-serif" <?php selected($styles['font_family'], "'Lato', sans-serif"); ?>>Lato</option>
                <option value="'Montserrat', sans-serif" <?php selected($styles['font_family'], "'Montserrat', sans-serif"); ?>>Montserrat</option>
                <option value="'Raleway', sans-serif" <?php selected($styles['font_family'], "'Raleway', sans-serif"); ?>>Raleway</option>
                <option value="'Poppins', sans-serif" <?php selected($styles['font_family'], "'Poppins', sans-serif"); ?>>Poppins</option>
                <option value="'Playfair Display', serif" <?php selected($styles['font_family'], "'Playfair Display', serif"); ?>>Playfair Display</option>
                <option value="'Merriweather', serif" <?php selected($styles['font_family'], "'Merriweather', serif"); ?>>Merriweather</option>
              </optgroup>
            </select>
            <p class="description">Note: Google Fonts must be loaded separately by your theme or another plugin</p>
          </td>
        </tr>
        <tr>
          <th>Padding Vertical (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[padding_vertical]" value="<?php echo esc_attr($styles['padding_vertical']); ?>" min="0" max="50" style="width: 80px;" />
          </td>
        </tr>
        <tr>
          <th>Padding Horizontal (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[padding_horizontal]" value="<?php echo esc_attr($styles['padding_horizontal']); ?>" min="0" max="100" style="width: 80px;" />
          </td>
        </tr>
      </table>

      <h3>Border & Shape</h3>
      <table class="form-table">
        <tr>
          <th>Border Width (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[border_width]" value="<?php echo esc_attr($styles['border_width']); ?>" min="0" max="10" style="width: 80px;" />
          </td>
        </tr>
        <tr>
          <th>Border Radius (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[border_radius]" value="<?php echo esc_attr($styles['border_radius']); ?>" min="0" max="999" style="width: 80px;" />
            <p class="description">Use 999 for pill-shaped buttons, 0 for square, or 8-12 for rounded corners</p>
          </td>
        </tr>
      </table>

      </div>

      <!-- Right Column: Live Preview (Sticky) -->
      <div class="smdp-button-preview" style="position: sticky; top: 32px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
        <h3 style="margin-top: 0;">Live Preview</h3>
        <p class="description" style="margin-bottom: 15px;">Updates as you make changes</p>
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div>
            <small style="color: #666; display: block; margin-bottom: 4px;">Default State:</small>
            <button type="button" class="smdp-preview-btn" style="
              background: <?php echo esc_attr($styles['bg_color']); ?>;
              color: <?php echo esc_attr($styles['text_color']); ?>;
              border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['border_color']); ?>;
              font-size: <?php echo esc_attr($styles['font_size']); ?>px;
              padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px;
              border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
              font-weight: <?php echo esc_attr($styles['font_weight']); ?>;
              font-family: <?php echo esc_attr($styles['font_family']); ?>;
              cursor: pointer;
              transition: all 0.3s ease;
            ">Category Button</button>
          </div>

          <div>
            <small style="color: #666; display: block; margin-bottom: 4px;">Active State:</small>
            <button type="button" class="smdp-preview-btn smdp-preview-active" style="
              background: <?php echo esc_attr($styles['active_bg_color']); ?>;
              color: <?php echo esc_attr($styles['active_text_color']); ?>;
              border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['active_border_color']); ?>;
              font-size: <?php echo esc_attr($styles['font_size']); ?>px;
              padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px;
              border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
              font-weight: <?php echo esc_attr($styles['font_weight']); ?>;
              font-family: <?php echo esc_attr($styles['font_family']); ?>;
              cursor: pointer;
              transition: all 0.3s ease;
            ">Active Category</button>
          </div>
        </div>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      function updatePreview() {
        var $preview = $('.smdp-button-preview');
        var bgColor = $('input[name="<?php echo esc_js($name); ?>[bg_color]"]').val();
        var textColor = $('input[name="<?php echo esc_js($name); ?>[text_color]"]').val();
        var borderColor = $('input[name="<?php echo esc_js($name); ?>[border_color]"]').val();
        var activeBg = $('input[name="<?php echo esc_js($name); ?>[active_bg_color]"]').val();
        var activeText = $('input[name="<?php echo esc_js($name); ?>[active_text_color]"]').val();
        var activeBorder = $('input[name="<?php echo esc_js($name); ?>[active_border_color]"]').val();
        var fontSize = $('input[name="<?php echo esc_js($name); ?>[font_size]"]').val();
        var padV = $('input[name="<?php echo esc_js($name); ?>[padding_vertical]"]').val();
        var padH = $('input[name="<?php echo esc_js($name); ?>[padding_horizontal]"]').val();
        var borderRadius = $('input[name="<?php echo esc_js($name); ?>[border_radius]"]').val();
        var borderWidth = $('input[name="<?php echo esc_js($name); ?>[border_width]"]').val();
        var fontWeight = $('select[name="<?php echo esc_js($name); ?>[font_weight]"]').val();
        var fontFamily = $('select[name="<?php echo esc_js($name); ?>[font_family]"]').val();

        $preview.find('.smdp-preview-btn').not('.smdp-preview-active').css({
          'background': bgColor,
          'color': textColor,
          'border-color': borderColor,
          'font-size': fontSize + 'px',
          'padding': padV + 'px ' + padH + 'px',
          'border-radius': borderRadius + 'px',
          'border-width': borderWidth + 'px',
          'font-weight': fontWeight,
          'font-family': fontFamily
        });

        $preview.find('.smdp-preview-btn.smdp-preview-active').css({
          'background': activeBg,
          'color': activeText,
          'border-color': activeBorder,
          'font-size': fontSize + 'px',
          'padding': padV + 'px ' + padH + 'px',
          'border-radius': borderRadius + 'px',
          'border-width': borderWidth + 'px',
          'font-weight': fontWeight,
          'font-family': fontFamily
        });
      }

      // Update preview on any input change
      $('.smdp-style-controls input, .smdp-style-controls select').on('change keyup input', updatePreview);

      // Listen for color picker changes from external script
      $(document).on('smdp-color-changed', updatePreview);
    });
    </script>
    <?php
  }

  /** Embedded helpers to reuse existing Menu Editor UIs */
  private static function capture_output($callable) {
    ob_start();
    call_user_func($callable);
    return ob_get_clean();
  }
  private static function render_items_editor_embed() {
    if (function_exists('smdp_render_items_page')) {
      return self::capture_output('smdp_render_items_page');
    }
    return '<div class="notice notice-error"><p>Items editor function not found in this plugin build.</p></div>';
  }
  private static function render_categories_editor_embed() {
    if (function_exists('smdp_render_categories_page')) {
      return self::capture_output('smdp_render_categories_page');
    }
    return '<div class="notice notice-error"><p>Categories editor function not found in this plugin build.</p></div>';
  }

  /** ---------------- REST ---------------- */

  public static function rest_get_catalog($req) {
    $catalog = get_option(self::OPT_CATALOG, array());
    if (is_string($catalog)) { $catalog = json_decode($catalog, true); if (!is_array($catalog)) $catalog = array(); }
    return rest_ensure_response($catalog);
  }

  public static function rest_bootstrap_from_cache($req) {
    $res = self::bootstrap_from_cache();
    if (is_wp_error($res)) return $res;
    return rest_ensure_response(array('ok' => true));
  }

  public static function rest_catalog_search_local($req) {
    $q = sanitize_text_field($req->get_param('q'));
    $q = mb_strtolower($q);
    $catalog = get_option(self::OPT_CATALOG, array());
    if (is_string($catalog)) { $catalog = json_decode($catalog, true); if (!is_array($catalog)) $catalog = array(); }
    if (!$q || empty($catalog)) return rest_ensure_response(array());

    $out = array();
    foreach ($catalog as $it) {
      $hay = mb_strtolower( (isset($it['name'])?$it['name']:'') . ' ' . (isset($it['desc'])?$it['desc']:'') . ' ' . (isset($it['category'])?$it['category']:'') );
      if (strpos($hay, $q) !== false) $out[] = $it;
      if (count($out) >= 100) break;
    }
    return rest_ensure_response($out);
  }

  public static function rest_save_categories_order($req) {
    $cat_opt = defined('SMDP_CATEGORIES_OPTION') ? SMDP_CATEGORIES_OPTION : 'square_menu_categories';
    $cats = get_option($cat_opt, array());
    if (!is_array($cats)) $cats = array();

    $body = $req->get_json_params();
    $order = array();
    if (is_array($body) && isset($body['order']) && is_array($body['order'])) {
      $order = $body['order'];
    }
    if (empty($order)) {
      return new WP_Error('invalid_order', 'Expected non-empty order array');
    }

    $i = 0;
    foreach ($order as $cid) {
      if (isset($cats[$cid])) {
        $cats[$cid]['order'] = $i++;
      }
    }
    update_option($cat_opt, $cats);
    return rest_ensure_response(array('ok'=>true, 'updated'=>count($order)));
  }

  /** ---------------- Helpers ---------------- */

  private static function bootstrap_from_cache() {
    $items_opt = defined('SMDP_ITEMS_OPTION') ? SMDP_ITEMS_OPTION : 'square_menu_items';
    $map_opt   = defined('SMDP_MAPPING_OPTION') ? SMDP_MAPPING_OPTION : 'square_menu_item_mapping';
    $cat_opt   = defined('SMDP_CATEGORIES_OPTION') ? SMDP_CATEGORIES_OPTION : 'square_menu_categories';

    $all_items  = get_option($items_opt, array());
    $mapping    = get_option($map_opt, array());
    $categories = get_option($cat_opt, array());

    if (!is_array($all_items))  $all_items = array();
    if (!is_array($mapping))    $mapping = array();
    if (!is_array($categories)) $categories = array();

    $images = array();
    foreach ($all_items as $obj) {
      if (isset($obj['type']) && $obj['type']==='IMAGE' && !empty($obj['image_data']['url'])) {
        $images[$obj['id']] = $obj['image_data']['url'];
      }
    }

    // Check if this is old-style mapping or new-style mapping with instance IDs
    $is_new_style = false;
    foreach ($mapping as $key => $data) {
      if (isset($data['instance_id'])) {
        $is_new_style = true;
        break;
      }
    }

    // DEBUG: Log mapping style and custom categories
    error_log('[SMDP Menu Builder] Mapping style: ' . ($is_new_style ? 'NEW (instance-based)' : 'OLD (item-based)'));
    error_log('[SMDP Menu Builder] Total categories: ' . count($categories));
    $custom_cats = array_filter($categories, function($cat, $id) {
      return strpos($id, 'cat_') === 0;
    }, ARRAY_FILTER_USE_BOTH);
    error_log('[SMDP Menu Builder] Custom categories found: ' . count($custom_cats) . ' - ' . implode(', ', array_column($custom_cats, 'name')));
    error_log('[SMDP Menu Builder] Total mapping entries: ' . count($mapping));

    // DEBUG: Count how many mapping entries belong to custom categories
    $custom_mappings = 0;
    foreach ($mapping as $instance_id => $map_data) {
      if (isset($map_data['category']) && strpos($map_data['category'], 'cat_') === 0) {
        $custom_mappings++;
      }
    }
    error_log('[SMDP Menu Builder] Mapping entries for custom categories: ' . $custom_mappings);

    $normalized = array();

    if ($is_new_style) {
      // New-style mapping: instance_id => {item_id, instance_id, category, order, hide_image}
      // Build a lookup of item_id => item_obj
      $items_by_id = array();
      foreach ($all_items as $obj) {
        if (empty($obj['type']) || $obj['type'] !== 'ITEM') continue;
        $items_by_id[$obj['id']] = $obj;
      }

      // Process each instance in the mapping
      $custom_cat_items = array();
      foreach ($mapping as $instance_id => $map_data) {
        if (!isset($map_data['item_id']) || !isset($items_by_id[$map_data['item_id']])) continue;

        $obj = $items_by_id[$map_data['item_id']];
        $data = isset($obj['item_data']) ? $obj['item_data'] : array();

        $cat_id = isset($map_data['category']) ? $map_data['category'] : '';
        $cat_name = isset($categories[$cat_id]['name']) ? $categories[$cat_id]['name'] : 'Uncategorized';

        // DEBUG: Track custom category assignments
        if (strpos($cat_id, 'cat_') === 0) {
          if (!isset($custom_cat_items[$cat_id])) $custom_cat_items[$cat_id] = array();
          $custom_cat_items[$cat_id][] = $data['name'];
        }

        $img = '';
        if (!empty($data['image_ids'][0])) {
          $img_id = $data['image_ids'][0];
          $img = isset($images[$img_id]) ? $images[$img_id] : '';
        }

        // Check hide_image flag from mapping
        if (!empty($map_data['hide_image'])) {
          $img = '';
        }

        $normalized[] = array(
          'id'          => $obj['id'],
          'name'        => isset($data['name']) ? $data['name'] : '',
          'desc'        => isset($data['description']) ? $data['description'] : '',
          'image'       => $img,
          'category'    => $cat_name,
          'category_id' => $cat_id,
          'order'       => isset($map_data['order']) ? intval($map_data['order']) : 0,
          'instance_id' => $instance_id,
        );
      }
    } else {
      // Old-style mapping: item_id => {category, order}
      foreach ($all_items as $obj) {
        if (empty($obj['type']) || $obj['type']!=='ITEM') continue;
        $id   = $obj['id'];
        $data = isset($obj['item_data']) ? $obj['item_data'] : array();

        $cat_id = isset($mapping[$id]['category']) ? $mapping[$id]['category'] : '';
        $cat_name = isset($categories[$cat_id]['name']) ? $categories[$cat_id]['name'] : 'Uncategorized';

        $img = '';
        if (!empty($data['image_ids'][0])) {
          $img_id = $data['image_ids'][0];
          $img = isset($images[$img_id]) ? $images[$img_id] : '';
        }

        $normalized[] = array(
          'id'          => $id,
          'name'        => isset($data['name']) ? $data['name'] : '',
          'desc'        => isset($data['description']) ? $data['description'] : '',
          'image'       => $img,
          'category'    => $cat_name,
          'category_id' => $cat_id,
          'order'       => isset($mapping[$id]['order']) ? intval($mapping[$id]['order']) : 0,
        );
      }
    }

    // DEBUG: Log items assigned to custom categories
    if ($is_new_style && !empty($custom_cat_items)) {
      error_log('[SMDP Menu Builder] Items assigned to custom categories:');
      foreach ($custom_cat_items as $cat_id => $items) {
        error_log('  - ' . $cat_id . ' (' . $categories[$cat_id]['name'] . '): ' . implode(', ', $items));
      }
    }

    update_option(self::OPT_CATALOG, wp_json_encode($normalized));

    $cat_sorted = $categories;
    uasort($cat_sorted, function($a,$b){
      $oa = isset($a['order']) ? intval($a['order']) : 0;
      $ob = isset($b['order']) ? intval($b['order']) : 0;
      if ($oa === $ob) {
        $an = isset($a['name']) ? $a['name'] : '';
        $bn = isset($b['name']) ? $b['name'] : '';
        return strcasecmp($an, $bn);
      }
      return ($oa < $ob) ? -1 : 1;
    });

    $by_cat = array();
    foreach ($normalized as $it) {
      $cid = !empty($it['category_id']) ? $it['category_id'] : 'uncategorized';
      if (!isset($by_cat[$cid])) $by_cat[$cid] = array();
      $by_cat[$cid][] = $it;
    }

    // DEBUG: Show which custom categories have items in by_cat
    error_log('[SMDP Menu Builder] Custom categories in $by_cat array:');
    foreach ($by_cat as $cid => $items) {
      if (strpos($cid, 'cat_') === 0) {
        error_log('  - ' . $cid . ': ' . count($items) . ' items - ' . implode(', ', array_column($items, 'name')));
      }
    }

    foreach ($by_cat as $cid => $arr) {
      usort($arr, function($a,$b){
        $oa = isset($a['order']) ? intval($a['order']) : 0;
        $ob = isset($b['order']) ? intval($b['order']) : 0;
        if ($oa === $ob) {
          $an = isset($a['name']) ? $a['name'] : '';
          $bn = isset($b['name']) ? $b['name'] : '';
          return strcasecmp($an, $bn);
        }
        return ($oa < $ob) ? -1 : 1;
      });
      $by_cat[$cid] = $arr;
    }

    $menu = array('id'=>'default','title'=>'Menu','categories'=>array());
    foreach ($cat_sorted as $cid => $cat) {
      if (!empty($cat['hidden'])) continue;
      $items = array();
      if (isset($by_cat[$cid])) {
        foreach ($by_cat[$cid] as $it) {
          $items[] = array('id'=>$it['id'], 'name'=>$it['name'], 'image'=>$it['image']);
        }
      }
      // Always add category, even if empty (important for custom categories)
      $name = isset($cat['name']) ? $cat['name'] : 'Category';
      $menu['categories'][] = array('name'=>$name, 'items'=>$items);

      // DEBUG: Log category being added to menu
      $is_custom = (strpos($cid, 'cat_') === 0);
      if ($is_custom) {
        error_log('[SMDP Menu Builder] Adding custom category to menu: ' . $name . ' (ID: ' . $cid . ') with ' . count($items) . ' items');
      }
    }

    if (!empty($by_cat['uncategorized'])) {
      $unc = array();
      foreach ($by_cat['uncategorized'] as $it) {
        $unc[] = array('id'=>$it['id'],'name'=>$it['name'],'image'=>$it['image']);
      }
      $menu['categories'][] = array('name'=>'Uncategorized', 'items'=>$unc);
    }

    update_option(self::OPT_MENUS, wp_json_encode(array($menu)));
    return true;
  }

  /** ---------------- Frontend ---------------- */

  public static function shortcode_render($atts) {
    $atts = shortcode_atts(array('id' => 'default', 'layout' => '', 'category' => ''), $atts, 'smdp_menu_app');

    $cat_opt = defined('SMDP_CATEGORIES_OPTION') ? SMDP_CATEGORIES_OPTION : 'square_menu_categories';
    $map_opt = defined('SMDP_MAPPING_OPTION')    ? SMDP_MAPPING_OPTION    : 'square_menu_item_mapping';
    $itm_opt = defined('SMDP_ITEMS_OPTION')      ? SMDP_ITEMS_OPTION      : 'square_menu_items';

    $categories = get_option($cat_opt, array());
    $mapping    = get_option($map_opt, array());
    $items      = get_option($itm_opt, array());

    if (!is_array($categories)) $categories = array();
    if (!is_array($mapping))    $mapping = array();
    if (!is_array($items))      $items = array();

    $valid_items = array();
    foreach ($items as $o) { if (!empty($o['type']) && $o['type']==='ITEM') $valid_items[$o['id']] = true; }

    // Check if this is new-style mapping (instance_id => data with item_id field)
    $is_new_style = false;
    foreach ($mapping as $key => $data) {
      if (isset($data['item_id']) && isset($data['instance_id'])) {
        $is_new_style = true;
        break;
      }
    }

    $counts = array();
    $skipped_invalid = 0;
    foreach ($mapping as $key => $m) {
      // For new-style mapping, use item_id field; for old-style, use the key
      $item_id = $is_new_style ? (isset($m['item_id']) ? $m['item_id'] : '') : $key;

      if (!$item_id || !isset($valid_items[$item_id])) {
        $skipped_invalid++;
        continue;
      }
      $cid = isset($m['category']) ? $m['category'] : '';
      if ($cid==='') continue;
      if (!isset($counts[$cid])) $counts[$cid] = 0;
      $counts[$cid]++;
    }

    $cats = array_values($categories);

    // Filter by category slug if provided
    if (!empty($atts['category'])) {
      $category_slug = $atts['category'];
      $cats = array_filter($cats, function($c) use ($category_slug) {
        return isset($c['slug']) && $c['slug'] === $category_slug;
      });
      $cats = array_values($cats); // Re-index after filter
    }

    usort($cats, function($a,$b){
      $oa = isset($a['order']) ? intval($a['order']) : 0;
      $ob = isset($b['order']) ? intval($b['order']) : 0;
      if ($oa === $ob) {
        $an = isset($a['name']) ? $a['name'] : '';
        $bn = isset($b['name']) ? $b['name'] : '';
        return strcasecmp($an, $bn);
      }
      return ($oa < $ob) ? -1 : 1;
    });

    $cats_to_show = array();
    foreach ($cats as $c) {
      $hidden = !empty($c['hidden']);
      if ($hidden) continue;
      $cid = isset($c['id']) ? $c['id'] : '';
      if (!$cid) continue;
      if (empty($counts[$cid])) continue;
      $cats_to_show[] = $c;
    }

    // Enqueue hardcoded CSS files (structural CSS is a dependency of menu-app CSS)
    wp_enqueue_style('smdp-structural');
    wp_enqueue_style('smdp-menu-app');
    wp_enqueue_script('smdp-menu-app');

    // Force register and enqueue help/bill button scripts
    $plugin_main = dirname(dirname(__FILE__)) . '/Main.php';
    $base_url = plugins_url('', $plugin_main);

    // Register and enqueue table-setup
    if ( ! wp_script_is( 'smdp-table-setup', 'registered' ) ) {
      wp_register_script( 'smdp-table-setup', $base_url . '/assets/js/table-setup.js', [], null, true );
    }
    wp_enqueue_script( 'smdp-table-setup' );

    // Register and enqueue view-bill
    if ( ! wp_script_is( 'smdp-view-bill', 'registered' ) ) {
      wp_register_script( 'smdp-view-bill', $base_url . '/assets/js/view-bill.js', [ 'jquery' ], null, true );
    }
    wp_enqueue_script( 'smdp-view-bill' );
    wp_localize_script( 'smdp-view-bill', 'smdpViewBill', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('smdp_get_bill')
    ]);

    // Register and enqueue help-frontend
    if ( ! wp_script_is( 'smdp-help-frontend', 'registered' ) ) {
      wp_register_script( 'smdp-help-frontend', $base_url . '/assets/js/help-request.js', [ 'jquery', 'smdp-table-setup' ], null, true );
    }
    wp_enqueue_script( 'smdp-help-frontend' );
    wp_localize_script( 'smdp-help-frontend', 'smdpHelp', [ 'ajax_url'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('smdp_request_help') ] );
    wp_localize_script( 'smdp-help-frontend', 'smdpBill', [ 'ajax_url'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('smdp_request_bill') ] );

    // Register and enqueue PWA install prompt
    if ( ! wp_script_is( 'smdp-pwa-install', 'registered' ) ) {
      wp_register_script( 'smdp-pwa-install', $base_url . '/assets/js/pwa-install.js', [], null, true );
    }
    wp_enqueue_script( 'smdp-pwa-install' );

    // Signal that menu app was rendered (for debug panel and PWA handler)
    if ( ! defined( 'SMDP_MENU_APP_RENDERED' ) ) {
      define( 'SMDP_MENU_APP_RENDERED', true );
    }
    if ( class_exists( 'SMDP_Debug_Panel' ) ) {
      SMDP_Debug_Panel::set_menu_app_rendered();
    }

    $settings = get_option(self::OPT_SETTINGS, array());
    if (!is_array($settings)) $settings = array();
    $layout = !empty($atts['layout']) ? $atts['layout'] : ( isset($settings['layout']) ? $settings['layout'] : 'top' );
    $layout = ($layout === 'left') ? 'left' : 'top';
    
    // Pass promo settings to frontend
    $promo_images = isset($settings['promo_images']) ? $settings['promo_images'] : array();
    // Backwards compatibility - convert old single image to array
    if (empty($promo_images) && !empty($settings['promo_image'])) {
      $promo_images = array($settings['promo_image']);
    }
    // Ensure it's always an array
    if (!is_array($promo_images)) {
      $promo_images = array();
    }
    
    $promo_timeout = isset($settings['promo_timeout']) ? intval($settings['promo_timeout']) : 600;
    $slide_duration = isset($settings['slide_duration']) ? intval($settings['slide_duration']) : 5;
    $transition_type = isset($settings['transition_type']) ? $settings['transition_type'] : 'fade';
    $transition_speed = isset($settings['transition_speed']) ? floatval($settings['transition_speed']) : 1;
    
    // Only localize if we have the script enqueued
    if (wp_script_is('smdp-menu-app', 'enqueued')) {
      wp_localize_script('smdp-menu-app', 'smdpPromo', array(
        'images' => $promo_images,
        'timeout' => $promo_timeout * 1000,
        'slideDuration' => $slide_duration * 1000,
        'transitionType' => $transition_type,
        'transitionSpeed' => $transition_speed
      ));
    }

    if (empty($cats_to_show)) {
      return '<p>No categories with items found. Please assign items in Menu → Items.</p>';
    }

    ob_start();
    ?>
      <div class="smdp-menu-app-fe layout-<?php echo esc_attr($layout); ?>" data-promo-enabled="<?php echo !empty($promo_images) ? '1' : '0'; ?>">
  <div class="smdp-app-header">
    <div class="smdp-cat-bar" role="tablist" aria-label="Menu Categories">

            <?php
            $first = true;
            foreach ($cats_to_show as $cat):
              $slug = isset($cat['slug']) ? $cat['slug'] : '';
              $name = isset($cat['name']) ? $cat['name'] : 'Category';
              $active = $first ? 'active' : '';
              ?>
              <button class="smdp-cat-btn <?php echo esc_attr($active); ?>" role="tab" data-slug="<?php echo esc_attr($slug); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                <?php echo esc_html($name); ?>
              </button>
              <?php
              $first = false;
            endforeach;
            ?>
          </div>
        </div>
        <div class="smdp-app-sections">
          <?php
          $first = true;
          foreach ($cats_to_show as $cat):
            $slug = isset($cat['slug']) ? $cat['slug'] : '';
            $style = $first ? 'display:block;' : 'display:none;';
            ?>
            <section class="smdp-app-section" data-slug="<?php echo esc_attr($slug); ?>" style="<?php echo esc_attr($style); ?>">
              <div class="smdp-menu-container" data-menu-id="<?php echo esc_attr($slug); ?>">
                <?php echo do_shortcode('[square_menu category="'.sanitize_title($slug).'"]'); ?>
              </div>
            </section>
            <?php
            $first = false;
          endforeach;
          ?>
        </div>
      </div>
      
      <?php if (!empty($promo_images)): ?>
      <!-- Promo Screen - Single image only -->
      <div id="smdp-promo-screen" style="display:none;">
        <div id="smdp-slides-container">
          <img src="<?php echo esc_url($promo_images[0]); ?>"
               alt="Promo Image"
               class="smdp-promo-slide"
               style="display:block;">
        </div>
      </div>
      <style>
        /* Category buttons inherit site font */
        .smdp-cat-btn {
          font-family: inherit !important;
        }
        
        #smdp-promo-screen {
          position: fixed;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          bottom: 0 !important;
          width: 100vw !important;
          width: 100dvw !important;
          height: 100vh !important;
          height: 100dvh !important;
          background: #000 !important;
          z-index: 2147483647 !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow: hidden !important;
          -webkit-transform: translate3d(0,0,0);
          transform: translate3d(0,0,0);
        }
        #smdp-slides-container {
          position: relative;
          width: 100%;
          height: 100%;
          overflow: hidden;
        }
        .smdp-promo-slide {
          position: absolute !important;
          top: 0 !important;
          left: 0 !important;
          width: 100% !important;
          height: 100% !important;
          object-fit: cover !important;
          object-position: center center !important;
          display: block !important;
          margin: 0 !important;
          padding: 0 !important;
          border: none !important;
          -webkit-transform: translate3d(0,0,0);
          transform: translate3d(0,0,0);
        }
        /* Mobile viewport fixes (iOS & Android) */
        #smdp-promo-screen {
          min-height: 100vh;
          min-height: -webkit-fill-available;
          min-height: 100dvh; /* Modern standard for dynamic viewport */
          min-width: 100vw;
          min-width: -webkit-fill-available;
          min-width: 100dvw;
        }
      </style>
      <?php endif; ?>
    <?php
    return ob_get_clean();
  }

  /**
   * Handle PWA settings save from the PWA & Debug tab
   */
  public static function handle_pwa_settings_save() {
    // Verify nonce
    if ( ! isset( $_POST['smdp_pwa_nonce'] ) || ! wp_verify_nonce( $_POST['smdp_pwa_nonce'], 'smdp_pwa_settings_save' ) ) {
      wp_die( 'Security check failed' );
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Insufficient permissions' );
    }

    // Save debug mode
    $debug_mode = isset( $_POST['smdp_pwa_debug_mode'] ) ? 1 : 0;
    update_option( 'smdp_pwa_debug_mode', $debug_mode );

    // Save cache version
    $cache_version = isset( $_POST['smdp_cache_version'] ) ? intval( $_POST['smdp_cache_version'] ) : 1;
    update_option( 'smdp_cache_version', $cache_version );

    // Save PWA manifest settings to the existing settings option
    $settings = get_option( self::OPT_SETTINGS, array() );
    if ( ! is_array( $settings ) ) {
      $settings = array();
    }

    // Save theme colors
    if ( isset( $_POST['smdp_pwa_theme_color'] ) ) {
      $settings['theme_color'] = sanitize_hex_color( $_POST['smdp_pwa_theme_color'] );
    }
    if ( isset( $_POST['smdp_pwa_background_color'] ) ) {
      $settings['background_color'] = sanitize_hex_color( $_POST['smdp_pwa_background_color'] );
    }

    // Save PWA app settings if they exist in the POST data
    if ( isset( $_POST[ self::OPT_SETTINGS ] ) && is_array( $_POST[ self::OPT_SETTINGS ] ) ) {
      $pwa_data = $_POST[ self::OPT_SETTINGS ];

      // Icons - only update if value is provided (preserve existing if empty)
      if ( isset( $pwa_data['icon_192'] ) && !empty( $pwa_data['icon_192'] ) ) {
        $settings['icon_192'] = esc_url_raw( $pwa_data['icon_192'] );
      } elseif ( isset( $pwa_data['icon_192'] ) && empty( $pwa_data['icon_192'] ) && isset( $settings['icon_192'] ) ) {
        // Keep existing value if form field is empty
        // (Don't overwrite with empty string)
      }

      if ( isset( $pwa_data['icon_512'] ) && !empty( $pwa_data['icon_512'] ) ) {
        $settings['icon_512'] = esc_url_raw( $pwa_data['icon_512'] );
      } elseif ( isset( $pwa_data['icon_512'] ) && empty( $pwa_data['icon_512'] ) && isset( $settings['icon_512'] ) ) {
        // Keep existing
      }

      if ( isset( $pwa_data['apple_touch_icon'] ) && !empty( $pwa_data['apple_touch_icon'] ) ) {
        $settings['apple_touch_icon'] = esc_url_raw( $pwa_data['apple_touch_icon'] );
      } elseif ( isset( $pwa_data['apple_touch_icon'] ) && empty( $pwa_data['apple_touch_icon'] ) && isset( $settings['apple_touch_icon'] ) ) {
        // Keep existing
      }

      // App Identity - these can be intentionally empty, so always save
      if ( isset( $pwa_data['app_name'] ) ) {
        $settings['app_name'] = sanitize_text_field( $pwa_data['app_name'] );
      }
      if ( isset( $pwa_data['app_short_name'] ) ) {
        $settings['app_short_name'] = sanitize_text_field( $pwa_data['app_short_name'] );
      }
      if ( isset( $pwa_data['app_description'] ) ) {
        $settings['app_description'] = sanitize_textarea_field( $pwa_data['app_description'] );
      }

      // Display Options - these should always have values from dropdowns
      if ( isset( $pwa_data['display_mode'] ) ) {
        $valid_modes = array( 'standalone', 'fullscreen', 'minimal-ui', 'browser' );
        $settings['display_mode'] = in_array( $pwa_data['display_mode'], $valid_modes ) ? $pwa_data['display_mode'] : 'standalone';
      }
      if ( isset( $pwa_data['orientation'] ) ) {
        $valid_orientations = array( 'any', 'portrait', 'landscape', 'portrait-primary', 'landscape-primary' );
        $settings['orientation'] = in_array( $pwa_data['orientation'], $valid_orientations ) ? $pwa_data['orientation'] : 'any';
      }
    }

    update_option( self::OPT_SETTINGS, $settings );

    // Redirect back with success message
    wp_safe_redirect( add_query_arg( array(
      'page' => 'smdp_menu_app_builder',
      'pwa_saved' => '1'
    ), admin_url( 'admin.php' ) ) );
    exit;
  }

  /**
   * Handle flush rewrite rules action for menu app URL
   */
  public static function handle_flush_rewrite_rules() {
    // Check if we're on the menu app builder page with the flush action
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'smdp_menu_app_builder' ) {
      return;
    }

    if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'flush_rewrite_rules' ) {
      return;
    }

    // Verify nonce
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'smdp_flush_rewrite_rules' ) ) {
      wp_die( 'Security check failed' );
    }

    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Insufficient permissions' );
    }

    // Register rewrite rules before flushing
    if ( class_exists( 'SMDP_Plugin_Activation' ) ) {
      SMDP_Plugin_Activation::register_menu_app_rewrite_rules();
    }

    // Flush rewrite rules
    flush_rewrite_rules();

    // Set transient to show success message
    set_transient( 'smdp_rewrite_rules_flushed', true, 30 );

    // Redirect back to menu app builder page
    wp_safe_redirect( admin_url( 'admin.php?page=smdp_menu_app_builder#tab-advanced' ) );
    exit;
  }
}