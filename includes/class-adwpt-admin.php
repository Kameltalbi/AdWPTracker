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
        add_action('admin_init', [$this, 'sync_access_capabilities']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_action('pre_get_posts', [$this, 'filter_ads_by_zone']);
        add_filter('redirect_post_location', [$this, 'redirect_after_publish'], 10, 2);
        add_action('admin_notices', [$this, 'show_publish_notice']);
        
        // Export CSV handler
        add_action('admin_init', [$this, 'handle_export_csv']);
    }

    /**
     * Capability used to access plugin admin pages.
     */
    private function get_plugin_capability() {
        return 'adwpt_manage';
    }

    /**
     * Normalize a CSS dimension. Numeric values are stored as pixels.
     */
    private function normalize_dimension($value, $default = '') {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return $default;
        }

        if ($value === 'auto') {
            return 'auto';
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $value . 'px';
        }

        if (preg_match('/^\d+(\.\d+)?(px|%|vw|vh|rem|em)$/', $value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Normalize width/height and support shorthand format like "970x100".
     */
    private function normalize_zone_dimensions($raw_width, $raw_height) {
        $width = trim((string) $raw_width);
        $height = trim((string) $raw_height);

        if (preg_match('/^\s*(\d+(\.\d+)?)\s*x\s*(\d+(\.\d+)?)\s*$/i', $width, $m)) {
            $width = $m[1] . 'px';
            if ($height === '' || strtolower($height) === 'auto') {
                $height = $m[3] . 'px';
            }
        }

        return [
            $this->normalize_dimension($width, ''),
            $this->normalize_dimension($height, 'auto'),
        ];
    }

    /**
     * Grant/revoke plugin access capability by role.
     */
    public function sync_access_capabilities() {
        $capability = $this->get_plugin_capability();
        $allow_editor = get_option('adwpt_access_editor', '0') === '1';
        $allow_contributor = get_option('adwpt_access_contributor', '0') === '1';

        $roles_map = [
            'administrator' => true,
            'editor' => $allow_editor,
            'contributor' => $allow_contributor,
        ];

        foreach ($roles_map as $role_name => $should_have_access) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            if ($should_have_access) {
                $role->add_cap($capability);
            } else {
                $role->remove_cap($capability);
            }
        }
    }

    /**
     * Filter ads list by zone from query string.
     */
    public function filter_ads_by_zone($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'adwpt_ad') {
            return;
        }

        if (empty($_GET['adwpt_zone_id'])) {
            return;
        }

        $zone_id = absint($_GET['adwpt_zone_id']);
        if (!$zone_id) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key' => '_adwpt_zone_id',
            'value' => $zone_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ];
        $query->set('meta_query', $meta_query);
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
        $capability = $this->get_plugin_capability();

        // Main menu with modern icon
        add_menu_page(
            __('AdWPtracker', 'adwptracker'),
            __('AdWPtracker', 'adwptracker'),
            $capability,
            'adwptracker',
            [$this, 'render_dashboard'],
            'dashicons-chart-area',
            30
        );
        
        add_submenu_page(
            'adwptracker',
            __('Tableau de bord', 'adwptracker'),
            __('Tableau de bord', 'adwptracker'),
            $capability,
            'adwptracker',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'adwptracker',
            __('Publicités', 'adwptracker'),
            __('Publicités', 'adwptracker'),
            $capability,
            'edit.php?post_type=adwpt_ad'
        );

        add_submenu_page(
            'adwptracker',
            __('Statistiques', 'adwptracker'),
            __('Statistiques', 'adwptracker'),
            $capability,
            'adwptracker-stats',
            [$this, 'render_stats_page']
        );

        add_submenu_page(
            'adwptracker',
            __('Zones d’affichage', 'adwptracker'),
            __('Zones d’affichage', 'adwptracker'),
            $capability,
            'edit.php?post_type=adwpt_zone'
        );

        add_submenu_page(
            'adwptracker',
            __('Paramètres', 'adwptracker'),
            __('Paramètres', 'adwptracker'),
            $capability,
            'adwptracker-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'adwptracker',
            __('Outils', 'adwptracker'),
            __('Outils', 'adwptracker'),
            $capability,
            'adwptracker-tools',
            [$this, 'render_tools_page']
        );

        add_submenu_page(
            'adwptracker',
            __('Aide', 'adwptracker'),
            __('Aide', 'adwptracker'),
            $capability,
            'adwptracker-docs',
            [$this, 'render_docs_page']
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
        
        $current_post_type = $post_type ?: get_post_type();
        if (strpos($hook, 'adwptracker') === false && 
            !in_array($current_post_type, ['adwpt_zone', 'adwpt_ad'], true)) {
            return;
        }
        
        wp_enqueue_style(
            'adwptracker-admin',
            ADWPT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADWPT_VERSION
        );

        wp_enqueue_style(
            'adwptracker-admin-modern',
            ADWPT_PLUGIN_URL . 'assets/css/admin-modern.css',
            ['adwptracker-admin'],
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
    }

    /**
     * Render stats page
     */
    public function render_stats_page() {
        if (!class_exists('ADWPT_Stats')) {
            echo '<div class="wrap"><h1>Erreur</h1><p>La classe ADWPT_Stats n\'est pas chargée.</p></div>';
            return;
        }

        global $wpdb;

        $range = isset($_GET['range']) ? absint($_GET['range']) : 30;
        if (!in_array($range, [7, 30, 90, 365], true)) {
            $range = 30;
        }

        $stats = ADWPT_Stats::get_instance();
        $summary = $stats->get_summary_stats();
        $all_stats = $stats->get_stats();
        $stats_table = $wpdb->prefix . 'adwptracker_stats';
        $since_sql = gmdate('Y-m-d H:i:s', strtotime('-' . $range . ' days'));

        $daily_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS stat_date,
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) AS clicks
            FROM {$stats_table}
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY stat_date ASC",
            $since_sql
        ), ARRAY_A);

        $device_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT device, COUNT(*) AS total
            FROM {$stats_table}
            WHERE created_at >= %s AND type = 'impression'
            GROUP BY device
            ORDER BY total DESC",
            $since_sql
        ), ARRAY_A);

        $type_rows = $wpdb->get_results("SELECT pm.meta_value AS ad_type, COUNT(*) AS total
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_adwpt_type'
            WHERE p.post_type = 'adwpt_ad' AND p.post_status IN ('publish', 'draft')
            GROUP BY pm.meta_value", ARRAY_A);

        $chart_labels = array_map(function($row) { return $row['stat_date']; }, $daily_rows);
        $chart_impressions = array_map(function($row) { return (int) $row['impressions']; }, $daily_rows);
        $chart_clicks = array_map(function($row) { return (int) $row['clicks']; }, $daily_rows);
        ?>
        <div class="wrap">
            <div class="adwpt-admin">
                <div class="adwpt-page-header">
                    <div>
                        <h1 class="adwpt-page-title"><?php esc_html_e('Statistiques', 'adwptracker'); ?></h1>
                        <p class="adwpt-page-subtitle"><?php esc_html_e('Analysez les performances de vos publicités', 'adwptracker'); ?></p>
                    </div>
                    <form method="get" class="adwpt-toolbar">
                        <input type="hidden" name="page" value="adwptracker-stats">
                        <select name="range" onchange="this.form.submit()">
                            <option value="7" <?php selected($range, 7); ?>>7 derniers jours</option>
                            <option value="30" <?php selected($range, 30); ?>>30 derniers jours</option>
                            <option value="90" <?php selected($range, 90); ?>>90 derniers jours</option>
                            <option value="365" <?php selected($range, 365); ?>>12 derniers mois</option>
                        </select>
                        <a class="adwpt-button adwpt-button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?adwptracker_export=csv'), 'adwptracker_export_csv')); ?>"><?php esc_html_e('Exporter CSV', 'adwptracker'); ?></a>
                    </form>
                </div>

                <div class="adwpt-tabs">
                    <button type="button" class="adwpt-tab active"><?php esc_html_e('Vue d\'ensemble', 'adwptracker'); ?></button>
                    <button type="button" class="adwpt-tab"><?php esc_html_e('Par publicité', 'adwptracker'); ?></button>
                    <button type="button" class="adwpt-tab"><?php esc_html_e('Par zone', 'adwptracker'); ?></button>
                    <button type="button" class="adwpt-tab"><?php esc_html_e('Par jour', 'adwptracker'); ?></button>
                    <button type="button" class="adwpt-tab"><?php esc_html_e('Par type', 'adwptracker'); ?></button>
                </div>

                <div class="adwpt-grid adwpt-grid-4">
                    <div class="adwpt-card"><div class="adwpt-kpi-label"><?php esc_html_e('Impressions', 'adwptracker'); ?></div><div class="adwpt-kpi-value"><?php echo esc_html(number_format_i18n($summary['total_impressions'])); ?></div></div>
                    <div class="adwpt-card"><div class="adwpt-kpi-label"><?php esc_html_e('Clics', 'adwptracker'); ?></div><div class="adwpt-kpi-value"><?php echo esc_html(number_format_i18n($summary['total_clicks'])); ?></div></div>
                    <div class="adwpt-card"><div class="adwpt-kpi-label"><?php esc_html_e('CTR', 'adwptracker'); ?></div><div class="adwpt-kpi-value"><?php echo esc_html(number_format_i18n($summary['average_ctr'], 2)); ?>%</div></div>
                    <div class="adwpt-card"><div class="adwpt-kpi-label"><?php esc_html_e('Publicités actives', 'adwptracker'); ?></div><div class="adwpt-kpi-value"><?php echo esc_html(number_format_i18n($summary['active_ads'])); ?></div></div>
                </div>

                <div class="adwpt-grid adwpt-grid-2" style="margin-top:16px;">
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Évolution des impressions et clics', 'adwptracker'); ?></h2>
                        <canvas id="adwptStatsPerformanceChart" height="150"></canvas>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Répartition par appareil', 'adwptracker'); ?></h2>
                        <canvas id="adwptStatsDeviceChart" height="150"></canvas>
                    </div>
                </div>

                <div class="adwpt-card" style="margin-top:16px;">
                    <h2 class="adwpt-section-title"><?php esc_html_e('Statistiques par publicité', 'adwptracker'); ?></h2>
                    <div class="adwpt-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th>ID</th><th><?php esc_html_e('Publicité', 'adwptracker'); ?></th><th><?php esc_html_e('Zone', 'adwptracker'); ?></th><th><?php esc_html_e('Impressions', 'adwptracker'); ?></th><th><?php esc_html_e('Clics', 'adwptracker'); ?></th><th>CTR</th></tr></thead>
                            <tbody>
                                <?php if ($all_stats): ?>
                                    <?php foreach ($all_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo esc_html($stat['ad_id']); ?></td>
                                            <td><a href="<?php echo esc_url(get_edit_post_link($stat['ad_id'])); ?>"><?php echo esc_html(get_the_title($stat['ad_id']) ?: __('Sans titre', 'adwptracker')); ?></a></td>
                                            <td><?php echo $stat['zone_id'] ? '<a href="' . esc_url(get_edit_post_link($stat['zone_id'])) . '">' . esc_html(get_the_title($stat['zone_id'])) . '</a>' : esc_html__('Sans zone', 'adwptracker'); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat['impressions'])); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat['clicks'])); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($stat['ctr'], 2)); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6"><?php esc_html_e('Aucune statistique disponible pour le moment.', 'adwptracker'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($type_rows): ?>
                    <div class="adwpt-card" style="margin-top:16px;">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Répartition par type', 'adwptracker'); ?></h2>
                        <div class="adwpt-table-wrap">
                            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Type', 'adwptracker'); ?></th><th><?php esc_html_e('Nombre', 'adwptracker'); ?></th></tr></thead><tbody>
                                <?php foreach ($type_rows as $row): ?>
                                    <tr><td><?php echo esc_html($row['ad_type'] ?: 'image'); ?></td><td><?php echo esc_html(number_format_i18n($row['total'])); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody></table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                return;
            }
            var perf = document.getElementById('adwptStatsPerformanceChart');
            if (perf) {
                new Chart(perf, {type: 'line', data: {labels: <?php echo wp_json_encode($chart_labels); ?>, datasets: [{label: 'Impressions', data: <?php echo wp_json_encode($chart_impressions); ?>, borderColor: '#2563eb', tension: .3}, {label: 'Clics', data: <?php echo wp_json_encode($chart_clicks); ?>, borderColor: '#16a34a', tension: .3}]}, options: {responsive: true, plugins: {legend: {position: 'bottom'}}, scales: {y: {beginAtZero: true}}}});
            }
            var device = document.getElementById('adwptStatsDeviceChart');
            if (device) {
                new Chart(device, {type: 'doughnut', data: {labels: <?php echo wp_json_encode(array_map(function($row) { return $row['device'] ?: 'desktop'; }, $device_rows)); ?>, datasets: [{data: <?php echo wp_json_encode(array_map(function($row) { return (int) $row['total']; }, $device_rows)); ?>, backgroundColor: ['#2563eb', '#16a34a', '#f59e0b']}]}, options: {responsive: true, plugins: {legend: {position: 'bottom'}}}});
            }
        });
        </script>
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
            __('🎯 Configuration de la zone', 'adwptracker'),
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
    }

    /**
     * Render tools page.
     */
    public function render_tools_page() {
        if (!current_user_can($this->get_plugin_capability())) {
            wp_die(__('Vous n’avez pas les permissions nécessaires.', 'adwptracker'));
        }

        global $wpdb;
        $stats_table = $wpdb->prefix . 'adwptracker_stats';
        $stats_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$stats_table}");
        ?>
        <div class="wrap">
            <div class="adwpt-admin">
                <div class="adwpt-page-header">
                    <div>
                        <h1 class="adwpt-page-title"><?php esc_html_e('Outils', 'adwptracker'); ?></h1>
                        <p class="adwpt-page-subtitle"><?php esc_html_e('Utilitaires et ressources pour gérer le plugin.', 'adwptracker'); ?></p>
                    </div>
                </div>

                <div class="adwpt-grid adwpt-grid-4">
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Exporter les données', 'adwptracker'); ?></h2>
                        <p class="adwpt-page-subtitle"><?php esc_html_e('Télécharger les statistiques au format CSV.', 'adwptracker'); ?></p>
                        <p><a class="adwpt-button adwpt-button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?adwptracker_export=csv'), 'adwptracker_export_csv')); ?>"><?php esc_html_e('Exporter', 'adwptracker'); ?></a></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Vérifier l’installation', 'adwptracker'); ?></h2>
                        <p class="adwpt-page-subtitle"><?php printf(esc_html__('Table stats : %s lignes.', 'adwptracker'), number_format_i18n($stats_count)); ?></p>
                        <p><span class="adwpt-badge adwpt-badge-active"><?php esc_html_e('Installation détectée', 'adwptracker'); ?></span></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Shortcodes', 'adwptracker'); ?></h2>
                        <p><code>[adwptracker_zone id="123"]</code></p>
                        <p><code>[adwptracker_ad id="123"]</code></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Version', 'adwptracker'); ?></h2>
                        <div class="adwpt-kpi-value"><?php echo esc_html(ADWPT_VERSION); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render documentation page
     */
    public function render_docs_page() {
        ?>
        <div class="wrap">
            <div class="adwpt-admin">
                <div class="adwpt-page-header">
                    <div>
                        <h1 class="adwpt-page-title"><?php esc_html_e('Aide', 'adwptracker'); ?></h1>
                        <p class="adwpt-page-subtitle"><?php esc_html_e('Documentation, shortcodes et résolution des problèmes.', 'adwptracker'); ?></p>
                    </div>
                    <span class="adwpt-badge adwpt-badge-draft">v<?php echo esc_html(ADWPT_VERSION); ?></span>
                </div>

                <div class="adwpt-grid adwpt-grid-2">
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Documentation', 'adwptracker'); ?></h2>
                        <p><?php esc_html_e('Créez une zone, rattachez une ou plusieurs publicités, puis insérez le shortcode de la zone dans votre thème ou constructeur.', 'adwptracker'); ?></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Shortcodes', 'adwptracker'); ?></h2>
                        <p><code>[adwptracker_zone id="123"]</code></p>
                        <p><code>[adwptracker_ad id="123"]</code></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Zones d’affichage', 'adwptracker'); ?></h2>
                        <p><?php esc_html_e('Les dimensions, le mode aléatoire/toutes et le slider se règlent au niveau de la zone.', 'adwptracker'); ?></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Tracking', 'adwptracker'); ?></h2>
                        <p><?php esc_html_e('Les impressions sont enregistrées quand une publicité devient visible. Les clics sont envoyés en arrière-plan sans bloquer l’ouverture du lien.', 'adwptracker'); ?></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('FAQ', 'adwptracker'); ?></h2>
                        <p><strong><?php esc_html_e('Pourquoi une zone est vide ?', 'adwptracker'); ?></strong><br><?php esc_html_e('Vérifiez que la publicité est active, publiée, rattachée à cette zone et visible sur l’appareil courant.', 'adwptracker'); ?></p>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Résolution des problèmes', 'adwptracker'); ?></h2>
                        <p><?php esc_html_e('Videz le cache après une mise à jour, vérifiez le shortcode et confirmez que le dossier plugin actif est le bon.', 'adwptracker'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render support page
     */
    public function render_support_page() {
        $this->render_docs_page();
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
        if (!$zone_id && isset($_GET['adwpt_zone_id'])) {
            $zone_id = absint($_GET['adwpt_zone_id']);
        }
        $status = get_post_meta($post->ID, '_adwpt_status', true) ?: 'active';
        $start_date = get_post_meta($post->ID, '_adwpt_start_date', true);
        $end_date = get_post_meta($post->ID, '_adwpt_end_date', true);
        
        // Device options. Legacy _adwpt_device is only used when the new checkboxes were never saved.
        $show_on_mobile_meta = get_post_meta($post->ID, '_adwpt_show_on_mobile', true);
        $show_on_desktop_meta = get_post_meta($post->ID, '_adwpt_show_on_desktop', true);
        $legacy_device = get_post_meta($post->ID, '_adwpt_device', true);
        $show_on_mobile = $show_on_mobile_meta !== '0';
        $show_on_desktop = $show_on_desktop_meta !== '0';

        if ($show_on_mobile_meta === '' && $show_on_desktop_meta === '' && $legacy_device) {
            $show_on_mobile = in_array($legacy_device, ['all', 'mobile', 'tablet'], true);
            $show_on_desktop = in_array($legacy_device, ['all', 'desktop'], true);
        }
        $sticky_enabled = get_post_meta($post->ID, '_adwpt_sticky_enabled', true);
        $sticky_position = get_post_meta($post->ID, '_adwpt_sticky_position', true) ?: 'top';
        
        $zones = get_posts([
            'post_type' => 'adwpt_zone',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $selected_zone_title = $zone_id ? get_the_title($zone_id) : '';
        $device_label = [];
        if ($show_on_desktop) {
            $device_label[] = __('Desktop', 'adwptracker');
        }
        if ($show_on_mobile) {
            $device_label[] = __('Mobile/Tablette', 'adwptracker');
        }
        $device_label = $device_label ? implode(' + ', $device_label) : __('Aucun appareil', 'adwptracker');

        $current_date = current_time('Y-m-d');
        $schedule_label = __('Toujours visible', 'adwptracker');
        if ($start_date && $start_date > $current_date) {
            $schedule_label = sprintf(__('Démarre le %s', 'adwptracker'), $start_date);
        } elseif ($end_date && $end_date < $current_date) {
            $schedule_label = sprintf(__('Expirée le %s', 'adwptracker'), $end_date);
        } elseif ($start_date || $end_date) {
            $schedule_label = trim(($start_date ?: '...') . ' → ' . ($end_date ?: '...'));
        }
        
        ?>
        <div class="adwpt-delivery-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 0 0 18px; padding: 14px; background: #f8fafc; border: 1px solid #dbe3ea; border-radius: 8px;">
            <div>
                <strong style="display:block; color:#1d2327;"><?php esc_html_e('État', 'adwptracker'); ?></strong>
                <span style="color: <?php echo $status === 'active' ? '#047857' : '#b91c1c'; ?>;"><?php echo esc_html($status === 'active' ? __('Active', 'adwptracker') : __('Inactive', 'adwptracker')); ?></span>
            </div>
            <div>
                <strong style="display:block; color:#1d2327;"><?php esc_html_e('Zone', 'adwptracker'); ?></strong>
                <span style="color: <?php echo $zone_id ? '#047857' : '#b91c1c'; ?>;"><?php echo esc_html($zone_id ? $selected_zone_title : __('Aucune zone sélectionnée', 'adwptracker')); ?></span>
            </div>
            <div>
                <strong style="display:block; color:#1d2327;"><?php esc_html_e('Appareils', 'adwptracker'); ?></strong>
                <span><?php echo esc_html($device_label); ?></span>
            </div>
            <div>
                <strong style="display:block; color:#1d2327;"><?php esc_html_e('Calendrier', 'adwptracker'); ?></strong>
                <span><?php echo esc_html($schedule_label); ?></span>
            </div>
        </div>

        <div class="adwpt-form-grid">
            <div class="adwpt-card">
        <table class="form-table">
            <tr>
                <th><label for="adwpt_type"><?php esc_html_e('Type d\'annonce', 'adwptracker'); ?></label></th>
                <td>
                    <select name="adwpt_type" id="adwpt_type" class="regular-text">
                        <option value="image" <?php selected($type, 'image'); ?>><?php esc_html_e('Image', 'adwptracker'); ?></option>
                        <option value="html" <?php selected($type, 'html'); ?>><?php esc_html_e('HTML / Script publicitaire', 'adwptracker'); ?></option>
                        <option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Texte', 'adwptracker'); ?></option>
                        <option value="video" <?php selected($type, 'video'); ?>><?php esc_html_e('Vidéo', 'adwptracker'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choisissez le type de contenu publicitaire.', 'adwptracker'); ?></p>
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
                        <strong><?php esc_html_e('Aide formats image', 'adwptracker'); ?> :</strong><br>
                        <select id="adwpt_format_helper" style="margin-top: 5px;">
                            <option value="">-- Choisir un format standard --</option>
                            <option value="728x90">Leaderboard (728×90) - Desktop Header</option>
                            <option value="320x50">Mobile Banner (320×50) - Mobile/Sticky</option>
                            <option value="300x250">Medium Rectangle (300×250) - Sidebar</option>
                            <option value="336x280">Large Rectangle (336×280) - Content</option>
                            <option value="468x60">Banner (468×60) - Header/Footer</option>
                            <option value="970x90">Large Leaderboard (970×90) - Top</option>
                            <option value="970x100">Header Banner (970×100) - Top</option>
                            <option value="970x250">Billboard (970×250) - Top</option>
                            <option value="160x600">Wide Skyscraper (160×600) - Sidebar</option>
                            <option value="300x600">Half Page (300×600) - Sidebar</option>
                        </select>
                        <span style="display: block; margin-top: 5px; color: #666; font-size: 12px;">
                            <?php esc_html_e('Ce menu indique le format recommandé du fichier image. Le format d’affichage réel se règle dans la zone.', 'adwptracker'); ?>
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
                                } else if (format === '970x100') {
                                    desc += ' (Header large personnalisé)';
                                } else if (format === '970x250') {
                                    desc += ' (Billboard grand format)';
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
                    <p class="description"><?php esc_html_e('Obligatoire pour afficher cette annonce avec un shortcode de zone.', 'adwptracker'); ?></p>
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
            </div>

            <aside class="adwpt-card adwpt-preview-panel">
                <h2 class="adwpt-section-title"><?php esc_html_e('Aperçu', 'adwptracker'); ?></h2>
                <div id="adwpt-live-preview" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; background: #f9fafb; min-height: 180px;">
                    <?php if ($type === 'image' && $image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="display:block; max-width:100%; height:auto; margin:0 auto; border-radius:6px;">
                    <?php elseif ($type === 'text' && ($text_title || $text_content)): ?>
                        <strong><?php echo esc_html($text_title); ?></strong>
                        <p><?php echo esc_html($text_content); ?></p>
                    <?php elseif ($type === 'html' && $html_code): ?>
                        <div style="max-height:160px; overflow:auto;"><?php echo wp_kses_post($html_code); ?></div>
                    <?php else: ?>
                        <p class="description"><?php esc_html_e('Complétez le contenu pour afficher un aperçu.', 'adwptracker'); ?></p>
                    <?php endif; ?>
                </div>
                <p class="description" style="margin-top:10px;"><?php esc_html_e('Aperçu indicatif. Le rendu final dépend de la zone et du thème.', 'adwptracker'); ?></p>
            </aside>
        </div>
        
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
            'large_leaderboard_100' => [
                'label' => 'Header Banner (970×100)',
                'width' => '970px',
                'height' => '100px',
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
                'label' => 'Personnalisé (dimensions libres)',
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
                                   style="width: 120px;"
                                   placeholder="970px">
                            <span class="description">Ex: 970px, 100%, 90vw</span>
                        </label>
                        
                        <label style="display: inline-block;">
                            <?php esc_html_e('Hauteur', 'adwptracker'); ?>
                            <input type="text" name="adwpt_custom_height" id="adwpt_custom_height" 
                                   value="<?php echo esc_attr($custom_height); ?>" 
                                   class="small-text" 
                                   style="width: 120px;"
                                   placeholder="100px">
                            <span class="description">Ex: 100px, 250px, auto</span>
                        </label>

                        <p class="description" style="margin: 8px 0 0;">
                            <?php esc_html_e('Astuce : les valeurs numériques sont automatiquement enregistrées en pixels. Vous pouvez aussi saisir un raccourci comme 970x100 dans le champ largeur.', 'adwptracker'); ?>
                        </p>
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
                    <p style="margin-top: 10px;">
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(['post_type' => 'adwpt_ad', 'adwpt_zone_id' => $post->ID], admin_url('edit.php'))); ?>">
                            <?php esc_html_e('Voir les bannières de cette zone', 'adwptracker'); ?>
                        </a>
                        <a class="button button-primary" style="margin-left: 8px;" href="<?php echo esc_url(add_query_arg(['post_type' => 'adwpt_ad', 'adwpt_zone_id' => $post->ID], admin_url('post-new.php'))); ?>">
                            <?php esc_html_e('Ajouter une bannière', 'adwptracker'); ?>
                        </a>
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
            delete_post_meta($post_id, '_adwpt_device');
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
                $ad_size = sanitize_key($_POST['adwpt_ad_size']);
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
                    'large_leaderboard_100' => ['width' => '970px', 'height' => '100px'],
                    'billboard' => ['width' => '970px', 'height' => '250px'],
                    'square' => ['width' => '250px', 'height' => '250px'],
                    'small_square' => ['width' => '200px', 'height' => '200px'],
                    'button' => ['width' => '125px', 'height' => '125px'],
                    'sidebar_300' => ['width' => '300px', 'height' => 'auto'],
                    'sidebar_336' => ['width' => '336px', 'height' => 'auto'],
                ];
                
                if ($ad_size === 'custom') {
                    // Use custom dimensions
                    $custom_width_raw = isset($_POST['adwpt_custom_width']) ? sanitize_text_field($_POST['adwpt_custom_width']) : '';
                    $custom_height_raw = isset($_POST['adwpt_custom_height']) ? sanitize_text_field($_POST['adwpt_custom_height']) : '';
                    list($custom_width, $custom_height) = $this->normalize_zone_dimensions($custom_width_raw, $custom_height_raw);
                    
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
        if (!current_user_can($this->get_plugin_capability())) {
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
