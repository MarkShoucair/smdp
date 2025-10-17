<?php
/**
 * Temporary test file to debug rewrite rules
 *
 * Access this at: http://menu-app-testing.local/wp-content/plugins/3.0/test-rewrite.php
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo '<h1>SMDP Rewrite Rules Test</h1>';

echo '<h2>1. Flush Rewrite Rules</h2>';
flush_rewrite_rules();
echo '<p style="color:green;">✅ Rewrite rules flushed!</p>';

echo '<h2>2. Current Rewrite Rules</h2>';
global $wp_rewrite;
$rules = get_option('rewrite_rules');

echo '<p>Looking for <code>menu-app</code> rules...</p>';
echo '<pre>';
foreach ($rules as $pattern => $rewrite) {
    if (strpos($pattern, 'menu-app') !== false || strpos($rewrite, 'smdp_menu_app') !== false) {
        echo "<strong>Pattern:</strong> $pattern\n";
        echo "<strong>Rewrite:</strong> $rewrite\n\n";
    }
}
echo '</pre>';

echo '<h2>3. Query Vars</h2>';
global $wp;
echo '<pre>';
print_r($wp->public_query_vars);
echo '</pre>';

echo '<h2>4. Test Links</h2>';
echo '<ul>';
echo '<li><a href="' . home_url('/menu-app/') . '" target="_blank">' . home_url('/menu-app/') . '</a></li>';
echo '<li><a href="' . home_url('/menu-app/table/1/') . '" target="_blank">' . home_url('/menu-app/table/1/') . '</a></li>';
echo '</ul>';

echo '<h2>5. Class Check</h2>';
if (class_exists('SMDP_Standalone_Menu_App')) {
    echo '<p style="color:green;">✅ SMDP_Standalone_Menu_App class exists</p>';

    // Check if hooks are registered
    $init_callbacks = $GLOBALS['wp_filter']['init'] ?? null;
    if ($init_callbacks) {
        echo '<p>Checking for add_rewrite_rules hook...</p>';
        foreach ($init_callbacks as $priority => $hooks) {
            foreach ($hooks as $hook_name => $hook_data) {
                if (is_array($hook_data['function']) && $hook_data['function'][0] instanceof SMDP_Standalone_Menu_App) {
                    echo '<p style="color:green;">✅ Found init hook at priority ' . $priority . '</p>';
                }
            }
        }
    }
} else {
    echo '<p style="color:red;">❌ SMDP_Standalone_Menu_App class NOT found</p>';
}

echo '<hr>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ol>';
echo '<li>Click the test links above to see if they work</li>';
echo '<li>If they still 404, there might be a permalink structure conflict</li>';
echo '<li>Delete this file when done: <code>test-rewrite.php</code></li>';
echo '</ol>';
