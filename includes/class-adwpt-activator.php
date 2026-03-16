<?php
/**
 * Activation class
 * Handles plugin activation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Activator {
    
    /**
     * Activation callback
     */
    public static function activate() {
        self::create_stats_table();
        self::upgrade_database();
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('adwptracker_version', ADWPT_VERSION);
    }
    
    /**
     * Upgrade database if needed
     */
    private static function upgrade_database() {
        $current_db_version = get_option('adwptracker_db_version', '1.0');
        
        // Upgrade to version 1.1 - Add device column
        if (version_compare($current_db_version, '1.1', '<')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'adwptracker_stats';
            
            // Check if column exists
            $column_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = %s 
                    AND TABLE_NAME = %s 
                    AND COLUMN_NAME = 'device'",
                    DB_NAME,
                    $table_name
                )
            );
            
            // Add column if it doesn't exist
            if (empty($column_exists)) {
                $wpdb->query(
                    "ALTER TABLE {$table_name} 
                    ADD COLUMN device VARCHAR(20) DEFAULT 'desktop' AFTER type"
                );
            }
            
            // Update existing rows with NULL device to 'desktop'
            $wpdb->query(
                "UPDATE {$table_name} 
                SET device = 'desktop' 
                WHERE device IS NULL OR device = ''"
            );
            
            update_option('adwptracker_db_version', '1.1');
        }
        
        // Upgrade to version 1.2 - Ensure all NULL devices are set to desktop
        if (version_compare($current_db_version, '1.2', '<')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'adwptracker_stats';
            
            // Update any remaining NULL devices
            $wpdb->query(
                "UPDATE {$table_name} 
                SET device = 'desktop' 
                WHERE device IS NULL OR device = ''"
            );
            
            update_option('adwptracker_db_version', '1.2');
        }
    }
    
    /**
     * Create stats table
     */
    private static function create_stats_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'adwptracker_stats';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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
        
        update_option('adwptracker_db_version', '1.2');
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = [
            'adwptracker_tracking_enabled' => 'yes',
            'adwptracker_cache_compatible' => 'yes',
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
}
