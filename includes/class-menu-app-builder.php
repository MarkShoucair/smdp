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
        
        $sanitized = array();
        
        // Layout
        $sanitized['layout'] = isset($input['layout']) ? sanitize_text_field($input['layout']) : 'top';
        
        // Promo timeout
        $sanitized['promo_timeout'] = isset($input['promo_timeout']) ? intval($input['promo_timeout']) : 600;

        // PWA theme colors
        $sanitized['theme_color'] = ! empty( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : '#5C7BA6';
        $sanitized['background_color'] = ! empty( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '#ffffff';

        // PWA icons
        $sanitized['icon_192'] = ! empty( $input['icon_192'] ) ? esc_url_raw( $input['icon_192'] ) : '';
        $sanitized['icon_512'] = ! empty( $input['icon_512'] ) ? esc_url_raw( $input['icon_512'] ) : '';
        $sanitized['apple_touch_icon'] = ! empty( $input['apple_touch_icon'] ) ? esc_url_raw( $input['apple_touch_icon'] ) : '';

        // PWA identity
        $sanitized['app_name'] = ! empty( $input['app_name'] ) ? sanitize_text_field( $input['app_name'] ) : '';
        $sanitized['app_short_name'] = ! empty( $input['app_short_name'] ) ? sanitize_text_field( $input['app_short_name'] ) : '';
        $sanitized['app_description'] = ! empty( $input['app_description'] ) ? sanitize_text_field( $input['app_description'] ) : '';

        // PWA display options
        $sanitized['display_mode'] = ! empty( $input['display_mode'] ) ? sanitize_text_field( $input['display_mode'] ) : 'standalone';
        $sanitized['orientation'] = ! empty( $input['orientation'] ) ? sanitize_text_field( $input['orientation'] ) : 'any';

        // Promo image (single image only) - decode from JSON if needed
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
    if ($hook !== 'toplevel_page_smdp_menu_app_builder') return;

    // Enqueue media uploader for promo image
    wp_enqueue_media();

    $plugin_main = dirname(dirname(__FILE__)) . '/Main.php';
    $base_url = plugins_url('', $plugin_main);
    $ver = '1.8';

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
   * DEPRECATED: Old dynamic button CSS generation removed
   * All default styles are now hardcoded in CSS files
   * Users can override via Custom CSS textarea
   */
  private static function output_button_styles_DEPRECATED() {
    // This method is no longer used
  }

  /**
   * DEPRECATED: Dynamic CSS generation removed
   * All styles are now hardcoded in CSS files
   */
  private static function generate_button_css_DEPRECATED($styles) {
    // This method is no longer used
    return '';
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
        <a href="#tab-layout" class="nav-tab nav-tab-active">App Layout</a>
        <a href="#tab-styles" class="nav-tab">Styles</a>
        <a href="#tab-pwa" class="nav-tab">PWA & Debug</a>
        <a href="#tab-advanced" class="nav-tab">Advanced</a>
      </h2>

      <div id="tab-layout" class="smdp-tab active">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <form method="post" action="options.php">
          <?php settings_fields('smdp_menu_app_layout_group'); ?>
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row">App Layout</th>
              <td><?php self::field_settings(); ?></td>
            </tr>
            <tr>
              <th scope="row">Promo Screen</th>
              <td><?php self::field_promo_settings(); ?></td>
            </tr>
            <tr>
              <th scope="row">Custom CSS</th>
              <td><?php self::field_css(); ?></td>
            </tr>
          </table>
          <?php submit_button('Save Settings'); ?>
        </form>
        <p><strong>Display on a page</strong> with: <code>[smdp_menu_app id="default"]</code> &nbsp; Optional: <code>layout="left"</code>.</p>
        </div>
      </div>

      <div id="tab-styles" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <form method="post" action="options.php">
          <?php settings_fields('smdp_menu_app_styles_group'); ?>
          <h2>Category Button Styles</h2>
          <?php self::field_styles(); ?>
          <?php submit_button('Save Button Styles'); ?>
        </form>
        </div>
      </div>

      <!-- Items and Categories tabs moved to Menu Management → Menu Editor -->

      <!-- Tab: PWA & Debug -->
      <div id="tab-pwa" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">PWA & Debug Settings</h2>
        <p>Control Progressive Web App features, caching behavior, and debug tools for the menu app.</p>

        <?php
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

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
          <input type="hidden" name="action" value="smdp_save_pwa_settings">
          <?php wp_nonce_field( 'smdp_pwa_settings_save', 'smdp_pwa_nonce' ); ?>

          <h3>Debug Mode</h3>
          <table class="form-table">
            <tr>
              <th scope="row">Enable Debug Mode</th>
              <td>
                <label>
                  <input type="checkbox" name="smdp_pwa_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?>>
                  Enable PWA Debug Mode (bypass caching, show debug panel)
                </label>
                <p class="description">When enabled, tablets will always load the latest version of files and display a debug panel with cache-clearing tools.</p>
              </td>
            </tr>
            <tr>
              <th scope="row">Cache Version</th>
              <td>
                <input type="number" name="smdp_cache_version" id="smdp_cache_version_pwa" value="<?php echo esc_attr($cache_version); ?>" min="1" style="width:100px;">
                <button type="button" class="button" id="smdp-increment-version-pwa">Increment Version</button>
                <p class="description">
                  Current version: <strong>v<?php echo $cache_version; ?></strong><br>
                  Increment this number to force all tablets to reload assets, even without debug mode enabled.
                </p>
                <script>
                  jQuery(document).ready(function($){
                    $('#smdp-increment-version-pwa').click(function(){
                      var $input = $('#smdp_cache_version_pwa');
                      $input.val(parseInt($input.val()) + 1);
                    });
                  });
                </script>
              </td>
            </tr>
          </table>

          <h3>PWA Manifest Settings</h3>
          <p>These settings control how the menu app appears when installed as a Progressive Web App on tablets and phones.</p>

          <table class="form-table">
            <tr>
              <th scope="row">
                <label for="smdp_pwa_theme_color">Theme Color</label>
              </th>
              <td>
                <input type="text" name="smdp_pwa_theme_color" value="<?php echo esc_attr($theme_color); ?>" class="smdp-pwa-color-picker" />
                <p class="description">Color for the browser's address bar and splash screen when installed as PWA</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="smdp_pwa_background_color">Background Color</label>
              </th>
              <td>
                <input type="text" name="smdp_pwa_background_color" value="<?php echo esc_attr($background_color); ?>" class="smdp-pwa-color-picker" />
                <p class="description">Background color for the splash screen when launching PWA</p>
              </td>
            </tr>
          </table>

          <?php submit_button( 'Save PWA & Debug Settings', 'primary', 'smdp_save_pwa' ); ?>
        </form>
        </div>
      </div>

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
      .smdp-tab { display:none; }
      .smdp-tab.active { display:block; }
      .nav-tab-wrapper { margin-top: 12px; }
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
    // Enhanced tab switching with debugging
    jQuery(document).ready(function($){
        console.log('Menu App Builder: Tabs initializing');
        
        var $tabs = $('.nav-tab-wrapper .nav-tab');
        var $panes = $('.smdp-tab');
        
        console.log('Found tabs:', $tabs.length, 'Found panes:', $panes.length);
        
        $tabs.on('click', function(e){
            e.preventDefault();
            console.log('Tab clicked:', $(this).attr('href'));
            
            // Remove all active states
            $tabs.removeClass('nav-tab-active');
            $panes.removeClass('active');
            
            // Add active to clicked tab
            $(this).addClass('nav-tab-active');
            
            // Show corresponding pane
            var target = $(this).attr('href');
            var $targetPane = $(target);
            
            if ($targetPane.length) {
                $targetPane.addClass('active');
                console.log('Activated pane:', target);
            } else {
                console.error('Pane not found:', target);
            }
        });
        
        // Ensure first tab is active on load
        if (!$tabs.filter('.nav-tab-active').length) {
            $tabs.first().addClass('nav-tab-active');
            $panes.first().addClass('active');
        }
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
    $theme_color = isset($settings['theme_color']) ? $settings['theme_color'] : '#5C7BA6';
    $background_color = isset($settings['background_color']) ? $settings['background_color'] : '#ffffff';

    // PWA icons
    $icon_192 = isset($settings['icon_192']) ? $settings['icon_192'] : '';
    $icon_512 = isset($settings['icon_512']) ? $settings['icon_512'] : '';
    $apple_touch_icon = isset($settings['apple_touch_icon']) ? $settings['apple_touch_icon'] : '';

    // PWA identity
    $app_name = isset($settings['app_name']) ? $settings['app_name'] : '';
    $app_short_name = isset($settings['app_short_name']) ? $settings['app_short_name'] : '';
    $app_description = isset($settings['app_description']) ? $settings['app_description'] : '';

    // PWA display options
    $display_mode = isset($settings['display_mode']) ? $settings['display_mode'] : 'standalone';
    $orientation = isset($settings['orientation']) ? $settings['orientation'] : 'any';
    ?>
    <fieldset>
      <h3 style="margin-top:0;">⏱️ Idle Timeout</h3>
      <div style="margin-bottom:20px;">
        <label><strong>Idle Timeout (seconds)</strong></label><br>
        <input type="number" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[promo_timeout]" value="<?php echo esc_attr($promo_timeout); ?>" min="30" style="width:100px;">
        <p class="description">Show promo screen after this many seconds of inactivity (default: 600 = 10 minutes)</p>
      </div>

      <h3>🎨 PWA Colors</h3>
      <div style="margin-bottom:12px;">
        <label><strong>Theme Color</strong></label><br>
        <input type="text" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[theme_color]" value="<?php echo esc_attr($theme_color); ?>" class="smdp-pwa-color-picker" />
        <p class="description">Color for the browser's address bar and splash screen when installed as PWA</p>
      </div>

      <div style="margin-bottom:20px;">
        <label><strong>Background Color</strong></label><br>
        <input type="text" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[background_color]" value="<?php echo esc_attr($background_color); ?>" class="smdp-pwa-color-picker" />
        <p class="description">Background color for the splash screen when launching PWA</p>
      </div>

      <h3>📱 PWA App Icons</h3>
      <div style="margin-bottom:12px;">
        <label><strong>App Icon 192x192</strong></label><br>
        <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[icon_192]" id="smdp-icon-192" value="<?php echo esc_attr($icon_192); ?>">
        <button type="button" class="button smdp-upload-icon" data-target="smdp-icon-192" data-preview="smdp-icon-192-preview">Upload Icon (192x192)</button>
        <?php if ($icon_192): ?>
          <button type="button" class="button smdp-clear-icon" data-target="smdp-icon-192" data-preview="smdp-icon-192-preview">Remove</button>
        <?php endif; ?>
        <div id="smdp-icon-192-preview" style="margin-top:8px;">
          <?php if ($icon_192): ?>
            <img src="<?php echo esc_url($icon_192); ?>" style="max-width:192px; border:1px solid #ddd; padding:4px;">
          <?php endif; ?>
        </div>
        <p class="description">Used for Android home screen (192x192 pixels)</p>
      </div>

      <div style="margin-bottom:12px;">
        <label><strong>App Icon 512x512</strong></label><br>
        <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[icon_512]" id="smdp-icon-512" value="<?php echo esc_attr($icon_512); ?>">
        <button type="button" class="button smdp-upload-icon" data-target="smdp-icon-512" data-preview="smdp-icon-512-preview">Upload Icon (512x512)</button>
        <?php if ($icon_512): ?>
          <button type="button" class="button smdp-clear-icon" data-target="smdp-icon-512" data-preview="smdp-icon-512-preview">Remove</button>
        <?php endif; ?>
        <div id="smdp-icon-512-preview" style="margin-top:8px;">
          <?php if ($icon_512): ?>
            <img src="<?php echo esc_url($icon_512); ?>" style="max-width:192px; border:1px solid #ddd; padding:4px;">
          <?php endif; ?>
        </div>
        <p class="description">Used for splash screen and high-res displays (512x512 pixels)</p>
      </div>

      <div style="margin-bottom:20px;">
        <label><strong>Apple Touch Icon</strong></label><br>
        <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[apple_touch_icon]" id="smdp-apple-icon" value="<?php echo esc_attr($apple_touch_icon); ?>">
        <button type="button" class="button smdp-upload-icon" data-target="smdp-apple-icon" data-preview="smdp-apple-icon-preview">Upload Icon (180x180)</button>
        <?php if ($apple_touch_icon): ?>
          <button type="button" class="button smdp-clear-icon" data-target="smdp-apple-icon" data-preview="smdp-apple-icon-preview">Remove</button>
        <?php endif; ?>
        <div id="smdp-apple-icon-preview" style="margin-top:8px;">
          <?php if ($apple_touch_icon): ?>
            <img src="<?php echo esc_url($apple_touch_icon); ?>" style="max-width:180px; border:1px solid #ddd; padding:4px;">
          <?php endif; ?>
        </div>
        <p class="description">Used for iOS home screen (180x180 pixels, will be rounded automatically by iOS)</p>
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

      <div>
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
        <p class="description">Upload a single promo image. Leave empty to disable promo screen.</p>
      </div>
    </fieldset>
    
    <script>
    jQuery(document).ready(function($){
      // Initialize color pickers for PWA theme colors
      $('.smdp-pwa-color-picker').wpColorPicker();

      var mediaUploader;
      var iconUploaders = {}; // Store separate uploaders for each icon

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

      // PWA Icon Upload Handlers
      $('.smdp-upload-icon').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var targetId = $button.data('target');
        var previewId = $button.data('preview');

        // Check if we already have an uploader for this specific icon
        if (iconUploaders[targetId]) {
          iconUploaders[targetId].open();
          return;
        }

        // Create a new uploader instance for this specific icon
        iconUploaders[targetId] = wp.media({
          title: 'Select PWA Icon',
          button: { text: 'Use this icon' },
          multiple: false,
          library: { type: 'image' }
        });

        iconUploaders[targetId].on('select', function() {
          var attachment = iconUploaders[targetId].state().get('selection').first().toJSON();
          var url = attachment.url;

          // Update hidden field
          $('#' + targetId).val(url);

          // Update preview
          $('#' + previewId).html('<img src="' + url + '" style="max-width:192px; border:1px solid #ddd; padding:4px;">');

          // Show remove button
          $button.next('.smdp-clear-icon').show();
        });

        iconUploaders[targetId].open();
      });

      // PWA Icon Clear Handlers
      $('.smdp-clear-icon').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var targetId = $button.data('target');
        var previewId = $button.data('preview');

        if (confirm('Remove this icon?')) {
          $('#' + targetId).val('');
          $('#' + previewId).html('');
          $button.hide();
        }
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
    <div class="smdp-style-controls" style="max-width: 800px;">
      
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

      <div class="smdp-button-preview" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
        <h3>Preview</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
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
          ">Default Button</button>
          
          <button type="button" class="smdp-preview-btn" style="
            background: <?php echo esc_attr($styles['active_bg_color']); ?>;
            color: <?php echo esc_attr($styles['active_text_color']); ?>;
            border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['active_border_color']); ?>;
            font-size: <?php echo esc_attr($styles['font_size']); ?>px;
            padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px;
            border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
            font-weight: <?php echo esc_attr($styles['font_weight']); ?>;
            font-family: <?php echo esc_attr($styles['font_family']); ?>;
            cursor: pointer;
          ">Active Button</button>
        </div>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      $('.smdp-color-picker').wpColorPicker({
        change: function(event, ui) {
          updatePreview();
        }
      });

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

        $preview.find('.smdp-preview-btn').eq(0).css({
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

        $preview.find('.smdp-preview-btn').eq(1).css({
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
      $('.smdp-style-controls input, .smdp-style-controls select').on('change keyup', updatePreview);
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

    $normalized = array();
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
      if (!empty($items)) {
        $name = isset($cat['name']) ? $cat['name'] : 'Category';
        $menu['categories'][] = array('name'=>$name, 'items'=>$items);
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
    $atts = shortcode_atts(array('id' => 'default', 'layout' => ''), $atts, 'smdp_menu_app');

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

    $counts = array();
    foreach ($mapping as $item_id => $m) {
      if (!isset($valid_items[$item_id])) continue;
      $cid = isset($m['category']) ? $m['category'] : '';
      if ($cid==='') continue;
      if (!isset($counts[$cid])) $counts[$cid] = 0;
      $counts[$cid]++;
    }

    $cats = array_values($categories);
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

    if ( isset( $_POST['smdp_pwa_theme_color'] ) ) {
      $settings['theme_color'] = sanitize_hex_color( $_POST['smdp_pwa_theme_color'] );
    }
    if ( isset( $_POST['smdp_pwa_background_color'] ) ) {
      $settings['background_color'] = sanitize_hex_color( $_POST['smdp_pwa_background_color'] );
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