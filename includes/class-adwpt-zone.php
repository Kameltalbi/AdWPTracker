<?php
/**
 * Zone custom post type class
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Zone {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_filter('manage_adwpt_zone_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_adwpt_zone_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-adwpt_zone_sortable_columns', [$this, 'sortable_columns']);
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['zone_name'] = 'title';
        $columns['ads_count'] = 'ads_count';
        $columns['date'] = 'date';
        return $columns;
    }
    
    /**
     * Register zone post type
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Zones', 'adwptracker'),
            'singular_name' => __('Zone', 'adwptracker'),
            'add_new' => __('Ajouter une zone', 'adwptracker'),
            'add_new_item' => __('Ajouter une nouvelle zone', 'adwptracker'),
            'edit_item' => __('Modifier la zone', 'adwptracker'),
            'new_item' => __('New Zone', 'adwptracker'),
            'view_item' => __('Voir la zone', 'adwptracker'),
            'search_items' => __('Rechercher des zones', 'adwptracker'),
            'not_found' => __('No zones found', 'adwptracker'),
            'not_found_in_trash' => __('Aucune zone dans la corbeille', 'adwptracker'),
            'menu_name' => __('Zones', 'adwptracker'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title'],
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        ];
        
        register_post_type('adwpt_zone', $args);
    }
    
    /**
     * Add custom columns
     */
    public function add_custom_columns($columns) {
        // Remove default title
        unset($columns['title']);
        unset($columns['date']);
        
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['zone_name'] = __('Zone Name', 'adwptracker');
        $new_columns['shortcode'] = __('Shortcode', 'adwptracker');
        $new_columns['ads_count'] = __('Ads', 'adwptracker');
        $new_columns['status'] = __('Status', 'adwptracker');
        $new_columns['date'] = __('Date', 'adwptracker');
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'zone_name':
                $title = get_the_title($post_id);
                $edit_link = get_edit_post_link($post_id);
                echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a></strong>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url($edit_link) . '">' . __('Edit', 'adwptracker') . '</a> | </span>';
                echo '<span class="trash"><a href="' . get_delete_post_link($post_id) . '">' . __('Trash', 'adwptracker') . '</a></span>';
                echo '</div>';
                break;
            
            case 'shortcode':
                $shortcode = '[adwptracker_zone id="' . $post_id . '"]';
                ?>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <code id="zone-shortcode-<?php echo esc_attr($post_id); ?>" style="background: #1f2937; color: #10b981; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; cursor: pointer;" onclick="copyZoneShortcode(<?php echo esc_js($post_id); ?>)" title="Cliquer pour copier">
                        <?php echo esc_html($shortcode); ?>
                    </code>
                    <span id="zone-copied-<?php echo esc_attr($post_id); ?>" style="display: none; color: #10b981; font-size: 12px;">✓ Copié</span>
                </div>
                <script>
                function copyZoneShortcode(id) {
                    var code = document.getElementById('zone-shortcode-' + id);
                    var text = code.textContent;
                    
                    // Use modern Clipboard API with fallback
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            showZoneCopiedMessage(id);
                        }).catch(function() {
                            fallbackZoneCopy(text, id);
                        });
                    } else {
                        fallbackZoneCopy(text, id);
                    }
                }
                
                function fallbackZoneCopy(text, id) {
                    var input = document.createElement('input');
                    input.value = text;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    try {
                        document.execCommand('copy');
                        showZoneCopiedMessage(id);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                    document.body.removeChild(input);
                }
                
                function showZoneCopiedMessage(id) {
                    var copied = document.getElementById('zone-copied-' + id);
                    copied.style.display = 'inline';
                    setTimeout(function() {
                        copied.style.display = 'none';
                    }, 2000);
                }
                </script>
                <?php
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
                $is_active = ($status === 'active');
                $bg_color = $is_active ? '#d4edda' : '#f8d7da';
                $text_color = $is_active ? '#155724' : '#721c24';
                $label = $is_active ? __('Active', 'adwptracker') : __('Inactive', 'adwptracker');
                echo '<span style="display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; background: ' . $bg_color . '; color: ' . $text_color . '; white-space: nowrap;">' . esc_html($label) . '</span>';
                break;
                
            case 'ads_count':
                $ads = get_posts([
                    'post_type' => 'adwpt_ad',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'meta_query' => [
                        [
                            'key' => '_adwpt_zone_id',
                            'value' => $post_id,
                            'compare' => '=',
                        ],
                    ],
                    'fields' => 'ids',
                ]);
                
                // Filter out inactive ads
                $active_ads = array_filter($ads, function($ad_id) {
                    $status = get_post_meta($ad_id, '_adwpt_status', true);
                    return empty($status) || $status === 'active';
                });
                
                echo '<strong>' . count($active_ads) . '</strong>';
                break;
        }
    }
}
