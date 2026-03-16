<?php
/**
 * Stats class
 * Handles statistics tracking and retrieval
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Stats {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'adwptracker_stats';
        
        // Ensure table exists
        $this->maybe_create_table();
    }
    
    /**
     * Detect device type
     */
    private function detect_device() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Check for tablet first (more specific)
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
            return 'tablet';
        }
        
        // Check for mobile
        if (wp_is_mobile() || preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $user_agent)) {
            return 'mobile';
        }
        
        // Default to desktop
        return 'desktop';
    }
    
    /**
     * Create table if it doesn't exist
     */
    private function maybe_create_table() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        
        if ($table_exists != $this->table_name) {
            // Table doesn't exist, create it
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ad_id BIGINT(20) UNSIGNED NOT NULL,
                zone_id BIGINT(20) UNSIGNED NOT NULL,
                type VARCHAR(20) NOT NULL,
                device VARCHAR(20) DEFAULT 'desktop',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY ad_id (ad_id),
                KEY zone_id (zone_id),
                KEY type (type),
                KEY device (device),
                KEY created_at (created_at)
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Record an impression
     */
    public function record_impression($ad_id, $zone_id) {
        global $wpdb;
        
        $ad_id = absint($ad_id);
        $zone_id = absint($zone_id);
        
        if (!$ad_id || !$zone_id) {
            return false;
        }
        
        $device = $this->detect_device();
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'ad_id' => $ad_id,
                'zone_id' => $zone_id,
                'type' => 'impression',
                'device' => $device,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Record a click
     */
    public function record_click($ad_id, $zone_id) {
        global $wpdb;
        
        $ad_id = absint($ad_id);
        $zone_id = absint($zone_id);
        
        if (!$ad_id || !$zone_id) {
            return false;
        }
        
        $device = $this->detect_device();
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'ad_id' => $ad_id,
                'zone_id' => $zone_id,
                'type' => 'click',
                'device' => $device,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get stats for all ads
     */
    public function get_stats() {
        global $wpdb;
        
        $query = "
            SELECT 
                ad_id,
                zone_id,
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks,
                CASE 
                    WHEN SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) > 0 
                    THEN (SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END))
                    ELSE 0 
                END as ctr
            FROM {$this->table_name}
            GROUP BY ad_id, zone_id
            ORDER BY impressions DESC
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Get stats for a specific ad
     */
    public function get_ad_stats($ad_id) {
        global $wpdb;
        
        $ad_id = absint($ad_id);
        
        if (!$ad_id) {
            return [
                'impressions' => 0,
                'clicks' => 0,
                'ctr' => 0,
            ];
        }
        
        $query = $wpdb->prepare("
            SELECT 
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks,
                CASE 
                    WHEN SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) > 0 
                    THEN (SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END))
                    ELSE 0 
                END as ctr
            FROM {$this->table_name}
            WHERE ad_id = %d
        ", $ad_id);
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        if (!$result) {
            return [
                'impressions' => 0,
                'clicks' => 0,
                'ctr' => 0,
            ];
        }
        
        return [
            'impressions' => (int) $result['impressions'],
            'clicks' => (int) $result['clicks'],
            'ctr' => (float) $result['ctr'],
        ];
    }
    
    /**
     * Get stats for a specific zone
     */
    public function get_zone_stats($zone_id) {
        global $wpdb;
        
        $zone_id = absint($zone_id);
        
        if (!$zone_id) {
            return [
                'impressions' => 0,
                'clicks' => 0,
                'ctr' => 0,
            ];
        }
        
        $query = $wpdb->prepare("
            SELECT 
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks,
                CASE 
                    WHEN SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) > 0 
                    THEN (SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END))
                    ELSE 0 
                END as ctr
            FROM {$this->table_name}
            WHERE zone_id = %d
        ", $zone_id);
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        if (!$result) {
            return [
                'impressions' => 0,
                'clicks' => 0,
                'ctr' => 0,
            ];
        }
        
        return [
            'impressions' => (int) $result['impressions'],
            'clicks' => (int) $result['clicks'],
            'ctr' => (float) $result['ctr'],
        ];
    }
    
    /**
     * Get summary stats
     */
    public function get_summary_stats() {
        global $wpdb;
        
        $query = "
            SELECT 
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as total_impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as total_clicks,
                CASE 
                    WHEN SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) > 0 
                    THEN (SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END))
                    ELSE 0 
                END as average_ctr
            FROM {$this->table_name}
        ";
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        // Get active ads count
        $active_ads = get_posts([
            'post_type' => 'adwpt_ad',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_adwpt_status',
                    'value' => 'active',
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);
        
        return [
            'total_impressions' => $result ? (int) $result['total_impressions'] : 0,
            'total_clicks' => $result ? (int) $result['total_clicks'] : 0,
            'average_ctr' => $result ? (float) $result['average_ctr'] : 0,
            'active_ads' => count($active_ads),
        ];
    }
    
    /**
     * Get stats by date range
     */
    public function get_stats_by_date($start_date, $end_date) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT 
                ad_id,
                zone_id,
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks,
                CASE 
                    WHEN SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) > 0 
                    THEN (SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) * 100.0 / SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END))
                    ELSE 0 
                END as ctr
            FROM {$this->table_name}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY ad_id, zone_id
            ORDER BY impressions DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Delete old stats (optional cleanup function)
     */
    public function delete_old_stats($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        return $result;
    }
    
    /**
     * Export stats to CSV
     */
    public function export_to_csv($start_date = null, $end_date = null) {
        global $wpdb;
        
        // Default: last 30 days
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        // Get all ads stats
        $query = $wpdb->prepare("
            SELECT 
                s.ad_id,
                p.post_title as ad_name,
                z.post_title as zone_name,
                COUNT(CASE WHEN s.type = 'impression' THEN 1 END) as impressions,
                COUNT(CASE WHEN s.type = 'click' THEN 1 END) as clicks,
                ROUND(
                    (COUNT(CASE WHEN s.type = 'click' THEN 1 END) * 100.0) / 
                    NULLIF(COUNT(CASE WHEN s.type = 'impression' THEN 1 END), 0),
                    2
                ) as ctr,
                MIN(s.created_at) as first_view,
                MAX(s.created_at) as last_view
            FROM {$this->table_name} s
            LEFT JOIN {$wpdb->posts} p ON s.ad_id = p.ID
            LEFT JOIN {$wpdb->posts} z ON s.zone_id = z.ID
            WHERE DATE(s.created_at) BETWEEN %s AND %s
            GROUP BY s.ad_id
            ORDER BY impressions DESC
        ", $start_date, $end_date);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=adwptracker-stats-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'ID Annonce',
            'Nom Annonce',
            'Zone',
            'Impressions',
            'Clicks',
            'CTR (%)',
            'Première vue',
            'Dernière vue'
        ]);
        
        // Add data rows
        foreach ($results as $row) {
            fputcsv($output, [
                $row['ad_id'],
                $row['ad_name'] ?: 'Sans nom',
                $row['zone_name'] ?: 'Sans zone',
                $row['impressions'],
                $row['clicks'],
                $row['ctr'],
                $row['first_view'],
                $row['last_view']
            ]);
        }
        
        fclose($output);
        exit;
    }
}
