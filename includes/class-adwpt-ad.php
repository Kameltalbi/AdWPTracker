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
        add_filter('views_edit-adwpt_ad', [$this, 'add_status_views']);
        add_action('pre_get_posts', [$this, 'filter_admin_ads']);
        
        add_filter('post_row_actions', [$this, 'add_duplicate_action'], 10, 2);
        add_action('admin_action_duplicate_ad', [$this, 'duplicate_ad']);
        add_action('admin_action_toggle_ad_status', [$this, 'toggle_ad_status']);
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['ad_name'] = 'title';
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
            'capabilities' => [
                'edit_post' => 'adwpt_manage',
                'read_post' => 'adwpt_manage',
                'delete_post' => 'adwpt_manage',
                'edit_posts' => 'adwpt_manage',
                'edit_others_posts' => 'adwpt_manage',
                'publish_posts' => 'adwpt_manage',
                'read_private_posts' => 'adwpt_manage',
                'delete_posts' => 'adwpt_manage',
                'delete_private_posts' => 'adwpt_manage',
                'delete_published_posts' => 'adwpt_manage',
                'delete_others_posts' => 'adwpt_manage',
                'edit_private_posts' => 'adwpt_manage',
                'edit_published_posts' => 'adwpt_manage',
                'create_posts' => 'adwpt_manage',
            ],
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
        $new_columns['preview'] = __('Aperçu', 'adwptracker');
        $new_columns['ad_name'] = __('Publicité', 'adwptracker');
        $new_columns['shortcode'] = __('Shortcode', 'adwptracker');
        $new_columns['type'] = __('Type', 'adwptracker');
        $new_columns['zone'] = __('Zone', 'adwptracker');
        $new_columns['status'] = __('Statut', 'adwptracker');
        $new_columns['impressions'] = __('Impressions', 'adwptracker');
        $new_columns['clicks'] = __('Clics', 'adwptracker');
        $new_columns['ctr'] = __('CTR', 'adwptracker');
        $new_columns['date'] = __('Date', 'adwptracker');
        $new_columns['actions'] = __('Actions', 'adwptracker');
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'preview':
                $image_url = get_post_meta($post_id, '_adwpt_image_url', true);
                if ($image_url) {
                    echo '<img src="' . esc_url($image_url) . '" alt="" style="width: 56px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb;">';
                } else {
                    echo '<span style="color: #9ca3af;">—</span>';
                }
                break;

            case 'ad_name':
                $title = get_the_title($post_id);
                $edit_link = get_edit_post_link($post_id);
                echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($title) . '</a></strong>';
                break;

            case 'shortcode':
                $shortcode = '[adwptracker_ad id="' . $post_id . '"]';
                echo '<code style="cursor: pointer;" title="' . esc_attr__('Cliquer pour copier', 'adwptracker') . '">' . esc_html($shortcode) . '</code>';
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

            case 'device':
                $show_on_mobile_meta = get_post_meta($post_id, '_adwpt_show_on_mobile', true);
                $show_on_desktop_meta = get_post_meta($post_id, '_adwpt_show_on_desktop', true);
                $legacy_device = get_post_meta($post_id, '_adwpt_device', true);
                $show_on_mobile = $show_on_mobile_meta !== '0';
                $show_on_desktop = $show_on_desktop_meta !== '0';

                if ($show_on_mobile_meta === '' && $show_on_desktop_meta === '' && $legacy_device) {
                    $show_on_mobile = in_array($legacy_device, ['all', 'mobile', 'tablet'], true);
                    $show_on_desktop = in_array($legacy_device, ['all', 'desktop'], true);
                }

                if ($show_on_mobile && $show_on_desktop) {
                    echo esc_html__('Tous', 'adwptracker');
                } elseif ($show_on_desktop) {
                    echo esc_html__('Desktop', 'adwptracker');
                } elseif ($show_on_mobile) {
                    echo esc_html__('Mobile/Tablette', 'adwptracker');
                } else {
                    echo '<span style="color: #991b1b;">' . esc_html__('Masquée', 'adwptracker') . '</span>';
                }
                break;
                
            case 'status':
                $status_data = $this->get_display_status($post_id);
                echo '<span class="adwpt-badge ' . esc_attr($status_data['class']) . '">' . esc_html($status_data['label']) . '</span>';
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

            case 'actions':
                $this->render_actions_menu($post_id);
                break;
        }
    }

    private function render_actions_menu($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            echo '-';
            return;
        }

        $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
        $duplicate_url = wp_nonce_url(
            admin_url('admin.php?action=duplicate_ad&post=' . $post_id),
            'duplicate_ad_' . $post_id
        );
        $toggle_url = wp_nonce_url(
            admin_url('admin.php?action=toggle_ad_status&post=' . $post_id),
            'toggle_ad_status_' . $post_id
        );
        ?>
        <div class="adwpt-row-actions-menu">
            <button type="button" class="adwpt-row-actions-toggle" aria-haspopup="true" aria-expanded="false">
                <span class="screen-reader-text"><?php esc_html_e('Actions de la publicité', 'adwptracker'); ?></span>
                ⋯
            </button>
            <div class="adwpt-row-actions-dropdown" role="menu">
                <a role="menuitem" href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php esc_html_e('Modifier', 'adwptracker'); ?></a>
                <a role="menuitem" href="<?php echo esc_url($duplicate_url); ?>"><?php esc_html_e('Dupliquer', 'adwptracker'); ?></a>
                <a role="menuitem" href="<?php echo esc_url($toggle_url); ?>"><?php echo esc_html($status === 'active' ? __('Mettre en pause', 'adwptracker') : __('Activer', 'adwptracker')); ?></a>
                <a role="menuitem" href="<?php echo esc_url(admin_url('admin.php?page=adwptracker-stats')); ?>"><?php esc_html_e('Statistiques', 'adwptracker'); ?></a>
                <a role="menuitem" class="is-danger" href="<?php echo esc_url(get_delete_post_link($post_id)); ?>"><?php esc_html_e('Supprimer', 'adwptracker'); ?></a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add duplicate link to row actions
     */
    public function add_duplicate_action($actions, $post) {
        if ($post->post_type === 'adwpt_ad' && current_user_can('edit_post', $post->ID)) {
            $duplicate_url = wp_nonce_url(
                admin_url('admin.php?action=duplicate_ad&post=' . $post->ID),
                'duplicate_ad_' . $post->ID
            );
            
            $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . 
                esc_attr__('Duplicate this ad', 'adwptracker') . '" style="color: #2271b1;">' . 
                '🔄 ' . __('Duplicate', 'adwptracker') . '</a>';

            $status = get_post_meta($post->ID, '_adwpt_status', true) ?: 'active';
            $toggle_url = wp_nonce_url(
                admin_url('admin.php?action=toggle_ad_status&post=' . $post->ID),
                'toggle_ad_status_' . $post->ID
            );
            $actions['toggle_status'] = '<a href="' . esc_url($toggle_url) . '">' . esc_html($status === 'active' ? __('Mettre en pause', 'adwptracker') : __('Activer', 'adwptracker')) . '</a>';
            $actions['statistics'] = '<a href="' . esc_url(admin_url('admin.php?page=adwptracker-stats')) . '">' . esc_html__('Statistiques', 'adwptracker') . '</a>';
        }
        
        return $actions;
    }

    public function add_status_views($views) {
        $base_url = admin_url('edit.php?post_type=adwpt_ad');
        $views['adwpt_active'] = '<a href="' . esc_url(add_query_arg('adwpt_status_filter', 'active', $base_url)) . '">' . esc_html__('Actives', 'adwptracker') . '</a>';
        $views['adwpt_paused'] = '<a href="' . esc_url(add_query_arg('adwpt_status_filter', 'paused', $base_url)) . '">' . esc_html__('En pause', 'adwptracker') . '</a>';
        $views['adwpt_expired'] = '<a href="' . esc_url(add_query_arg('adwpt_status_filter', 'expired', $base_url)) . '">' . esc_html__('Expirées', 'adwptracker') . '</a>';
        return $views;
    }

    public function filter_admin_ads($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'adwpt_ad') {
            return;
        }

        $filter = isset($_GET['adwpt_status_filter']) ? sanitize_key($_GET['adwpt_status_filter']) : '';
        $meta_query = (array) $query->get('meta_query');

        if ($filter === 'active') {
            $meta_query[] = [
                'key' => '_adwpt_status',
                'value' => 'active',
                'compare' => '=',
            ];
        } elseif ($filter === 'paused') {
            $meta_query[] = [
                'key' => '_adwpt_status',
                'value' => 'inactive',
                'compare' => '=',
            ];
        } elseif ($filter === 'expired') {
            $meta_query[] = [
                'key' => '_adwpt_end_date',
                'value' => current_time('Y-m-d'),
                'compare' => '<',
                'type' => 'DATE',
            ];
        }

        if ($meta_query) {
            $query->set('meta_query', $meta_query);
        }
    }

    private function get_display_status($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'draft') {
            return ['label' => __('Brouillon', 'adwptracker'), 'class' => 'adwpt-badge-draft'];
        }

        $end_date = get_post_meta($post_id, '_adwpt_end_date', true);
        if ($end_date && $end_date < current_time('Y-m-d')) {
            return ['label' => __('Expirée', 'adwptracker'), 'class' => 'adwpt-badge-expired'];
        }

        $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
        if ($status !== 'active') {
            return ['label' => __('En pause', 'adwptracker'), 'class' => 'adwpt-badge-paused'];
        }

        return ['label' => __('Active', 'adwptracker'), 'class' => 'adwpt-badge-active'];
    }

    public function toggle_ad_status() {
        if (!isset($_GET['post'])) {
            wp_die(__('Aucune annonce sélectionnée.', 'adwptracker'));
        }

        $post_id = absint($_GET['post']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'toggle_ad_status_' . $post_id)) {
            wp_die(__('Security check failed!', 'adwptracker'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('Vous n’avez pas la permission de modifier cette annonce.', 'adwptracker'));
        }

        $status = get_post_meta($post_id, '_adwpt_status', true) ?: 'active';
        update_post_meta($post_id, '_adwpt_status', $status === 'active' ? 'inactive' : 'active');

        wp_safe_redirect(admin_url('edit.php?post_type=adwpt_ad'));
        exit;
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
        
        if (!current_user_can('edit_post', $post_id)) {
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
