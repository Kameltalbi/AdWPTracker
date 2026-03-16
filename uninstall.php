<?php
/**
 * Fired when the plugin is uninstalled
 */

// Exit if accessed directly or not in uninstall context
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete all zones
$zones = get_posts([
    'post_type' => 'adwpt_zone',
    'posts_per_page' => -1,
    'post_status' => 'any',
]);

foreach ($zones as $zone) {
    wp_delete_post($zone->ID, true);
}

// Delete all ads
$ads = get_posts([
    'post_type' => 'adwpt_ad',
    'posts_per_page' => -1,
    'post_status' => 'any',
]);

foreach ($ads as $ad) {
    wp_delete_post($ad->ID, true);
}

// Drop stats table
$table_name = $wpdb->prefix . 'adwptracker_stats';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options
delete_option('adwptracker_version');
delete_option('adwptracker_db_version');

// Clear any cached data
wp_cache_flush();
