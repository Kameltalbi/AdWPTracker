<?php
/**
 * Plugin Name: AdWPtracker - Advanced Ad Manager with Mobile Sticky Footer
 * Plugin URI: https://adwptracker.com
 * Description: Professional WordPress advertising management plugin with unique mobile sticky footer, real-time statistics, device targeting, and advanced analytics
 * Version: 3.6.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Kamel Talbi
 * Author URI: https://kameltalbi.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adwptracker
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>AdWPtracker:</strong> This plugin requires PHP 7.4 or higher. You are using PHP ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Define plugin constants
if (!defined('ADWPT_VERSION')) {
    define('ADWPT_VERSION', '3.6.0');
}
if (!defined('ADWPT_PLUGIN_DIR')) {
    define('ADWPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('ADWPT_PLUGIN_URL')) {
    define('ADWPT_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('ADWPT_PLUGIN_BASENAME')) {
    define('ADWPT_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Load plugin text domain for translations
 */
function adwptracker_load_textdomain() {
    load_plugin_textdomain(
        'adwptracker',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'adwptracker_load_textdomain');

// Require core classes with error handling
$required_files = [
    'includes/class-adwpt-activator.php',
    'includes/class-adwpt-deactivator.php',
    'includes/class-adwpt-admin.php',
    'includes/class-adwpt-frontend.php',
    'includes/class-adwpt-zone.php',
    'includes/class-adwpt-ad.php',
    'includes/class-adwpt-stats.php',
];

foreach ($required_files as $file) {
    $filepath = ADWPT_PLUGIN_DIR . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="error"><p><strong>AdWPtracker:</strong> Fichier manquant : ' . esc_html($file) . '</p></div>';
        });
        return;
    }
}

// Activation hook
register_activation_hook(__FILE__, ['ADWPT_Activator', 'activate']);

// Deactivation hook
register_deactivation_hook(__FILE__, ['ADWPT_Deactivator', 'deactivate']);

/**
 * Main plugin class
 */
class AdWPTracker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if required classes exist
        if (!class_exists('ADWPT_Activator') || 
            !class_exists('ADWPT_Admin') || 
            !class_exists('ADWPT_Frontend') || 
            !class_exists('ADWPT_Zone') || 
            !class_exists('ADWPT_Ad')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>AdWPtracker:</strong> Erreur de chargement des classes. Veuillez réinstaller le plugin.</p></div>';
            });
            return;
        }
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('adwptracker', false, dirname(ADWPT_PLUGIN_BASENAME) . '/languages');
        
        // Initialize admin
        if (is_admin()) {
            ADWPT_Admin::get_instance();
        }
        
        // Initialize frontend
        ADWPT_Frontend::get_instance();
        
        // Initialize custom post types
        ADWPT_Zone::get_instance();
        ADWPT_Ad::get_instance();
        
        // Register helper function for themes
        if (!function_exists('adwptracker_display_zone')) {
            /**
             * Display ad zone - Safe function for use in theme files
             * @param int $zone_id Zone ID
             * @param string $mode Display mode (random or all)
             * @param string $slider Enable slider (auto, yes, no)
             */
            function adwptracker_display_zone($zone_id, $mode = 'random', $slider = 'auto') {
                if (class_exists('ADWPT_Frontend')) {
                    $frontend = ADWPT_Frontend::get_instance();
                    echo $frontend->render_zone_shortcode([
                        'id' => $zone_id, 
                        'mode' => $mode,
                        'slider' => $slider
                    ]);
                }
            }
        }
    }
}

// Initialize plugin only if all classes are loaded
if (class_exists('AdWPTracker')) {
    AdWPTracker::get_instance();
}
