<?php
/**
 * Deactivation class
 * Handles plugin deactivation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Deactivator {
    
    /**
     * Deactivation callback
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled events if any
        wp_clear_scheduled_hook('adwptracker_cleanup_old_stats');
    }
}
