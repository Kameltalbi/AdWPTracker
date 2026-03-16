<?php
/**
 * Admin class
 * Handles all admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_filter('redirect_post_location', [$this, 'redirect_after_publish'], 10, 2);
        add_action('admin_notices', [$this, 'show_publish_notice']);
        
        // Custom columns for ads
        add_filter('manage_adwpt_ad_posts_columns', [$this, 'set_ad_columns']);
        add_action('manage_adwpt_ad_posts_custom_column', [$this, 'render_ad_columns'], 10, 2);
        
        // Export CSV handler
        add_action('admin_init', [$this, 'handle_export_csv']);
    }
    
    /**
     * Show notice after publishing
     */
    public function show_publish_notice() {
        if (isset($_GET['published']) && $_GET['published'] === '1') {
            // Verify nonce for security
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'adwpt_publish_notice')) {
                return;
            }
            
            $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
            
            if ($post_type === 'adwpt_ad') {
                $message = '✅ Annonce publiée avec succès !';
            } elseif ($post_type === 'adwpt_zone') {
                $message = '✅ Zone publiée avec succès !';
            } else {
                return;
            }
            
            echo '<div class="notice notice-success is-dismissible" style="border-left: 4px solid #10b981; padding: 12px 16px;">
                    <p style="margin: 0; font-weight: 600;">' . esc_html($message) . '</p>
                  </div>';
        }
    }
    
    /**
     * Redirect to list page after publishing
     */
    public function redirect_after_publish($location, $post_id) {
        $post = get_post($post_id);
        
        // Only redirect for our post types
        if (!in_array($post->post_type, ['adwpt_ad', 'adwpt_zone'])) {
            return $location;
        }
        
        // Only redirect after publish action
        if (!isset($_POST['publish']) && !isset($_POST['save'])) {
            return $location;
        }
        
        // Create nonce for the notice
        $nonce = wp_create_nonce('adwpt_publish_notice');
        
        // Redirect to list page with success message
        if ($post->post_type === 'adwpt_ad') {
            return add_query_arg(['published' => '1', '_wpnonce' => $nonce], admin_url('edit.php?post_type=adwpt_ad'));
        } elseif ($post->post_type === 'adwpt_zone') {
            return add_query_arg(['published' => '1', '_wpnonce' => $nonce], admin_url('edit.php?post_type=adwpt_zone'));
        }
        
        return $location;
    }
    
    /**
     * Set custom columns for ads
     */
    public function set_ad_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            // Add shortcode column after title
            if ($key === 'title') {
                $new_columns['shortcode'] = __('Shortcode', 'adwptracker');
            }
        }
        
        // Add other columns
        $new_columns['zone'] = __('Zone', 'adwptracker');
        $new_columns['type'] = __('Type', 'adwptracker');
        $new_columns['device'] = __('Appareil', 'adwptracker');
        $new_columns['status'] = __('Status', 'adwptracker');
        
        return $new_columns;
    }
    
    /**
     * Render custom columns for ads
     */
    public function render_ad_columns($column, $post_id) {
        switch ($column) {
            case 'shortcode':
                $shortcode = '[adwptracker_ad id="' . $post_id . '"]';
                ?>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <code id="shortcode-<?php echo esc_attr($post_id); ?>" style="background: #1f2937; color: #10b981; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; cursor: pointer;" onclick="copyShortcode(<?php echo esc_js($post_id); ?>)" title="Cliquer pour copier">
                        <?php echo esc_html($shortcode); ?>
                    </code>
                    <span id="copied-<?php echo esc_attr($post_id); ?>" style="display: none; color: #10b981; font-size: 12px;">✓ Copié</span>
                </div>
                <script>
                function copyShortcode(id) {
                    var code = document.getElementById('shortcode-' + id);
                    var text = code.textContent;
                    
                    // Use modern Clipboard API with fallback
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            showCopiedMessage(id);
                        }).catch(function() {
                            fallbackCopy(text, id);
                        });
                    } else {
                        fallbackCopy(text, id);
                    }
                }
                
                function fallbackCopy(text, id) {
                    var input = document.createElement('input');
                    input.value = text;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    try {
                        document.execCommand('copy');
                        showCopiedMessage(id);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                    document.body.removeChild(input);
                }
                
                function showCopiedMessage(id) {
                    var copied = document.getElementById('copied-' + id);
                    copied.style.display = 'inline';
                    setTimeout(function() {
                        copied.style.display = 'none';
                    }, 2000);
                }
                </script>
                <?php
                break;
                
            case 'zone':
                $zone_id = get_post_meta($post_id, '_adwpt_zone_id', true);
                if ($zone_id) {
                    $zone = get_post($zone_id);
                    if ($zone) {
                        echo '<a href="' . get_edit_post_link($zone_id) . '">' . esc_html($zone->post_title) . '</a>';
                    } else {
                        echo '<span style="color: #999;">—</span>';
                    }
                } else {
                    echo '<span style="color: #999;">Non assignée</span>';
                }
                break;
                
            case 'type':
                $type = get_post_meta($post_id, '_adwpt_type', true) ?: 'image';
                $types = [
                    'image' => '🖼️ Image',
                    'html' => '💻 HTML',
                    'text' => '📝 Texte',
                    'video' => '🎥 Vidéo'
                ];
                echo isset($types[$type]) ? $types[$type] : $type;
                break;
                
            case 'device':
                $device = get_post_meta($post_id, '_adwpt_device', true) ?: 'all';
                $devices = [
                    'all' => '🌐 Tous',
                    'desktop' => '🖥️ Desktop',
                    'mobile' => '📱 Mobile',
                    'tablet' => '📱 Tablette'
                ];
                echo isset($devices[$device]) ? $devices[$device] : $device;
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
                if ($status === 'active') {
                    echo '<span class="adwpt-badge-active" style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #d1fae5; color: #065f46;">Active</span>';
                } else {
                    echo '<span class="adwpt-badge-inactive" style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #991b1b;">Inactive</span>';
                }
                break;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu with modern icon
        add_menu_page(
            __('AdWPtracker', 'adwptracker'),
            __('AdWPtracker', 'adwptracker'),
            'manage_options',
            'adwptracker',
            [$this, 'render_dashboard'],
            'dashicons-chart-area',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'adwptracker',
            __('Dashboard', 'adwptracker'),
            __('📊 Dashboard', 'adwptracker'),
            'manage_options',
            'adwptracker',
            [$this, 'render_dashboard']
        );
        
        // === GESTION DES CONTENUS ===
        
        // Liste des Annonces & Zones (shortcodes centralisés)
        add_submenu_page(
            'adwptracker',
            __('Liste des Annonces & Zones', 'adwptracker'),
            __('📋 Liste des Annonces & Zones', 'adwptracker'),
            'manage_options',
            'adwptracker-liste',
            [$this, 'render_liste_page']
        );
        
        // Separator
        add_submenu_page(
            'adwptracker',
            '',
            '<span style="display:block; margin: 5px 0; border-top: 1px solid #ddd;"></span>',
            'manage_options',
            '#'
        );
        
        // Ads management
        add_submenu_page(
            'adwptracker',
            __('Annonces', 'adwptracker'),
            __('📢 Annonces', 'adwptracker'),
            'manage_options',
            'edit.php?post_type=adwpt_ad'
        );
        
        // Add new ad
        add_submenu_page(
            'adwptracker',
            __('Nouvelle Annonce', 'adwptracker'),
            __('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✨ Nouvelle Annonce', 'adwptracker'),
            'manage_options',
            'post-new.php?post_type=adwpt_ad'
        );
        
        // Zones management
        add_submenu_page(
            'adwptracker',
            __('Zones', 'adwptracker'),
            __('🎯 Zones', 'adwptracker'),
            'manage_options',
            'edit.php?post_type=adwpt_zone'
        );
        
        // Add new zone
        add_submenu_page(
            'adwptracker',
            __('Nouvelle Zone', 'adwptracker'),
            __('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;✨ Nouvelle Zone', 'adwptracker'),
            'manage_options',
            'post-new.php?post_type=adwpt_zone'
        );
        
        // === STATISTIQUES ===
        
        // Separator
        add_submenu_page(
            'adwptracker',
            '',
            '<span style="display:block; margin: 5px 0; border-top: 1px solid #ddd;"></span>',
            'manage_options',
            '#'
        );
        
        // Statistics
        add_submenu_page(
            'adwptracker',
            __('Statistiques', 'adwptracker'),
            __('📈 Statistiques', 'adwptracker'),
            'manage_options',
            'adwptracker-stats',
            [$this, 'render_stats_page']
        );
        
        // === CONFIGURATION ===
        
        // Separator
        add_submenu_page(
            'adwptracker',
            '',
            '<span style="display:block; margin: 5px 0; border-top: 1px solid #ddd;"></span>',
            'manage_options',
            '#'
        );
        
        // Settings
        add_submenu_page(
            'adwptracker',
            __('Paramètres', 'adwptracker'),
            __('⚙️ Paramètres', 'adwptracker'),
            'manage_options',
            'adwptracker-settings',
            [$this, 'render_settings_page']
        );
        
        // === AIDE ===
        
        // Separator
        add_submenu_page(
            'adwptracker',
            '',
            '<span style="display:block; margin: 5px 0; border-top: 1px solid #ddd;"></span>',
            'manage_options',
            '#'
        );
        
        // Documentation
        add_submenu_page(
            'adwptracker',
            __('Documentation', 'adwptracker'),
            __('📖 Documentation', 'adwptracker'),
            'manage_options',
            'adwptracker-docs',
            [$this, 'render_docs_page']
        );
        
        // Support
        add_submenu_page(
            'adwptracker',
            __('Support', 'adwptracker'),
            __('💬 Support', 'adwptracker'),
            'manage_options',
            'adwptracker-support',
            [$this, 'render_support_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Load media uploader on ad/zone edit pages
        global $post_type;
        if (in_array($post_type, ['adwpt_zone', 'adwpt_ad'])) {
            wp_enqueue_media();
        }
        
        if (strpos($hook, 'adwptracker') === false && 
            !in_array(get_post_type(), ['adwpt_zone', 'adwpt_ad'])) {
            return;
        }
        
        wp_enqueue_style(
            'adwptracker-admin',
            ADWPT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADWPT_VERSION
        );
        
        // Enqueue admin menu styling
        wp_enqueue_style(
            'adwptracker-admin-menu',
            ADWPT_PLUGIN_URL . 'assets/css/admin-menu.css',
            [],
            ADWPT_VERSION
        );
        
        // Dashboard modern CSS
        if (strpos($hook, 'adwptracker') !== false) {
            wp_enqueue_style(
                'adwptracker-dashboard',
                ADWPT_PLUGIN_URL . 'assets/css/dashboard.css',
                [],
                ADWPT_VERSION
            );
            
            // Premium dashboard design
            wp_enqueue_style(
                'adwptracker-dashboard-premium',
                ADWPT_PLUGIN_URL . 'assets/css/dashboard-premium.css',
                ['adwptracker-dashboard'],
                ADWPT_VERSION
            );
            
            // Chart.js for graphs
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );
        }
        
        wp_enqueue_script(
            'adwptracker-admin',
            ADWPT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            ADWPT_VERSION,
            true
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        if (!class_exists('ADWPT_Dashboard')) {
            require_once ADWPT_PLUGIN_DIR . 'includes/class-adwpt-dashboard.php';
        }
        ADWPT_Dashboard::render();
        return;
        
        if (!class_exists('ADWPT_Stats')) {
            echo '<div class="wrap"><h1>Erreur</h1><p>La classe ADWPT_Stats n\'est pas chargée.</p></div>';
            return;
        }
        
        $stats = ADWPT_Stats::get_instance();
        $summary = $stats->get_summary_stats();
        
        // Get current page
        $current_page = isset($_GET['page']) ? $_GET['page'] : 'adwptracker';
        
        ?>
        <!-- Horizontal Menu -->
        <div class="adwpt-horizontal-menu">
            <div class="adwpt-menu-container">
                <div class="adwpt-menu-logo">
                    <span class="adwpt-menu-logo-icon">📊</span>
                    <span>AdWPtracker</span>
                </div>
                
                <nav class="adwpt-menu-nav">
                    <a href="<?php echo admin_url('admin.php?page=adwptracker'); ?>" class="adwpt-menu-item <?php echo $current_page === 'adwptracker' ? 'active' : ''; ?>">
                        📊 Dashboard
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=adwpt_ad'); ?>" class="adwpt-menu-item">
                        📢 Annonces
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=adwpt_zone'); ?>" class="adwpt-menu-item">
                        🎯 Zones
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=adwptracker-stats'); ?>" class="adwpt-menu-item">
                        📈 Statistiques
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=adwptracker-settings'); ?>" class="adwpt-menu-item">
                        ⚙️ Paramètres
                    </a>
                </nav>
                
                <div class="adwpt-menu-actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="adwpt-btn-primary">
                        + Nouvelle Annonce
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="adwpt-dashboard-wrap">
            <div class="adwpt-dashboard-container">
                
                <!-- Header with Gradient -->
                <div class="adwpt-dashboard-header-premium">
                    <div class="adwpt-header-content">
                        <div class="adwpt-header-text">
                            <h1 class="adwpt-dashboard-title-premium">
                                <?php esc_html_e('Welcome Back', 'adwptracker'); ?> 👋
                            </h1>
                            <p class="adwpt-dashboard-subtitle-premium">
                                <?php esc_html_e('Track your advertising performance in real-time', 'adwptracker'); ?>
                            </p>
                        </div>
                        <div class="adwpt-header-actions">
                            <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="adwpt-btn-gradient">
                                <span class="adwpt-btn-icon">✨</span>
                                <?php esc_html_e('Create New Ad', 'adwptracker'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=adwptracker-stats'); ?>" class="adwpt-btn-outline">
                                <span class="adwpt-btn-icon">📊</span>
                                <?php esc_html_e('Full Report', 'adwptracker'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid Premium -->
                <div class="adwpt-stats-grid-premium">
                    <!-- Impressions Card -->
                    <div class="adwpt-stat-card-premium impressions-card">
                        <div class="adwpt-stat-icon-large">
                            <div class="adwpt-icon-circle blue-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="adwpt-stat-content">
                            <span class="adwpt-stat-label-premium"><?php esc_html_e('Total Impressions', 'adwptracker'); ?></span>
                            <div class="adwpt-stat-value-premium"><?php echo number_format_i18n($summary['total_impressions']); ?></div>
                            <div class="adwpt-stat-trend positive">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5l4 4H9v5H7v-5H4l4-4z"/>
                                </svg>
                                <span>+12.5%</span>
                                <span class="trend-period"><?php esc_html_e('vs last week', 'adwptracker'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Clicks Card -->
                    <div class="adwpt-stat-card-premium clicks-card">
                        <div class="adwpt-stat-icon-large">
                            <div class="adwpt-icon-circle green-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 11l3 3L22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            </div>
                        </div>
                        <div class="adwpt-stat-content">
                            <span class="adwpt-stat-label-premium"><?php esc_html_e('Total Clicks', 'adwptracker'); ?></span>
                            <div class="adwpt-stat-value-premium"><?php echo number_format_i18n($summary['total_clicks']); ?></div>
                            <div class="adwpt-stat-trend positive">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5l4 4H9v5H7v-5H4l4-4z"/>
                                </svg>
                                <span>+8.3%</span>
                                <span class="trend-period"><?php esc_html_e('vs last week', 'adwptracker'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CTR Card -->
                    <div class="adwpt-stat-card-premium ctr-card">
                        <div class="adwpt-stat-icon-large">
                            <div class="adwpt-icon-circle purple-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                </svg>
                            </div>
                        </div>
                        <div class="adwpt-stat-content">
                            <span class="adwpt-stat-label-premium"><?php esc_html_e('Average CTR', 'adwptracker'); ?></span>
                            <div class="adwpt-stat-value-premium"><?php echo number_format($summary['average_ctr'], 2); ?>%</div>
                            <div class="adwpt-stat-trend neutral">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M2 8h12"/>
                                </svg>
                                <span>-0.3%</span>
                                <span class="trend-period"><?php esc_html_e('vs last week', 'adwptracker'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Ads Card -->
                    <div class="adwpt-stat-card-premium active-ads-card">
                        <div class="adwpt-stat-icon-large">
                            <div class="adwpt-icon-circle orange-gradient">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                        </div>
                        <div class="adwpt-stat-content">
                            <span class="adwpt-stat-label-premium"><?php esc_html_e('Active Ads', 'adwptracker'); ?></span>
                            <div class="adwpt-stat-value-premium"><?php echo number_format_i18n($summary['active_ads']); ?></div>
                            <div class="adwpt-stat-trend neutral">
                                <span><?php 
                                $total_ads = wp_count_posts('adwpt_ad');
                                printf(esc_html__('of %s total', 'adwptracker'), $total_ads->publish);
                                ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <div class="adwpt-content-grid">
                    
                    <!-- Performance Chart -->
                    <div class="adwpt-card">
                        <div class="adwpt-card-header">
                            <h2 class="adwpt-card-title">Performance (7 derniers jours)</h2>
                            <a href="<?php echo admin_url('admin.php?page=adwptracker-stats'); ?>" class="adwpt-card-action">
                                Voir tout →
                            </a>
                        </div>
                        <div class="adwpt-chart-container">
                            <canvas id="adwptPerformanceChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        
                        <!-- Top Ads -->
                        <div class="adwpt-card">
                            <div class="adwpt-card-header">
                                <h2 class="adwpt-card-title">Top Annonces</h2>
                                <a href="<?php echo admin_url('edit.php?post_type=adwpt_ad'); ?>" class="adwpt-card-action">
                                    Voir tout →
                                </a>
                            </div>
                            <div class="adwpt-top-list">
                                <?php
                                $top_ads = get_posts([
                                    'post_type' => 'adwpt_ad',
                                    'posts_per_page' => 5,
                                    'post_status' => 'publish',
                                    'meta_query' => [
                                        [
                                            'key' => '_adwpt_status',
                                            'value' => 'active',
                                            'compare' => '='
                                        ]
                                    ]
                                ]);
                                
                                if (!empty($top_ads)) {
                                    $rank = 1;
                                    foreach ($top_ads as $ad) {
                                        $ad_stats = $stats->get_ad_stats($ad->ID);
                                        $impressions = 0;
                                        $clicks = 0;
                                        
                                        if ($ad_stats && is_array($ad_stats)) {
                                            $impressions = isset($ad_stats['impressions']) ? (int)$ad_stats['impressions'] : 0;
                                            $clicks = isset($ad_stats['clicks']) ? (int)$ad_stats['clicks'] : 0;
                                        } elseif ($ad_stats && is_object($ad_stats)) {
                                            $impressions = isset($ad_stats->impressions) ? (int)$ad_stats->impressions : 0;
                                            $clicks = isset($ad_stats->clicks) ? (int)$ad_stats->clicks : 0;
                                        }
                                        ?>
                                        <div class="adwpt-top-item">
                                            <div class="adwpt-top-rank"><?php echo $rank; ?></div>
                                            <div class="adwpt-top-info">
                                                <p class="adwpt-top-name"><?php echo esc_html($ad->post_title); ?></p>
                                                <p class="adwpt-top-meta"><?php echo number_format_i18n($impressions); ?> impressions</p>
                                            </div>
                                            <div class="adwpt-top-value">
                                                <?php echo number_format_i18n($clicks); ?>
                                                <span style="font-size: 12px; color: #6B7280; font-weight: 400;">clics</span>
                                            </div>
                                        </div>
                                        <?php
                                        $rank++;
                                    }
                                } else {
                                    echo '<p style="color: #6B7280; text-align: center; padding: 20px 0;">Aucune annonce active</p>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="adwpt-card">
                            <div class="adwpt-card-header">
                                <h2 class="adwpt-card-title">Actions Rapides</h2>
                            </div>
                            <div class="adwpt-quick-actions">
                                <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="adwpt-quick-action">
                                    <div class="adwpt-quick-action-icon">+</div>
                                    <div class="adwpt-quick-action-content">
                                        <p class="adwpt-quick-action-title">Nouvelle Annonce</p>
                                        <p class="adwpt-quick-action-desc">Créer une nouvelle publicité</p>
                                    </div>
                                </a>
                                
                                <a href="<?php echo admin_url('post-new.php?post_type=adwpt_zone'); ?>" class="adwpt-quick-action">
                                    <div class="adwpt-quick-action-icon">🎯</div>
                                    <div class="adwpt-quick-action-content">
                                        <p class="adwpt-quick-action-title">Nouvelle Zone</p>
                                        <p class="adwpt-quick-action-desc">Définir un emplacement</p>
                                    </div>
                                </a>
                                
                                <a href="<?php echo admin_url('admin.php?page=adwptracker-stats'); ?>" class="adwpt-quick-action">
                                    <div class="adwpt-quick-action-icon">📊</div>
                                    <div class="adwpt-quick-action-content">
                                        <p class="adwpt-quick-action-title">Voir les Stats</p>
                                        <p class="adwpt-quick-action-desc">Rapport détaillé</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </div>
        
        <?php
        // Get last 7 days stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'adwptracker_stats';
        
        $last_7_days = [];
        $impressions_data = [];
        $clicks_data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $day_name = date('D', strtotime("-$i days"));
            
            // Translate day names to French
            $day_fr = [
                'Mon' => 'Lun',
                'Tue' => 'Mar', 
                'Wed' => 'Mer',
                'Thu' => 'Jeu',
                'Fri' => 'Ven',
                'Sat' => 'Sam',
                'Sun' => 'Dim'
            ];
            
            $last_7_days[] = isset($day_fr[$day_name]) ? $day_fr[$day_name] : $day_name;
            
            // Get impressions for this day
            $impressions = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE type = 'impression' 
                AND DATE(created_at) = %s",
                $date
            ));
            $impressions_data[] = (int)$impressions;
            
            // Get clicks for this day
            $clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                WHERE type = 'click' 
                AND DATE(created_at) = %s",
                $date
            ));
            $clicks_data[] = (int)$clicks;
        }
        ?>
        
        <!-- Chart.js Script -->
        <script>
        jQuery(document).ready(function($) {
            const ctx = document.getElementById('adwptPerformanceChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($last_7_days); ?>,
                        datasets: [{
                            label: 'Impressions',
                            data: <?php echo json_encode($impressions_data); ?>,
                            borderColor: '#0066FF',
                            backgroundColor: 'rgba(0, 102, 255, 0.05)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }, {
                            label: 'Clicks',
                            data: <?php echo json_encode($clicks_data); ?>,
                            borderColor: '#00D924',
                            backgroundColor: 'rgba(0, 217, 36, 0.05)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    boxWidth: 12,
                                    boxHeight: 12,
                                    borderRadius: 6,
                                    useBorderRadius: true,
                                    padding: 15,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1A1A1A',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#E5E7EB',
                                borderWidth: 1,
                                padding: 12,
                                boxPadding: 6,
                                usePointStyle: true,
                                bodyFont: {
                                    size: 13
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#F7F9FC',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    color: '#6B7280'
                                }
                            },
                            x: {
                                grid: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    color: '#6B7280'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render stats page
     */
    public function render_stats_page() {
        if (!class_exists('ADWPT_Stats')) {
            echo '<div class="wrap"><h1>Erreur</h1><p>La classe ADWPT_Stats n\'est pas chargée.</p></div>';
            return;
        }
        
        $stats = ADWPT_Stats::get_instance();
        $all_stats = $stats->get_stats();
        $summary = $stats->get_summary_stats();
        
        ?>
        <div class="wrap">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h1 style="margin: 0;"><?php esc_html_e('Detailed Statistics', 'adwptracker'); ?></h1>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?adwptracker_export=csv'), 'adwptracker_export_csv'); ?>" 
                   class="button button-primary" 
                   style="background: #00D924; border-color: #00D924; display: inline-flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px;">📥</span>
                    <?php esc_html_e('Export CSV', 'adwptracker'); ?>
                </a>
            </div>
            
            <!-- Summary Cards -->
            <div class="adwpt-stats-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px; font-size: 13px; color: #646970;">Total Impressions</h3>
                    <p style="font-size: 24px; font-weight: 600; margin: 0; color: #1d2327;"><?php echo number_format_i18n($summary['total_impressions']); ?></p>
                </div>
                <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px; font-size: 13px; color: #646970;">Total Clics</h3>
                    <p style="font-size: 24px; font-weight: 600; margin: 0; color: #1d2327;"><?php echo number_format_i18n($summary['total_clicks']); ?></p>
                </div>
                <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px; font-size: 13px; color: #646970;">CTR Moyen</h3>
                    <p style="font-size: 24px; font-weight: 600; margin: 0; color: #1d2327;"><?php echo number_format($summary['average_ctr'], 2); ?>%</p>
                </div>
            </div>
            
            <h2><?php esc_html_e('Statistics by Ad', 'adwptracker'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'adwptracker'); ?></th>
                        <th><?php esc_html_e('Ad', 'adwptracker'); ?></th>
                        <th><?php esc_html_e('Zone', 'adwptracker'); ?></th>
                        <th><?php esc_html_e('Impressions', 'adwptracker'); ?></th>
                        <th><?php esc_html_e('Clicks', 'adwptracker'); ?></th>
                        <th><?php esc_html_e('CTR %', 'adwptracker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_stats)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px 20px;">
                                <p style="font-size: 16px; color: #646970; margin: 0 0 10px;">
                                    📊 <?php esc_html_e('No statistics available yet', 'adwptracker'); ?>
                                </p>
                                <p style="color: #999; margin: 0;">
                                    <?php esc_html_e('Les statistiques apparaîtront dès que vos annonces commenceront à être affichées.', 'adwptracker'); ?>
                                </p>
                                <p style="margin: 15px 0 0;">
                                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_zone'); ?>" class="button button-primary">
                                        <?php esc_html_e('Create Zone', 'adwptracker'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="button button-primary">
                                        <?php esc_html_e('Create Ad', 'adwptracker'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_stats as $stat): ?>
                            <tr>
                                <td><?php echo esc_html($stat['ad_id']); ?></td>
                                <td>
                                    <?php 
                                    $ad_title = get_the_title($stat['ad_id']);
                                    if ($ad_title) {
                                        echo '<a href="' . get_edit_post_link($stat['ad_id']) . '">' . esc_html($ad_title) . '</a>';
                                    } else {
                                        echo esc_html__('Sans titre', 'adwptracker');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $zone_title = get_the_title($stat['zone_id']);
                                    if ($zone_title) {
                                        echo '<a href="' . get_edit_post_link($stat['zone_id']) . '">' . esc_html($zone_title) . '</a>';
                                    } else {
                                        echo esc_html__('Sans zone', 'adwptracker');
                                    }
                                    ?>
                                </td>
                                <td><strong><?php echo number_format_i18n($stat['impressions']); ?></strong></td>
                                <td><strong><?php echo number_format_i18n($stat['clicks']); ?></strong></td>
                                <td><strong style="color: <?php echo $stat['ctr'] > 2 ? '#28a745' : '#666'; ?>"><?php echo number_format($stat['ctr'], 2); ?>%</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if (!empty($all_stats)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <p style="margin: 0; font-size: 13px;">
                        💡 <strong>Astuce:</strong> Un bon CTR se situe généralement entre 2% et 5%. 
                        Si votre CTR est faible, essayez de changer l'image ou le texte de votre annonce.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Meta box for ads
        add_meta_box(
            'adwpt_ad_settings',
            __('Paramètres de l\'annonce', 'adwptracker'),
            [$this, 'render_ad_meta_box'],
            'adwpt_ad',
            'normal',
            'high'
        );
        
        // Meta box for zones
        add_meta_box(
            'adwpt_zone_settings',
            __('Zone Settings', 'adwptracker'),
            [$this, 'render_zone_meta_box'],
            'adwpt_zone',
            'normal',
            'high'
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!class_exists('ADWPT_Settings')) {
            require_once ADWPT_PLUGIN_DIR . 'includes/class-adwpt-settings.php';
        }
        ADWPT_Settings::render();
        return;
        ?>
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 32px;">⚙️</span>
                <span style="font-weight: 600;"><?php esc_html_e('Paramètres', 'adwptracker'); ?></span>
            </h1>
            
            <?php
            // Handle form submission
            if (isset($_POST['adwpt_settings_submit'])) {
                check_admin_referer('adwpt_settings_nonce');
                
                // Save all settings
                update_option('adwpt_tracking_enabled', isset($_POST['tracking_enabled']) ? '1' : '0');
                update_option('adwpt_notification_email', sanitize_email($_POST['notification_email']));
                update_option('adwpt_cache_enabled', isset($_POST['cache_enabled']) ? '1' : '0');
                update_option('adwpt_cache_duration', intval($_POST['cache_duration']));
                update_option('adwpt_lazy_load', isset($_POST['lazy_load']) ? '1' : '0');
                update_option('adwpt_auto_optimize', isset($_POST['auto_optimize']) ? '1' : '0');
                update_option('adwpt_gdpr_mode', isset($_POST['gdpr_mode']) ? '1' : '0');
                update_option('adwpt_data_retention', intval($_POST['data_retention']));
                
                echo '<div class="notice notice-success is-dismissible" style="border-left: 4px solid #10b981;"><p><strong>✅ ' . __('Paramètres enregistrés avec succès !', 'adwptracker') . '</strong></p></div>';
            }
            
            // Handle reset stats
            if (isset($_POST['adwpt_reset_stats'])) {
                check_admin_referer('adwpt_reset_stats_nonce');
                
                global $wpdb;
                $table_name = $wpdb->prefix . 'adwpt_stats';
                $wpdb->query("TRUNCATE TABLE $table_name");
                
                echo '<div class="notice notice-success is-dismissible" style="border-left: 4px solid #10b981;"><p><strong>✅ ' . __('Statistiques réinitialisées avec succès !', 'adwptracker') . '</strong></p></div>';
            }
            
            // Handle export settings
            if (isset($_POST['adwpt_export_settings'])) {
                check_admin_referer('adwpt_export_settings_nonce');
                
                $settings = [
                    'tracking_enabled' => get_option('adwpt_tracking_enabled', '1'),
                    'notification_email' => get_option('adwpt_notification_email'),
                    'cache_enabled' => get_option('adwpt_cache_enabled', '0'),
                    'cache_duration' => get_option('adwpt_cache_duration', '3600'),
                    'lazy_load' => get_option('adwpt_lazy_load', '0'),
                    'auto_optimize' => get_option('adwpt_auto_optimize', '0'),
                    'gdpr_mode' => get_option('adwpt_gdpr_mode', '0'),
                    'data_retention' => get_option('adwpt_data_retention', '90'),
                ];
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="adwptracker-settings-' . date('Y-m-d') . '.json"');
                echo json_encode($settings, JSON_PRETTY_PRINT);
                exit;
            }
            
            // Get current settings
            $tracking_enabled = get_option('adwpt_tracking_enabled', '1');
            $notification_email = get_option('adwpt_notification_email', get_option('admin_email'));
            $cache_enabled = get_option('adwpt_cache_enabled', '0');
            $cache_duration = get_option('adwpt_cache_duration', '3600');
            $lazy_load = get_option('adwpt_lazy_load', '0');
            $auto_optimize = get_option('adwpt_auto_optimize', '0');
            $gdpr_mode = get_option('adwpt_gdpr_mode', '0');
            $data_retention = get_option('adwpt_data_retention', '90');
            
            // Get database stats
            global $wpdb;
            $stats_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adwpt_stats");
            $db_size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->prefix}adwpt_stats'");
            ?>
            
            <div style="max-width: 900px; margin-top: 20px;">
                
                <!-- General Settings -->
                <div class="adwpt-widget" style="margin-bottom: 20px;">
                    <h2>🎛️ <?php esc_html_e('General Settings', 'adwptracker'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('adwpt_settings_nonce'); ?>
                        
                        <table class="form-table">
                            <!-- Tracking Enable/Disable -->
                            <tr>
                                <th scope="row">
                                    <label for="tracking_enabled">
                                        📊 <?php esc_html_e('Statistics Tracking', 'adwptracker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" 
                                               name="tracking_enabled" 
                                               id="tracking_enabled" 
                                               value="1" 
                                               <?php checked($tracking_enabled, '1'); ?>
                                               style="width: 18px; height: 18px;">
                                        <span style="font-weight: 500;">
                                            <?php esc_html_e('Enable impressions and clicks tracking', 'adwptracker'); ?>
                                        </span>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Disable this to stop collecting statistics (not recommended)', 'adwptracker'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Email Notifications -->
                            <tr>
                                <th scope="row">
                                    <label for="notification_email">
                                        📧 <?php esc_html_e('Notification Email', 'adwptracker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" 
                                           name="notification_email" 
                                           id="notification_email" 
                                           value="<?php echo esc_attr($notification_email); ?>" 
                                           class="regular-text"
                                           style="padding: 8px;">
                                    <p class="description">
                                        <?php esc_html_e('Email address for weekly statistics reports (coming soon)', 'adwptracker'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Dark Mode -->
                            <tr>
                                <th scope="row">
                                    <label for="dark_mode">
                                        🌙 <?php esc_html_e('Dark Mode', 'adwptracker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" 
                                               name="dark_mode" 
                                               id="dark_mode" 
                                               value="1" 
                                               <?php checked($dark_mode, '1'); ?>
                                               style="width: 18px; height: 18px;">
                                        <span style="font-weight: 500;">
                                            <?php esc_html_e('Enable dark mode for admin dashboard', 'adwptracker'); ?>
                                        </span>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Switch to dark theme for better viewing at night', 'adwptracker'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" 
                                    name="adwpt_settings_submit" 
                                    class="button button-primary button-large"
                                    style="padding: 10px 30px; font-size: 14px;">
                                💾 <?php esc_html_e('Save Settings', 'adwptracker'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <!-- Statistics Management -->
                <div class="adwpt-widget" style="margin-bottom: 20px; border: 2px solid #dc3545;">
                    <h2 style="color: #dc3545;">⚠️ <?php esc_html_e('Danger Zone', 'adwptracker'); ?></h2>
                    
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Are you sure? This will delete ALL statistics permanently!', 'adwptracker')); ?>');">
                        <?php wp_nonce_field('adwpt_reset_stats_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    🗑️ <?php esc_html_e('Reset Statistics', 'adwptracker'); ?>
                                </th>
                                <td>
                                    <p style="margin-bottom: 15px; color: #721c24;">
                                        <strong><?php esc_html_e('Warning:', 'adwptracker'); ?></strong>
                                        <?php esc_html_e('This will permanently delete all impressions and clicks data. This action cannot be undone!', 'adwptracker'); ?>
                                    </p>
                                    <button type="submit" 
                                            name="adwpt_reset_stats" 
                                            class="button button-secondary"
                                            style="background: #dc3545; color: white; border-color: #dc3545; padding: 8px 20px;">
                                        🗑️ <?php esc_html_e('Reset All Statistics', 'adwptracker'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                
                <!-- Plugin Info -->
                <div class="adwpt-widget">
                    <h2>ℹ️ <?php esc_html_e('Plugin Information', 'adwptracker'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Version', 'adwptracker'); ?></th>
                            <td><strong><?php echo ADWPT_VERSION; ?></strong></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Status', 'adwptracker'); ?></th>
                            <td><span style="color: #155724; font-weight: bold;">✅ <?php esc_html_e('Full Version', 'adwptracker'); ?></span></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Features', 'adwptracker'); ?></th>
                            <td>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li>✅ <?php esc_html_e('Unlimited Zones & Ads', 'adwptracker'); ?></li>
                                    <li>✅ <?php esc_html_e('Real-time Statistics', 'adwptracker'); ?></li>
                                    <li>✅ <?php esc_html_e('Mobile Sticky Footer', 'adwptracker'); ?></li>
                                    <li>✅ <?php esc_html_e('Device Targeting', 'adwptracker'); ?></li>
                                    <li>✅ <?php esc_html_e('CSV Export', 'adwptracker'); ?></li>
                                    <li>✅ <?php esc_html_e('4 Ad Types', 'adwptracker'); ?></li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                </div>
                
            </div>
        </div>
        
        <?php if ($dark_mode === '1'): ?>
        <style>
            /* Simple Dark Mode for Dashboard */
            .adwpt-widget {
                background: #1e293b !important;
                color: #e2e8f0 !important;
            }
            .adwpt-widget h2 {
                color: #f1f5f9 !important;
            }
            .form-table th,
            .form-table td {
                color: #e2e8f0 !important;
            }
            .description {
                color: #94a3b8 !important;
            }
        </style>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Show limit notices for FREE version
     */
    /**
     * Render documentation page
     */
    public function render_docs_page() {
        ?>
        <div class="wrap">
            <h1>📖 <?php esc_html_e('Documentation', 'adwptracker'); ?></h1>
            
            <div style="max-width: 900px;">
                <div class="adwpt-widget" style="margin-top: 20px;">
                    <h2>🚀 <?php esc_html_e('Quick Start Guide', 'adwptracker'); ?></h2>
                    
                    <h3>1. Créer une Zone</h3>
                    <p>AdWPtracker → Zones → Nouvelle zone</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Choisir un nom (ex: "Header", "Sidebar")</li>
                        <li>Sélectionner le format (ex: Leaderboard 728×90)</li>
                        <li>Configurer le mode d'affichage (Random ou Toutes)</li>
                        <li>Activer/désactiver le slider</li>
                    </ul>
                    
                    <h3>2. Créer des Annonces</h3>
                    <p>AdWPtracker → Annonces → Nouvelle annonce</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Choisir un titre</li>
                        <li>Sélectionner la zone</li>
                        <li>Upload une image ou code HTML</li>
                        <li>Ajouter un lien (optionnel)</li>
                        <li>Définir les dates (optionnel)</li>
                    </ul>
                    
                    <h3>3. Afficher sur le site</h3>
                    <p><strong>Dans ton thème (header.php, sidebar.php, etc.) :</strong></p>
                    <code style="display: block; background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0;">
&lt;?php<br>
if (function_exists('adwptracker_display_zone')) {<br>
&nbsp;&nbsp;&nbsp;&nbsp;adwptracker_display_zone(1); // ID de la zone<br>
}<br>
?&gt;
                    </code>
                    
                    <p><strong>Ou en shortcode :</strong></p>
                    <code style="display: block; background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0;">
[adwptracker_zone id="1"]
                    </code>
                </div>
                
                <div class="adwpt-widget" style="margin-top: 20px;">
                    <h2>📐 <?php esc_html_e('Available Formats', 'adwptracker'); ?></h2>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Format</th>
                                <th>Dimensions</th>
                                <th>Usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Responsive</td><td>100% × auto</td><td>Mobile-first</td></tr>
                            <tr><td>Leaderboard</td><td>728 × 90</td><td>Header desktop</td></tr>
                            <tr><td>Medium Rectangle</td><td>300 × 250</td><td>Content</td></tr>
                            <tr><td>Large Rectangle</td><td>336 × 280</td><td>Content</td></tr>
                            <tr><td>Wide Skyscraper</td><td>160 × 600</td><td>Sidebar</td></tr>
                            <tr><td>Half Page</td><td>300 × 600</td><td>Sidebar</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render liste complète page
     */
    public function render_liste_page() {
        if (!class_exists('ADWPT_Liste')) {
            require_once ADWPT_PLUGIN_DIR . 'includes/class-adwpt-liste.php';
        }
        ADWPT_Liste::render_page();
    }
    
    /**
     * Render support page
     */
    public function render_support_page() {
        ?>
        <div class="wrap">
            <h1>💬 <?php esc_html_e('Support', 'adwptracker'); ?></h1>
            
            <div class="adwpt-widget" style="max-width: 800px; margin-top: 20px;">
                <h2><?php esc_html_e('Besoin d\'aide ?', 'adwptracker'); ?></h2>
                
                <h3>📧 Contact</h3>
                <p><?php esc_html_e('Pour toute question ou problème, contactez le développeur.', 'adwptracker'); ?></p>
                
                <h3>🐛 Rapport de bug</h3>
                <p><?php esc_html_e('Si vous rencontrez un bug, merci de fournir :', 'adwptracker'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>Version de WordPress</li>
                    <li>Version du plugin : <strong>v<?php echo ADWPT_VERSION; ?></strong></li>
                    <li>Thème utilisé</li>
                    <li>Description du problème</li>
                    <li>Capture d'écran si possible</li>
                </ul>
                
                <h3>💡 Suggestions</h3>
                <p><?php esc_html_e('Vos suggestions d\'amélioration sont les bienvenues !', 'adwptracker'); ?></p>
                
                <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white;">
                    <h3 style="margin-top: 0; color: white;"><?php esc_html_e('Informations système', 'adwptracker'); ?></h3>
                    <p><strong>Version du plugin :</strong> <?php echo ADWPT_VERSION; ?></p>
                    <p><strong>Version WordPress :</strong> <?php echo get_bloginfo('version'); ?></p>
                    <p><strong>Version PHP :</strong> <?php echo PHP_VERSION; ?></p>
                    <p><strong>Thème actif :</strong> <?php echo wp_get_theme()->get('Name'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render ad meta box
     */
    public function render_ad_meta_box($post) {
        wp_nonce_field('adwpt_ad_meta_box', 'adwpt_ad_meta_box_nonce');
        
        $type = get_post_meta($post->ID, '_adwpt_type', true) ?: 'image';
        $image_url = get_post_meta($post->ID, '_adwpt_image_url', true);
        $html_code = get_post_meta($post->ID, '_adwpt_html_code', true);
        $text_title = get_post_meta($post->ID, '_adwpt_text_title', true);
        $text_content = get_post_meta($post->ID, '_adwpt_text_content', true);
        $video_url = get_post_meta($post->ID, '_adwpt_video_url', true);
        $video_type = get_post_meta($post->ID, '_adwpt_video_type', true) ?: 'youtube';
        $link_url = get_post_meta($post->ID, '_adwpt_link_url', true);
        $link_target = get_post_meta($post->ID, '_adwpt_link_target', true) ?: '_blank';
        $zone_id = get_post_meta($post->ID, '_adwpt_zone_id', true);
        $status = get_post_meta($post->ID, '_adwpt_status', true) ?: 'active';
        $start_date = get_post_meta($post->ID, '_adwpt_start_date', true);
        $end_date = get_post_meta($post->ID, '_adwpt_end_date', true);
        
        // New options
        $show_on_mobile = get_post_meta($post->ID, '_adwpt_show_on_mobile', true) !== '0';
        $show_on_desktop = get_post_meta($post->ID, '_adwpt_show_on_desktop', true) !== '0';
        $sticky_enabled = get_post_meta($post->ID, '_adwpt_sticky_enabled', true);
        $sticky_position = get_post_meta($post->ID, '_adwpt_sticky_position', true) ?: 'top';
        
        $zones = get_posts([
            'post_type' => 'adwpt_zone',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="adwpt_type"><?php esc_html_e('Type d\'annonce', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_type" id="adwpt_type" class="regular-text">
                        <option value="image" <?php selected($type, 'image'); ?>>🖼️ <?php esc_html_e('Image', 'adwptracker'); ?></option>
                        <option value="html" <?php selected($type, 'html'); ?>>💻 <?php esc_html_e('HTML/Code', 'adwptracker'); ?></option>
                        <option value="text" <?php selected($type, 'text'); ?>>📝 <?php esc_html_e('Text', 'adwptracker'); ?></option>
                        <option value="video" <?php selected($type, 'video'); ?>>🎥 <?php esc_html_e('Video', 'adwptracker'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose advertising content type', 'adwptracker'); ?></p>
                </td>
            </tr>
            
            <tr class="adwpt-image-field">
                <th><label for="adwpt_image_url"><?php esc_html_e('URL de l\'image', 'adwptracker'); ?></label></th>
                <td>
                    <input type="url" name="adwpt_image_url" id="adwpt_image_url" value="<?php echo esc_attr($image_url); ?>" class="regular-text">
                    <button type="button" class="button adwpt-upload-image"><?php esc_html_e('Upload', 'adwptracker'); ?></button>
                    <?php if ($image_url): ?>
                        <div class="adwpt-image-preview" style="margin-top: 10px;">
                            <img src="<?php echo esc_url($image_url); ?>" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px; display: block;">
                            <p class="description" id="adwpt-image-dimensions">Chargement des dimensions...</p>
                        </div>
                        <script>
                        jQuery(document).ready(function($) {
                            var img = new Image();
                            img.onload = function() {
                                $('#adwpt-image-dimensions').text('Dimensions: ' + this.width + ' × ' + this.height + ' pixels');
                            };
                            img.src = '<?php echo esc_js($image_url); ?>';
                        });
                        </script>
                    <?php endif; ?>
                    <p class="description">
                        💡 <strong>Formats recommandés :</strong><br>
                        <select id="adwpt_format_helper" style="margin-top: 5px;">
                            <option value="">-- Choisir un format standard --</option>
                            <option value="728x90">Leaderboard (728×90) - Desktop Header</option>
                            <option value="320x50">Mobile Banner (320×50) - Mobile/Sticky</option>
                            <option value="300x250">Medium Rectangle (300×250) - Sidebar</option>
                            <option value="336x280">Large Rectangle (336×280) - Content</option>
                            <option value="468x60">Banner (468×60) - Header/Footer</option>
                            <option value="970x90">Large Leaderboard (970×90) - Top</option>
                            <option value="160x600">Wide Skyscraper (160×600) - Sidebar</option>
                            <option value="300x600">Half Page (300×600) - Sidebar</option>
                        </select>
                        <span style="display: block; margin-top: 5px; color: #666; font-size: 12px;">
                            Sélectionnez un format pour voir les dimensions recommandées. Format WebP ou JPG conseillé.
                        </span>
                    </p>
                    <script>
                    jQuery(document).ready(function($) {
                        $('#adwpt_format_helper').on('change', function() {
                            var format = $(this).val();
                            if (format) {
                                var desc = 'Format sélectionné: ' + format + ' pixels';
                                if (format === '320x50') {
                                    desc += ' (Idéal pour sticky footer mobile)';
                                } else if (format === '728x90') {
                                    desc += ' (Standard desktop header)';
                                }
                                $(this).next('span').html('<strong style="color: #0066FF;">✓ ' + desc + '</strong>');
                            }
                        });
                    });
                    </script>
                </td>
            </tr>
            
            <tr class="adwpt-html-field">
                <th><label for="adwpt_html_code"><?php esc_html_e('HTML Code', 'adwptracker'); ?></label></th>
                <td>
                    <textarea name="adwpt_html_code" id="adwpt_html_code" rows="10" class="large-text"><?php echo esc_textarea($html_code); ?></textarea>
                    <p class="description"><?php esc_html_e('HTML/JavaScript code (e.g., Google AdSense)', 'adwptracker'); ?></p>
                </td>
            </tr>
            
            <!-- Champs Texte -->
            <tr class="adwpt-text-field">
                <th><label for="adwpt_text_title"><?php esc_html_e('Title', 'adwptracker'); ?></label></th>
                <td>
                    <input type="text" name="adwpt_text_title" id="adwpt_text_title" value="<?php echo esc_attr($text_title); ?>" class="regular-text" placeholder="Ex: Promotion -50%">
                </td>
            </tr>
            
            <tr class="adwpt-text-field">
                <th><label for="adwpt_text_content"><?php esc_html_e('Content', 'adwptracker'); ?></label></th>
                <td>
                    <textarea name="adwpt_text_content" id="adwpt_text_content" rows="4" class="large-text" placeholder="Texte de votre annonce..."><?php echo esc_textarea($text_content); ?></textarea>
                    <p class="description"><?php esc_html_e('Texte descriptif de l\'annonce', 'adwptracker'); ?></p>
                </td>
            </tr>
            
            <!-- Champs Vidéo -->
            <tr class="adwpt-video-field">
                <th><label for="adwpt_video_type"><?php esc_html_e('Video Type', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_video_type" id="adwpt_video_type" class="regular-text">
                        <option value="youtube" <?php selected($video_type, 'youtube'); ?>>YouTube</option>
                        <option value="vimeo" <?php selected($video_type, 'vimeo'); ?>>Vimeo</option>
                        <option value="mp4" <?php selected($video_type, 'mp4'); ?>>MP4 (fichier)</option>
                    </select>
                </td>
            </tr>
            
            <tr class="adwpt-video-field">
                <th><label for="adwpt_video_url"><?php esc_html_e('Video URL', 'adwptracker'); ?></label></th>
                <td>
                    <input type="url" name="adwpt_video_url" id="adwpt_video_url" value="<?php echo esc_attr($video_url); ?>" class="regular-text" placeholder="https://youtube.com/watch?v=...">
                    <button type="button" class="button adwpt-upload-video" style="margin-left: 5px;">
                        <?php esc_html_e('📹 Télécharger MP4', 'adwptracker'); ?>
                    </button>
                    <?php if ($video_url && $video_type === 'mp4'): ?>
                        <div class="adwpt-video-preview" style="margin-top: 10px;">
                            <video controls style="max-width: 400px; height: auto; border: 1px solid #ddd;">
                                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                            </video>
                        </div>
                    <?php endif; ?>
                    <p class="description">
                        <strong>YouTube:</strong> https://youtube.com/watch?v=ID<br>
                        <strong>Vimeo:</strong> https://vimeo.com/ID<br>
                        <strong>MP4:</strong> URL complète du fichier .mp4 ou utilisez le bouton pour uploader
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_link_url"><?php esc_html_e('Destination URL', 'adwptracker'); ?></label></th>
                <td>
                    <input type="url" name="adwpt_link_url" id="adwpt_link_url" value="<?php echo esc_attr($link_url); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_link_target"><?php esc_html_e('Link Target', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_link_target" id="adwpt_link_target">
                        <option value="_blank" <?php selected($link_target, '_blank'); ?>><?php esc_html_e('New Tab', 'adwptracker'); ?></option>
                        <option value="_self" <?php selected($link_target, '_self'); ?>><?php esc_html_e('Même onglet', 'adwptracker'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_zone_id"><?php esc_html_e('Zone', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_zone_id" id="adwpt_zone_id" class="regular-text">
                        <option value=""><?php esc_html_e('Select a zone', 'adwptracker'); ?></option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?php echo esc_attr($zone->ID); ?>" <?php selected($zone_id, $zone->ID); ?>>
                                <?php echo esc_html($zone->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_status"><?php esc_html_e('Status', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_status" id="adwpt_status">
                        <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'adwptracker'); ?></option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'adwptracker'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_start_date"><?php esc_html_e('Start Date', 'adwptracker'); ?></label></th>
                <td>
                    <input type="date" name="adwpt_start_date" id="adwpt_start_date" value="<?php echo esc_attr($start_date); ?>">
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_end_date"><?php esc_html_e('End Date', 'adwptracker'); ?></label></th>
                <td>
                    <input type="date" name="adwpt_end_date" id="adwpt_end_date" value="<?php echo esc_attr($end_date); ?>">
                </td>
            </tr>
            
            <!-- ============================================
                 DISPLAY OPTIONS - Clear & Professional
                 ============================================ -->
            <tr>
                <th colspan="2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; color: white;">
                    <strong style="font-size: 14px;">📱 <?php esc_html_e('Display Options', 'adwptracker'); ?></strong>
                </th>
            </tr>
            
            <!-- Device Targeting -->
            <tr>
                <th style="vertical-align: top; padding-top: 15px;">
                    <label><?php esc_html_e('Device Display', 'adwptracker'); ?></label>
                </th>
                <td>
                    <div style="background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                        <label style="display: flex; align-items: center; margin-bottom: 12px; cursor: pointer; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">
                            <input type="checkbox" name="adwpt_show_on_desktop" value="1" <?php checked($show_on_desktop, true); ?> style="margin: 0 10px 0 0; width: 18px; height: 18px;">
                            <span style="font-size: 24px; margin-right: 10px;">💻</span>
                            <span style="font-weight: 500;"><?php esc_html_e('Show on Desktop', 'adwptracker'); ?></span>
                            <span style="margin-left: auto; color: #6b7280; font-size: 12px;">(≥1024px)</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">
                            <input type="checkbox" name="adwpt_show_on_mobile" value="1" <?php checked($show_on_mobile, true); ?> style="margin: 0 10px 0 0; width: 18px; height: 18px;">
                            <span style="font-size: 24px; margin-right: 10px;">📱</span>
                            <span style="font-weight: 500;"><?php esc_html_e('Show on Mobile/Tablet', 'adwptracker'); ?></span>
                            <span style="margin-left: auto; color: #6b7280; font-size: 12px;">(<1024px)</span>
                        </label>
                        
                        <p class="description" style="margin: 12px 0 0 0; color: #6b7280;">
                            💡 <?php esc_html_e('Control where your ad appears', 'adwptracker'); ?>
                        </p>
                    </div>
                </td>
            </tr>
            
            <!-- Sticky Position -->
            <tr>
                <th style="vertical-align: top; padding-top: 15px;">
                    <label><?php esc_html_e('Sticky Mode', 'adwptracker'); ?></label>
                </th>
                <td>
                    <div style="background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 15px;">
                            <input type="checkbox" name="adwpt_sticky_enabled" value="1" <?php checked($sticky_enabled, '1'); ?> style="margin: 0 10px 0 0; width: 18px; height: 18px;">
                            <span style="font-size: 24px; margin-right: 10px;">📌</span>
                            <span style="font-weight: 500;"><?php esc_html_e('Enable Sticky', 'adwptracker'); ?></span>
                        </label>
                        
                        <label style="display: block; font-weight: 500; margin-bottom: 8px;">
                            <?php esc_html_e('Position', 'adwptracker'); ?>:
                        </label>
                        <select name="adwpt_sticky_position" id="adwpt_sticky_position" style="width: 100%; padding: 8px; border-radius: 6px;">
                            <option value="top" <?php selected($sticky_position, 'top'); ?>>⬆️ <?php esc_html_e('Top', 'adwptracker'); ?> (<?php esc_html_e('all devices', 'adwptracker'); ?>)</option>
                            <option value="bottom" <?php selected($sticky_position, 'bottom'); ?>>⬇️ <?php esc_html_e('Bottom', 'adwptracker'); ?> (<?php esc_html_e('mobile only', 'adwptracker'); ?> ⭐)</option>
                        </select>
                        
                        <div style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #e0f2fe 0%, #e0e7ff 100%); border-left: 4px solid #3b82f6; border-radius: 6px;">
                            <strong style="color: #1e40af;">💡 <?php esc_html_e('Mobile Sticky Footer Setup', 'adwptracker'); ?>:</strong>
                            <ul style="margin: 8px 0 0 20px; color: #1e3a8a; font-size: 13px;">
                                <li>✅ <?php esc_html_e('Recommended size', 'adwptracker'); ?>: <strong>320×50px</strong></li>
                                <li>✅ <?php esc_html_e('Uncheck', 'adwptracker'); ?>: "<?php esc_html_e('Show on Desktop', 'adwptracker'); ?>"</li>
                                <li>✅ <?php esc_html_e('Check', 'adwptracker'); ?>: "<?php esc_html_e('Show on Mobile/Tablet', 'adwptracker'); ?>"</li>
                                <li>✅ <?php esc_html_e('Position', 'adwptracker'); ?>: "<?php esc_html_e('Bottom', 'adwptracker'); ?>"</li>
                            </ul>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #1e40af;">
                                🚀 <?php esc_html_e('Ad will automatically appear in mobile footer', 'adwptracker'); ?>!
                            </p>
                        </div>
                    </div>
                </td>
            </tr>
            
            <!-- Shortcode Annonce -->
            <tr style="background: #f0f6fc;">
                <th><?php esc_html_e('Shortcode annonce', 'adwptracker'); ?></th>
                <td>
                    <div style="background: #1f2937; color: #10b981; padding: 12px 16px; border-radius: 6px; font-family: monospace; display: inline-block;">
                        <code style="color: #10b981;">[adwptracker_ad id="<?php echo esc_attr($post->ID); ?>"]</code>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Utilisez ce shortcode pour afficher cette annonce spécifique', 'adwptracker'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            var typeSelect = $('#adwpt_type');
            var imageField = $('.adwpt-image-field');
            var htmlField = $('.adwpt-html-field');
            var textField = $('.adwpt-text-field');
            var videoField = $('.adwpt-video-field');
            
            function toggleFields() {
                // Cacher tous les champs
                imageField.hide();
                htmlField.hide();
                textField.hide();
                videoField.hide();
                
                // Afficher selon le type
                var type = typeSelect.val();
                if (type === 'image') {
                    imageField.show();
                } else if (type === 'html') {
                    htmlField.show();
                } else if (type === 'text') {
                    textField.show();
                } else if (type === 'video') {
                    videoField.show();
                }
            }
            
            toggleFields();
            typeSelect.on('change', toggleFields);
        });
        </script>
        <?php
    }
    
    /**
     * Render zone meta box
     */
    public function render_zone_meta_box($post) {
        wp_nonce_field('adwpt_zone_meta_box', 'adwpt_zone_meta_box_nonce');
        
        $slug = get_post_meta($post->ID, '_adwpt_slug', true) ?: sanitize_title($post->post_title);
        $status = get_post_meta($post->ID, '_adwpt_status', true) ?: 'active';
        $display_mode = get_post_meta($post->ID, '_adwpt_display_mode', true) ?: 'random';
        $slider_enabled = get_post_meta($post->ID, '_adwpt_slider_enabled', true) ?: 'auto';
        $slider_speed = get_post_meta($post->ID, '_adwpt_slider_speed', true) ?: '5';
        $ad_size = get_post_meta($post->ID, '_adwpt_ad_size', true) ?: 'responsive';
        $custom_width = get_post_meta($post->ID, '_adwpt_custom_width', true) ?: '';
        $custom_height = get_post_meta($post->ID, '_adwpt_custom_height', true) ?: '';
        
        // Predefined ad sizes (IAB Standard + Common)
        $ad_sizes = [
            'responsive' => [
                'label' => 'Responsive (100% largeur)',
                'width' => '100%',
                'height' => 'auto',
            ],
            'leaderboard' => [
                'label' => 'Leaderboard (728×90)',
                'width' => '728px',
                'height' => '90px',
            ],
            'banner' => [
                'label' => 'Banner (468×60)',
                'width' => '468px',
                'height' => '60px',
            ],
            'medium_rectangle' => [
                'label' => 'Medium Rectangle (300×250)',
                'width' => '300px',
                'height' => '250px',
            ],
            'large_rectangle' => [
                'label' => 'Large Rectangle (336×280)',
                'width' => '336px',
                'height' => '280px',
            ],
            'skyscraper' => [
                'label' => 'Wide Skyscraper (160×600)',
                'width' => '160px',
                'height' => '600px',
            ],
            'half_page' => [
                'label' => 'Half Page Ad (300×600)',
                'width' => '300px',
                'height' => '600px',
            ],
            'large_leaderboard' => [
                'label' => 'Large Leaderboard (970×90)',
                'width' => '970px',
                'height' => '90px',
            ],
            'billboard' => [
                'label' => 'Billboard (970×250)',
                'width' => '970px',
                'height' => '250px',
            ],
            'square' => [
                'label' => 'Square (250×250)',
                'width' => '250px',
                'height' => '250px',
            ],
            'small_square' => [
                'label' => 'Small Square (200×200)',
                'width' => '200px',
                'height' => '200px',
            ],
            'button' => [
                'label' => 'Button (125×125)',
                'width' => '125px',
                'height' => '125px',
            ],
            'sidebar_300' => [
                'label' => 'Sidebar Standard (300×auto)',
                'width' => '300px',
                'height' => 'auto',
            ],
            'sidebar_336' => [
                'label' => 'Sidebar Large (336×auto)',
                'width' => '336px',
                'height' => 'auto',
            ],
            'custom' => [
                'label' => 'Personnalisé',
                'width' => '',
                'height' => '',
            ],
        ];
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="adwpt_slug"><?php esc_html_e('Slug', 'adwptracker'); ?></label></th>
                <td>
                    <input type="text" name="adwpt_slug" id="adwpt_slug" value="<?php echo esc_attr($slug); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Identifiant unique pour cette zone', 'adwptracker'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_status"><?php esc_html_e('Status', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_status" id="adwpt_status">
                        <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'adwptracker'); ?></option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'adwptracker'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_display_mode">🎨 <?php esc_html_e('Mode d\'affichage', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_display_mode" id="adwpt_display_mode" class="regular-text" style="padding: 8px; font-size: 14px;">
                        <option value="random" <?php selected($display_mode, 'random'); ?>>🎲 <?php esc_html_e('Aléatoire (1 pub choisie au hasard)', 'adwptracker'); ?></option>
                        <option value="all" <?php selected($display_mode, 'all'); ?>>📋 <?php esc_html_e('Toutes (affiche toutes les pubs)', 'adwptracker'); ?></option>
                    </select>
                    <div style="margin-top: 12px; padding: 12px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid #3b82f6; border-radius: 4px;">
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #1e40af;">🎲 Aléatoire :</strong>
                            <span style="color: #475569;">Affiche 1 seule annonce choisie au hasard à chaque chargement</span>
                        </div>
                        <div>
                            <strong style="color: #1e40af;">📋 Toutes :</strong>
                            <span style="color: #475569;">Affiche toutes les annonces (avec slider ou empilées)</span>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_slider_enabled">🎬 <?php esc_html_e('Slider (rotation)', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_slider_enabled" id="adwpt_slider_enabled" class="regular-text" style="padding: 8px; font-size: 14px;">
                        <option value="auto" <?php selected($slider_enabled, 'auto'); ?>>⚙️ <?php esc_html_e('Automatique (slider si plusieurs pubs)', 'adwptracker'); ?></option>
                        <option value="yes" <?php selected($slider_enabled, 'yes'); ?>>✅ <?php esc_html_e('Toujours activé (force le slider)', 'adwptracker'); ?></option>
                        <option value="no" <?php selected($slider_enabled, 'no'); ?>>❌ <?php esc_html_e('Désactivé (pas de rotation)', 'adwptracker'); ?></option>
                    </select>
                    <div style="margin-top: 12px; padding: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #10b981; border-radius: 4px;">
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #065f46;">⚙️ Automatique :</strong>
                            <span style="color: #475569;">Slider activé automatiquement si mode "Toutes"</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #065f46;">✅ Toujours activé :</strong>
                            <span style="color: #475569;">Force le slider même en mode aléatoire</span>
                        </div>
                        <div>
                            <strong style="color: #065f46;">❌ Désactivé :</strong>
                            <span style="color: #475569;">Pas de rotation, toutes les pubs visibles simultanément</span>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr id="slider-speed-row">
                <th><label for="adwpt_slider_speed">⏱️ <?php esc_html_e('Vitesse du slider', 'adwptracker'); ?></label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" name="adwpt_slider_speed" id="adwpt_slider_speed" value="<?php echo esc_attr($slider_speed); ?>" min="1" max="60" step="1" class="small-text" style="padding: 8px; font-size: 14px; width: 80px;">
                        <span style="font-weight: 600; color: #475569;"><?php esc_html_e('secondes', 'adwptracker'); ?></span>
                        <span style="padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 12px; font-weight: 600;">
                            <?php esc_html_e('Recommandé: 5s', 'adwptracker'); ?>
                        </span>
                    </div>
                    <p class="description" style="margin-top: 10px; color: #64748b;">
                        <?php esc_html_e('Temps d\'affichage de chaque annonce avant rotation (entre 1 et 60 secondes)', 'adwptracker'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="adwpt_ad_size"><?php esc_html_e('Zone Format', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_ad_size" id="adwpt_ad_size" class="regular-text" style="max-width: 400px;">
                        <?php foreach ($ad_sizes as $key => $size): ?>
                            <option value="<?php echo esc_attr($key); ?>" 
                                    data-width="<?php echo esc_attr($size['width']); ?>"
                                    data-height="<?php echo esc_attr($size['height']); ?>"
                                    <?php selected($ad_size, $key); ?>>
                                <?php echo esc_html($size['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="custom-dimensions" style="margin-top: 15px; <?php echo $ad_size === 'custom' ? '' : 'display: none;'; ?>">
                        <label style="display: inline-block; margin-right: 15px;">
                            <?php esc_html_e('Largeur', 'adwptracker'); ?>
                            <input type="text" name="adwpt_custom_width" id="adwpt_custom_width" 
                                   value="<?php echo esc_attr($custom_width); ?>" 
                                   class="small-text" 
                                   placeholder="728px">
                            <span class="description">Ex: 728px, 90vw</span>
                        </label>
                        
                        <label style="display: inline-block;">
                            <?php esc_html_e('Hauteur', 'adwptracker'); ?>
                            <input type="text" name="adwpt_custom_height" id="adwpt_custom_height" 
                                   value="<?php echo esc_attr($custom_height); ?>" 
                                   class="small-text" 
                                   placeholder="90px">
                            <span class="description">Ex: 90px, auto</span>
                        </label>
                    </div>
                    
                    <div id="size-preview" style="margin-top: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                        <strong>📐 Aperçu :</strong>
                        <div id="size-preview-text" style="margin-top: 5px; font-size: 14px;"></div>
                    </div>
                    
                    <p class="description" style="margin-top: 10px;">
                        <strong>💡 Formats IAB Standard :</strong> Formats publicitaires standardisés recommandés<br>
                        <strong>📱 Responsive :</strong> S'adapte automatiquement à tous les écrans
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><?php esc_html_e('Shortcode', 'adwptracker'); ?></th>
                <td>
                    <code>[adwptracker_zone id="<?php echo esc_attr($post->ID); ?>"]</code>
                    <p class="description">
                        <?php esc_html_e('Copiez ce shortcode pour afficher cette zone. Les paramètres ci-dessus seront appliqués automatiquement.', 'adwptracker'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            var adSizes = <?php echo json_encode($ad_sizes); ?>;
            
            function toggleSliderSpeed() {
                var sliderEnabled = $('#adwpt_slider_enabled').val();
                if (sliderEnabled === 'no') {
                    $('#slider-speed-row').hide();
                } else {
                    $('#slider-speed-row').show();
                }
            }
            
            function updateSizePreview() {
                var selectedSize = $('#adwpt_ad_size').val();
                var previewText = '';
                
                if (selectedSize === 'custom') {
                    $('#custom-dimensions').show();
                    var width = $('#adwpt_custom_width').val() || 'non défini';
                    var height = $('#adwpt_custom_height').val() || 'non défini';
                    previewText = '<strong>Largeur :</strong> ' + width + ' &nbsp;|&nbsp; <strong>Hauteur :</strong> ' + height;
                } else {
                    $('#custom-dimensions').hide();
                    var size = adSizes[selectedSize];
                    if (size) {
                        previewText = '<strong>Largeur :</strong> ' + size.width + ' &nbsp;|&nbsp; <strong>Hauteur :</strong> ' + size.height;
                        if (selectedSize === 'responsive') {
                            previewText += '<br><span style="color: #2271b1;">✓ Recommandé pour mobile</span>';
                        } else {
                            previewText += '<br><span style="color: #666;">⚫ Centré automatiquement sur la page</span>';
                        }
                    }
                }
                
                $('#size-preview-text').html(previewText);
            }
            
            toggleSliderSpeed();
            updateSizePreview();
            
            $('#adwpt_slider_enabled').on('change', toggleSliderSpeed);
            $('#adwpt_ad_size').on('change', updateSizePreview);
            $('#adwpt_custom_width, #adwpt_custom_height').on('input', updateSizePreview);
        });
        </script>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save ad meta
        if (isset($_POST['adwpt_ad_meta_box_nonce']) && 
            wp_verify_nonce($_POST['adwpt_ad_meta_box_nonce'], 'adwpt_ad_meta_box')) {
            
            $fields = [
                '_adwpt_type' => 'sanitize_text_field',
                '_adwpt_image_url' => 'esc_url_raw',
                '_adwpt_html_code' => 'wp_kses_post',
                '_adwpt_text_title' => 'sanitize_text_field',
                '_adwpt_text_content' => 'sanitize_textarea_field',
                '_adwpt_video_url' => 'esc_url_raw',
                '_adwpt_video_type' => 'sanitize_text_field',
                '_adwpt_link_url' => 'esc_url_raw',
                '_adwpt_link_target' => 'sanitize_text_field',
                '_adwpt_zone_id' => 'absint',
                '_adwpt_status' => 'sanitize_text_field',
                '_adwpt_start_date' => 'sanitize_text_field',
                '_adwpt_end_date' => 'sanitize_text_field',
                '_adwpt_sticky_enabled' => 'sanitize_text_field',
                '_adwpt_sticky_position' => 'sanitize_text_field',
            ];
            
            foreach ($fields as $key => $sanitize_func) {
                $field_name = str_replace('_adwpt_', 'adwpt_', $key);
                if (isset($_POST[$field_name])) {
                    update_post_meta($post_id, $key, $sanitize_func($_POST[$field_name]));
                }
            }
            
            // Handle checkboxes (mobile/desktop)
            update_post_meta($post_id, '_adwpt_show_on_mobile', isset($_POST['adwpt_show_on_mobile']) ? '1' : '0');
            update_post_meta($post_id, '_adwpt_show_on_desktop', isset($_POST['adwpt_show_on_desktop']) ? '1' : '0');
        }
        
        // Save zone meta
        if (isset($_POST['adwpt_zone_meta_box_nonce']) && 
            wp_verify_nonce($_POST['adwpt_zone_meta_box_nonce'], 'adwpt_zone_meta_box')) {
            
            if (isset($_POST['adwpt_slug'])) {
                update_post_meta($post_id, '_adwpt_slug', sanitize_title($_POST['adwpt_slug']));
            }
            
            if (isset($_POST['adwpt_status'])) {
                update_post_meta($post_id, '_adwpt_status', sanitize_text_field($_POST['adwpt_status']));
            }
            
            if (isset($_POST['adwpt_display_mode'])) {
                update_post_meta($post_id, '_adwpt_display_mode', sanitize_text_field($_POST['adwpt_display_mode']));
            }
            
            if (isset($_POST['adwpt_slider_enabled'])) {
                update_post_meta($post_id, '_adwpt_slider_enabled', sanitize_text_field($_POST['adwpt_slider_enabled']));
            }
            
            if (isset($_POST['adwpt_slider_speed'])) {
                update_post_meta($post_id, '_adwpt_slider_speed', absint($_POST['adwpt_slider_speed']));
            }
            
            // Handle ad size
            if (isset($_POST['adwpt_ad_size'])) {
                $ad_size = sanitize_text_field($_POST['adwpt_ad_size']);
                update_post_meta($post_id, '_adwpt_ad_size', $ad_size);
                
                // Predefined sizes mapping
                $sizes = [
                    'responsive' => ['width' => '100%', 'height' => 'auto'],
                    'leaderboard' => ['width' => '728px', 'height' => '90px'],
                    'banner' => ['width' => '468px', 'height' => '60px'],
                    'medium_rectangle' => ['width' => '300px', 'height' => '250px'],
                    'large_rectangle' => ['width' => '336px', 'height' => '280px'],
                    'skyscraper' => ['width' => '160px', 'height' => '600px'],
                    'half_page' => ['width' => '300px', 'height' => '600px'],
                    'large_leaderboard' => ['width' => '970px', 'height' => '90px'],
                    'billboard' => ['width' => '970px', 'height' => '250px'],
                    'square' => ['width' => '250px', 'height' => '250px'],
                    'small_square' => ['width' => '200px', 'height' => '200px'],
                    'button' => ['width' => '125px', 'height' => '125px'],
                    'sidebar_300' => ['width' => '300px', 'height' => 'auto'],
                    'sidebar_336' => ['width' => '336px', 'height' => 'auto'],
                ];
                
                if ($ad_size === 'custom') {
                    // Use custom dimensions
                    $custom_width = isset($_POST['adwpt_custom_width']) ? sanitize_text_field($_POST['adwpt_custom_width']) : '';
                    $custom_height = isset($_POST['adwpt_custom_height']) ? sanitize_text_field($_POST['adwpt_custom_height']) : '';
                    
                    update_post_meta($post_id, '_adwpt_custom_width', $custom_width);
                    update_post_meta($post_id, '_adwpt_custom_height', $custom_height);
                    update_post_meta($post_id, '_adwpt_max_width', $custom_width);
                    update_post_meta($post_id, '_adwpt_max_height', $custom_height);
                } elseif (isset($sizes[$ad_size])) {
                    // Use predefined size
                    update_post_meta($post_id, '_adwpt_max_width', $sizes[$ad_size]['width']);
                    update_post_meta($post_id, '_adwpt_max_height', $sizes[$ad_size]['height']);
                    
                    // Clear custom dimensions
                    delete_post_meta($post_id, '_adwpt_custom_width');
                    delete_post_meta($post_id, '_adwpt_custom_height');
                }
            }
        }
    }
    
    /**
     * Handle CSV export
     */
    public function handle_export_csv() {
        // Check if export is requested
        if (!isset($_GET['adwptracker_export']) || $_GET['adwptracker_export'] !== 'csv') {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions nécessaires.', 'adwptracker'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'adwptracker_export_csv')) {
            wp_die(__('Nonce invalide.', 'adwptracker'));
        }
        
        // Get date range if provided
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
        
        // Export
        if (class_exists('ADWPT_Stats')) {
            $stats = ADWPT_Stats::get_instance();
            $stats->export_to_csv($start_date, $end_date);
        }
    }
}
