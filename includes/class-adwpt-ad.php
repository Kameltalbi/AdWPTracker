<?php
/**
 * Ad custom post type class
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Ad {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_filter('manage_adwpt_ad_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_adwpt_ad_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-adwpt_ad_sortable_columns', [$this, 'sortable_columns']);
        
        // Add duplicate action
        add_filter('post_row_actions', [$this, 'add_duplicate_action'], 10, 2);
        add_action('admin_action_duplicate_ad', [$this, 'duplicate_ad']);
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['ad_name'] = 'title';
        $columns['impressions'] = 'impressions';
        $columns['clicks'] = 'clicks';
        $columns['ctr'] = 'ctr';
        $columns['date'] = 'date';
        return $columns;
    }
    
    /**
     * Register ad post type
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Ads', 'adwptracker'),
            'singular_name' => __('Ad', 'adwptracker'),
            'add_new' => __('Ajouter une annonce', 'adwptracker'),
            'add_new_item' => __('Ajouter une nouvelle annonce', 'adwptracker'),
            'edit_item' => __('Modifier l\'annonce', 'adwptracker'),
            'new_item' => __('New Ad', 'adwptracker'),
            'view_item' => __('Voir l\'annonce', 'adwptracker'),
            'search_items' => __('Rechercher des annonces', 'adwptracker'),
            'not_found' => __('No ads found', 'adwptracker'),
            'not_found_in_trash' => __('Aucune annonce dans la corbeille', 'adwptracker'),
            'menu_name' => __('Ads', 'adwptracker'),
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
        
        register_post_type('adwpt_ad', $args);
    }
    
    /**
     * Add custom columns
     */
    public function add_custom_columns($columns) {
        // Remove default columns we don't want
        unset($columns['title']);
        unset($columns['date']);
        
        // Build new column structure
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['ad_name'] = __('Ad Name', 'adwptracker');
        $new_columns['shortcode'] = __('Shortcode', 'adwptracker');
        $new_columns['type'] = __('Type', 'adwptracker');
        $new_columns['zone'] = __('Zone', 'adwptracker');
        $new_columns['status'] = __('Status', 'adwptracker');
        $new_columns['impressions'] = __('Impressions', 'adwptracker');
        $new_columns['clicks'] = __('Clicks', 'adwptracker');
        $new_columns['ctr'] = __('CTR', 'adwptracker');
        $new_columns['date'] = __('Date', 'adwptracker');
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'ad_name':
                $title = get_the_title($post_id);
                $edit_link = get_edit_post_link($post_id);
                echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a></strong>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url($edit_link) . '">' . __('Edit', 'adwptracker') . '</a> | </span>';
                echo '<span class="trash"><a href="' . get_delete_post_link($post_id) . '">' . __('Trash', 'adwptracker') . '</a></span>';
                echo '</div>';
                break;
            
            case 'shortcode':
                // Add JavaScript only once
                static $script_added = false;
                if (!$script_added) {
                    ?>
                    <script>
                    function copyAdShortcode(id) {
                        var code = document.getElementById('ad-shortcode-' + id);
                        var text = code.textContent;
                        
                        // Use modern Clipboard API with fallback
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text).then(function() {
                                showAdCopiedMessage(id);
                            }).catch(function() {
                                fallbackAdCopy(text, id);
                            });
                        } else {
                            fallbackAdCopy(text, id);
                        }
                    }
                    
                    function fallbackAdCopy(text, id) {
                        var input = document.createElement('input');
                        input.value = text;
                        input.style.position = 'fixed';
                        input.style.opacity = '0';
                        document.body.appendChild(input);
                        input.select();
                        try {
                            document.execCommand('copy');
                            showAdCopiedMessage(id);
                        } catch (err) {
                            console.error('Copy failed:', err);
                        }
                        document.body.removeChild(input);
                    }
                    
                    function showAdCopiedMessage(id) {
                        var copied = document.getElementById('ad-copied-' + id);
                        copied.style.display = 'inline';
                        setTimeout(function() {
                            copied.style.display = 'none';
                        }, 2000);
                    }
                    </script>
                    <?php
                    $script_added = true;
                }
                
                $shortcode = '[adwptracker_ad id="' . $post_id . '"]';
                ?>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <code id="ad-shortcode-<?php echo esc_attr($post_id); ?>" style="background: #1f2937; color: #10b981; padding: 6px 10px; border-radius: 4px; font-size: 12px; font-family: monospace; cursor: pointer;" onclick="copyAdShortcode(<?php echo esc_js($post_id); ?>)" title="Cliquer pour copier">
                        <?php echo esc_html($shortcode); ?>
                    </code>
                    <span id="ad-copied-<?php echo esc_attr($post_id); ?>" style="display: none; color: #10b981; font-size: 12px;">✓ Copié</span>
                </div>
                <?php
                break;
            
            case 'type':
                $type = get_post_meta($post_id, '_adwpt_type', true) ?: 'image';
                $icons = [
                    'image' => '🖼️',
                    'html' => '💻',
                    'text' => '📝',
                    'video' => '🎥',
                ];
                $labels = [
                    'image' => __('Image', 'adwptracker'),
                    'html' => __('HTML', 'adwptracker'),
                    'text' => __('Text', 'adwptracker'),
                    'video' => __('Video', 'adwptracker'),
                ];
                $icon = $icons[$type] ?? '📄';
                $label = $labels[$type] ?? __('Other', 'adwptracker');
                echo '<span class="adwpt-type-badge" style="white-space: nowrap;">' . $icon . ' ' . esc_html($label) . '</span>';
                break;
                
            case 'zone':
                $zone_id = get_post_meta($post_id, '_adwpt_zone_id', true);
                if ($zone_id) {
                    $zone = get_post($zone_id);
                    if ($zone) {
                        echo '<a href="' . get_edit_post_link($zone_id) . '">' . esc_html($zone->post_title) . '</a>';
                    } else {
                        echo '-';
                    }
                } else {
                    echo '-';
                }
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
                $is_active = ($status === 'active');
                $bg_color = $is_active ? '#d4edda' : '#f8d7da';
                $text_color = $is_active ? '#155724' : '#721c24';
                $label = $is_active ? __('Active', 'adwptracker') : __('Inactive', 'adwptracker');
                echo '<span style="display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; background: ' . $bg_color . '; color: ' . $text_color . '; white-space: nowrap;">' . esc_html($label) . '</span>';
                break;
                
            case 'impressions':
                if (class_exists('ADWPT_Stats')) {
                    $stats = ADWPT_Stats::get_instance();
                    $ad_stats = $stats->get_ad_stats($post_id);
                    echo '<strong>' . number_format_i18n($ad_stats['impressions']) . '</strong>';
                } else {
                    echo '-';
                }
                break;
                
            case 'clicks':
                if (class_exists('ADWPT_Stats')) {
                    $stats = ADWPT_Stats::get_instance();
                    $ad_stats = $stats->get_ad_stats($post_id);
                    echo '<strong>' . number_format_i18n($ad_stats['clicks']) . '</strong>';
                } else {
                    echo '-';
                }
                break;
                
            case 'ctr':
                if (class_exists('ADWPT_Stats')) {
                    $stats = ADWPT_Stats::get_instance();
                    $ad_stats = $stats->get_ad_stats($post_id);
                    echo '<strong>' . number_format($ad_stats['ctr'], 2) . '%</strong>';
                } else {
                    echo '-';
                }
                break;
        }
    }
    
    /**
     * Add duplicate link to row actions
     */
    public function add_duplicate_action($actions, $post) {
        if ($post->post_type === 'adwpt_ad' && current_user_can('edit_posts')) {
            $duplicate_url = wp_nonce_url(
                admin_url('admin.php?action=duplicate_ad&post=' . $post->ID),
                'duplicate_ad_' . $post->ID
            );
            
            $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . 
                esc_attr__('Duplicate this ad', 'adwptracker') . '" style="color: #2271b1;">' . 
                '🔄 ' . __('Duplicate', 'adwptracker') . '</a>';
        }
        
        return $actions;
    }
    
    /**
     * Duplicate ad functionality
     */
    public function duplicate_ad() {
        // Security checks
        if (!isset($_GET['post'])) {
            wp_die(__('No ad to duplicate!', 'adwptracker'));
        }
        
        $post_id = absint($_GET['post']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'duplicate_ad_' . $post_id)) {
            wp_die(__('Security check failed!', 'adwptracker'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to duplicate ads.', 'adwptracker'));
        }
        
        // Get original post
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'adwpt_ad') {
            wp_die(__('Invalid ad!', 'adwptracker'));
        }
        
        // Create duplicate
        $new_post = array(
            'post_title'   => $post->post_title . ' (' . __('Copy', 'adwptracker') . ')',
            'post_content' => $post->post_content,
            'post_status'  => 'draft', // Set as draft
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id(),
        );
        
        // Insert new post
        $new_post_id = wp_insert_post($new_post);
        
        if (is_wp_error($new_post_id)) {
            wp_die(__('Failed to duplicate ad!', 'adwptracker'));
        }
        
        // Duplicate all post meta
        $post_meta = get_post_meta($post_id);
        
        foreach ($post_meta as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($new_post_id, $key, maybe_unserialize($value));
            }
        }
        
        // Set status to inactive for safety
        update_post_meta($new_post_id, '_adwpt_status', 'inactive');
        
        // Success message
        add_settings_error(
            'adwptracker_messages',
            'ad_duplicated',
            __('Ad duplicated successfully!', 'adwptracker'),
            'success'
        );
        
        // Redirect to edit new post
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id . '&message=1'));
        exit;
    }
}
