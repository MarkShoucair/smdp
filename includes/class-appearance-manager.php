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
		// Enqueue hardcoded structural CSS only - no dynamic CSS
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
	 * REMOVED: Dynamic CSS generation - all styles are now hardcoded in CSS files
	 * This method is kept for reference but no longer hooked
	 */
	private function inject_dynamic_css_DEPRECATED() {
		// This method is deprecated - all styles are now hardcoded in:
		// - assets/css/smdp-structural.css (structural & layout styles)
		// - assets/css/menu-app.css (visual styles)
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
