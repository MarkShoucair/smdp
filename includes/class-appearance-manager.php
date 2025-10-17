<?php
/**
 * Appearance Manager Class
 *
 * Handles dynamic CSS generation and custom styling for menu app
 *
 * @package SquareMenuDisplayPWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMDP_Appearance_Manager {

	/**
	 * Settings option name
	 */
	const OPTION_NAME = 'smdp_appearance_settings';

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Only enqueue structural CSS - all styles are now hard-coded
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_structural_css' ) );
	}

	/**
	 * Enqueue structural CSS file
	 */
	public function enqueue_structural_css() {
		// Check if we're on a menu app page
		if ( ! $this->is_menu_app_page() ) {
			return;
		}

		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		wp_enqueue_style(
			'smdp-structural',
			$plugin_url . 'assets/css/smdp-structural.css',
			array(),
			'1.0.0'
		);
	}

	/**
	 * Check if current page is a menu app page
	 *
	 * @return bool
	 */
	private function is_menu_app_page() {
		// Check if the menu app shortcode was rendered
		if ( defined( 'SMDP_MENU_APP_RENDERED' ) && SMDP_MENU_APP_RENDERED ) {
			return true;
		}

		// Fallback: Check if the page has the shortcode
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for shortcode in content
		if ( has_shortcode( $post->post_content, 'smdp_menu_app' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Inject dynamic CSS based on appearance settings
	 */
	public function inject_dynamic_css() {
		if ( ! $this->is_menu_app_page() ) {
			return;
		}

		$settings = $this->get_settings();
		$css = $this->generate_css( $settings );

		if ( ! empty( $css ) ) {
			echo "\n<style id=\"smdp-dynamic-styles\">\n" . $css . "\n</style>\n";
		}

		// Add custom CSS if provided
		if ( ! empty( $settings['custom_css'] ) ) {
			echo "\n<style id=\"smdp-custom-styles\">\n" . wp_strip_all_tags( $settings['custom_css'] ) . "\n</style>\n";
		}
	}

	/**
	 * Generate CSS from settings
	 *
	 * @param array $settings Appearance settings
	 * @return string Generated CSS
	 */
	private function generate_css( $settings ) {
		$css = "/* SMDP Dynamic Styles */\n";

		// Help & Bill Button Styles
		$css .= "\n/* Help & Bill Button Styles */\n";
		$css .= ".smdp-help-btn,\n.smdp-bill-btn {\n";
		$css .= "\tbackground-color: " . esc_attr( $settings['help_btn_bg_color'] ) . ";\n";
		$css .= "\tcolor: " . esc_attr( $settings['help_btn_text_color'] ) . ";\n";
		$css .= "\tborder: none;\n";
		$css .= "\tborder-radius: " . esc_attr( $settings['help_btn_border_radius'] ) . "px;\n";
		$css .= "\tpadding: " . esc_attr( $settings['help_btn_padding_v'] ) . "em " . esc_attr( $settings['help_btn_padding_h'] ) . "em;\n";
		$css .= "\tfont-size: " . esc_attr( $settings['help_btn_font_size'] ) . "rem;\n";
		$css .= "\tfont-weight: " . esc_attr( $settings['help_btn_font_weight'] ) . ";\n";
		$css .= "\tcursor: pointer;\n";
		$css .= "\ttransition: background-color 0.2s ease;\n";
		$css .= "}\n\n";

		$css .= ".smdp-help-btn:hover,\n";
		$css .= ".smdp-help-btn:focus,\n";
		$css .= ".smdp-bill-btn:hover,\n";
		$css .= ".smdp-bill-btn:focus {\n";
		$css .= "\tbackground-color: " . esc_attr( $settings['help_btn_bg_color'] ) . ";\n";
		$css .= "\toutline: none;\n";
		$css .= "}\n\n";

		// Disabled state
		$css .= "/* Disabled Help/Bill Button State */\n";
		$css .= ".smdp-help-btn.smdp-btn-disabled,\n";
		$css .= ".smdp-help-btn:disabled,\n";
		$css .= ".smdp-bill-btn.smdp-bill-disabled,\n";
		$css .= ".smdp-bill-btn:disabled {\n";
		$css .= "\tbackground-color: " . esc_attr( $settings['help_btn_disabled_bg'] ) . ";\n";
		$css .= "\tcolor: " . esc_attr( $settings['help_btn_disabled_text'] ) . ";\n";
		$css .= "\tcursor: not-allowed;\n";
		$css .= "\topacity: 0.8;\n";
		$css .= "}\n\n";

		// Category Button Styles
		$css .= "/* Category Button Styles */\n";
		$css .= ".smdp-cat-btn {\n";
		$css .= "\tbox-sizing: border-box !important;\n";
		$css .= "\tpadding: " . esc_attr( $settings['cat_btn_padding_v'] ) . "px " . esc_attr( $settings['cat_btn_padding_h'] ) . "px !important;\n";
		$css .= "}\n\n";

		$css .= ".smdp-cat-bar {\n";
		$css .= "\tgap: " . esc_attr( $settings['cat_btn_gap'] ) . "px !important;\n";
		$css .= "}\n\n";

		// Left Sidebar Layout Styles
		$css .= "/* Left Sidebar Layout Styles */\n";
		$css .= ".smdp-menu-app-fe.layout-left {\n";
		$css .= "\tgrid-template-columns: " . esc_attr( $settings['left_sidebar_width'] ) . "px 1fr !important;\n";
		$css .= "}\n\n";

		$css .= ".smdp-menu-app-fe.layout-left .smdp-cat-btn {\n";
		$css .= "\tmin-width: " . esc_attr( $settings['left_sidebar_width'] ) . "px !important;\n";
		$css .= "\tpadding-left: " . esc_attr( $settings['left_sidebar_btn_padding'] ) . "px !important;\n";
		$css .= "}\n";

		return $css;
	}

	/**
	 * Get appearance settings with defaults
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			// Help/Bill Buttons - Normal State
			'help_btn_bg_color'       => '#f5fdfc',
			'help_btn_text_color'     => '#00649d',
			'help_btn_border_radius'  => '50',
			'help_btn_padding_v'      => '0.75',
			'help_btn_padding_h'      => '1.5',
			'help_btn_font_size'      => '1',
			'help_btn_font_weight'    => '600',

			// Help/Bill Buttons - Disabled State
			'help_btn_disabled_bg'    => '#e74c3c',
			'help_btn_disabled_text'  => '#ffffff',

			// Category Buttons
			'cat_btn_padding_v'       => '10',
			'cat_btn_padding_h'       => '14',
			'cat_btn_gap'             => '8',

			// Left Sidebar Layout
			'left_sidebar_width'      => '260',
			'left_sidebar_btn_padding' => '20',

			// Custom CSS
			'custom_css'              => '',
		);

		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Sanitize appearance settings
	 *
	 * @param array $input Raw input data
	 * @return array Sanitized data
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = array();

		// Color fields
		$color_fields = array(
			'help_btn_bg_color',
			'help_btn_text_color',
			'help_btn_disabled_bg',
			'help_btn_disabled_text',
		);

		foreach ( $color_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] ) ? sanitize_hex_color( $input[ $field ] ) : '';
		}

		// Numeric fields (with validation ranges)
		$numeric_fields = array(
			'help_btn_border_radius'  => array( 'min' => 0, 'max' => 50, 'default' => 50 ),
			'help_btn_padding_v'      => array( 'min' => 0.5, 'max' => 2, 'default' => 0.75 ),
			'help_btn_padding_h'      => array( 'min' => 1, 'max' => 3, 'default' => 1.5 ),
			'help_btn_font_size'      => array( 'min' => 0.8, 'max' => 1.5, 'default' => 1 ),
			'cat_btn_padding_v'       => array( 'min' => 5, 'max' => 30, 'default' => 10 ),
			'cat_btn_padding_h'       => array( 'min' => 10, 'max' => 40, 'default' => 14 ),
			'cat_btn_gap'             => array( 'min' => 0, 'max' => 20, 'default' => 8 ),
			'left_sidebar_width'      => array( 'min' => 200, 'max' => 400, 'default' => 260 ),
			'left_sidebar_btn_padding' => array( 'min' => 10, 'max' => 40, 'default' => 20 ),
		);

		foreach ( $numeric_fields as $field => $rules ) {
			if ( isset( $input[ $field ] ) ) {
				$value = floatval( $input[ $field ] );
				$value = max( $rules['min'], min( $rules['max'], $value ) );
				$sanitized[ $field ] = $value;
			} else {
				$sanitized[ $field ] = $rules['default'];
			}
		}

		// Font weight (select dropdown)
		$valid_weights = array( '400', '500', '600', '700', '800' );
		$sanitized['help_btn_font_weight'] = ! empty( $input['help_btn_font_weight'] ) && in_array( $input['help_btn_font_weight'], $valid_weights, true )
			? $input['help_btn_font_weight']
			: '600';

		// Custom CSS (allow CSS but strip tags)
		$sanitized['custom_css'] = ! empty( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';

		return $sanitized;
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting(
			'smdp_appearance_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}
}
