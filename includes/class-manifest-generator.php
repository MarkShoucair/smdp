<?php
/**
 * Manifest Generator
 *
 * Generates dynamic web app manifest.json for PWA installation.
 * Creates table-specific manifests with custom start URLs and names.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Manifest_Generator
 *
 * Handles dynamic manifest.json generation for PWA functionality.
 */
class SMDP_Manifest_Generator {

    /**
     * Singleton instance
     *
     * @var SMDP_Manifest_Generator
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Manifest_Generator
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Intercept manifest request VERY early (before any redirects)
        add_action( 'parse_request', array( $this, 'intercept_manifest_request' ), 1 );

        // Inject manifest link when shortcode is rendered
        // This is more reliable than checking in wp_head
        add_action( 'wp_footer', array( $this, 'inject_manifest_link_footer' ), 1 );
    }

    /**
     * Inject manifest link in footer if menu app was rendered
     *
     * Uses JavaScript to dynamically add manifest to head
     */
    public function inject_manifest_link_footer() {
        // Check if menu app was rendered
        if ( ! defined( 'SMDP_MENU_APP_RENDERED' ) ) {
            return;
        }

        global $post;
        $page_id = $post ? $post->ID : 0;

        // Build base manifest URL
        $manifest_url = home_url( '/smdp-manifest.json' );
        $manifest_url = add_query_arg( array( 'page_id' => $page_id ), $manifest_url );

        // Get settings
        $settings = get_option( 'smdp_app_settings', array() );
        $theme_color = ! empty( $settings['theme_color'] ) ? $settings['theme_color'] : '#5C7BA6';
        $site_name = get_bloginfo( 'name' );

        // Get custom app name or use default
        $app_name = ! empty( $settings['app_name'] ) ? $settings['app_name'] : ( $site_name . ' Menu' );

        // Get Apple Touch Icon if set
        $apple_touch_icon = ! empty( $settings['apple_touch_icon'] ) ? $settings['apple_touch_icon'] : '';

        // Inject manifest and meta tags via JavaScript (to ensure they're in head)
        ?>
        <script>
        (function() {
            // Create and inject manifest link
            var manifestLink = document.createElement('link');
            manifestLink.rel = 'manifest';
            manifestLink.id = 'smdp-manifest-link';
            manifestLink.href = '<?php echo esc_js( $manifest_url ); ?>';

            // Update with table number if available
            var tableNum = localStorage.getItem('smdp_table_number');
            if (tableNum) {
                manifestLink.href += '&table=' + encodeURIComponent(tableNum);
                console.log('[SMDP PWA] Manifest link created with table:', tableNum);
            } else {
                console.log('[SMDP PWA] Manifest link created (no table set yet)');
            }

            document.head.appendChild(manifestLink);

            // Add theme color meta tag
            var themeColorMeta = document.createElement('meta');
            themeColorMeta.name = 'theme-color';
            themeColorMeta.content = '<?php echo esc_js( $theme_color ); ?>';
            document.head.appendChild(themeColorMeta);

            // Add Apple Touch Icon if custom icon is set
            <?php if ( $apple_touch_icon ) : ?>
            var appleTouchIconLink = document.createElement('link');
            appleTouchIconLink.rel = 'apple-touch-icon';
            appleTouchIconLink.href = '<?php echo esc_js( $apple_touch_icon ); ?>';
            document.head.appendChild(appleTouchIconLink);
            <?php endif; ?>

            // Add Apple meta tags for iOS
            var appleMeta1 = document.createElement('meta');
            appleMeta1.name = 'apple-mobile-web-app-capable';
            appleMeta1.content = 'yes';
            document.head.appendChild(appleMeta1);

            var appleMeta2 = document.createElement('meta');
            appleMeta2.name = 'apple-mobile-web-app-status-bar-style';
            appleMeta2.content = 'default';
            document.head.appendChild(appleMeta2);

            var appleMeta3 = document.createElement('meta');
            appleMeta3.name = 'apple-mobile-web-app-title';
            appleMeta3.content = '<?php echo esc_js( $app_name ); ?>';
            document.head.appendChild(appleMeta3);

            console.log('[SMDP PWA] Manifest and meta tags injected into <head>');
        })();
        </script>
        <?php
    }

    /**
     * Intercept manifest request before WordPress routing
     *
     * @param WP $wp WordPress object.
     */
    public function intercept_manifest_request( $wp ) {
        // Check if this is a request for our manifest
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Parse the URI to get just the path (without query string)
        $parsed_uri = parse_url( $request_uri );
        $path = $parsed_uri['path'] ?? '';

        // Check if the path ends with /smdp-manifest.json
        if ( substr( $path, -20 ) === '/smdp-manifest.json' ) {
            $this->serve_manifest();
        }
    }

    /**
     * Serve manifest.json file
     *
     * Generates and serves the web app manifest with proper headers.
     */
    private function serve_manifest() {
        // Get page_id and table from query string
        $page_id = isset( $_GET['page_id'] ) ? intval( $_GET['page_id'] ) : 0;
        $table = isset( $_GET['table'] ) ? sanitize_text_field( $_GET['table'] ) : '';

        // Set proper headers for manifest
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: max-age=3600' ); // Cache for 1 hour

        // Generate and output manifest
        echo wp_json_encode( $this->generate_manifest( $page_id, $table ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    /**
     * Generate manifest data
     *
     * @param int    $page_id Page ID where menu app is rendered.
     * @param string $table   Table number.
     * @return array Manifest data.
     */
    private function generate_manifest( $page_id = 0, $table = '' ) {
        // Get site info
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url( '/' );

        // Determine page URL
        $page_url = $page_id ? get_permalink( $page_id ) : $site_url;

        // Build start URL with table parameter
        $start_url = $page_url;
        if ( $table ) {
            $start_url = add_query_arg( array(
                'table' => $table,
                'pwa'   => '1', // Flag to indicate PWA mode
            ), $page_url );
        }

        // Get settings
        $settings = get_option( 'smdp_app_settings', array() );

        // Colors
        $theme_color = ! empty( $settings['theme_color'] ) ? $settings['theme_color'] : '#5C7BA6';
        $background_color = ! empty( $settings['background_color'] ) ? $settings['background_color'] : '#ffffff';

        // Build app name with custom settings
        $default_name = $site_name . ' Menu';
        $default_short_name = 'Menu';
        $default_description = 'Restaurant menu for ' . $site_name;

        // Use custom app name if provided, otherwise use default
        $custom_app_name = ! empty( $settings['app_name'] ) ? $settings['app_name'] : $default_name;
        $custom_short_name = ! empty( $settings['app_short_name'] ) ? $settings['app_short_name'] : $default_short_name;
        $custom_description = ! empty( $settings['app_description'] ) ? $settings['app_description'] : $default_description;

        // If table is set, append table info to name
        if ( $table ) {
            $app_name = $custom_app_name . ' - Table ' . $table;
            $short_name = 'Table ' . $table;
        } else {
            $app_name = $custom_app_name;
            $short_name = $custom_short_name;
        }

        // Display options
        $display_mode = ! empty( $settings['display_mode'] ) ? $settings['display_mode'] : 'standalone';
        $orientation = ! empty( $settings['orientation'] ) ? $settings['orientation'] : 'any';

        // Get icon URLs
        $icons = $this->get_app_icons();

        // Calculate scope - restrict PWA to menu app page only
        // Scope determines which pages can be part of the PWA
        // Using page URL as scope ensures only menu-app pages are installable
        $scope = trailingslashit( parse_url( $page_url, PHP_URL_PATH ) );

        // Build manifest
        $manifest = array(
            'id'               => $scope,
            'name'             => $app_name,
            'short_name'       => $short_name,
            'description'      => $custom_description,
            'start_url'        => $start_url,
            'display'          => $display_mode,
            'orientation'      => $orientation,
            'background_color' => $background_color,
            'theme_color'      => $theme_color,
            'scope'            => $scope,
            'icons'            => $icons,
        );

        return $manifest;
    }

    /**
     * Get app icon URLs
     *
     * @return array Array of icon objects.
     */
    private function get_app_icons() {
        $icons = array();

        // Check for custom icons in settings
        $settings = get_option( 'smdp_app_settings', array() );

        if ( ! empty( $settings['icon_192'] ) ) {
            $icons[] = array(
                'src'     => $settings['icon_192'],
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            );
        } else {
            // Use default/generated icon
            $icons[] = array(
                'src'     => $this->get_default_icon_url( 192 ),
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            );
        }

        if ( ! empty( $settings['icon_512'] ) ) {
            $icons[] = array(
                'src'     => $settings['icon_512'],
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            );
        } else {
            // Use default/generated icon
            $icons[] = array(
                'src'     => $this->get_default_icon_url( 512 ),
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            );
        }

        return $icons;
    }

    /**
     * Get default icon URL
     *
     * For now, returns a data URI with a simple SVG icon.
     * In production, you'd want to upload actual PNG files.
     *
     * @param int $size Icon size (192 or 512).
     * @return string Icon URL or data URI.
     */
    private function get_default_icon_url( $size ) {
        // Check if we have a site icon (favicon) to use
        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $icon_url = wp_get_attachment_image_url( $site_icon_id, array( $size, $size ) );
            if ( $icon_url ) {
                return $icon_url;
            }
        }

        // Fallback: Create a simple SVG icon as data URI
        $theme_color = get_option( 'smdp_app_settings', array() )['theme_color'] ?? '#5C7BA6';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 512 512">' .
               '<rect width="512" height="512" fill="' . esc_attr( $theme_color ) . '"/>' .
               '<path fill="#ffffff" d="M256 64c-106 0-192 86-192 192s86 192 192 192 192-86 192-192S362 64 256 64zm0 320c-71 0-128-57-128-128S185 128 256 128s128 57 128 128-57 128-128 128z"/>' .
               '<circle fill="#ffffff" cx="256" cy="200" r="32"/>' .
               '<rect fill="#ffffff" x="240" y="250" width="32" height="96" rx="16"/>' .
               '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /**
     * Get manifest URL for current page
     *
     * @param string $table Optional table number.
     * @return string Manifest URL.
     */
    public static function get_manifest_url( $table = '' ) {
        global $post;
        $page_id = $post ? $post->ID : 0;

        $manifest_url = home_url( '/smdp-manifest.json' );
        $args = array( 'page_id' => $page_id );

        if ( $table ) {
            $args['table'] = $table;
        }

        return add_query_arg( $args, $manifest_url );
    }
}
