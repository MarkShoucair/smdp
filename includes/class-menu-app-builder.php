<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMDP_Menu_App_Builder {
  const OPT_MENUS    = 'smdp_app_menus';
  const OPT_CSS      = 'smdp_app_custom_css';
  const OPT_CATALOG  = 'smdp_app_catalog';
  const OPT_SETTINGS = 'smdp_app_settings'; // ['layout' => 'top'|'left', 'promo_image' => url, 'promo_timeout' => seconds]
  const OPT_STYLES   = 'smdp_app_button_styles'; // Category button styles
  const OPT_HELP_BTN_STYLES = 'smdp_app_help_button_styles'; // Help button styles
  const OPT_BG_COLORS = 'smdp_app_background_colors'; // Background colors
  const OPT_ITEM_CARD_STYLES = 'smdp_app_item_card_styles'; // Item card styles
  const OPT_ITEM_DETAIL_STYLES = 'smdp_app_item_detail_styles'; // Item detail modal styles
  const OPT_STYLE_GENERATOR = 'smdp_app_style_generator_prefs'; // Style generator preferences (theme mode, button shape, colors)

  public static function init() {
    add_action('admin_menu', array(__CLASS__, 'admin_menu'));
    add_action('admin_init', array(__CLASS__, 'register_settings'));
    add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin'));
    add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend'));
    add_action('wp_head', array(__CLASS__, 'inject_custom_styles'), 999); // Priority 999 to run after all enqueued styles
    add_action('admin_post_smdp_save_pwa_settings', array(__CLASS__, 'handle_pwa_settings_save'));
    add_action('admin_post_smdp_reset_styles', array(__CLASS__, 'handle_reset_styles'));
    add_action('admin_init', array(__CLASS__, 'handle_flush_rewrite_rules'));
    add_action('wp_ajax_smdp_save_all_styles', array(__CLASS__, 'ajax_save_all_styles'));
    add_action('wp_ajax_smdp_reset_all_styles', array(__CLASS__, 'ajax_reset_all_styles'));
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
    // Add Menu App submenu
    add_submenu_page(
      'smdp_main',                          // Parent slug (Square Menu)
      'Menu App',                           // Page title
      'Menu App',                           // Menu title
      'manage_options',                     // Capability
      'smdp_menu_app_builder',              // Menu slug
      array(__CLASS__, 'render_admin_page') // Callback
    );

    // Add Styles & Customization submenu
    add_submenu_page(
      'smdp_main',                              // Parent slug (Square Menu)
      'Styles & Customization',                 // Page title
      'Styles & Customization',                 // Menu title
      'manage_options',                         // Capability
      'smdp_styles_customization',              // Menu slug
      array(__CLASS__, 'render_styles_page')    // Callback
    );
  }

  public static function register_settings() {
    // Sanitize callback for styles (defaults match hardcoded CSS in smdp-structural.css lines 231-248)
    $sanitize_styles = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();
        $sanitized['bg_color'] = !empty($input['bg_color']) ? sanitize_hex_color($input['bg_color']) : '#ffffff';
        $sanitized['text_color'] = !empty($input['text_color']) ? sanitize_hex_color($input['text_color']) : '#000000';
        $sanitized['border_color'] = !empty($input['border_color']) ? sanitize_hex_color($input['border_color']) : '#01669c';
        $sanitized['active_bg_color'] = !empty($input['active_bg_color']) ? sanitize_hex_color($input['active_bg_color']) : '#0986bf';
        $sanitized['active_text_color'] = !empty($input['active_text_color']) ? sanitize_hex_color($input['active_text_color']) : '#ffffff';
        $sanitized['active_border_color'] = !empty($input['active_border_color']) ? sanitize_hex_color($input['active_border_color']) : '#0986bf';
        $sanitized['font_size'] = !empty($input['font_size']) ? intval($input['font_size']) : 25;
        $sanitized['padding_vertical'] = !empty($input['padding_vertical']) ? intval($input['padding_vertical']) : 16;
        $sanitized['padding_horizontal'] = !empty($input['padding_horizontal']) ? intval($input['padding_horizontal']) : 40;
        $sanitized['border_radius'] = !empty($input['border_radius']) ? intval($input['border_radius']) : 500;
        $sanitized['border_width'] = !empty($input['border_width']) ? intval($input['border_width']) : 1;
        $sanitized['font_weight'] = !empty($input['font_weight']) ? sanitize_text_field($input['font_weight']) : 'normal';
        $sanitized['font_family'] = !empty($input['font_family']) ? sanitize_text_field($input['font_family']) : '';
        $sanitized['box_shadow'] = !empty($input['box_shadow']) ? sanitize_text_field($input['box_shadow']) : 'none';

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

        // Action button enable/disable settings
        // Only update if _form_type is action_buttons
        if (isset($input['_form_type']) && $input['_form_type'] === 'action_buttons') {
            // Note: Checkboxes only send value when checked, so we need to handle unchecked state
            $sanitized['enable_help_btn'] = isset($input['enable_help_btn']) ? '1' : '0';
            $sanitized['enable_bill_btn'] = isset($input['enable_bill_btn']) ? '1' : '0';
            $sanitized['enable_view_bill_btn'] = isset($input['enable_view_bill_btn']) ? '1' : '0';
            $sanitized['enable_table_badge'] = isset($input['enable_table_badge']) ? '1' : '0';
            $sanitized['enable_table_selector'] = isset($input['enable_table_selector']) ? '1' : '0';
        }

        // Item detail modal settings
        // Only update if _form_type is item_detail_modal
        if (isset($input['_form_type']) && $input['_form_type'] === 'item_detail_modal') {
            $sanitized['enable_modal_shortcode'] = isset($input['enable_modal_shortcode']) ? '1' : '0';
            $sanitized['enable_modal_menuapp'] = isset($input['enable_modal_menuapp']) ? '1' : '0';
            $sanitized['enable_modal_filter'] = isset($input['enable_modal_filter']) ? '1' : '0';
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
        }
        // NOTE: If promo_images is not in input, preserve existing value (don't clear it)
        
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

    // Help button styles settings (Styles tab > Help Buttons subtab) - SEPARATE GROUP
    // Each button type has its own set of styles
    $sanitize_help_btn_styles = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();

        // Process each button type
        $button_types = array('help', 'bill', 'view_bill', 'table_badge');
        foreach ($button_types as $type) {
            if (!isset($input[$type]) || !is_array($input[$type])) {
                $sanitized[$type] = array();
                continue;
            }

            $btn = $input[$type];
            $sanitized[$type] = array();
            $sanitized[$type]['bg_color'] = !empty($btn['bg_color']) ? sanitize_hex_color($btn['bg_color']) : '#ffffff';
            $sanitized[$type]['text_color'] = !empty($btn['text_color']) ? sanitize_hex_color($btn['text_color']) : '#ffffff';
            $sanitized[$type]['border_color'] = !empty($btn['border_color']) ? sanitize_hex_color($btn['border_color']) : '#ffffff';
            $sanitized[$type]['hover_bg_color'] = !empty($btn['hover_bg_color']) ? sanitize_hex_color($btn['hover_bg_color']) : '#ffffff';
            $sanitized[$type]['hover_text_color'] = !empty($btn['hover_text_color']) ? sanitize_hex_color($btn['hover_text_color']) : '#ffffff';
            $sanitized[$type]['hover_border_color'] = !empty($btn['hover_border_color']) ? sanitize_hex_color($btn['hover_border_color']) : '#ffffff';

            // Disabled states only for help and bill buttons
            if ($type === 'help' || $type === 'bill') {
                $sanitized[$type]['disabled_bg_color'] = !empty($btn['disabled_bg_color']) ? sanitize_hex_color($btn['disabled_bg_color']) : '#ffffff';
                $sanitized[$type]['disabled_text_color'] = !empty($btn['disabled_text_color']) ? sanitize_hex_color($btn['disabled_text_color']) : '#ffffff';
            }

            $sanitized[$type]['font_size'] = !empty($btn['font_size']) ? intval($btn['font_size']) : 16;
            $sanitized[$type]['padding_vertical'] = !empty($btn['padding_vertical']) ? intval($btn['padding_vertical']) : 10;
            $sanitized[$type]['padding_horizontal'] = !empty($btn['padding_horizontal']) ? intval($btn['padding_horizontal']) : 14;
            $sanitized[$type]['border_radius'] = !empty($btn['border_radius']) ? intval($btn['border_radius']) : 8;
            $sanitized[$type]['border_width'] = !empty($btn['border_width']) ? intval($btn['border_width']) : 0;
            $sanitized[$type]['font_weight'] = !empty($btn['font_weight']) ? sanitize_text_field($btn['font_weight']) : '600';
            $sanitized[$type]['font_family'] = !empty($btn['font_family']) ? sanitize_text_field($btn['font_family']) : '';
            $sanitized[$type]['box_shadow'] = !empty($btn['box_shadow']) ? sanitize_text_field($btn['box_shadow']) : '0 4px 10px rgba(0,0,0,0.3)';
        }

        return $sanitized;
    };

    register_setting('smdp_menu_app_help_btn_styles_group', self::OPT_HELP_BTN_STYLES, array(
        'sanitize_callback' => $sanitize_help_btn_styles,
        'default' => array()
    ));

    // Background colors settings (Styles tab > Background Colors subtab) - SEPARATE GROUP
    $sanitize_bg_colors = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();
        $sanitized['main_bg'] = !empty($input['main_bg']) ? sanitize_hex_color($input['main_bg']) : '#ffffff';
        $sanitized['category_bar_bg'] = !empty($input['category_bar_bg']) ? sanitize_hex_color($input['category_bar_bg']) : '#ffffff';
        $sanitized['content_area_bg'] = !empty($input['content_area_bg']) ? sanitize_hex_color($input['content_area_bg']) : '#ffffff';

        return $sanitized;
    };

    register_setting('smdp_menu_app_bg_colors_group', self::OPT_BG_COLORS, array(
        'sanitize_callback' => $sanitize_bg_colors,
        'default' => array()
    ));

    // Item card styles settings (Styles tab > Item Cards subtab) - SEPARATE GROUP
    $sanitize_item_card_styles = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();
        $sanitized['bg_color'] = !empty($input['bg_color']) ? sanitize_hex_color($input['bg_color']) : '#ffffff';
        $sanitized['text_color'] = !empty($input['text_color']) ? sanitize_hex_color($input['text_color']) : '#000000';
        $sanitized['border_color'] = !empty($input['border_color']) ? sanitize_hex_color($input['border_color']) : '#eeeeee';
        $sanitized['border_width'] = isset($input['border_width']) ? absint($input['border_width']) : 1;
        $sanitized['border_radius'] = isset($input['border_radius']) ? absint($input['border_radius']) : 0;
        $sanitized['padding'] = isset($input['padding']) ? absint($input['padding']) : 8;
        $sanitized['title_color'] = !empty($input['title_color']) ? sanitize_hex_color($input['title_color']) : '#000000';
        $sanitized['title_size'] = isset($input['title_size']) ? absint($input['title_size']) : 19;
        $sanitized['title_weight'] = !empty($input['title_weight']) ? sanitize_text_field($input['title_weight']) : 'bold';
        $sanitized['price_color'] = !empty($input['price_color']) ? sanitize_hex_color($input['price_color']) : '#000000';
        $sanitized['price_size'] = isset($input['price_size']) ? absint($input['price_size']) : 16;
        $sanitized['price_weight'] = !empty($input['price_weight']) ? sanitize_text_field($input['price_weight']) : 'bold';
        $sanitized['desc_color'] = !empty($input['desc_color']) ? sanitize_hex_color($input['desc_color']) : '#666666';
        $sanitized['desc_size'] = isset($input['desc_size']) ? absint($input['desc_size']) : 14;
        $sanitized['box_shadow'] = !empty($input['box_shadow']) ? sanitize_text_field($input['box_shadow']) : '0 2px 4px rgba(0,0,0,0.1)';

        return $sanitized;
    };

    register_setting('smdp_menu_app_item_card_styles_group', self::OPT_ITEM_CARD_STYLES, array(
        'sanitize_callback' => $sanitize_item_card_styles,
        'default' => array()
    ));

    // Item detail modal styles settings (Styles tab > Item Detail subtab) - SEPARATE GROUP
    $sanitize_item_detail_styles = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();
        $sanitized['modal_bg'] = !empty($input['modal_bg']) ? sanitize_hex_color($input['modal_bg']) : '#ffffff';
        $sanitized['modal_border_color'] = !empty($input['modal_border_color']) ? sanitize_hex_color($input['modal_border_color']) : '#3498db';
        $sanitized['modal_border_width'] = isset($input['modal_border_width']) ? absint($input['modal_border_width']) : 6;
        $sanitized['modal_border_radius'] = isset($input['modal_border_radius']) ? absint($input['modal_border_radius']) : 12;
        $sanitized['modal_box_shadow'] = !empty($input['modal_box_shadow']) ? sanitize_text_field($input['modal_box_shadow']) : '0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)';
        $sanitized['title_color'] = !empty($input['title_color']) ? sanitize_hex_color($input['title_color']) : '#000000';
        $sanitized['title_size'] = isset($input['title_size']) ? absint($input['title_size']) : 24;
        $sanitized['title_weight'] = !empty($input['title_weight']) ? sanitize_text_field($input['title_weight']) : 'bold';
        $sanitized['price_color'] = !empty($input['price_color']) ? sanitize_hex_color($input['price_color']) : '#27ae60';
        $sanitized['price_size'] = isset($input['price_size']) ? absint($input['price_size']) : 19;
        $sanitized['price_weight'] = !empty($input['price_weight']) ? sanitize_text_field($input['price_weight']) : 'bold';
        $sanitized['desc_color'] = !empty($input['desc_color']) ? sanitize_hex_color($input['desc_color']) : '#666666';
        $sanitized['desc_size'] = isset($input['desc_size']) ? absint($input['desc_size']) : 16;
        $sanitized['close_btn_bg'] = !empty($input['close_btn_bg']) ? sanitize_hex_color($input['close_btn_bg']) : '#3498db';
        $sanitized['close_btn_text'] = !empty($input['close_btn_text']) ? sanitize_hex_color($input['close_btn_text']) : '#ffffff';
        $sanitized['close_btn_hover_bg'] = !empty($input['close_btn_hover_bg']) ? sanitize_hex_color($input['close_btn_hover_bg']) : '#2980b9';

        return $sanitized;
    };

    register_setting('smdp_menu_app_item_detail_styles_group', self::OPT_ITEM_DETAIL_STYLES, array(
        'sanitize_callback' => $sanitize_item_detail_styles,
        'default' => array()
    ));

    // Style generator preferences
    $sanitize_style_generator = function($input) {
        if (!is_array($input)) return array();

        $sanitized = array();
        $sanitized['theme_mode'] = !empty($input['theme_mode']) && in_array($input['theme_mode'], array('light', 'dark')) ? $input['theme_mode'] : 'light';
        $sanitized['button_shape'] = !empty($input['button_shape']) && in_array($input['button_shape'], array('pill', 'rounded', 'slightly-rounded', 'square')) ? $input['button_shape'] : 'pill';
        $sanitized['primary_color'] = !empty($input['primary_color']) ? sanitize_hex_color($input['primary_color']) : '#0986bf';
        $sanitized['secondary_color'] = !empty($input['secondary_color']) ? sanitize_hex_color($input['secondary_color']) : '#27ae60';
        $sanitized['accent_color'] = !empty($input['accent_color']) ? sanitize_hex_color($input['accent_color']) : '#e74c3c';
        $sanitized['include_help_buttons'] = !empty($input['include_help_buttons']) ? true : false;

        return $sanitized;
    };

    register_setting('smdp_menu_app_style_generator_group', self::OPT_STYLE_GENERATOR, array(
        'sanitize_callback' => $sanitize_style_generator,
        'default' => array()
    ));

    add_settings_section('smdp_menu_app_section', '', '__return_false', 'smdp_menu_app_builder');
    add_settings_field(self::OPT_SETTINGS, 'App Layout', array(__CLASS__, 'field_settings'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
    add_settings_field('promo_settings', 'Promo Screen', array(__CLASS__, 'field_promo_settings'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
    add_settings_field(self::OPT_CSS, 'Custom CSS', array(__CLASS__, 'field_css'), 'smdp_menu_app_builder', 'smdp_menu_app_section');
  }

  public static function enqueue_admin($hook) {
    // Load assets for both Menu App Builder and Styles pages
    if ($hook !== 'square-menu_page_smdp_menu_app_builder' && $hook !== 'square-menu_page_smdp_styles_customization') return;

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

    // Default values (matches hardcoded CSS in smdp-structural.css lines 231-248)
    $defaults = array(
      'bg_color' => '#ffffff',
      'text_color' => '#000000',
      'border_color' => '#01669c',
      'active_bg_color' => '#0986bf',
      'active_text_color' => '#ffffff',
      'active_border_color' => '#0986bf',
      'font_size' => 25,
      'padding_vertical' => 16,
      'padding_horizontal' => 40,
      'border_radius' => 500,
      'border_width' => 1,
      'font_weight' => 'normal',
      'font_family' => '',
      'box_shadow' => 'none',
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
  box-shadow: <?php echo esc_attr($styles['box_shadow']); ?> !important;
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

    // Get saved Help button styles (individual for each button type)
    $all_help_styles = get_option(self::OPT_HELP_BTN_STYLES, array());

    // If custom Help button styles are saved, output them
    if (!empty($all_help_styles)) {
      // Default values for each button type
      $help_defaults = array(
        'help' => array(
          'bg_color' => '#e74c3c', 'text_color' => '#ffffff', 'border_color' => '#e74c3c',
          'hover_bg_color' => '#c0392b', 'hover_text_color' => '#ffffff', 'hover_border_color' => '#c0392b',
          'disabled_bg_color' => '#c0392b', 'disabled_text_color' => '#ffffff',
          'font_size' => 16, 'padding_vertical' => 16, 'padding_horizontal' => 24,
          'border_radius' => 8, 'border_width' => 0, 'font_weight' => '600', 'font_family' => '',
          'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
        ),
        'bill' => array(
          'bg_color' => '#27ae60', 'text_color' => '#ffffff', 'border_color' => '#27ae60',
          'hover_bg_color' => '#1e8449', 'hover_text_color' => '#ffffff', 'hover_border_color' => '#1e8449',
          'disabled_bg_color' => '#1e8449', 'disabled_text_color' => '#ffffff',
          'font_size' => 16, 'padding_vertical' => 16, 'padding_horizontal' => 24,
          'border_radius' => 8, 'border_width' => 0, 'font_weight' => '600', 'font_family' => '',
          'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
        ),
        'view_bill' => array(
          'bg_color' => '#9b59b6', 'text_color' => '#ffffff', 'border_color' => '#9b59b6',
          'hover_bg_color' => '#8e44ad', 'hover_text_color' => '#ffffff', 'hover_border_color' => '#8e44ad',
          'font_size' => 14, 'padding_vertical' => 12, 'padding_horizontal' => 16,
          'border_radius' => 8, 'border_width' => 0, 'font_weight' => '600', 'font_family' => '',
          'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
        ),
        'table_badge' => array(
          'bg_color' => '#3498db', 'text_color' => '#ffffff', 'border_color' => '#3498db',
          'hover_bg_color' => '#2980b9', 'hover_text_color' => '#ffffff', 'hover_border_color' => '#2980b9',
          'font_size' => 14, 'padding_vertical' => 12, 'padding_horizontal' => 16,
          'border_radius' => 8, 'border_width' => 0, 'font_weight' => '600', 'font_family' => '',
          'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
        ),
      );

      // Merge with saved values for each button type
      foreach ($help_defaults as $type => $defaults) {
        if (!isset($all_help_styles[$type])) $all_help_styles[$type] = array();
        $all_help_styles[$type] = array_merge($defaults, $all_help_styles[$type]);
      }

      // Generate individual CSS for each button type
      ?>
<style id="smdp-custom-help-button-styles">
/* Custom Action Button Styles from Menu App Builder - Individual Styling */

/* Request Help Button */
.smdp-help-btn {
  background-color: <?php echo esc_attr($all_help_styles['help']['bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['help']['text_color']); ?> !important;
  border: <?php echo esc_attr($all_help_styles['help']['border_width']); ?>px solid <?php echo esc_attr($all_help_styles['help']['border_color']); ?> !important;
  font-size: <?php echo esc_attr($all_help_styles['help']['font_size']); ?>px !important;
  padding: <?php echo esc_attr($all_help_styles['help']['padding_vertical']); ?>px <?php echo esc_attr($all_help_styles['help']['padding_horizontal']); ?>px !important;
  border-radius: <?php echo esc_attr($all_help_styles['help']['border_radius']); ?>px !important;
  font-weight: <?php echo esc_attr($all_help_styles['help']['font_weight']); ?> !important;
  box-shadow: <?php echo esc_attr($all_help_styles['help']['box_shadow']); ?> !important;
  <?php if (!empty($all_help_styles['help']['font_family'])): ?>font-family: <?php echo esc_attr($all_help_styles['help']['font_family']); ?> !important;<?php endif; ?>
}
.smdp-help-btn:hover {
  background-color: <?php echo esc_attr($all_help_styles['help']['hover_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['help']['hover_text_color']); ?> !important;
  border-color: <?php echo esc_attr($all_help_styles['help']['hover_border_color']); ?> !important;
}
.smdp-help-btn.smdp-btn-disabled,
.smdp-help-btn:disabled {
  background-color: <?php echo esc_attr($all_help_styles['help']['disabled_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['help']['disabled_text_color']); ?> !important;
}

/* Request Bill Button */
.smdp-bill-btn {
  background-color: <?php echo esc_attr($all_help_styles['bill']['bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['bill']['text_color']); ?> !important;
  border: <?php echo esc_attr($all_help_styles['bill']['border_width']); ?>px solid <?php echo esc_attr($all_help_styles['bill']['border_color']); ?> !important;
  font-size: <?php echo esc_attr($all_help_styles['bill']['font_size']); ?>px !important;
  padding: <?php echo esc_attr($all_help_styles['bill']['padding_vertical']); ?>px <?php echo esc_attr($all_help_styles['bill']['padding_horizontal']); ?>px !important;
  border-radius: <?php echo esc_attr($all_help_styles['bill']['border_radius']); ?>px !important;
  font-weight: <?php echo esc_attr($all_help_styles['bill']['font_weight']); ?> !important;
  box-shadow: <?php echo esc_attr($all_help_styles['bill']['box_shadow']); ?> !important;
  <?php if (!empty($all_help_styles['bill']['font_family'])): ?>font-family: <?php echo esc_attr($all_help_styles['bill']['font_family']); ?> !important;<?php endif; ?>
}
.smdp-bill-btn:hover {
  background-color: <?php echo esc_attr($all_help_styles['bill']['hover_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['bill']['hover_text_color']); ?> !important;
  border-color: <?php echo esc_attr($all_help_styles['bill']['hover_border_color']); ?> !important;
}
.smdp-bill-btn.smdp-bill-disabled,
.smdp-bill-btn:disabled {
  background-color: <?php echo esc_attr($all_help_styles['bill']['disabled_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['bill']['disabled_text_color']); ?> !important;
}

/* Disabled state animation */
.smdp-help-btn.smdp-btn-disabled,
.smdp-help-btn:disabled,
.smdp-bill-btn.smdp-bill-disabled,
.smdp-bill-btn:disabled {
  animation: smdp-disabled-pulse 2s ease-in-out infinite !important;
}

@keyframes smdp-disabled-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

/* View Bill Button */
.smdp-view-bill-btn {
  background-color: <?php echo esc_attr($all_help_styles['view_bill']['bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['view_bill']['text_color']); ?> !important;
  border: <?php echo esc_attr($all_help_styles['view_bill']['border_width']); ?>px solid <?php echo esc_attr($all_help_styles['view_bill']['border_color']); ?> !important;
  font-size: <?php echo esc_attr($all_help_styles['view_bill']['font_size']); ?>px !important;
  padding: <?php echo esc_attr($all_help_styles['view_bill']['padding_vertical']); ?>px <?php echo esc_attr($all_help_styles['view_bill']['padding_horizontal']); ?>px !important;
  border-radius: <?php echo esc_attr($all_help_styles['view_bill']['border_radius']); ?>px !important;
  font-weight: <?php echo esc_attr($all_help_styles['view_bill']['font_weight']); ?> !important;
  box-shadow: <?php echo esc_attr($all_help_styles['view_bill']['box_shadow']); ?> !important;
  <?php if (!empty($all_help_styles['view_bill']['font_family'])): ?>font-family: <?php echo esc_attr($all_help_styles['view_bill']['font_family']); ?> !important;<?php endif; ?>
}
.smdp-view-bill-btn:hover {
  background-color: <?php echo esc_attr($all_help_styles['view_bill']['hover_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['view_bill']['hover_text_color']); ?> !important;
  border-color: <?php echo esc_attr($all_help_styles['view_bill']['hover_border_color']); ?> !important;
}

/* Table Badge */
#smdp-table-badge {
  background-color: <?php echo esc_attr($all_help_styles['table_badge']['bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['table_badge']['text_color']); ?> !important;
  border: <?php echo esc_attr($all_help_styles['table_badge']['border_width']); ?>px solid <?php echo esc_attr($all_help_styles['table_badge']['border_color']); ?> !important;
  font-size: <?php echo esc_attr($all_help_styles['table_badge']['font_size']); ?>px !important;
  padding: <?php echo esc_attr($all_help_styles['table_badge']['padding_vertical']); ?>px <?php echo esc_attr($all_help_styles['table_badge']['padding_horizontal']); ?>px !important;
  border-radius: <?php echo esc_attr($all_help_styles['table_badge']['border_radius']); ?>px !important;
  font-weight: <?php echo esc_attr($all_help_styles['table_badge']['font_weight']); ?> !important;
  box-shadow: <?php echo esc_attr($all_help_styles['table_badge']['box_shadow']); ?> !important;
  <?php if (!empty($all_help_styles['table_badge']['font_family'])): ?>font-family: <?php echo esc_attr($all_help_styles['table_badge']['font_family']); ?> !important;<?php endif; ?>
}
#smdp-table-badge:hover {
  background-color: <?php echo esc_attr($all_help_styles['table_badge']['hover_bg_color']); ?> !important;
  color: <?php echo esc_attr($all_help_styles['table_badge']['hover_text_color']); ?> !important;
  border-color: <?php echo esc_attr($all_help_styles['table_badge']['hover_border_color']); ?> !important;
}
</style>
<?php
    }

    // Get saved background colors
    $bg_colors = get_option(self::OPT_BG_COLORS, array());

    // If custom background colors are saved, output them
    if (!empty($bg_colors)) {
      // Default values
      $bg_defaults = array(
        'main_bg' => '#ffffff',
        'category_bar_bg' => '#ffffff',
        'content_area_bg' => '#ffffff',
        'item_card_bg' => '#ffffff',
      );

      $bg_colors = array_merge($bg_defaults, $bg_colors);

      // Generate CSS for background colors
      ?>
<style id="smdp-custom-background-colors">
/* Custom Background Colors from Menu App Builder */

/* Main container background */
.smdp-menu-app-fe {
  background-color: <?php echo esc_attr($bg_colors['main_bg']); ?> !important;
}

/* Category bar background - includes header wrapper and bar itself */
.smdp-app-header,
.smdp-cat-bar {
  background-color: <?php echo esc_attr($bg_colors['category_bar_bg']); ?> !important;
}

/* Content area background */
.smdp-app-sections {
  background-color: <?php echo esc_attr($bg_colors['content_area_bg']); ?> !important;
}
</style>
<?php
    }

    // Get saved Item Card styles
    $item_card_styles = get_option(self::OPT_ITEM_CARD_STYLES, array());

    if (!empty($item_card_styles)) {
      // Default values
      $defaults = array(
        'bg_color' => '#ffffff',
        'text_color' => '#000000',
        'border_color' => '#eeeeee',
        'border_width' => 1,
        'border_radius' => 0,
        'padding' => 8,
        'title_color' => '#000000',
        'title_size' => 19,
        'title_weight' => 'bold',
        'price_color' => '#000000',
        'price_size' => 16,
        'price_weight' => 'bold',
        'desc_color' => '#666666',
        'desc_size' => 14,
        'box_shadow' => '0 2px 4px rgba(0,0,0,0.1)',
      );

      $item_card_styles = array_merge($defaults, $item_card_styles);

      // Generate CSS for item cards
      ?>
<style id="smdp-custom-item-card-styles">
/* Custom Item Card Styles from Menu App Builder */

/* Item card container */
.smdp-menu-item,
.smdp-item-tile {
  background-color: <?php echo esc_attr($item_card_styles['bg_color']); ?> !important;
  border: <?php echo esc_attr($item_card_styles['border_width']); ?>px solid <?php echo esc_attr($item_card_styles['border_color']); ?> !important;
  border-radius: <?php echo esc_attr($item_card_styles['border_radius']); ?>px !important;
  padding: <?php echo esc_attr($item_card_styles['padding']); ?>px !important;
  box-shadow: <?php echo esc_attr($item_card_styles['box_shadow']); ?> !important;
}

/* Item title */
.smdp-menu-item h3,
.smdp-item-tile h3 {
  color: <?php echo esc_attr($item_card_styles['title_color']); ?> !important;
  font-size: <?php echo esc_attr($item_card_styles['title_size']); ?>px !important;
  font-weight: <?php echo esc_attr($item_card_styles['title_weight']); ?> !important;
}

/* Item description */
.smdp-menu-item p:not(:has(strong)),
.smdp-item-tile p:not(:has(strong)) {
  color: <?php echo esc_attr($item_card_styles['desc_color']); ?> !important;
  font-size: <?php echo esc_attr($item_card_styles['desc_size']); ?>px !important;
}

/* Item price */
.smdp-menu-item p strong,
.smdp-item-tile p strong,
.smdp-menu-item p:has(strong),
.smdp-item-tile p:has(strong) {
  color: <?php echo esc_attr($item_card_styles['price_color']); ?> !important;
  font-size: <?php echo esc_attr($item_card_styles['price_size']); ?>px !important;
  font-weight: <?php echo esc_attr($item_card_styles['price_weight']); ?> !important;
}
</style>
<?php
    }

    // Get saved Item Detail styles
    $item_detail_styles = get_option(self::OPT_ITEM_DETAIL_STYLES, array());

    if (!empty($item_detail_styles)) {
      // Default values
      $defaults = array(
        'modal_bg' => '#ffffff',
        'modal_border_color' => '#3498db',
        'modal_border_width' => 6,
        'modal_border_radius' => 12,
        'modal_box_shadow' => '0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)',
        'title_color' => '#000000',
        'title_size' => 24,
        'title_weight' => 'bold',
        'price_color' => '#27ae60',
        'price_size' => 19,
        'price_weight' => 'bold',
        'desc_color' => '#666666',
        'desc_size' => 16,
        'close_btn_bg' => '#3498db',
        'close_btn_text' => '#ffffff',
        'close_btn_hover_bg' => '#2980b9',
      );

      $item_detail_styles = array_merge($defaults, $item_detail_styles);

      // Generate CSS for item detail modal
      ?>
<style id="smdp-custom-item-detail-styles">
/* Custom Item Detail Modal Styles from Menu App Builder */

/* Modal container */
#smdp-item-modal .smdp-item-modal-inner {
  background-color: <?php echo esc_attr($item_detail_styles['modal_bg']); ?> !important;
  border: <?php echo esc_attr($item_detail_styles['modal_border_width']); ?>px solid <?php echo esc_attr($item_detail_styles['modal_border_color']); ?> !important;
  border-radius: <?php echo esc_attr($item_detail_styles['modal_border_radius']); ?>px !important;
  box-shadow: <?php echo esc_attr($item_detail_styles['modal_box_shadow']); ?> !important;
}

/* Item title in modal */
#smdp-item-modal #smdp-item-name {
  color: <?php echo esc_attr($item_detail_styles['title_color']); ?> !important;
  font-size: <?php echo esc_attr($item_detail_styles['title_size']); ?>px !important;
  font-weight: <?php echo esc_attr($item_detail_styles['title_weight']); ?> !important;
}

/* Item price in modal */
#smdp-item-modal #smdp-item-price {
  color: <?php echo esc_attr($item_detail_styles['price_color']); ?> !important;
  font-size: <?php echo esc_attr($item_detail_styles['price_size']); ?>px !important;
  font-weight: <?php echo esc_attr($item_detail_styles['price_weight']); ?> !important;
}

/* Item description in modal */
#smdp-item-modal #smdp-item-desc {
  color: <?php echo esc_attr($item_detail_styles['desc_color']); ?> !important;
  font-size: <?php echo esc_attr($item_detail_styles['desc_size']); ?>px !important;
}

/* Close button */
#smdp-item-modal #smdp-item-close {
  background-color: <?php echo esc_attr($item_detail_styles['close_btn_bg']); ?> !important;
  color: <?php echo esc_attr($item_detail_styles['close_btn_text']); ?> !important;
}

#smdp-item-modal #smdp-item-close:hover {
  background-color: <?php echo esc_attr($item_detail_styles['close_btn_hover_bg']); ?> !important;
}
</style>
<?php
    }
  }

  public static function render_admin_page() {
    ?>
    <div class="wrap smdp-menu-app">
      <h1>Menu App</h1>
      <p>Create an app-like, tablet-friendly menu layout. <strong>Categories and items come from your Menu Editor</strong>. Edit there to see changes live.</p>

      <div style="margin:10px 0 18px;">
        <button type="button" class="button" id="smdp-bootstrap-btn">Rebuild Catalog Cache (from Menu Editor)</button>
        <span id="smdp-bootstrap-status" style="margin-left:8px;"></span>
      </div>

      <h2 class="nav-tab-wrapper">
        <a href="#tab-main" class="nav-tab nav-tab-active">Main</a>
        <a href="#tab-promo" class="nav-tab">Promo Screen</a>
        <a href="#tab-pwa" class="nav-tab">PWA</a>
        <a href="#tab-advanced" class="nav-tab">Advanced</a>
      </h2>

      <!-- Tab: Main -->
      <div id="tab-main" class="smdp-tab active">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">App Layout</h2>

        <form method="post" action="options.php">
          <?php settings_fields('smdp_menu_app_layout_group'); ?>
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
      </div>

      <!-- Tab: Promo Screen -->
      <div id="tab-promo" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">Promo Screen Settings</h2>
        <form method="post" action="options.php">
          <?php settings_fields('smdp_menu_app_layout_group'); ?>
          <?php self::field_promo_settings(); ?>
          <?php submit_button('Save Promo Settings'); ?>
        </form>
        </div>
      </div>

      <!-- Tab: PWA -->
      <div id="tab-pwa" class="smdp-tab">
        <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
        <h2 style="margin-top:0;">Progressive Web App Settings</h2>

        <?php
        // Get all settings needed for PWA tab
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

      <!-- Items and Categories tabs moved to Menu Management → Menu Editor -->
      <!-- Styles tab moved to separate "Styles & Customization" page -->

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

  public static function render_styles_page() {
    ?>
    <div class="wrap">
      <h1>Styles & Customization</h1>
      <p>Customize colors, fonts, and appearance of the menu app.</p>

      <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">

        <!-- Subtabs for Styles -->
        <div class="smdp-style-subtabs" style="margin:20px 0; border-bottom:1px solid #ccc;">
          <a href="#style-generator" class="smdp-style-subtab active" style="display:inline-block; padding:10px 20px; text-decoration:none; border-bottom:2px solid #2271b1; margin-bottom:-1px;">Style Generator</a>
          <a href="#style-category-buttons" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Category Buttons</a>
          <a href="#style-help-buttons" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Help Buttons</a>
          <a href="#style-background" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Background Colors</a>
          <a href="#style-item-cards" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Item Cards</a>
          <a href="#style-item-detail" class="smdp-style-subtab" style="display:inline-block; padding:10px 20px; text-decoration:none; color:#666;">Item Detail</a>
        </div>

        <!-- Subtab: Style Generator -->
        <div id="style-generator" class="smdp-style-subtab-content active" style="margin-top:20px;">
          <?php
          // Load saved style generator preferences
          $generator_prefs = get_option(self::OPT_STYLE_GENERATOR, array());
          $theme_mode = !empty($generator_prefs['theme_mode']) ? $generator_prefs['theme_mode'] : 'light';
          $button_shape = !empty($generator_prefs['button_shape']) ? $generator_prefs['button_shape'] : 'pill';
          $primary_color = !empty($generator_prefs['primary_color']) ? $generator_prefs['primary_color'] : '#0986bf';
          $secondary_color = !empty($generator_prefs['secondary_color']) ? $generator_prefs['secondary_color'] : '#27ae60';
          $accent_color = !empty($generator_prefs['accent_color']) ? $generator_prefs['accent_color'] : '#e74c3c';
          $include_help_buttons = !empty($generator_prefs['include_help_buttons']);
          ?>
          <h3>🎨 Smart Style Generator</h3>
          <p>Choose your brand colors and theme mode - we'll automatically generate a complete, cohesive design for all elements.</p>

          <div style="background:#fff; border:1px solid #ddd; border-left:4px solid #2271b1; padding:15px; margin:20px 0;">
            <h4 style="margin-top:0;">✨ What this does:</h4>
            <ul style="margin:10px 0; line-height:1.8;">
              <li>Applies your colors to <strong>all buttons, cards, modals, and backgrounds</strong></li>
              <li>Automatically adjusts <strong>text colors</strong> for perfect readability</li>
              <li>Generates <strong>hover, active, and disabled states</strong></li>
              <li>Creates <strong>lighter/darker shades</strong> where needed</li>
              <li>Optimizes for <strong>Light or Dark theme</strong></li>
            </ul>
          </div>

          <!-- Two Column Layout: Controls + Preview -->
          <div style="display: grid; grid-template-columns: 1fr 500px; gap: 20px; align-items: start;">
            <!-- Left Column: Form Controls -->
            <div class="smdp-style-controls">

          <!-- Theme Mode Selection -->
          <table class="form-table" style="margin-bottom:30px;">
            <tr>
              <th style="width:200px;">Theme Mode</th>
              <td>
                <label style="display:inline-block; margin-right:20px;">
                  <input type="radio" name="theme_mode" value="light" <?php checked($theme_mode, 'light'); ?>>
                  <span style="font-size:16px;">☀️ Light Mode</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">White backgrounds, dark text</span>
                </label>
                <label style="display:inline-block;">
                  <input type="radio" name="theme_mode" value="dark" <?php checked($theme_mode, 'dark'); ?>>
                  <span style="font-size:16px;">🌙 Dark Mode</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">Dark backgrounds, light text</span>
                </label>
              </td>
            </tr>
            <tr>
              <th>Button Shape</th>
              <td>
                <label style="display:inline-block; margin-right:20px;">
                  <input type="radio" name="button_shape" value="pill" <?php checked($button_shape, 'pill'); ?>>
                  <span style="font-size:16px;">💊 Pill</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">Fully rounded ends (500px radius)</span>
                </label>
                <label style="display:inline-block; margin-right:20px;">
                  <input type="radio" name="button_shape" value="rounded" <?php checked($button_shape, 'rounded'); ?>>
                  <span style="font-size:16px;">⬜ Rounded</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">Moderately rounded corners (12px radius)</span>
                </label>
                <label style="display:inline-block; margin-right:20px;">
                  <input type="radio" name="button_shape" value="slightly-rounded" <?php checked($button_shape, 'slightly-rounded'); ?>>
                  <span style="font-size:16px;">▢ Slightly Rounded</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">Subtle rounded corners (4px radius)</span>
                </label>
                <label style="display:inline-block;">
                  <input type="radio" name="button_shape" value="square" <?php checked($button_shape, 'square'); ?>>
                  <span style="font-size:16px;">◻️ Square</span>
                  <span style="color:#666; display:block; margin-left:24px; font-size:13px;">Sharp corners (0px radius)</span>
                </label>
                <p class="description">Applies to category buttons, action buttons, item cards, and item detail modal</p>
              </td>
            </tr>
            <tr>
              <th>Apply to Help Buttons</th>
              <td>
                <label>
                  <input type="checkbox" id="include_help_buttons" <?php checked($include_help_buttons); ?>>
                  <span>Include Help/Bill buttons in theme generation</span>
                </label>
                <p class="description">Check this to apply the theme colors to Request Help, Request Bill, View Bill, and Table Badge buttons. Leave unchecked to keep their custom colors.</p>
              </td>
            </tr>
          </table>

          <!-- Color Presets -->
          <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; margin:20px 0;">
            <h4 style="margin-top:0;">Quick Presets (Click to apply)</h4>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <button type="button" class="button preset-btn" data-primary="#0986bf" data-secondary="#27ae60" data-accent="#e74c3c">🔵 Blue, Green & Red (Default)</button>
              <button type="button" class="button preset-btn" data-primary="#9b59b6" data-secondary="#3498db" data-accent="#e74c3c">🟣 Purple, Blue & Red</button>
              <button type="button" class="button preset-btn" data-primary="#e67e22" data-secondary="#f39c12" data-accent="#27ae60">🟠 Orange, Yellow & Green</button>
              <button type="button" class="button preset-btn" data-primary="#1abc9c" data-secondary="#16a085" data-accent="#e91e63">🟦 Teal, Dark Teal & Pink</button>
              <button type="button" class="button preset-btn" data-primary="#3498db" data-secondary="#9b59b6" data-accent="#e74c3c">🔷 Blue, Purple & Red</button>
              <button type="button" class="button preset-btn" data-primary="#34495e" data-secondary="#7f8c8d" data-accent="#95a5a6">⚫ Monochrome Grays</button>
            </div>
          </div>

          <!-- Color Inputs -->
          <table class="form-table">
            <tr>
              <th style="width:200px;">Primary Brand Color</th>
              <td>
                <input type="text" id="brand_color_primary" value="<?php echo esc_attr($primary_color); ?>" class="smdp-color-picker" />
                <p class="description">Main brand color - Used for: Active category buttons, table badge, borders, highlights</p>
              </td>
            </tr>
            <tr>
              <th>Secondary Brand Color</th>
              <td>
                <input type="text" id="brand_color_secondary" value="<?php echo esc_attr($secondary_color); ?>" class="smdp-color-picker" />
                <button type="button" id="generate-analogous" class="button button-small" style="margin-left:10px;">🎨 Generate from Primary (Analogous)</button>
                <button type="button" id="generate-complementary" class="button button-small" style="margin-left:5px;">🎨 Generate Complementary</button>
                <p class="description">Secondary brand color - Used for: Bill button, price text, view bill button</p>
              </td>
            </tr>
            <tr>
              <th>Accent Color</th>
              <td>
                <input type="text" id="brand_color_accent" value="<?php echo esc_attr($accent_color); ?>" class="smdp-color-picker" />
                <button type="button" id="generate-accent-complementary" class="button button-small" style="margin-left:10px;">🎨 Generate Complementary</button>
                <p class="description">Accent color for alerts - Used for: Help button, urgent actions, warnings</p>
              </td>
            </tr>
          </table>

            </div>
            <!-- End Left Column -->

            <!-- Right Column: Live Preview (Sticky) -->
            <div style="position: sticky; top: 32px;">
          <div id="style-preview" style="padding:20px; background:#f9f9f9; border:1px solid #ddd; border-radius:8px;">
            <h3 style="margin-top:0;">Live Preview</h3>

            <!-- Preview container with background -->
            <div id="preview-container" style="background:#ffffff; padding:20px; border-radius:8px;">

              <!-- Category Buttons Preview -->
              <div style="margin-bottom:20px;">
                <p style="font-size:12px; color:#666; margin-bottom:10px; text-transform:uppercase; font-weight:600;">Category Buttons</p>
                <div style="display:flex; gap:8px; flex-direction:column;">
                  <button type="button" id="preview-cat-btn" style="padding:12px 30px; border-radius:500px; border:1px solid #01669c; background:#ffffff; color:#000; font-size:16px; cursor:pointer;">Appetizers</button>
                  <button type="button" id="preview-cat-btn-active" style="padding:12px 30px; border-radius:500px; border:1px solid #0986bf; background:#0986bf; color:#fff; font-size:16px; cursor:pointer; font-weight:normal;">Entrees</button>
                  <button type="button" style="padding:12px 30px; border-radius:500px; border:1px solid #01669c; background:#ffffff; color:#000; font-size:16px; cursor:pointer;">Desserts</button>
                </div>
              </div>

              <!-- Action Buttons Preview -->
              <div style="margin-bottom:20px;">
                <p style="font-size:12px; color:#666; margin-bottom:10px; text-transform:uppercase; font-weight:600;">Action Buttons</p>
                <div style="display:flex; gap:8px; flex-direction:column;">
                  <button type="button" id="preview-help-btn" style="padding:12px 20px; border-radius:8px; border:none; background:#e74c3c; color:#fff; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; box-shadow:0 3px 8px rgba(0,0,0,0.25);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;">
                      <circle cx="12" cy="12" r="10"></circle>
                      <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                      <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span>Request Help</span>
                  </button>
                  <button type="button" id="preview-bill-btn" style="padding:12px 20px; border-radius:8px; border:none; background:#27ae60; color:#fff; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; box-shadow:0 3px 8px rgba(0,0,0,0.25);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; vertical-align:middle;">
                      <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                      <line x1="2" y1="10" x2="22" y2="10"></line>
                    </svg>
                    <span>Request Bill</span>
                  </button>
                  <button type="button" id="preview-view-bill-btn" style="padding:10px 16px; border-radius:8px; border:none; background:#9b59b6; color:#fff; font-size:13px; font-weight:600; cursor:pointer; box-shadow:0 3px 8px rgba(0,0,0,0.25);">View Bill</button>
                </div>
              </div>

              <!-- Item Cards Preview -->
              <div style="margin-bottom:20px;">
                <p style="font-size:12px; color:#666; margin-bottom:10px; text-transform:uppercase; font-weight:600;">Menu Item Cards</p>
                <div style="display:flex; flex-direction:column; gap:12px;">
                  <div id="preview-item-card-1" style="padding:15px; background:#fff; border:1px solid #eee; border-radius:0; cursor:pointer;">
                    <div id="preview-card-title" style="font-weight:bold; font-size:16px; color:#000; margin-bottom:5px;">Margherita Pizza</div>
                    <div id="preview-card-price" style="font-weight:bold; font-size:14px; color:#000; margin-bottom:8px;">$12.99</div>
                    <div id="preview-card-desc" style="font-size:13px; color:#666; line-height:1.5;">Fresh mozzarella, basil, and tomato sauce</div>
                  </div>
                  <div id="preview-item-card-2" style="padding:15px; background:#fff; border:1px solid #eee; border-radius:0; cursor:pointer;">
                    <div style="font-weight:bold; font-size:16px; color:#000; margin-bottom:5px;">Caesar Salad</div>
                    <div style="font-weight:bold; font-size:14px; color:#000; margin-bottom:8px;">$8.99</div>
                    <div style="font-size:13px; color:#666; line-height:1.5;">Romaine, parmesan, croutons</div>
                  </div>
                </div>
              </div>

              <!-- Item Detail Modal Preview -->
              <div>
                <p style="font-size:12px; color:#666; margin-bottom:10px; text-transform:uppercase; font-weight:600;">Item Detail Modal</p>
                <div id="preview-modal" style="background:#ffffff; border:5px solid #3498db; border-radius:12px; padding:20px; box-shadow:0 0 25px rgba(52,152,219,0.5);">
                  <div id="preview-modal-title" style="font-size:20px; font-weight:bold; color:#000; margin-bottom:8px;">Margherita Pizza</div>
                  <div id="preview-modal-price" style="font-size:17px; font-weight:bold; color:#27ae60; margin-bottom:12px;">$12.99</div>
                  <div id="preview-modal-desc" style="font-size:14px; color:#666; line-height:1.6; margin-bottom:15px;">Fresh mozzarella cheese, fresh basil leaves, and homemade tomato sauce.</div>
                  <button type="button" id="preview-close-btn" style="margin-top:15px; padding:10px 20px; background:#3498db; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:14px; width:100%; display:block;">Close</button>
                </div>
              </div>

            </div>
          </div>
            </div>
            <!-- End Right Column -->

          </div>
          <!-- End Grid Layout -->

          <!-- Generate Button (Full Width Below Grid) -->
          <div style="background:#fff; border:1px solid #ddd; border-left:4px solid #00a32a; padding:15px; margin:20px 0;">
            <button type="button" id="generate-styles" class="button button-primary button-hero" style="margin-bottom:10px;">
              🎨 Generate & Apply Complete Theme
            </button>
            <p style="margin:0; color:#666; font-size:13px;">This will update ALL style settings instantly. You can fine-tune individual elements afterward in their respective tabs.</p>
          </div>

          <!-- Reset All Styles Button -->
          <div style="background:#fff; border:1px solid #ddd; border-left:4px solid #dc3232; padding:15px; margin:20px 0;">
            <button type="button" id="reset-all-styles" class="button button-secondary button-large" style="color:#dc3232; border-color:#dc3232;">
              🔄 Reset All Styles to Plugin Defaults
            </button>
            <p style="margin:10px 0 0 0; color:#666; font-size:13px;">This will delete ALL custom styles across ALL tabs and restore the plugin's default appearance. This cannot be undone!</p>
          </div>

          <script>
          jQuery(document).ready(function($) {
            // Initialize custom color pickers
            if (typeof window.initColorPickers === 'function') {
              setTimeout(window.initColorPickers, 100);
            }

            // Listen for custom color picker changes
            $(document).on('smdp-color-changed', function() {
              updateStylePreview();
            });

            // Preset buttons
            $('.preset-btn').on('click', function() {
              var primary = $(this).data('primary');
              var secondary = $(this).data('secondary');
              var accent = $(this).data('accent');
              $('#brand_color_primary').val(primary).trigger('change');
              $('#brand_color_secondary').val(secondary).trigger('change');
              $('#brand_color_accent').val(accent).trigger('change');
              updateStylePreview();
            });

            // Theme mode change
            $('input[name="theme_mode"]').on('change', function() {
              updateStylePreview();
            });

            // Button shape change
            $('input[name="button_shape"]').on('change', function() {
              updateStylePreview();
            });

            // === COLOR THEORY FUNCTIONS ===

            // Convert HEX to HSL
            function hexToHSL(hex) {
              var r = parseInt(hex.substr(1,2), 16) / 255;
              var g = parseInt(hex.substr(3,2), 16) / 255;
              var b = parseInt(hex.substr(5,2), 16) / 255;

              var max = Math.max(r, g, b);
              var min = Math.min(r, g, b);
              var h, s, l = (max + min) / 2;

              if (max === min) {
                h = s = 0;
              } else {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                  case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                  case g: h = ((b - r) / d + 2) / 6; break;
                  case b: h = ((r - g) / d + 4) / 6; break;
                }
              }

              return { h: h * 360, s: s * 100, l: l * 100 };
            }

            // Convert HSL to HEX
            function hslToHex(h, s, l) {
              h = h / 360;
              s = s / 100;
              l = l / 100;

              var r, g, b;
              if (s === 0) {
                r = g = b = l;
              } else {
                var hue2rgb = function(p, q, t) {
                  if (t < 0) t += 1;
                  if (t > 1) t -= 1;
                  if (t < 1/6) return p + (q - p) * 6 * t;
                  if (t < 1/2) return q;
                  if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                  return p;
                };
                var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                var p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
              }

              var toHex = function(x) {
                var hex = Math.round(x * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
              };

              return '#' + toHex(r) + toHex(g) + toHex(b);
            }

            // Generate analogous color (30 degrees on color wheel)
            function getAnalogousColor(hex) {
              var hsl = hexToHSL(hex);
              var newHue = (hsl.h + 30) % 360;
              return hslToHex(newHue, hsl.s, hsl.l);
            }

            // Generate complementary color (180 degrees on color wheel)
            function getComplementaryColor(hex) {
              var hsl = hexToHSL(hex);
              var newHue = (hsl.h + 180) % 360;
              return hslToHex(newHue, hsl.s, hsl.l);
            }

            // Generate split-complementary color
            function getSplitComplementaryColor(hex, offset) {
              var hsl = hexToHSL(hex);
              var newHue = (hsl.h + 180 + offset) % 360;
              return hslToHex(newHue, hsl.s, hsl.l);
            }

            // Adjust brightness (lightness in HSL)
            function adjustBrightness(hex, percent) {
              var hsl = hexToHSL(hex);
              var newL = Math.max(0, Math.min(100, hsl.l + percent));
              return hslToHex(hsl.h, hsl.s, newL);
            }

            // Simple brightness adjustment for backwards compatibility
            function adjustColor(color, percent) {
              return adjustBrightness(color, percent);
            }

            function getContrastColor(hexcolor) {
              if (!hexcolor) return '#000000';
              var r = parseInt(hexcolor.substr(1,2), 16);
              var g = parseInt(hexcolor.substr(3,2), 16);
              var b = parseInt(hexcolor.substr(5,2), 16);
              var yiq = ((r*299)+(g*587)+(b*114))/1000;
              return (yiq >= 128) ? '#000000' : '#ffffff';
            }

            // Color generation buttons
            $('#generate-analogous').on('click', function() {
              var primary = $('#brand_color_primary').val();
              var analogous = getAnalogousColor(primary);
              $('#brand_color_secondary').val(analogous).trigger('change');
              updateStylePreview();
            });

            $('#generate-complementary').on('click', function() {
              var primary = $('#brand_color_primary').val();
              var complementary = getComplementaryColor(primary);
              $('#brand_color_secondary').val(complementary).trigger('change');
              updateStylePreview();
            });

            $('#generate-accent-complementary').on('click', function() {
              var primary = $('#brand_color_primary').val();
              var complementary = getComplementaryColor(primary);
              $('#brand_color_accent').val(complementary).trigger('change');
              updateStylePreview();
            });

            // Update preview in real-time
            function updateStylePreview() {
              var primary = $('#brand_color_primary').val() || '#0986bf';
              var secondary = $('#brand_color_secondary').val() || '#27ae60';
              var accent = $('#brand_color_accent').val() || '#e74c3c';
              var isDark = $('input[name="theme_mode"]:checked').val() === 'dark';
              var buttonShape = $('input[name="button_shape"]:checked').val() || 'pill';

              // Button shape radius values
              var radiusMap = {
                'pill': { category: 500, action: 500, card: 12, modal: 12 },
                'rounded': { category: 12, action: 12, card: 8, modal: 12 },
                'slightly-rounded': { category: 4, action: 4, card: 4, modal: 4 },
                'square': { category: 0, action: 0, card: 0, modal: 0 }
              };
              var radius = radiusMap[buttonShape];

              var primaryDark = adjustBrightness(primary, -15);
              var secondaryDark = adjustBrightness(secondary, -15);
              var accentDark = adjustBrightness(accent, -15);
              var primaryText = getContrastColor(primary);
              var secondaryText = getContrastColor(secondary);
              var accentText = getContrastColor(accent);

              // Theme-based colors
              var bgMain = isDark ? '#1a1a1a' : '#ffffff';
              var bgContent = isDark ? '#2d2d2d' : '#ffffff';
              var bgCard = isDark ? '#3a3a3a' : '#ffffff';
              var textPrimary = isDark ? '#ffffff' : '#000000';
              var textSecondary = isDark ? '#b0b0b0' : '#666666';
              var borderColor = isDark ? '#4a4a4a' : '#eeeeee';
              var catBtnBorder = isDark ? primary : adjustColor(primary, -10);

              // Apply to preview container
              $('#preview-container').css('background', bgContent);

              // Category buttons
              $('#preview-cat-btn, #preview-cat-btn + button + button').css({
                'background': isDark ? '#2d2d2d' : '#ffffff',
                'color': textPrimary,
                'border-color': catBtnBorder,
                'border-radius': radius.category + 'px'
              });
              $('#preview-cat-btn-active').css({
                'background': primary,
                'color': primaryText,
                'border-color': primary,
                'border-radius': radius.category + 'px'
              });

              // Action buttons (using all 3 colors intelligently)
              $('#preview-help-btn').css({
                'background': accent,
                'color': accentText,
                'border-radius': radius.action + 'px'
              });
              $('#preview-bill-btn').css({
                'background': secondary,
                'color': secondaryText,
                'border-radius': radius.action + 'px'
              });
              $('#preview-view-bill-btn').css({
                'background': secondaryDark,
                'color': getContrastColor(secondaryDark),
                'border-radius': radius.action + 'px'
              });

              // Item cards
              $('#preview-item-card-1, #preview-item-card-2').css({
                'background': bgCard,
                'border-color': borderColor,
                'border-radius': radius.card + 'px'
              });
              $('#preview-card-title, #preview-modal-title').css('color', textPrimary);
              $('#preview-card-price').css('color', secondary);
              $('#preview-card-desc, #preview-modal-desc').css('color', textSecondary);

              // Modal
              $('#preview-modal').css({
                'background': bgCard,
                'border-color': primary,
                'box-shadow': '0 0 30px ' + primary + '99, 0 0 60px ' + primary + '66',
                'border-radius': radius.modal + 'px'
              });
              $('#preview-modal-price').css('color', secondary);
              $('#preview-close-btn').css({
                'background': primary,
                'color': primaryText,
                'border-radius': radius.action + 'px'
              });
            }

            // Generate and apply all styles
            $('#generate-styles').on('click', function() {
              var primary = $('#brand_color_primary').val() || '#0986bf';
              var secondary = $('#brand_color_secondary').val() || '#27ae60';
              var accent = $('#brand_color_accent').val() || '#e74c3c';
              var isDark = $('input[name="theme_mode"]:checked').val() === 'dark';
              var includeHelpButtons = $('#include_help_buttons').is(':checked');
              var buttonShape = $('input[name="button_shape"]:checked').val() || 'pill';

              // Button shape radius values
              var radiusMap = {
                'pill': { category: 500, action: 8, card: 0, modal: 12 },
                'rounded': { category: 12, action: 8, card: 0, modal: 12 },
                'slightly-rounded': { category: 4, action: 4, card: 0, modal: 4 },
                'square': { category: 0, action: 0, card: 0, modal: 0 }
              };
              var radius = radiusMap[buttonShape];

              if (!confirm('This will completely regenerate ALL styles for buttons, cards, modals, backgrounds, and text colors based on your selections. Continue?')) {
                return;
              }

              var primaryDark = adjustBrightness(primary, -15);
              var secondaryDark = adjustBrightness(secondary, -15);
              var accentDark = adjustBrightness(accent, -15);
              var primaryText = getContrastColor(primary);
              var secondaryText = getContrastColor(secondary);
              var accentText = getContrastColor(accent);

              // Theme-based colors
              var bgMain = isDark ? '#1a1a1a' : '#ffffff';
              var bgCategoryBar = isDark ? '#2d2d2d' : '#ffffff';
              var bgContent = isDark ? '#1a1a1a' : '#ffffff';
              var bgCard = isDark ? '#3a3a3a' : '#ffffff';
              var textPrimary = isDark ? '#ffffff' : '#000000';
              var textSecondary = isDark ? '#b0b0b0' : '#666666';
              var borderColor = isDark ? '#4a4a4a' : '#eeeeee';
              var catBtnBg = isDark ? '#2d2d2d' : '#ffffff';
              var catBtnBorder = isDark ? primary : adjustColor(primary, -10);

              // === BOX SHADOWS ===
              var boxShadowActionButtons = '0 4px 10px rgba(0,0,0,0.3)';
              var boxShadowCards = '0 2px 4px rgba(0,0,0,0.1)';
              var boxShadowModal = '0 0 30px ' + primary.replace('#', 'rgba(') + ', 0.6), 0 0 60px ' + primary.replace('#', 'rgba(') + ', 0.4)';

              // Helper to convert hex to rgba for box shadow
              function hexToRgba(hex, opacity) {
                var r = parseInt(hex.substr(1,2), 16);
                var g = parseInt(hex.substr(3,2), 16);
                var b = parseInt(hex.substr(5,2), 16);
                return 'rgba(' + r + ',' + g + ',' + b + ',' + opacity + ')';
              }

              boxShadowModal = '0 0 30px ' + hexToRgba(primary, 0.6) + ', 0 0 60px ' + hexToRgba(primary, 0.4);

              // === CATEGORY BUTTONS ===
              $('input[name="<?php echo self::OPT_STYLES; ?>[bg_color]"]').val(catBtnBg).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[text_color]"]').val(textPrimary).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[border_color]"]').val(catBtnBorder).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[active_bg_color]"]').val(primary).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[active_text_color]"]').val(primaryText).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[active_border_color]"]').val(primary).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[border_radius]"]').val(radius.category).trigger('change');
              $('input[name="<?php echo self::OPT_STYLES; ?>[box_shadow]"]').val('none').trigger('change');

              // === HELP BUTTONS === (only if toggle is checked)
              if (includeHelpButtons) {
                // Help button - uses accent color (red for urgency/attention)
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][bg_color]"]').val(accent).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][text_color]"]').val(accentText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][hover_bg_color]"]').val(accentDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][hover_text_color]"]').val(accentText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][disabled_bg_color]"]').val(accentDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][disabled_text_color]"]').val(accentText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][border_radius]"]').val(radius.action).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[help][box_shadow]"]').val(boxShadowActionButtons).trigger('change');

                // Bill button - uses secondary color (green for money/payment)
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][bg_color]"]').val(secondary).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][text_color]"]').val(secondaryText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][hover_bg_color]"]').val(secondaryDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][hover_text_color]"]').val(secondaryText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][disabled_bg_color]"]').val(secondaryDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][disabled_text_color]"]').val(secondaryText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][border_radius]"]').val(radius.action).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[bill][box_shadow]"]').val(boxShadowActionButtons).trigger('change');

                // View Bill button - uses darker secondary
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[view_bill][bg_color]"]').val(secondaryDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[view_bill][text_color]"]').val(getContrastColor(secondaryDark)).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[view_bill][hover_bg_color]"]').val(adjustBrightness(secondaryDark, -10)).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[view_bill][border_radius]"]').val(radius.action).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[view_bill][box_shadow]"]').val(boxShadowActionButtons).trigger('change');

                // Table Badge - uses primary brand color
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][bg_color]"]').val(primary).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][text_color]"]').val(primaryText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][hover_bg_color]"]').val(primaryDark).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][hover_text_color]"]').val(primaryText).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][border_radius]"]').val(radius.action).trigger('change');
                $('input[name="<?php echo self::OPT_HELP_BTN_STYLES; ?>[table_badge][box_shadow]"]').val(boxShadowActionButtons).trigger('change');
              }

              // === BACKGROUND COLORS ===
              $('input[name="<?php echo self::OPT_BG_COLORS; ?>[main_bg]"]').val(bgMain).trigger('change');
              $('input[name="<?php echo self::OPT_BG_COLORS; ?>[category_bar_bg]"]').val(bgCategoryBar).trigger('change');
              $('input[name="<?php echo self::OPT_BG_COLORS; ?>[content_area_bg]"]').val(bgContent).trigger('change');

              // === ITEM CARDS ===
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[bg_color]"]').val(bgCard).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[border_color]"]').val(borderColor).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[title_color]"]').val(textPrimary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[price_color]"]').val(secondary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[desc_color]"]').val(textSecondary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[border_radius]"]').val(radius.card).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_CARD_STYLES; ?>[box_shadow]"]').val(boxShadowCards).trigger('change');

              // === ITEM DETAIL MODAL ===
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[modal_bg]"]').val(bgCard).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[modal_border_color]"]').val(primary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[title_color]"]').val(textPrimary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[price_color]"]').val(secondary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[desc_color]"]').val(textSecondary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[close_btn_bg]"]').val(primary).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[close_btn_text]"]').val(primaryText).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[close_btn_hover_bg]"]').val(primaryDark).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[modal_border_radius]"]').val(radius.modal).trigger('change');
              $('input[name="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>[modal_box_shadow]"]').val(boxShadowModal).trigger('change');

              // Auto-save all settings via AJAX
              var formData = new FormData();
              formData.append('action', 'smdp_save_all_styles');
              formData.append('security', '<?php echo wp_create_nonce("smdp_save_all_styles_nonce"); ?>');

              // Collect all form data from the different sections
              var allData = {};

              // Category Buttons
              allData['<?php echo self::OPT_STYLES; ?>'] = {};
              $('input[name^="<?php echo self::OPT_STYLES; ?>"], select[name^="<?php echo self::OPT_STYLES; ?>"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/\[([^\]]+)\]$/);
                if (match) {
                  var val = $(this).val();
                  // Properly escape special characters for JSON
                  allData['<?php echo self::OPT_STYLES; ?>'][match[1]] = val ? String(val) : '';
                }
              });

              // Help Buttons
              allData['<?php echo self::OPT_HELP_BTN_STYLES; ?>'] = {};
              $('input[name^="<?php echo self::OPT_HELP_BTN_STYLES; ?>"], select[name^="<?php echo self::OPT_HELP_BTN_STYLES; ?>"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/\[([^\]]+)\]\[([^\]]+)\]$/);
                if (match) {
                  if (!allData['<?php echo self::OPT_HELP_BTN_STYLES; ?>'][match[1]]) {
                    allData['<?php echo self::OPT_HELP_BTN_STYLES; ?>'][match[1]] = {};
                  }
                  var val = $(this).val();
                  allData['<?php echo self::OPT_HELP_BTN_STYLES; ?>'][match[1]][match[2]] = val ? String(val) : '';
                }
              });

              // Background Colors
              allData['<?php echo self::OPT_BG_COLORS; ?>'] = {};
              $('input[name^="<?php echo self::OPT_BG_COLORS; ?>"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/\[([^\]]+)\]$/);
                if (match) {
                  var val = $(this).val();
                  allData['<?php echo self::OPT_BG_COLORS; ?>'][match[1]] = val ? String(val) : '';
                }
              });

              // Item Cards
              allData['<?php echo self::OPT_ITEM_CARD_STYLES; ?>'] = {};
              $('input[name^="<?php echo self::OPT_ITEM_CARD_STYLES; ?>"], select[name^="<?php echo self::OPT_ITEM_CARD_STYLES; ?>"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/\[([^\]]+)\]$/);
                if (match) {
                  var val = $(this).val();
                  allData['<?php echo self::OPT_ITEM_CARD_STYLES; ?>'][match[1]] = val ? String(val) : '';
                }
              });

              // Item Detail
              allData['<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>'] = {};
              $('input[name^="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>"], select[name^="<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>"]').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/\[([^\]]+)\]$/);
                if (match) {
                  var val = $(this).val();
                  allData['<?php echo self::OPT_ITEM_DETAIL_STYLES; ?>'][match[1]] = val ? String(val) : '';
                }
              });

              // Style Generator Preferences
              allData['<?php echo self::OPT_STYLE_GENERATOR; ?>'] = {
                'theme_mode': $('input[name="theme_mode"]:checked').val() || 'light',
                'button_shape': $('input[name="button_shape"]:checked').val() || 'pill',
                'primary_color': $('#brand_color_primary').val() || '#0986bf',
                'secondary_color': $('#brand_color_secondary').val() || '#27ae60',
                'accent_color': $('#brand_color_accent').val() || '#e74c3c',
                'include_help_buttons': $('#include_help_buttons').is(':checked')
              };

              formData.append('style_data', JSON.stringify(allData));

              // Debug logging
              console.log('=== AJAX SAVE DEBUG ===');
              console.log('All data being sent:', allData);
              console.log('JSON stringified:', JSON.stringify(allData).substring(0, 200));
              console.log('======================');

              // Show loading
              $('#generate-styles').prop('disabled', true).text('⏳ Generating & Saving...');

              // Save via AJAX
              fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
              })
              .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                // First check if response is ok
                if (!response.ok) {
                  throw new Error('HTTP error ' + response.status);
                }
                // Get the text first to see what we're dealing with
                return response.text().then(text => {
                  console.log('Response text (first 500 chars):', text.substring(0, 500));
                  try {
                    var parsed = JSON.parse(text);
                    console.log('Parsed JSON:', parsed);
                    return parsed;
                  } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Full response text:', text);
                    throw new Error('Server returned invalid JSON. Check console for details.');
                  }
                });
              })
              .then(data => {
                console.log('Success handler - data:', data);
                $('#generate-styles').prop('disabled', false).text('🎨 Generate & Apply Complete Theme');
                if (data.success) {
                  alert('✅ Complete theme generated and saved!\n\n' +
                        'All styles have been applied and saved automatically.\n' +
                        'The page will now reload to show the updated values.');
                  window.location.reload();
                } else {
                  console.error('Save failed with data:', data);
                  alert('⚠️ Theme generated but save failed: ' + (data.data || 'Unknown error') + '\n\n' +
                        'Please click "Save" on each section manually:\n' +
                        '• Category Buttons\n' +
                        '• Help Buttons\n' +
                        '• Background Colors\n' +
                        '• Item Cards\n' +
                        '• Item Detail');
                }
              })
              .catch(error => {
                $('#generate-styles').prop('disabled', false).text('🎨 Generate & Apply Complete Theme');
                alert('⚠️ Theme generated but save failed: ' + error.message + '\n\n' +
                      'Please click "Save" on each section manually.');
                console.error('Full error:', error);
              });
            });

            // Reset All Styles Button
            $('#reset-all-styles').on('click', function() {
              if (!confirm('⚠️ WARNING: This will delete ALL custom styles and restore plugin defaults!\n\nThis includes:\n• Category Button Styles\n• Help Button Styles\n• Background Colors\n• Item Card Styles\n• Item Detail Styles\n• Style Generator Preferences\n\nThis action cannot be undone. Are you sure?')) {
                return;
              }

              if (!confirm('Are you ABSOLUTELY sure? All your custom styling will be lost!')) {
                return;
              }

              var $btn = $(this);
              $btn.prop('disabled', true).text('⏳ Resetting All Styles...');

              var formData = new FormData();
              formData.append('action', 'smdp_reset_all_styles');
              formData.append('security', '<?php echo wp_create_nonce("smdp_reset_all_styles_nonce"); ?>');

              fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                $btn.prop('disabled', false).text('🔄 Reset All Styles to Plugin Defaults');
                if (data.success) {
                  alert('✅ All styles have been reset to defaults!\n\nThe page will now reload to show the default styles.');
                  window.location.reload();
                } else {
                  alert('❌ Reset failed: ' + (data.data || 'Unknown error'));
                }
              })
              .catch(error => {
                $btn.prop('disabled', false).text('🔄 Reset All Styles to Plugin Defaults');
                alert('❌ Reset failed: ' + error.message);
                console.error('Reset error:', error);
              });
            });

            // Initial preview update
            updateStylePreview();
          });
          </script>
        </div>

        <!-- Subtab: Category Buttons -->
        <div id="style-category-buttons" class="smdp-style-subtab-content" style="margin-top:20px; display:none;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_styles_group'); ?>
            <h3>Category Button Styles</h3>
            <?php self::field_styles(); ?>
            <?php submit_button('Save Category Button Styles', 'primary', 'submit', false); ?>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to reset Category Button styles to defaults? This cannot be undone.');">
            <input type="hidden" name="action" value="smdp_reset_styles">
            <input type="hidden" name="reset_option" value="<?php echo esc_attr(self::OPT_STYLES); ?>">
            <?php wp_nonce_field('smdp_reset_styles', 'smdp_reset_nonce'); ?>
            <?php submit_button('Reset to Defaults', 'secondary', 'reset', false); ?>
          </form>
        </div>

        <!-- Subtab: Help Buttons -->
        <div id="style-help-buttons" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_help_btn_styles_group'); ?>
            <h3>Action Button Styles</h3>
            <p class="description">These styles apply to: Request Help, Request Bill, View Bill, and Table Badge buttons</p>
            <?php self::field_help_button_styles(); ?>
            <?php submit_button('Save Action Button Styles', 'primary', 'submit', false); ?>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to reset Action Button styles to defaults? This cannot be undone.');">
            <input type="hidden" name="action" value="smdp_reset_styles">
            <input type="hidden" name="reset_option" value="<?php echo esc_attr(self::OPT_HELP_BTN_STYLES); ?>">
            <?php wp_nonce_field('smdp_reset_styles', 'smdp_reset_nonce'); ?>
            <?php submit_button('Reset to Defaults', 'secondary', 'reset', false); ?>
          </form>
        </div>

        <!-- Subtab: Background Colors -->
        <div id="style-background" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_bg_colors_group'); ?>
            <h3>Background Colors</h3>
            <p class="description">Customize background colors for different sections of the menu app</p>
            <?php self::field_background_colors(); ?>
            <?php submit_button('Save Background Colors', 'primary', 'submit', false); ?>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to reset Background Colors to defaults? This cannot be undone.');">
            <input type="hidden" name="action" value="smdp_reset_styles">
            <input type="hidden" name="reset_option" value="<?php echo esc_attr(self::OPT_BG_COLORS); ?>">
            <?php wp_nonce_field('smdp_reset_styles', 'smdp_reset_nonce'); ?>
            <?php submit_button('Reset to Defaults', 'secondary', 'reset', false); ?>
          </form>
        </div>

        <!-- Subtab: Item Cards -->
        <div id="style-item-cards" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">
          <form method="post" action="options.php">
            <?php settings_fields('smdp_menu_app_item_card_styles_group'); ?>
            <h3>Item Card Styles</h3>
            <p class="description">Customize the appearance of menu item cards</p>
            <?php self::field_item_card_styles(); ?>
            <?php submit_button('Save Item Card Styles', 'primary', 'submit', false); ?>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to reset Item Card styles to defaults? This cannot be undone.');">
            <input type="hidden" name="action" value="smdp_reset_styles">
            <input type="hidden" name="reset_option" value="<?php echo esc_attr(self::OPT_ITEM_CARD_STYLES); ?>">
            <?php wp_nonce_field('smdp_reset_styles', 'smdp_reset_nonce'); ?>
            <?php submit_button('Reset to Defaults', 'secondary', 'reset', false); ?>
          </form>
        </div>

        <!-- Subtab: Item Detail -->
        <div id="style-item-detail" class="smdp-style-subtab-content" style="display:none; margin-top:20px;">

          <!-- Item Detail Modal Enable/Disable Settings -->
          <div style="background:#fff; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,0.04); padding:20px; margin-bottom:20px;">
            <h3 style="margin-top:0;">Item Detail Modal Settings</h3>
            <form method="post" action="options.php">
              <?php
              settings_fields('smdp_menu_app_layout_group');
              $settings = get_option(self::OPT_SETTINGS, array());
              if (!is_array($settings)) $settings = array();
              ?>
              <input type="hidden" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[_form_type]" value="item_detail_modal">

              <fieldset>
                <legend style="font-weight:600; font-size:14px; margin-bottom:10px;">Enable/Disable Item Detail Modal</legend>
                <p class="description" style="margin-bottom:15px;">Control where the item detail modal (tap to view details) is enabled.</p>

                <label style="display:block; margin-bottom:8px;">
                  <input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[enable_modal_shortcode]" value="1" <?php checked(isset($settings['enable_modal_shortcode']) ? $settings['enable_modal_shortcode'] : '1', '1'); ?>>
                  <strong>Enable for Category Shortcodes</strong> <code>[square_menu category="..."]</code>
                </label>

                <label style="display:block; margin-bottom:8px;">
                  <input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[enable_modal_menuapp]" value="1" <?php checked(isset($settings['enable_modal_menuapp']) ? $settings['enable_modal_menuapp'] : '1', '1'); ?>>
                  <strong>Enable for Menu App</strong> (full menu app view)
                </label>

                <label style="display:block; margin-bottom:8px;">
                  <input type="checkbox" name="<?php echo esc_attr(self::OPT_SETTINGS); ?>[enable_modal_filter]" value="1" <?php checked(isset($settings['enable_modal_filter']) ? $settings['enable_modal_filter'] : '1', '1'); ?>>
                  <strong>Enable for Menu App Category Filters</strong> (category filter pages)
                </label>

                <p class="description" style="margin-top:10px;">When disabled, tapping on menu items will not open the detail modal in that context.</p>
              </fieldset>

              <?php submit_button('Save Modal Settings', 'primary', 'submit', false); ?>
            </form>
          </div>

          <!-- Item Detail Modal Styles -->
          <div style="background:#fff; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,0.04); padding:20px; margin-bottom:20px;">
            <form method="post" action="options.php">
              <?php settings_fields('smdp_menu_app_item_detail_styles_group'); ?>
              <h3 style="margin-top:0;">Item Detail Modal Styles</h3>
              <p class="description">Customize the appearance of the item detail popup/modal</p>
              <?php self::field_item_detail_styles(); ?>
              <?php submit_button('Save Item Detail Styles', 'primary', 'submit', false); ?>
            </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;" onsubmit="return confirm('Are you sure you want to reset Item Detail styles to defaults? This cannot be undone.');">
            <input type="hidden" name="action" value="smdp_reset_styles">
            <input type="hidden" name="reset_option" value="<?php echo esc_attr(self::OPT_ITEM_DETAIL_STYLES); ?>">
            <?php wp_nonce_field('smdp_reset_styles', 'smdp_reset_nonce'); ?>
            <?php submit_button('Reset to Defaults', 'secondary', 'reset', false); ?>
          </form>
        </div>

      </div>
    </div>

    <style>
      /* Style subtabs */
      .smdp-style-subtab { cursor: pointer; transition: all 0.2s; }
      .smdp-style-subtab:hover { color: #2271b1; }
      .smdp-style-subtab.active { color: #2271b1; font-weight: 600; border-bottom: 2px solid #2271b1; }
      .smdp-style-subtab-content { display: none !important; }
      .smdp-style-subtab-content.active { display: block !important; }
    </style>

    <script>
    jQuery(document).ready(function($){
      // Style subtabs switching
      $('.smdp-style-subtab').on('click', function(e){
        e.preventDefault();
        var target = $(this).attr('href');

        // Remove all active states
        $('.smdp-style-subtab').removeClass('active');
        $('.smdp-style-subtab-content').removeClass('active');

        // Add active states to clicked tab and its content
        $(this).addClass('active');
        $(target).addClass('active');

        // Save which style subtab is currently active
        sessionStorage.setItem('smdp_active_style_subtab', target);
      });

      // Check for fragment identifier in URL (e.g., #style-item-detail)
      var urlHash = window.location.hash;
      var targetSubtab = null;

      if (urlHash && $(urlHash).hasClass('smdp-style-subtab-content')) {
        targetSubtab = urlHash;
      } else {
        // Fallback to session storage
        targetSubtab = sessionStorage.getItem('smdp_active_style_subtab');
      }

      if (targetSubtab) {
        $('.smdp-style-subtab').removeClass('active');
        $('.smdp-style-subtab-content').removeClass('active');
        $('.smdp-style-subtab[href="' + targetSubtab + '"]').addClass('active');
        $(targetSubtab).addClass('active');
      }

      // Save current subtab state before any form submission
      $('form').on('submit', function(){
        // Save which style subtab is currently active (if any)
        var $activeStyleSubtab = $('.smdp-style-subtab.active');
        if ($activeStyleSubtab.length) {
          sessionStorage.setItem('smdp_active_style_subtab', $activeStyleSubtab.attr('href'));
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

    // Get button enable/disable settings (default to enabled)
    $enable_help_btn = isset($settings['enable_help_btn']) ? $settings['enable_help_btn'] : '1';
    $enable_bill_btn = isset($settings['enable_bill_btn']) ? $settings['enable_bill_btn'] : '1';
    $enable_view_bill_btn = isset($settings['enable_view_bill_btn']) ? $settings['enable_view_bill_btn'] : '1';
    $enable_table_badge = isset($settings['enable_table_badge']) ? $settings['enable_table_badge'] : '1';
    $enable_table_selector = isset($settings['enable_table_selector']) ? $settings['enable_table_selector'] : '1';
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

    // Default values (matches hardcoded CSS in smdp-structural.css lines 231-248)
    $defaults = array(
      'bg_color' => '#ffffff',
      'text_color' => '#000000',
      'border_color' => '#01669c',
      'active_bg_color' => '#0986bf',
      'active_text_color' => '#ffffff',
      'active_border_color' => '#0986bf',
      'font_size' => 25,
      'padding_vertical' => 16,
      'padding_horizontal' => 40,
      'border_radius' => 500,
      'border_width' => 1,
      'font_weight' => 'normal',
      'font_family' => '',
      'box_shadow' => 'none',
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
        <tr>
          <th>Box Shadow</th>
          <td>
            <input type="hidden" name="<?php echo esc_attr($name); ?>[box_shadow]" class="smdp-box-shadow-value" value="<?php echo esc_attr($styles['box_shadow']); ?>" />

            <div class="smdp-box-shadow-builder">
              <label style="display:block; margin-bottom:10px;">
                <strong>Shadow Preset:</strong>
                <select class="smdp-shadow-preset" style="margin-left:10px;">
                  <option value="none">None</option>
                  <option value="subtle">Subtle (0 1px 3px)</option>
                  <option value="medium">Medium (0 4px 6px)</option>
                  <option value="strong">Strong (0 10px 25px)</option>
                  <option value="custom">Custom</option>
                </select>
              </label>

              <div class="smdp-shadow-custom" style="display:none; margin-top:15px; padding:15px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>X Offset (px):</strong>
                      <input type="number" class="smdp-shadow-x" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Y Offset (px):</strong>
                      <input type="number" class="smdp-shadow-y" value="4" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Blur (px):</strong>
                      <input type="number" class="smdp-shadow-blur" value="6" min="0" max="100" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Spread (px):</strong>
                      <input type="number" class="smdp-shadow-spread" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Color:</strong>
                      <input type="text" class="smdp-shadow-color smdp-color-picker" value="#000000" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Opacity (0-1):</strong>
                      <input type="number" class="smdp-shadow-opacity" value="0.15" min="0" max="1" step="0.05" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                </div>
                <div style="margin-top:10px;">
                  <small style="color:#666;">Preview: <code class="smdp-shadow-preview" style="background:#fff; padding:3px 6px; border:1px solid #ddd;">0 4px 6px rgba(0,0,0,0.15)</code></small>
                </div>
              </div>
            </div>
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
              box-shadow: <?php echo esc_attr($styles['box_shadow']); ?>;
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
              box-shadow: <?php echo esc_attr($styles['box_shadow']); ?>;
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
        var boxShadow = $('input[name="<?php echo esc_js($name); ?>[box_shadow]"]').val();

        $preview.find('.smdp-preview-btn').not('.smdp-preview-active').css({
          'background': bgColor,
          'color': textColor,
          'border-color': borderColor,
          'font-size': fontSize + 'px',
          'padding': padV + 'px ' + padH + 'px',
          'border-radius': borderRadius + 'px',
          'border-width': borderWidth + 'px',
          'font-weight': fontWeight,
          'font-family': fontFamily,
          'box-shadow': boxShadow
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
          'font-family': fontFamily,
          'box-shadow': boxShadow
        });
      }

      // Update preview on any input change
      $('.smdp-style-controls input, .smdp-style-controls select').on('change keyup input', updatePreview);

      // Listen for color picker changes from external script
      $(document).on('smdp-color-changed', updatePreview);

      // Box Shadow Builder
      function updateBoxShadow($builder) {
        var $container = $builder.closest('.smdp-box-shadow-builder');
        var $hiddenInput = $container.siblings('.smdp-box-shadow-value');
        var preset = $container.find('.smdp-shadow-preset').val();
        var shadowValue = '';

        if (preset === 'none') {
          shadowValue = 'none';
        } else if (preset === 'subtle') {
          shadowValue = '0 1px 3px rgba(0,0,0,0.12)';
        } else if (preset === 'medium') {
          shadowValue = '0 4px 6px rgba(0,0,0,0.15)';
        } else if (preset === 'strong') {
          shadowValue = '0 10px 25px rgba(0,0,0,0.2)';
        } else if (preset === 'custom') {
          var x = $container.find('.smdp-shadow-x').val() || 0;
          var y = $container.find('.smdp-shadow-y').val() || 0;
          var blur = $container.find('.smdp-shadow-blur').val() || 0;
          var spread = $container.find('.smdp-shadow-spread').val() || 0;
          var color = $container.find('.smdp-shadow-color').val() || '#000000';
          var opacity = $container.find('.smdp-shadow-opacity').val() || 0.15;

          // Convert hex to rgb
          var r = parseInt(color.substr(1,2), 16);
          var g = parseInt(color.substr(3,2), 16);
          var b = parseInt(color.substr(5,2), 16);

          shadowValue = x + 'px ' + y + 'px ' + blur + 'px ' + spread + 'px rgba(' + r + ',' + g + ',' + b + ',' + opacity + ')';
          $container.find('.smdp-shadow-preview').text(shadowValue);
        }

        $hiddenInput.val(shadowValue).trigger('change');
      }

      // Initialize box shadow builders
      $('.smdp-box-shadow-builder').each(function() {
        var $builder = $(this);
        var $hiddenInput = $builder.siblings('.smdp-box-shadow-value');
        var currentValue = $hiddenInput.val();

        // Try to match current value to a preset
        var matchedPreset = false;
        if (currentValue === 'none' || !currentValue) {
          $builder.find('.smdp-shadow-preset').val('none');
          matchedPreset = true;
        } else if (currentValue.includes('0 1px 3px') || currentValue.includes('0px 1px 3px')) {
          $builder.find('.smdp-shadow-preset').val('subtle');
          matchedPreset = true;
        } else if (currentValue.includes('0 4px 6px') || currentValue.includes('0px 4px 6px')) {
          $builder.find('.smdp-shadow-preset').val('medium');
          matchedPreset = true;
        } else if (currentValue.includes('0 10px 25px') || currentValue.includes('0px 10px 25px')) {
          $builder.find('.smdp-shadow-preset').val('strong');
          matchedPreset = true;
        }

        // If no preset matched, set to custom and parse values
        if (!matchedPreset && currentValue && currentValue !== 'none') {
          $builder.find('.smdp-shadow-preset').val('custom');
          $builder.find('.smdp-shadow-custom').show();

          // Try to parse the shadow value
          var parts = currentValue.match(/([-\d.]+)px\s+([-\d.]+)px\s+([-\d.]+)px(?:\s+([-\d.]+)px)?\s+rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
          if (parts) {
            $builder.find('.smdp-shadow-x').val(parts[1] || 0);
            $builder.find('.smdp-shadow-y').val(parts[2] || 0);
            $builder.find('.smdp-shadow-blur').val(parts[3] || 0);
            $builder.find('.smdp-shadow-spread').val(parts[4] || 0);
            var hexColor = '#' +
              ('0' + parseInt(parts[5]).toString(16)).slice(-2) +
              ('0' + parseInt(parts[6]).toString(16)).slice(-2) +
              ('0' + parseInt(parts[7]).toString(16)).slice(-2);
            $builder.find('.smdp-shadow-color').val(hexColor);
            $builder.find('.smdp-shadow-opacity').val(parts[8] || 0.15);
            $builder.find('.smdp-shadow-preview').text(currentValue);
          }
        }

        // Custom color picker will be initialized by menu-app-builder-admin.js
        // No initialization needed here - the plugin's custom color picker handles it automatically
      });

      // Shadow preset change
      $(document).on('change', '.smdp-shadow-preset', function() {
        var $builder = $(this).closest('.smdp-box-shadow-builder');
        var preset = $(this).val();

        if (preset === 'custom') {
          $builder.find('.smdp-shadow-custom').show();
          // Re-initialize custom color picker when custom is shown
          setTimeout(function() {
            if (typeof initColorPickers === 'function') {
              initColorPickers();
            }
          }, 100);
        } else {
          $builder.find('.smdp-shadow-custom').hide();
        }

        updateBoxShadow($builder);
      });

      // Custom shadow input changes
      $(document).on('change keyup input', '.smdp-shadow-x, .smdp-shadow-y, .smdp-shadow-blur, .smdp-shadow-spread, .smdp-shadow-color, .smdp-shadow-opacity', function() {
        var $builder = $(this).closest('.smdp-box-shadow-builder');
        updateBoxShadow($builder);
      });
    });
    </script>
    <?php
  }

  public static function field_help_button_styles() {
    $all_styles = get_option(self::OPT_HELP_BTN_STYLES, array());
    $name = self::OPT_HELP_BTN_STYLES;

    // Default values for each button type based on hardcoded CSS
    $defaults = array(
      'help' => array(
        'bg_color' => '#e74c3c',
        'text_color' => '#ffffff',
        'border_color' => '#e74c3c',
        'hover_bg_color' => '#c0392b',
        'hover_text_color' => '#ffffff',
        'hover_border_color' => '#c0392b',
        'disabled_bg_color' => '#c0392b',
        'disabled_text_color' => '#ffffff',
        'font_size' => 16,
        'padding_vertical' => 16,
        'padding_horizontal' => 24,
        'border_radius' => 8,
        'border_width' => 0,
        'font_weight' => '600',
        'font_family' => '',
        'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
      ),
      'bill' => array(
        'bg_color' => '#27ae60',
        'text_color' => '#ffffff',
        'border_color' => '#27ae60',
        'hover_bg_color' => '#1e8449',
        'hover_text_color' => '#ffffff',
        'hover_border_color' => '#1e8449',
        'disabled_bg_color' => '#1e8449',
        'disabled_text_color' => '#ffffff',
        'font_size' => 16,
        'padding_vertical' => 16,
        'padding_horizontal' => 24,
        'border_radius' => 8,
        'border_width' => 0,
        'font_weight' => '600',
        'font_family' => '',
        'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
      ),
      'view_bill' => array(
        'bg_color' => '#9b59b6',
        'text_color' => '#ffffff',
        'border_color' => '#9b59b6',
        'hover_bg_color' => '#8e44ad',
        'hover_text_color' => '#ffffff',
        'hover_border_color' => '#8e44ad',
        'font_size' => 14,
        'padding_vertical' => 12,
        'padding_horizontal' => 16,
        'border_radius' => 8,
        'border_width' => 0,
        'font_weight' => '600',
        'font_family' => '',
        'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
      ),
      'table_badge' => array(
        'bg_color' => '#3498db',
        'text_color' => '#ffffff',
        'border_color' => '#3498db',
        'hover_bg_color' => '#2980b9',
        'hover_text_color' => '#ffffff',
        'hover_border_color' => '#2980b9',
        'font_size' => 14,
        'padding_vertical' => 12,
        'padding_horizontal' => 16,
        'border_radius' => 8,
        'border_width' => 0,
        'font_weight' => '600',
        'font_family' => '',
        'box_shadow' => '0 4px 10px rgba(0,0,0,0.3)',
      ),
    );

    // Merge with saved values
    foreach ($defaults as $type => $default_values) {
      if (!isset($all_styles[$type]) || !is_array($all_styles[$type])) {
        $all_styles[$type] = array();
      }
      $all_styles[$type] = array_merge($default_values, $all_styles[$type]);
    }

    // Helper function to render button style fields
    $render_button_fields = function($button_type, $styles, $include_disabled = false) use ($name) {
      $type_name = ucwords(str_replace('_', ' ', $button_type));
      $button_text = $type_name;
      if ($button_type === 'table_badge') $button_text = 'Table 5';
      ?>
      <div style="display: grid; grid-template-columns: 1fr 280px; gap: 20px; align-items: start;">
        <!-- Left Column: Form Fields -->
        <div>
          <h3 style="margin-top: 0;"><?php echo esc_html($type_name); ?> Button</h3>
          <table class="form-table">
        <tr>
          <th>Background Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][bg_color]" value="<?php echo esc_attr($styles['bg_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="bg_color" />
          </td>
        </tr>
        <tr>
          <th>Text Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][text_color]" value="<?php echo esc_attr($styles['text_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="text_color" />
          </td>
        </tr>
        <tr>
          <th>Border Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][border_color]" value="<?php echo esc_attr($styles['border_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="border_color" />
          </td>
        </tr>
        <tr>
          <th>Hover Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][hover_bg_color]" value="<?php echo esc_attr($styles['hover_bg_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="hover_bg_color" />
          </td>
        </tr>
        <tr>
          <th>Hover Text Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][hover_text_color]" value="<?php echo esc_attr($styles['hover_text_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="hover_text_color" />
          </td>
        </tr>
        <tr>
          <th>Hover Border Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][hover_border_color]" value="<?php echo esc_attr($styles['hover_border_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="hover_border_color" />
          </td>
        </tr>
        <?php if ($include_disabled): ?>
        <tr>
          <th>Disabled Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][disabled_bg_color]" value="<?php echo esc_attr($styles['disabled_bg_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="disabled_bg_color" />
            <p class="description">When button shows "Already Requested"</p>
          </td>
        </tr>
        <tr>
          <th>Disabled Text Color</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][disabled_text_color]" value="<?php echo esc_attr($styles['disabled_text_color']); ?>" class="smdp-color-picker smdp-<?php echo esc_attr($button_type); ?>-field" data-style="disabled_text_color" />
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <th>Font Size (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][font_size]" value="<?php echo esc_attr($styles['font_size']); ?>" min="10" max="32" style="width: 80px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="font_size" />
          </td>
        </tr>
        <tr>
          <th>Font Weight</th>
          <td>
            <select name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][font_weight]" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="font_weight">
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
            <select name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][font_family]" style="width: 250px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="font_family">
              <option value="">Inherit from theme</option>
              <optgroup label="System Fonts">
                <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" <?php selected($styles['font_family'], "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"); ?>>System Default</option>
                <option value="Arial, sans-serif" <?php selected($styles['font_family'], 'Arial, sans-serif'); ?>>Arial</option>
                <option value="'Helvetica Neue', Helvetica, Arial, sans-serif" <?php selected($styles['font_family'], "'Helvetica Neue', Helvetica, Arial, sans-serif"); ?>>Helvetica</option>
              </optgroup>
            </select>
          </td>
        </tr>
        <tr>
          <th>Padding Vertical (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][padding_vertical]" value="<?php echo esc_attr($styles['padding_vertical']); ?>" min="0" max="50" style="width: 80px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="padding_vertical" />
          </td>
        </tr>
        <tr>
          <th>Padding Horizontal (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][padding_horizontal]" value="<?php echo esc_attr($styles['padding_horizontal']); ?>" min="0" max="100" style="width: 80px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="padding_horizontal" />
          </td>
        </tr>
        <tr>
          <th>Border Width (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][border_width]" value="<?php echo esc_attr($styles['border_width']); ?>" min="0" max="10" style="width: 80px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="border_width" />
          </td>
        </tr>
        <tr>
          <th>Border Radius (px)</th>
          <td>
            <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][border_radius]" value="<?php echo esc_attr($styles['border_radius']); ?>" min="0" max="999" style="width: 80px;" class="smdp-<?php echo esc_attr($button_type); ?>-field" data-style="border_radius" />
          </td>
        </tr>
        <tr>
          <th>Box Shadow</th>
          <td>
            <input type="hidden" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr($button_type); ?>][box_shadow]" class="smdp-box-shadow-value smdp-<?php echo esc_attr($button_type); ?>-field" data-style="box_shadow" value="<?php echo esc_attr($styles['box_shadow']); ?>" />

            <div class="smdp-box-shadow-builder">
              <label style="display:block; margin-bottom:10px;">
                <strong>Shadow Preset:</strong>
                <select class="smdp-shadow-preset" style="margin-left:10px;">
                  <option value="none">None</option>
                  <option value="subtle">Subtle (0 1px 3px)</option>
                  <option value="medium">Medium (0 4px 6px)</option>
                  <option value="strong">Strong (0 10px 25px)</option>
                  <option value="custom">Custom</option>
                </select>
              </label>

              <div class="smdp-shadow-custom" style="display:none; margin-top:15px; padding:15px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>X Offset (px):</strong>
                      <input type="number" class="smdp-shadow-x" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Y Offset (px):</strong>
                      <input type="number" class="smdp-shadow-y" value="4" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Blur (px):</strong>
                      <input type="number" class="smdp-shadow-blur" value="6" min="0" max="100" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Spread (px):</strong>
                      <input type="number" class="smdp-shadow-spread" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Color:</strong>
                      <input type="text" class="smdp-shadow-color smdp-color-picker" value="#000000" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                  <div>
                    <label style="display:block; margin-bottom:5px;">
                      <strong>Opacity (0-1):</strong>
                      <input type="number" class="smdp-shadow-opacity" value="0.15" min="0" max="1" step="0.05" style="width:100%; margin-top:5px;" />
                    </label>
                  </div>
                </div>
                <div style="margin-top:10px;">
                  <small style="color:#666;">Preview: <code class="smdp-shadow-preview" style="background:#fff; padding:3px 6px; border:1px solid #ddd;">0 4px 6px rgba(0,0,0,0.15)</code></small>
                </div>
              </div>
            </div>
          </td>
        </tr>
      </table>
        </div><!-- End Left Column -->

        <!-- Right Column: Live Preview -->
        <div style="position: sticky; top: 20px;">
          <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h4 style="margin-top: 0; font-size: 14px; text-transform: uppercase; color: #666;">Live Preview</h4>
            <button type="button" id="smdp-<?php echo esc_attr(str_replace('_', '-', $button_type)); ?>-preview" style="
              background: <?php echo esc_attr($styles['bg_color']); ?>;
              color: <?php echo esc_attr($styles['text_color']); ?>;
              border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['border_color']); ?>;
              font-size: <?php echo esc_attr($styles['font_size']); ?>px;
              padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px;
              border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
              font-weight: <?php echo esc_attr($styles['font_weight']); ?>;
              <?php if (!empty($styles['font_family'])): ?>font-family: <?php echo esc_attr($styles['font_family']); ?>;<?php endif; ?>
              box-shadow: <?php echo esc_attr($styles['box_shadow']); ?>;
              cursor: pointer;
              transition: all 0.3s ease;
              white-space: nowrap;
              line-height: 1;
              display: flex;
              align-items: center;
              justify-content: center;
              <?php if ($button_type === 'table_badge'): ?>
              width: 120px;
              min-width: 120px;
              max-width: 120px;
              <?php else: ?>
              width: auto;
              min-width: 160px;
              <?php endif; ?>
            "><?php echo esc_html($button_text); ?></button>
            <?php if ($include_disabled): ?>
            <button type="button" id="smdp-<?php echo esc_attr(str_replace('_', '-', $button_type)); ?>-preview-disabled" style="
              background: <?php echo esc_attr($styles['disabled_bg_color']); ?>;
              color: <?php echo esc_attr($styles['disabled_text_color']); ?>;
              border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['border_color']); ?>;
              font-size: <?php echo esc_attr($styles['font_size']); ?>px;
              padding: <?php echo esc_attr($styles['padding_vertical']); ?>px <?php echo esc_attr($styles['padding_horizontal']); ?>px;
              border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
              font-weight: <?php echo esc_attr($styles['font_weight']); ?>;
              <?php if (!empty($styles['font_family'])): ?>font-family: <?php echo esc_attr($styles['font_family']); ?>;<?php endif; ?>
              cursor: not-allowed;
              opacity: 0.8;
              box-shadow: 0 4px 10px rgba(0,0,0,0.3);
              white-space: nowrap;
              line-height: 1;
              display: flex;
              align-items: center;
              justify-content: center;
              width: auto;
              min-width: 160px;
              margin-top: 10px;
            ">Already Requested</button>
            <small style="display: block; margin-top: 8px; color: #666; font-size: 11px;">Disabled state preview</small>
            <?php endif; ?>
          </div>
        </div><!-- End Right Column -->
      </div><!-- End Grid Wrapper -->
      <?php
    };

    ?>
    <div style="max-width: 1200px;">
      <p class="description" style="margin-bottom: 20px;">Customize each action button individually. Styles are applied separately to each button type.</p>

      <!-- Request Help Button -->
      <div style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <?php $render_button_fields('help', $all_styles['help'], true); ?>
      </div>

      <!-- Request Bill Button -->
      <div style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <?php $render_button_fields('bill', $all_styles['bill'], true); ?>
      </div>

      <!-- View Bill Button -->
      <div style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <?php $render_button_fields('view_bill', $all_styles['view_bill'], false); ?>
      </div>

      <!-- Table Badge -->
      <div style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <?php $render_button_fields('table_badge', $all_styles['table_badge'], false); ?>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      // Function to update button preview
      function updateButtonPreview(buttonType) {
        var $preview = $('#smdp-' + buttonType.replace('_', '-') + '-preview');
        var $previewDisabled = $('#smdp-' + buttonType.replace('_', '-') + '-preview-disabled');
        var prefix = 'input[name="<?php echo esc_js(self::OPT_HELP_BTN_STYLES); ?>[' + buttonType + ']';

        var bgColor = $(prefix + '[bg_color]"]').val();
        var textColor = $(prefix + '[text_color]"]').val();
        var borderColor = $(prefix + '[border_color]"]').val();
        var borderWidth = $(prefix + '[border_width]"]').val();
        var borderRadius = $(prefix + '[border_radius]"]').val();
        var fontSize = $(prefix + '[font_size]"]').val();
        var paddingV = $(prefix + '[padding_vertical]"]').val();
        var paddingH = $(prefix + '[padding_horizontal]"]').val();
        var fontWeight = $(prefix + '[font_weight]"] option:selected').val();
        var fontFamily = $(prefix + '[font_family]"] option:selected').val();
        var boxShadow = $(prefix + '[box_shadow]"]').val();
        var disabledBgColor = $(prefix + '[disabled_bg_color]"]').val();
        var disabledTextColor = $(prefix + '[disabled_text_color]"]').val();

        // Update normal state preview
        $preview.css({
          'background-color': bgColor,
          'color': textColor,
          'border': borderWidth + 'px solid ' + borderColor,
          'border-radius': borderRadius + 'px',
          'font-size': fontSize + 'px',
          'padding': paddingV + 'px ' + paddingH + 'px',
          'font-weight': fontWeight,
          'font-family': fontFamily,
          'box-shadow': boxShadow
        });

        // Update disabled state preview (if exists)
        if ($previewDisabled.length) {
          $previewDisabled.css({
            'background-color': disabledBgColor,
            'color': disabledTextColor,
            'border': borderWidth + 'px solid ' + borderColor,
            'border-radius': borderRadius + 'px',
            'font-size': fontSize + 'px',
            'padding': paddingV + 'px ' + paddingH + 'px',
            'font-weight': fontWeight,
            'font-family': fontFamily,
            'box-shadow': boxShadow
          });
        }
      }

      // Update on field changes
      $('.smdp-help-field, .smdp-bill-field, .smdp-view_bill-field, .smdp-table_badge-field').on('change keyup input', function(){
        var buttonType = '';
        if ($(this).hasClass('smdp-help-field')) buttonType = 'help';
        else if ($(this).hasClass('smdp-bill-field')) buttonType = 'bill';
        else if ($(this).hasClass('smdp-view_bill-field')) buttonType = 'view_bill';
        else if ($(this).hasClass('smdp-table_badge-field')) buttonType = 'table_badge';

        if (buttonType) updateButtonPreview(buttonType);
      });

      // Listen for color picker changes
      $(document).on('smdp-color-changed', function(){
        updateButtonPreview('help');
        updateButtonPreview('bill');
        updateButtonPreview('view_bill');
        updateButtonPreview('table_badge');
      });
    });
    </script>
    <?php
  }
  public static function field_background_colors() {
    $colors = get_option(self::OPT_BG_COLORS, array());
    $name = self::OPT_BG_COLORS;

    // Default values
    $defaults = array(
      'main_bg' => '#ffffff',
      'category_bar_bg' => '#ffffff',
      'content_area_bg' => '#ffffff',
    );
    $colors = array_merge($defaults, $colors);
    ?>
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 15px; align-items: start;">
      <!-- Left Column: Form Controls -->
      <div class="smdp-bg-color-controls">

        <h3>Background Colors</h3>
        <table class="form-table">
        <tr>
          <th>Main Container Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[main_bg]" value="<?php echo esc_attr($colors['main_bg']); ?>" class="smdp-color-picker" />
            <p class="description">Background for the entire menu app container (<code>.smdp-menu-app-fe</code>)</p>
          </td>
        </tr>
        <tr>
          <th>Category Bar Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[category_bar_bg]" value="<?php echo esc_attr($colors['category_bar_bg']); ?>" class="smdp-color-picker" />
            <p class="description">Background for the category button area (<code>.smdp-cat-bar</code>)</p>
          </td>
        </tr>
        <tr>
          <th>Content Area Background</th>
          <td>
            <input type="text" name="<?php echo esc_attr($name); ?>[content_area_bg]" value="<?php echo esc_attr($colors['content_area_bg']); ?>" class="smdp-color-picker" />
            <p class="description">Background for the menu items section (<code>.smdp-app-sections</code>). Note: Individual item card backgrounds are customized in the Item Cards tab.</p>
          </td>
        </tr>
      </table>

      </div>

      <!-- Right Column: Visual Guide -->
      <div class="smdp-bg-preview" style="position: sticky; top: 32px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
        <h3 style="margin-top: 0;">Visual Guide</h3>
        <p class="description" style="margin-bottom: 15px;">Shows which areas each color affects</p>

        <div style="border: 2px dashed #999; padding: 8px; border-radius: 8px; background: #fff;">
          <small style="color: #666; display: block; margin-bottom: 4px; font-weight: bold;">Main Container</small>
          <div style="background: <?php echo esc_attr($colors['main_bg']); ?>; padding: 12px; border: 1px solid #ddd; border-radius: 4px; min-height: 40px;">

            <small style="color: #666; display: block; margin-bottom: 4px; font-weight: bold;">Category Bar</small>
            <div style="background: <?php echo esc_attr($colors['category_bar_bg']); ?>; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; min-height: 30px;" class="smdp-bg-cat-bar-preview">
              <small style="font-size: 10px; color: #666;">Category Buttons Here</small>
            </div>

            <small style="color: #666; display: block; margin-bottom: 4px; font-weight: bold;">Content Area</small>
            <div style="background: <?php echo esc_attr($colors['content_area_bg']); ?>; padding: 12px; border: 1px solid #ddd; border-radius: 4px; min-height: 50px;" class="smdp-bg-content-preview">
              <small style="font-size: 10px; color: #666;">Menu Items Display Here</small>
            </div>

          </div>
        </div>

        <div style="margin-top: 12px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
          <small style="font-size: 11px; color: #856404;">
            <strong>Tip:</strong> Use contrasting colors for visual hierarchy. The content area typically looks best slightly lighter or darker than the main container.
          </small>
        </div>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      function updateBgPreview() {
        var mainBg = $('input[name="<?php echo esc_js($name); ?>[main_bg]"]').val();
        var catBarBg = $('input[name="<?php echo esc_js($name); ?>[category_bar_bg]"]').val();
        var contentBg = $('input[name="<?php echo esc_js($name); ?>[content_area_bg]"]').val();

        // Update preview
        $('.smdp-bg-preview > div > div').css('background-color', mainBg);
        $('.smdp-bg-cat-bar-preview').css('background-color', catBarBg);
        $('.smdp-bg-content-preview').css('background-color', contentBg);
      }

      // Update preview on any input change
      $('.smdp-bg-color-controls input').on('change keyup input', updateBgPreview);

      // Listen for color picker changes from external script
      $(document).on('smdp-color-changed', updateBgPreview);
    });
    </script>
    <?php
  }

  public static function field_item_card_styles() {
    $styles = get_option(self::OPT_ITEM_CARD_STYLES, array());
    $name = self::OPT_ITEM_CARD_STYLES;

    // Default values based on current hardcoded styles
    $defaults = array(
      'bg_color' => '#ffffff',
      'text_color' => '#000000',
      'border_color' => '#eeeeee',
      'border_width' => 1,
      'border_radius' => 0,
      'padding' => 8,
      'title_color' => '#000000',
      'title_size' => 19,
      'title_weight' => 'bold',
      'price_color' => '#000000',
      'price_size' => 16,
      'price_weight' => 'bold',
      'desc_color' => '#666666',
      'desc_size' => 14,
      'box_shadow' => '0 2px 4px rgba(0,0,0,0.1)',
    );
    $styles = array_merge($defaults, $styles);
    ?>
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start;">
      <!-- Left Column: Form Controls -->
      <div>
        <h3 style="margin-top: 0;">Card Container</h3>
        <table class="form-table">
          <tr>
            <th>Background Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[bg_color]" value="<?php echo esc_attr($styles['bg_color']); ?>" class="smdp-color-picker smdp-item-card-field" data-style="bg_color" />
              <p class="description">Background color for the card</p>
            </td>
          </tr>
          <tr>
            <th>Border Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[border_color]" value="<?php echo esc_attr($styles['border_color']); ?>" class="smdp-color-picker smdp-item-card-field" data-style="border_color" />
            </td>
          </tr>
          <tr>
            <th>Border Width (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[border_width]" value="<?php echo esc_attr($styles['border_width']); ?>" min="0" max="10" style="width: 80px;" class="smdp-item-card-field" data-style="border_width" />
            </td>
          </tr>
          <tr>
            <th>Border Radius (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[border_radius]" value="<?php echo esc_attr($styles['border_radius']); ?>" min="0" max="50" style="width: 80px;" class="smdp-item-card-field" data-style="border_radius" />
              <p class="description">Rounded corners (0 = square, higher = more rounded)</p>
            </td>
          </tr>
          <tr>
            <th>Padding (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[padding]" value="<?php echo esc_attr($styles['padding']); ?>" min="0" max="50" style="width: 80px;" class="smdp-item-card-field" data-style="padding" />
              <p class="description">Space inside the card</p>
            </td>
          </tr>
          <tr>
            <th>Box Shadow</th>
            <td>
              <input type="hidden" name="<?php echo esc_attr($name); ?>[box_shadow]" class="smdp-box-shadow-value smdp-item-card-field" data-style="box_shadow" value="<?php echo esc_attr($styles['box_shadow']); ?>" />

              <div class="smdp-box-shadow-builder">
                <label style="display:block; margin-bottom:10px;">
                  <strong>Shadow Preset:</strong>
                  <select class="smdp-shadow-preset" style="margin-left:10px;">
                    <option value="none">None</option>
                    <option value="subtle">Subtle (0 1px 3px)</option>
                    <option value="medium">Medium (0 4px 6px)</option>
                    <option value="strong">Strong (0 10px 25px)</option>
                    <option value="custom">Custom</option>
                  </select>
                </label>

                <div class="smdp-shadow-custom" style="display:none; margin-top:15px; padding:15px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
                  <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>X Offset (px):</strong>
                        <input type="number" class="smdp-shadow-x" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Y Offset (px):</strong>
                        <input type="number" class="smdp-shadow-y" value="4" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Blur (px):</strong>
                        <input type="number" class="smdp-shadow-blur" value="6" min="0" max="100" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Spread (px):</strong>
                        <input type="number" class="smdp-shadow-spread" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Color:</strong>
                        <input type="text" class="smdp-shadow-color smdp-color-picker" value="#000000" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Opacity (0-1):</strong>
                        <input type="number" class="smdp-shadow-opacity" value="0.15" min="0" max="1" step="0.05" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                  </div>
                  <div style="margin-top:10px;">
                    <small style="color:#666;">Preview: <code class="smdp-shadow-preview" style="background:#fff; padding:3px 6px; border:1px solid #ddd;">0 4px 6px rgba(0,0,0,0.15)</code></small>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </table>

        <h3>Item Title</h3>
        <table class="form-table">
          <tr>
            <th>Title Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[title_color]" value="<?php echo esc_attr($styles['title_color']); ?>" class="smdp-color-picker smdp-item-card-field" data-style="title_color" />
            </td>
          </tr>
          <tr>
            <th>Title Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[title_size]" value="<?php echo esc_attr($styles['title_size']); ?>" min="10" max="40" style="width: 80px;" class="smdp-item-card-field" data-style="title_size" />
            </td>
          </tr>
          <tr>
            <th>Title Font Weight</th>
            <td>
              <select name="<?php echo esc_attr($name); ?>[title_weight]" class="smdp-item-card-field" data-style="title_weight">
                <option value="normal" <?php selected($styles['title_weight'], 'normal'); ?>>Normal</option>
                <option value="bold" <?php selected($styles['title_weight'], 'bold'); ?>>Bold</option>
                <option value="600" <?php selected($styles['title_weight'], '600'); ?>>Semi-Bold (600)</option>
                <option value="500" <?php selected($styles['title_weight'], '500'); ?>>Medium (500)</option>
              </select>
            </td>
          </tr>
        </table>

        <h3>Price</h3>
        <table class="form-table">
          <tr>
            <th>Price Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[price_color]" value="<?php echo esc_attr($styles['price_color']); ?>" class="smdp-color-picker smdp-item-card-field" data-style="price_color" />
            </td>
          </tr>
          <tr>
            <th>Price Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[price_size]" value="<?php echo esc_attr($styles['price_size']); ?>" min="10" max="40" style="width: 80px;" class="smdp-item-card-field" data-style="price_size" />
            </td>
          </tr>
          <tr>
            <th>Price Font Weight</th>
            <td>
              <select name="<?php echo esc_attr($name); ?>[price_weight]" class="smdp-item-card-field" data-style="price_weight">
                <option value="normal" <?php selected($styles['price_weight'], 'normal'); ?>>Normal</option>
                <option value="bold" <?php selected($styles['price_weight'], 'bold'); ?>>Bold</option>
                <option value="600" <?php selected($styles['price_weight'], '600'); ?>>Semi-Bold (600)</option>
                <option value="500" <?php selected($styles['price_weight'], '500'); ?>>Medium (500)</option>
              </select>
            </td>
          </tr>
        </table>

        <h3>Description</h3>
        <table class="form-table">
          <tr>
            <th>Description Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[desc_color]" value="<?php echo esc_attr($styles['desc_color']); ?>" class="smdp-color-picker smdp-item-card-field" data-style="desc_color" />
            </td>
          </tr>
          <tr>
            <th>Description Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[desc_size]" value="<?php echo esc_attr($styles['desc_size']); ?>" min="10" max="24" style="width: 80px;" class="smdp-item-card-field" data-style="desc_size" />
            </td>
          </tr>
        </table>
      </div>

      <!-- Right Column: Live Preview -->
      <div style="position: sticky; top: 20px;">
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
          <h4 style="margin-top: 0; font-size: 14px; text-transform: uppercase; color: #666;">Live Preview</h4>

          <div id="smdp-item-card-preview" style="
            background: <?php echo esc_attr($styles['bg_color']); ?>;
            border: <?php echo esc_attr($styles['border_width']); ?>px solid <?php echo esc_attr($styles['border_color']); ?>;
            border-radius: <?php echo esc_attr($styles['border_radius']); ?>px;
            padding: <?php echo esc_attr($styles['padding']); ?>px;
            box-shadow: <?php echo esc_attr($styles['box_shadow']); ?>;
            position: relative;
          ">
            <h3 id="smdp-item-card-preview-title" style="
              color: <?php echo esc_attr($styles['title_color']); ?>;
              font-size: <?php echo esc_attr($styles['title_size']); ?>px;
              font-weight: <?php echo esc_attr($styles['title_weight']); ?>;
              margin: 0 0 5px;
            ">Burger & Fries</h3>

            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='150'%3E%3Crect fill='%23e0e0e0' width='200' height='150'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='Arial' font-size='14'%3EMenu Image%3C/text%3E%3C/svg%3E" alt="Sample" class="smdp-menu-image" style="width: 100%; height: auto; margin: 5px 0;" />

            <p id="smdp-item-card-preview-desc" style="
              color: <?php echo esc_attr($styles['desc_color']); ?>;
              font-size: <?php echo esc_attr($styles['desc_size']); ?>px;
              margin: 5px 0;
            ">Juicy beef patty with crispy golden fries and our special sauce</p>

            <p id="smdp-item-card-preview-price" style="
              color: <?php echo esc_attr($styles['price_color']); ?>;
              font-size: <?php echo esc_attr($styles['price_size']); ?>px;
              font-weight: <?php echo esc_attr($styles['price_weight']); ?>;
              margin: 5px 0 0;
            "><strong>$12.99</strong></p>
          </div>
        </div>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      // Function to update item card preview
      function updateItemCardPreview() {
        var prefix = 'input[name="<?php echo esc_js($name); ?>';

        var bgColor = $(prefix + '[bg_color]"]').val();
        var borderColor = $(prefix + '[border_color]"]').val();
        var borderWidth = $(prefix + '[border_width]"]').val();
        var borderRadius = $(prefix + '[border_radius]"]').val();
        var padding = $(prefix + '[padding]"]').val();
        var boxShadow = $(prefix + '[box_shadow]"]').val();
        var titleColor = $(prefix + '[title_color]"]').val();
        var titleSize = $(prefix + '[title_size]"]').val();
        var titleWeight = $('select[name="<?php echo esc_js($name); ?>[title_weight]"]').val();
        var priceColor = $(prefix + '[price_color]"]').val();
        var priceSize = $(prefix + '[price_size]"]').val();
        var priceWeight = $('select[name="<?php echo esc_js($name); ?>[price_weight]"]').val();
        var descColor = $(prefix + '[desc_color]"]').val();
        var descSize = $(prefix + '[desc_size]"]').val();

        // Update card container
        $('#smdp-item-card-preview').css({
          'background-color': bgColor,
          'border': borderWidth + 'px solid ' + borderColor,
          'border-radius': borderRadius + 'px',
          'padding': padding + 'px',
          'box-shadow': boxShadow
        });

        // Update title
        $('#smdp-item-card-preview-title').css({
          'color': titleColor,
          'font-size': titleSize + 'px',
          'font-weight': titleWeight
        });

        // Update price
        $('#smdp-item-card-preview-price').css({
          'color': priceColor,
          'font-size': priceSize + 'px',
          'font-weight': priceWeight
        });

        // Update description
        $('#smdp-item-card-preview-desc').css({
          'color': descColor,
          'font-size': descSize + 'px'
        });
      }

      // Update on field changes
      $('.smdp-item-card-field').on('change keyup input', updateItemCardPreview);

      // Listen for color picker changes
      $(document).on('smdp-color-changed', updateItemCardPreview);
    });
    </script>
    <?php
  }

  public static function field_item_detail_styles() {
    $styles = get_option(self::OPT_ITEM_DETAIL_STYLES, array());
    $name = self::OPT_ITEM_DETAIL_STYLES;

    // Default values based on current hardcoded styles in item-detail.js
    $defaults = array(
      'modal_bg' => '#ffffff',
      'modal_border_color' => '#3498db',
      'modal_border_width' => 6,
      'modal_border_radius' => 12,
      'modal_box_shadow' => '0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)',
      'title_color' => '#000000',
      'title_size' => 24,
      'title_weight' => 'bold',
      'price_color' => '#27ae60',
      'price_size' => 19,
      'price_weight' => 'bold',
      'desc_color' => '#666666',
      'desc_size' => 16,
      'close_btn_bg' => '#3498db',
      'close_btn_text' => '#ffffff',
      'close_btn_hover_bg' => '#2980b9',
    );
    $styles = array_merge($defaults, $styles);
    ?>
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start;">
      <!-- Left Column: Form Controls -->
      <div>
        <h3 style="margin-top: 0;">Modal Container</h3>
        <table class="form-table">
          <tr>
            <th>Background Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[modal_bg]" value="<?php echo esc_attr($styles['modal_bg']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="modal_bg" />
              <p class="description">Background color for the modal popup</p>
            </td>
          </tr>
          <tr>
            <th>Border Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[modal_border_color]" value="<?php echo esc_attr($styles['modal_border_color']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="modal_border_color" />
              <p class="description">The blue glow border around the modal</p>
            </td>
          </tr>
          <tr>
            <th>Border Width (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[modal_border_width]" value="<?php echo esc_attr($styles['modal_border_width']); ?>" min="0" max="20" style="width: 80px;" class="smdp-item-detail-field" data-style="modal_border_width" />
            </td>
          </tr>
          <tr>
            <th>Border Radius (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[modal_border_radius]" value="<?php echo esc_attr($styles['modal_border_radius']); ?>" min="0" max="50" style="width: 80px;" class="smdp-item-detail-field" data-style="modal_border_radius" />
              <p class="description">Rounded corners of the modal</p>
            </td>
          </tr>
          <tr>
            <th>Box Shadow</th>
            <td>
              <input type="hidden" name="<?php echo esc_attr($name); ?>[modal_box_shadow]" class="smdp-box-shadow-value smdp-item-detail-field" data-style="modal_box_shadow" value="<?php echo esc_attr($styles['modal_box_shadow']); ?>" />

              <div class="smdp-box-shadow-builder">
                <label style="display:block; margin-bottom:10px;">
                  <strong>Shadow Preset:</strong>
                  <select class="smdp-shadow-preset" style="margin-left:10px;">
                    <option value="none">None</option>
                    <option value="subtle">Subtle (0 1px 3px)</option>
                    <option value="medium">Medium (0 4px 6px)</option>
                    <option value="strong">Strong (0 10px 25px)</option>
                    <option value="custom">Custom</option>
                  </select>
                </label>

                <div class="smdp-shadow-custom" style="display:none; margin-top:15px; padding:15px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
                  <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>X Offset (px):</strong>
                        <input type="number" class="smdp-shadow-x" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Y Offset (px):</strong>
                        <input type="number" class="smdp-shadow-y" value="4" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Blur (px):</strong>
                        <input type="number" class="smdp-shadow-blur" value="6" min="0" max="100" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Spread (px):</strong>
                        <input type="number" class="smdp-shadow-spread" value="0" min="-50" max="50" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Color:</strong>
                        <input type="text" class="smdp-shadow-color smdp-color-picker" value="#000000" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                    <div>
                      <label style="display:block; margin-bottom:5px;">
                        <strong>Opacity (0-1):</strong>
                        <input type="number" class="smdp-shadow-opacity" value="0.15" min="0" max="1" step="0.05" style="width:100%; margin-top:5px;" />
                      </label>
                    </div>
                  </div>
                  <div style="margin-top:10px;">
                    <small style="color:#666;">Preview: <code class="smdp-shadow-preview" style="background:#fff; padding:3px 6px; border:1px solid #ddd;">0 4px 6px rgba(0,0,0,0.15)</code></small>
                  </div>
                  <div style="margin-top:10px; padding:10px; background:#fffbcc; border:1px solid #f0e68c; border-radius:4px;">
                    <small><strong>Tip:</strong> For a glowing effect around the modal, try multiple shadows like: <code>0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)</code></small>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </table>

        <h3>Item Title</h3>
        <table class="form-table">
          <tr>
            <th>Title Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[title_color]" value="<?php echo esc_attr($styles['title_color']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="title_color" />
            </td>
          </tr>
          <tr>
            <th>Title Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[title_size]" value="<?php echo esc_attr($styles['title_size']); ?>" min="10" max="48" style="width: 80px;" class="smdp-item-detail-field" data-style="title_size" />
            </td>
          </tr>
          <tr>
            <th>Title Font Weight</th>
            <td>
              <select name="<?php echo esc_attr($name); ?>[title_weight]" class="smdp-item-detail-field" data-style="title_weight">
                <option value="normal" <?php selected($styles['title_weight'], 'normal'); ?>>Normal</option>
                <option value="bold" <?php selected($styles['title_weight'], 'bold'); ?>>Bold</option>
                <option value="600" <?php selected($styles['title_weight'], '600'); ?>>Semi-Bold (600)</option>
                <option value="500" <?php selected($styles['title_weight'], '500'); ?>>Medium (500)</option>
              </select>
            </td>
          </tr>
        </table>

        <h3>Price</h3>
        <table class="form-table">
          <tr>
            <th>Price Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[price_color]" value="<?php echo esc_attr($styles['price_color']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="price_color" />
            </td>
          </tr>
          <tr>
            <th>Price Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[price_size]" value="<?php echo esc_attr($styles['price_size']); ?>" min="10" max="40" style="width: 80px;" class="smdp-item-detail-field" data-style="price_size" />
            </td>
          </tr>
          <tr>
            <th>Price Font Weight</th>
            <td>
              <select name="<?php echo esc_attr($name); ?>[price_weight]" class="smdp-item-detail-field" data-style="price_weight">
                <option value="normal" <?php selected($styles['price_weight'], 'normal'); ?>>Normal</option>
                <option value="bold" <?php selected($styles['price_weight'], 'bold'); ?>>Bold</option>
                <option value="600" <?php selected($styles['price_weight'], '600'); ?>>Semi-Bold (600)</option>
                <option value="500" <?php selected($styles['price_weight'], '500'); ?>>Medium (500)</option>
              </select>
            </td>
          </tr>
        </table>

        <h3>Description</h3>
        <table class="form-table">
          <tr>
            <th>Description Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[desc_color]" value="<?php echo esc_attr($styles['desc_color']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="desc_color" />
            </td>
          </tr>
          <tr>
            <th>Description Font Size (px)</th>
            <td>
              <input type="number" name="<?php echo esc_attr($name); ?>[desc_size]" value="<?php echo esc_attr($styles['desc_size']); ?>" min="10" max="24" style="width: 80px;" class="smdp-item-detail-field" data-style="desc_size" />
            </td>
          </tr>
        </table>

        <h3>Close Button</h3>
        <table class="form-table">
          <tr>
            <th>Background Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[close_btn_bg]" value="<?php echo esc_attr($styles['close_btn_bg']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="close_btn_bg" />
            </td>
          </tr>
          <tr>
            <th>Text Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[close_btn_text]" value="<?php echo esc_attr($styles['close_btn_text']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="close_btn_text" />
            </td>
          </tr>
          <tr>
            <th>Hover Background Color</th>
            <td>
              <input type="text" name="<?php echo esc_attr($name); ?>[close_btn_hover_bg]" value="<?php echo esc_attr($styles['close_btn_hover_bg']); ?>" class="smdp-color-picker smdp-item-detail-field" data-style="close_btn_hover_bg" />
            </td>
          </tr>
        </table>
      </div>

      <!-- Right Column: Live Preview -->
      <div style="position: sticky; top: 20px;">
        <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
          <h4 style="margin-top: 0; font-size: 14px; text-transform: uppercase; color: #666;">Live Preview</h4>
          <p class="description" style="font-size: 11px; margin-bottom: 15px;">Simplified preview of the modal</p>

          <div id="smdp-item-detail-preview" style="
            background: <?php echo esc_attr($styles['modal_bg']); ?>;
            border: <?php echo esc_attr($styles['modal_border_width']); ?>px solid <?php echo esc_attr($styles['modal_border_color']); ?>;
            border-radius: <?php echo esc_attr($styles['modal_border_radius']); ?>px;
            padding: 20px;
            position: relative;
            box-shadow: <?php echo esc_attr($styles['modal_box_shadow']); ?>;
          ">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='150'%3E%3Crect fill='%23e0e0e0' width='200' height='150'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='Arial' font-size='14'%3EItem Image%3C/text%3E%3C/svg%3E" alt="Sample" style="max-width: 100%; display: block; margin-bottom: 10px;" />

            <h2 id="smdp-item-detail-preview-title" style="
              color: <?php echo esc_attr($styles['title_color']); ?>;
              font-size: <?php echo esc_attr($styles['title_size']); ?>px;
              font-weight: <?php echo esc_attr($styles['title_weight']); ?>;
              margin: 0 0 8px;
            ">Burger & Fries</h2>

            <p id="smdp-item-detail-preview-price" style="
              color: <?php echo esc_attr($styles['price_color']); ?>;
              font-size: <?php echo esc_attr($styles['price_size']); ?>px;
              font-weight: <?php echo esc_attr($styles['price_weight']); ?>;
              margin: 0 0 8px;
            "><strong>$12.99</strong></p>

            <p id="smdp-item-detail-preview-desc" style="
              color: <?php echo esc_attr($styles['desc_color']); ?>;
              font-size: <?php echo esc_attr($styles['desc_size']); ?>px;
              margin: 0 0 16px;
              line-height: 1.5;
            ">Juicy beef patty with crispy golden fries and our special sauce</p>

            <button type="button" id="smdp-item-detail-preview-btn" style="
              background: <?php echo esc_attr($styles['close_btn_bg']); ?>;
              color: <?php echo esc_attr($styles['close_btn_text']); ?>;
              border: none;
              padding: 12px 32px;
              border-radius: 25px;
              font-size: 16px;
              font-weight: 600;
              cursor: pointer;
              transition: all 0.3s ease;
              width: 100%;
            ">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script>
    jQuery(document).ready(function($){
      // Function to update item detail preview
      function updateItemDetailPreview() {
        var prefix = 'input[name="<?php echo esc_js($name); ?>';

        var modalBg = $(prefix + '[modal_bg]"]').val();
        var modalBorderColor = $(prefix + '[modal_border_color]"]').val();
        var modalBorderWidth = $(prefix + '[modal_border_width]"]').val();
        var modalBorderRadius = $(prefix + '[modal_border_radius]"]').val();
        var modalBoxShadow = $(prefix + '[modal_box_shadow]"]').val();
        var titleColor = $(prefix + '[title_color]"]').val();
        var titleSize = $(prefix + '[title_size]"]').val();
        var titleWeight = $('select[name="<?php echo esc_js($name); ?>[title_weight]"]').val();
        var priceColor = $(prefix + '[price_color]"]').val();
        var priceSize = $(prefix + '[price_size]"]').val();
        var priceWeight = $('select[name="<?php echo esc_js($name); ?>[price_weight]"]').val();
        var descColor = $(prefix + '[desc_color]"]').val();
        var descSize = $(prefix + '[desc_size]"]').val();
        var closeBtnBg = $(prefix + '[close_btn_bg]"]').val();
        var closeBtnText = $(prefix + '[close_btn_text]"]').val();
        var closeBtnHoverBg = $(prefix + '[close_btn_hover_bg]"]').val();

        // Update modal container
        $('#smdp-item-detail-preview').css({
          'background-color': modalBg,
          'border': modalBorderWidth + 'px solid ' + modalBorderColor,
          'border-radius': modalBorderRadius + 'px',
          'box-shadow': modalBoxShadow
        });

        // Update title
        $('#smdp-item-detail-preview-title').css({
          'color': titleColor,
          'font-size': titleSize + 'px',
          'font-weight': titleWeight
        });

        // Update price
        $('#smdp-item-detail-preview-price').css({
          'color': priceColor,
          'font-size': priceSize + 'px',
          'font-weight': priceWeight
        });

        // Update description
        $('#smdp-item-detail-preview-desc').css({
          'color': descColor,
          'font-size': descSize + 'px'
        });

        // Update close button
        $('#smdp-item-detail-preview-btn').css({
          'background-color': closeBtnBg,
          'color': closeBtnText
        });

        // Store hover color in data attribute
        $('#smdp-item-detail-preview-btn').data('hover-bg', closeBtnHoverBg);
      }

      // Update on field changes
      $('.smdp-item-detail-field').on('change keyup input', updateItemDetailPreview);

      // Listen for color picker changes
      $(document).on('smdp-color-changed', updateItemDetailPreview);

      // Button hover effect
      $(document).on('mouseenter', '#smdp-item-detail-preview-btn', function(){
        var hoverBg = $(this).data('hover-bg');
        if (hoverBg) {
          $(this).css('background-color', hoverBg);
        }
      });

      $(document).on('mouseleave', '#smdp-item-detail-preview-btn', function(){
        var prefix = 'input[name="<?php echo esc_js($name); ?>';
        var normalBg = $(prefix + '[close_btn_bg]"]').val();
        $(this).css('background-color', normalBg);
      });
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
      // Always add category, even if empty (important for custom categories)
      $name = isset($cat['name']) ? $cat['name'] : 'Category';
      $menu['categories'][] = array('name'=>$name, 'items'=>$items);
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

    // Pass button enable/disable settings to table-setup script
    $settings = get_option(self::OPT_SETTINGS, array());
    $enable_help_btn = isset($settings['enable_help_btn']) ? $settings['enable_help_btn'] : '1';
    $enable_bill_btn = isset($settings['enable_bill_btn']) ? $settings['enable_bill_btn'] : '1';
    $enable_view_bill_btn = isset($settings['enable_view_bill_btn']) ? $settings['enable_view_bill_btn'] : '1';
    $enable_table_badge = isset($settings['enable_table_badge']) ? $settings['enable_table_badge'] : '1';
    $enable_table_selector = isset($settings['enable_table_selector']) ? $settings['enable_table_selector'] : '1';

    wp_localize_script( 'smdp-table-setup', 'smdpButtonSettings', [
      'enableHelp' => $enable_help_btn === '1',
      'enableBill' => $enable_bill_btn === '1',
      'enableViewBill' => $enable_view_bill_btn === '1',
      'enableTableBadge' => $enable_table_badge === '1',
      'enableTableSelector' => $enable_table_selector === '1'
    ]);

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

    // Get modal settings
    $enable_modal_shortcode = isset($settings['enable_modal_shortcode']) ? $settings['enable_modal_shortcode'] : '1';
    $enable_modal_menuapp = isset($settings['enable_modal_menuapp']) ? $settings['enable_modal_menuapp'] : '1';
    $enable_modal_filter = isset($settings['enable_modal_filter']) ? $settings['enable_modal_filter'] : '1';

    // Determine context and modal enabled state
    $is_category_filter = !empty($atts['category']);
    $context = $is_category_filter ? 'filter' : 'menuapp';
    $modal_enabled = $is_category_filter ? $enable_modal_filter : $enable_modal_menuapp;

    ob_start();
    ?>
      <div class="smdp-menu-app-fe layout-<?php echo esc_attr($layout); ?>"
           data-promo-enabled="<?php echo !empty($promo_images) ? '1' : '0'; ?>"
           data-category-filter="<?php echo $is_category_filter ? '1' : '0'; ?>"
           data-context="<?php echo esc_attr($context); ?>"
           data-modal-enabled="<?php echo esc_attr($modal_enabled); ?>">
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
   * Handle reset styles action - resets a specific style option to defaults
   */
  public static function handle_reset_styles() {
    // Verify nonce
    if (!isset($_POST['smdp_reset_nonce']) || !wp_verify_nonce($_POST['smdp_reset_nonce'], 'smdp_reset_styles')) {
      wp_die('Security check failed');
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
      wp_die('Insufficient permissions');
    }

    // Get which option to reset
    $option_to_reset = isset($_POST['reset_option']) ? sanitize_text_field($_POST['reset_option']) : '';

    // Validate it's one of our style options
    $valid_options = array(
      self::OPT_STYLES,
      self::OPT_HELP_BTN_STYLES,
      self::OPT_BG_COLORS,
      self::OPT_ITEM_CARD_STYLES,
      self::OPT_ITEM_DETAIL_STYLES
    );

    if (!in_array($option_to_reset, $valid_options)) {
      wp_die('Invalid option specified');
    }

    // Delete the option to reset to defaults
    delete_option($option_to_reset);

    // Redirect back with success message
    wp_redirect(add_query_arg(array(
      'page' => 'smdp_menu_app_builder',
      'reset_success' => '1',
      'reset_type' => basename($option_to_reset)
    ), admin_url('admin.php')));
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

  /**
   * AJAX handler to save all style sections at once from the Style Generator
   */
  public static function ajax_save_all_styles() {
    try {
      // Verify nonce
      if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'smdp_save_all_styles_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        return;
      }

      // Check user capabilities
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
        return;
      }

      // Get style data
      if ( ! isset( $_POST['style_data'] ) ) {
        wp_send_json_error( 'No style data provided' );
        return;
      }

      $json_string = stripslashes( $_POST['style_data'] );
      $style_data = json_decode( $json_string, true );

      // Check for JSON errors
      if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'JSON parsing error: ' . json_last_error_msg() . ' - First 200 chars: ' . substr($json_string, 0, 200) );
        return;
      }

      if ( ! is_array( $style_data ) ) {
        wp_send_json_error( 'Invalid style data format - not an array' );
        return;
      }

      // Save each option using the existing sanitization callbacks
      $saved_count = 0;

      // Get the registered sanitization callbacks from WordPress
      global $wp_registered_settings;

      // Save Category Buttons
      if ( isset( $style_data[ self::OPT_STYLES ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_STYLES ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_STYLES ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_STYLES ] ) : $style_data[ self::OPT_STYLES ];
        update_option( self::OPT_STYLES, $sanitized );
        $saved_count++;
      }

      // Save Help Buttons
      if ( isset( $style_data[ self::OPT_HELP_BTN_STYLES ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_HELP_BTN_STYLES ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_HELP_BTN_STYLES ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_HELP_BTN_STYLES ] ) : $style_data[ self::OPT_HELP_BTN_STYLES ];
        update_option( self::OPT_HELP_BTN_STYLES, $sanitized );
        $saved_count++;
      }

      // Save Background Colors
      if ( isset( $style_data[ self::OPT_BG_COLORS ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_BG_COLORS ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_BG_COLORS ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_BG_COLORS ] ) : $style_data[ self::OPT_BG_COLORS ];
        update_option( self::OPT_BG_COLORS, $sanitized );
        $saved_count++;
      }

      // Save Item Cards
      if ( isset( $style_data[ self::OPT_ITEM_CARD_STYLES ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_ITEM_CARD_STYLES ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_ITEM_CARD_STYLES ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_ITEM_CARD_STYLES ] ) : $style_data[ self::OPT_ITEM_CARD_STYLES ];
        update_option( self::OPT_ITEM_CARD_STYLES, $sanitized );
        $saved_count++;
      }

      // Save Item Detail
      if ( isset( $style_data[ self::OPT_ITEM_DETAIL_STYLES ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_ITEM_DETAIL_STYLES ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_ITEM_DETAIL_STYLES ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_ITEM_DETAIL_STYLES ] ) : $style_data[ self::OPT_ITEM_DETAIL_STYLES ];
        update_option( self::OPT_ITEM_DETAIL_STYLES, $sanitized );
        $saved_count++;
      }

      // Save Style Generator Preferences
      if ( isset( $style_data[ self::OPT_STYLE_GENERATOR ] ) ) {
        $callback = isset( $wp_registered_settings[ self::OPT_STYLE_GENERATOR ]['sanitize_callback'] )
                    ? $wp_registered_settings[ self::OPT_STYLE_GENERATOR ]['sanitize_callback']
                    : null;
        $sanitized = $callback ? call_user_func( $callback, $style_data[ self::OPT_STYLE_GENERATOR ] ) : $style_data[ self::OPT_STYLE_GENERATOR ];
        update_option( self::OPT_STYLE_GENERATOR, $sanitized );
        $saved_count++;
      }

      if ( $saved_count > 0 ) {
        wp_send_json_success( array(
          'message' => "Successfully saved $saved_count style sections",
          'count' => $saved_count
        ) );
      } else {
        wp_send_json_error( 'No style sections were saved' );
      }
    } catch ( Exception $e ) {
      wp_send_json_error( 'Exception: ' . $e->getMessage() );
    }
  }

  public static function ajax_reset_all_styles() {
    try {
      // Verify nonce
      if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'smdp_reset_all_styles_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
        return;
      }

      // Check user capabilities
      if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
        return;
      }

      // Delete all style options
      $deleted_count = 0;

      if ( delete_option( self::OPT_STYLES ) ) {
        $deleted_count++;
      }

      if ( delete_option( self::OPT_HELP_BTN_STYLES ) ) {
        $deleted_count++;
      }

      if ( delete_option( self::OPT_BG_COLORS ) ) {
        $deleted_count++;
      }

      if ( delete_option( self::OPT_ITEM_CARD_STYLES ) ) {
        $deleted_count++;
      }

      if ( delete_option( self::OPT_ITEM_DETAIL_STYLES ) ) {
        $deleted_count++;
      }

      if ( delete_option( self::OPT_STYLE_GENERATOR ) ) {
        $deleted_count++;
      }

      if ( $deleted_count > 0 ) {
        wp_send_json_success( array(
          'message' => "Successfully reset $deleted_count style sections to defaults",
          'count' => $deleted_count
        ) );
      } else {
        wp_send_json_success( array(
          'message' => 'No custom styles found - already using defaults',
          'count' => 0
        ) );
      }
    } catch ( Exception $e ) {
      wp_send_json_error( 'Exception: ' . $e->getMessage() );
    }
  }
}
