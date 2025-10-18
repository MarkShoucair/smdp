<?php
/**
 * Standalone Menu App Page
 *
 * Creates a blank page at /menu-app/ that displays only the menu app
 * without theme headers, footers, or sidebars.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Standalone_Menu_App
 *
 * Handles standalone menu app page rendering.
 */
class SMDP_Standalone_Menu_App {

    /**
     * Singleton instance
     *
     * @var SMDP_Standalone_Menu_App
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Standalone_Menu_App
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
        // Add rewrite rules
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );

        // Add query vars
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

        // Handle template redirect
        add_action( 'template_redirect', array( $this, 'template_redirect' ) );

        // Add admin notice to show the menu app URL
        add_action( 'admin_notices', array( $this, 'show_menu_app_url_notice' ) );
    }

    /**
     * Add rewrite rules for menu app
     */
    public function add_rewrite_rules() {
        // Use centralized method from activation class to ensure consistency
        if ( class_exists( 'SMDP_Plugin_Activation' ) ) {
            SMDP_Plugin_Activation::register_menu_app_rewrite_rules();
        } else {
            // Fallback: Register directly if activation class not loaded
            add_rewrite_rule(
                '^menu-app/?$',
                'index.php?smdp_menu_app=1',
                'top'
            );

            add_rewrite_rule(
                '^menu-app/table/([0-9]+)/?$',
                'index.php?smdp_menu_app=1&smdp_table=$matches[1]',
                'top'
            );
        }
    }

    /**
     * Add query vars
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'smdp_menu_app';
        $vars[] = 'smdp_table';
        return $vars;
    }

    /**
     * Handle template redirect
     */
    public function template_redirect() {
        $is_menu_app = get_query_var( 'smdp_menu_app' );

        if ( ! $is_menu_app ) {
            return;
        }

        // Get table number if provided
        $table = get_query_var( 'smdp_table' );

        // Render blank page with menu app
        $this->render_standalone_page( $table );
        exit;
    }

    /**
     * Render standalone menu app page
     *
     * @param string $table Table number (optional).
     */
    private function render_standalone_page( $table = '' ) {
        // Get settings for colors, etc.
        $settings = get_option( 'smdp_app_settings', array() );
        $theme_color = ! empty( $settings['theme_color'] ) ? $settings['theme_color'] : '#5C7BA6';
        $background_color = ! empty( $settings['background_color'] ) ? $settings['background_color'] : '#ffffff';

        // Generate manifest URL with table parameter if provided
        $manifest_url = home_url( '/smdp-manifest.json' );
        if ( $table ) {
            $manifest_url = add_query_arg( 'table', $table, $manifest_url );
        }

        // Get site info
        $site_name = get_bloginfo( 'name' );
        $app_name = ! empty( $settings['app_name'] ) ? $settings['app_name'] : $site_name . ' Menu';

        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?php echo esc_attr( $theme_color ); ?>">

    <title><?php echo esc_html( $app_name ); ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>">

    <!-- Favicon and Icons -->
    <?php if ( ! empty( $settings['icon_192'] ) ) : ?>
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( $settings['icon_192'] ); ?>">
    <?php endif; ?>

    <?php if ( ! empty( $settings['apple_touch_icon'] ) ) : ?>
    <link rel="apple-touch-icon" href="<?php echo esc_url( $settings['apple_touch_icon'] ); ?>">
    <?php endif; ?>

    <?php
    // Enqueue WordPress scripts and styles that are needed
    wp_head();
    ?>

    <style>
        /* Reset default WordPress/theme styles */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100%;
            overflow: auto;
            background: <?php echo esc_attr( $background_color ); ?>;
            max-width: none !important;
        }

        /* Hide any theme elements that might leak through */
        #wpadminbar {
            display: none !important;
        }

        /* Remove any container constraints */
        .container,
        .site-content,
        .content-area,
        #content,
        #primary,
        .site-main {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Ensure full-screen app experience */
        #smdp-standalone-app {
            width: 100% !important;
            max-width: none !important;
            min-height: 100vh;
            position: relative;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Force menu app to be full width */
        .smdp-menu-app-fe {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* iOS safe area handling */
        @supports (padding: max(0px)) {
            body {
                padding-left: env(safe-area-inset-left);
                padding-right: env(safe-area-inset-right);
            }
        }
    </style>
</head>
<body class="smdp-standalone-menu-app">

    <div id="smdp-standalone-app">
        <?php
        // Render the menu app shortcode
        echo do_shortcode( '[smdp_menu_app]' );
        ?>
    </div>

    <?php
    // Output footer scripts
    wp_footer();
    ?>

    <?php if ( $table ) : ?>
    <script>
        // Auto-set table number if provided in URL
        (function() {
            console.log('[SMDP Standalone] Table number from URL: <?php echo esc_js( $table ); ?>');
            localStorage.setItem('smdp_table_number', '<?php echo esc_js( $table ); ?>');

            // Dispatch event so table-setup.js knows table is set
            document.dispatchEvent(new CustomEvent('smdp-table-set', {
                detail: { table: '<?php echo esc_js( $table ); ?>' }
            }));
        })();
    </script>
    <?php endif; ?>

</body>
</html>
        <?php
    }

    /**
     * Show admin notice with menu app URL
     */
    public function show_menu_app_url_notice() {
        $screen = get_current_screen();

        // Only show on plugin admin pages
        if ( ! $screen || strpos( $screen->id, 'smdp' ) === false ) {
            return;
        }

        $menu_app_url = home_url( '/menu-app/' );
        $menu_app_url_with_table = home_url( '/menu-app/table/1/' );

        ?>
        <div class="notice notice-info">
            <p><strong>📱 Standalone Menu App URLs:</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>
                    <strong>Base URL:</strong>
                    <a href="<?php echo esc_url( $menu_app_url ); ?>" target="_blank">
                        <?php echo esc_html( $menu_app_url ); ?>
                    </a>
                    <em>(User sets table number)</em>
                </li>
                <li>
                    <strong>With Table Number:</strong>
                    <a href="<?php echo esc_url( $menu_app_url_with_table ); ?>" target="_blank">
                        <?php echo esc_html( $menu_app_url_with_table ); ?>
                    </a>
                    <em>(Replace "1" with desired table)</em>
                </li>
            </ul>
            <p><em>These URLs display the menu app without your theme's header/footer. Use for PWA installation or kiosk mode.</em></p>
        </div>
        <?php
    }

    /**
     * Get standalone menu app URL
     *
     * @param int $table Optional table number.
     * @return string
     */
    public static function get_menu_app_url( $table = null ) {
        if ( $table ) {
            return home_url( '/menu-app/table/' . absint( $table ) . '/' );
        }
        return home_url( '/menu-app/' );
    }
}

// Initialize the class
SMDP_Standalone_Menu_App::instance();
