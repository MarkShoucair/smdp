<?php
/**
 * Protection Settings Class
 *
 * Handles security and protection features for menu app pages
 * All protections are hard-coded and always enabled
 *
 * @package SquareMenuDisplayPWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMDP_Protection_Settings {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Always apply protection on menu app pages
		add_action( 'wp_head', array( $this, 'inject_protection' ), 5 );
	}

	/**
	 * Check if current page is a menu app page
	 *
	 * @return bool
	 */
	private function is_menu_app_page() {
		// Check if this is the standalone menu app page
		if ( isset( $_GET['smdp_app'] ) || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/menu-app' ) !== false ) ) {
			return true;
		}

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
	 * Inject all protection scripts/styles on menu app pages
	 */
	public function inject_protection() {
		if ( ! $this->is_menu_app_page() ) {
			return;
		}

		// Inject protection styles
		$this->inject_protection_styles();

		// Inject protection scripts
		$this->inject_protection_scripts();
	}

	/**
	 * Inject protection styles (hard-coded - always enabled)
	 */
	private function inject_protection_styles() {
		?>
		<style id="smdp-protection-styles">
		/* Disable text selection and callout menus */
		body, * {
			-webkit-user-select: none !important;
			-webkit-touch-callout: none !important;
			user-select: none !important;
		}

		/* Allow selection on specific areas (inputs, editable, custom class) */
		input, textarea, [contenteditable="true"], .allow-select {
			-webkit-user-select: text !important;
			-ms-user-select: text !important;
			user-select: text !important;
		}

		/* Stop long-press menu on images */
		img {
			-webkit-user-drag: none;
			user-drag: none;
			-webkit-touch-callout: none;
		}
		</style>
		<?php
	}

	/**
	 * Inject protection scripts (hard-coded - always enabled)
	 */
	private function inject_protection_scripts() {
		?>
		<script id="smdp-protection-scripts">
		(function() {
			// Prevent pinch zoom (finger pinch) on iPad/iOS Safari/Chrome
			(function () {
				var opts = { passive: false };

				function stop(e) {
					e.preventDefault();
				}

				// iOS Safari still fires these for pinch gestures
				document.addEventListener('gesturestart', stop, opts);
				document.addEventListener('gesturechange', stop, opts);
				document.addEventListener('gestureend', stop, opts);

				// iPadOS trackpad "pinch" arrives as a wheel event with ctrlKey = true
				document.addEventListener('wheel', function (e) {
					if (e.ctrlKey) e.preventDefault();
				}, opts);
			})();

			// Wait for DOM to be ready
			document.addEventListener('DOMContentLoaded', function() {
				// Prevent right-click except in .allow-context
				document.addEventListener("contextmenu", function (e) {
					if (!e.target.closest(".allow-context")) e.preventDefault();
				});

				// Block selection, copy, cut, dragstart (except .allow-select)
				["selectstart", "copy", "cut", "dragstart"].forEach(function(evt){
					document.addEventListener(evt, function(e){
						if (!e.target.closest(".allow-select")) e.preventDefault();
					}, { passive: false });
				});

				// Prevent long-press preview/share on iOS
				document.addEventListener("touchstart", function(e) {
					if (!e.target.closest(".allow-context, .allow-select")) {
						clearTimeout(window._pressTimer);
						window._pressTimer = setTimeout(function() {
							e.preventDefault();
						}, 400);
					}
				}, { passive: false });

				document.addEventListener("touchend", function() {
					clearTimeout(window._pressTimer);
				});

				// Block common shortcuts (Ctrl/Cmd + C, U, S, etc.)
				document.addEventListener("keydown", function(e){
					const k = e.key.toLowerCase();
					if ((e.ctrlKey || e.metaKey) && ["s","u","a","c","x","p"].includes(k)) {
						e.preventDefault();
					}
				});
			});

			console.log('[SMDP Protection] All protection features enabled');
		})();
		</script>
		<?php
	}
}
