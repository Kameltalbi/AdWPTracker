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
        
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_adwpt_zone', [$this, 'save_zone_meta'], 10, 2);
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
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'adwpt_zone_display_settings',
            '🎨 ' . __('Paramètres d\'Affichage', 'adwptracker'),
            [$this, 'render_display_settings_meta_box'],
            'adwpt_zone',
            'normal',
            'high'
        );
        
        add_meta_box(
            'adwpt_zone_slider_settings',
            '🎬 ' . __('Configuration du Slider', 'adwptracker'),
            [$this, 'render_slider_settings_meta_box'],
            'adwpt_zone',
            'normal',
            'high'
        );
        
        add_meta_box(
            'adwpt_zone_dimensions',
            '📐 ' . __('Dimensions', 'adwptracker'),
            [$this, 'render_dimensions_meta_box'],
            'adwpt_zone',
            'side',
            'default'
        );
        
        add_meta_box(
            'adwpt_zone_status',
            '⚡ ' . __('Statut', 'adwptracker'),
            [$this, 'render_status_meta_box'],
            'adwpt_zone',
            'side',
            'high'
        );
        
        add_meta_box(
            'adwpt_zone_shortcode',
            '📋 ' . __('Shortcode', 'adwptracker'),
            [$this, 'render_shortcode_meta_box'],
            'adwpt_zone',
            'side',
            'default'
        );
    }
    
    /**
     * Render display settings meta box
     */
    public function render_display_settings_meta_box($post) {
        wp_nonce_field('adwpt_zone_meta_box', 'adwpt_zone_meta_box_nonce');
        
        $display_mode = get_post_meta($post->ID, '_adwpt_display_mode', true) ?: 'random';
        ?>
        <div class="adwpt-meta-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="adwpt_display_mode">
                            <?php esc_html_e('Mode d\'Affichage', 'adwptracker'); ?>
                        </label>
                    </th>
                    <td>
                        <select name="adwpt_display_mode" id="adwpt_display_mode" class="regular-text">
                            <option value="random" <?php selected($display_mode, 'random'); ?>>
                                🎲 <?php esc_html_e('Aléatoire - Affiche 1 annonce au hasard', 'adwptracker'); ?>
                            </option>
                            <option value="all" <?php selected($display_mode, 'all'); ?>>
                                📋 <?php esc_html_e('Toutes - Affiche toutes les annonces', 'adwptracker'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choisissez comment les annonces de cette zone seront affichées', 'adwptracker'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <style>
            .adwpt-meta-box .form-table th {
                padding: 15px 10px 15px 0;
                width: 200px;
            }
            .adwpt-meta-box .form-table td {
                padding: 15px 10px;
            }
        </style>
        <?php
    }
    
    /**
     * Render slider settings meta box
     */
    public function render_slider_settings_meta_box($post) {
        $slider_enabled = get_post_meta($post->ID, '_adwpt_slider_enabled', true) ?: 'auto';
        $slider_speed = get_post_meta($post->ID, '_adwpt_slider_speed', true) ?: '5';
        ?>
        <div class="adwpt-meta-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="adwpt_slider_enabled">
                            <?php esc_html_e('Activer le Slider', 'adwptracker'); ?>
                        </label>
                    </th>
                    <td>
                        <select name="adwpt_slider_enabled" id="adwpt_slider_enabled" class="regular-text">
                            <option value="auto" <?php selected($slider_enabled, 'auto'); ?>>
                                ⚙️ <?php esc_html_e('Automatique - Slider si plusieurs annonces', 'adwptracker'); ?>
                            </option>
                            <option value="yes" <?php selected($slider_enabled, 'yes'); ?>>
                                ✅ <?php esc_html_e('Oui - Toujours activer le slider', 'adwptracker'); ?>
                            </option>
                            <option value="no" <?php selected($slider_enabled, 'no'); ?>>
                                ❌ <?php esc_html_e('Non - Désactiver le slider', 'adwptracker'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Le slider fait défiler automatiquement les annonces', 'adwptracker'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="slider_speed_row" style="<?php echo $slider_enabled === 'no' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="adwpt_slider_speed">
                            <?php esc_html_e('Vitesse du Slider', 'adwptracker'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="adwpt_slider_speed" 
                               id="adwpt_slider_speed" 
                               value="<?php echo esc_attr($slider_speed); ?>" 
                               min="1" 
                               max="60" 
                               step="1"
                               class="small-text">
                        <span><?php esc_html_e('secondes', 'adwptracker'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Temps d\'affichage de chaque annonce (1-60 secondes)', 'adwptracker'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#adwpt_slider_enabled').on('change', function() {
                if ($(this).val() === 'no') {
                    $('#slider_speed_row').fadeOut();
                } else {
                    $('#slider_speed_row').fadeIn();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render dimensions meta box
     */
    public function render_dimensions_meta_box($post) {
        $max_width = get_post_meta($post->ID, '_adwpt_max_width', true);
        $max_height = get_post_meta($post->ID, '_adwpt_max_height', true);
        ?>
        <div class="adwpt-meta-box">
            <p>
                <label for="adwpt_max_width">
                    <strong><?php esc_html_e('Largeur Max', 'adwptracker'); ?></strong>
                </label>
                <input type="text" 
                       name="adwpt_max_width" 
                       id="adwpt_max_width" 
                       value="<?php echo esc_attr($max_width); ?>" 
                       placeholder="100%"
                       class="widefat">
                <span class="description"><?php esc_html_e('Ex: 300px, 100%, auto', 'adwptracker'); ?></span>
            </p>
            
            <p>
                <label for="adwpt_max_height">
                    <strong><?php esc_html_e('Hauteur Max', 'adwptracker'); ?></strong>
                </label>
                <input type="text" 
                       name="adwpt_max_height" 
                       id="adwpt_max_height" 
                       value="<?php echo esc_attr($max_height); ?>" 
                       placeholder="auto"
                       class="widefat">
                <span class="description"><?php esc_html_e('Ex: 250px, auto', 'adwptracker'); ?></span>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render status meta box
     */
    public function render_status_meta_box($post) {
        $status = get_post_meta($post->ID, '_adwpt_status', true) ?: 'active';
        ?>
        <div class="adwpt-meta-box">
            <p>
                <label>
                    <input type="radio" 
                           name="adwpt_status" 
                           value="active" 
                           <?php checked($status, 'active'); ?>>
                    <strong style="color: #155724;">✅ <?php esc_html_e('Active', 'adwptracker'); ?></strong>
                </label>
            </p>
            <p>
                <label>
                    <input type="radio" 
                           name="adwpt_status" 
                           value="inactive" 
                           <?php checked($status, 'inactive'); ?>>
                    <strong style="color: #721c24;">❌ <?php esc_html_e('Inactive', 'adwptracker'); ?></strong>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e('Une zone inactive ne sera pas affichée sur le site', 'adwptracker'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render shortcode meta box
     */
    public function render_shortcode_meta_box($post) {
        if ($post->ID) {
            $shortcode = '[adwptracker_zone id="' . $post->ID . '"]';
            ?>
            <div class="adwpt-meta-box">
                <p>
                    <strong><?php esc_html_e('Shortcode de base :', 'adwptracker'); ?></strong>
                </p>
                <input type="text" 
                       value="<?php echo esc_attr($shortcode); ?>" 
                       readonly 
                       class="widefat"
                       onclick="this.select();"
                       style="font-family: monospace; background: #f0f0f1; padding: 8px;">
                
                <p style="margin-top: 15px;">
                    <strong><?php esc_html_e('Avec options :', 'adwptracker'); ?></strong>
                </p>
                <input type="text" 
                       value='[adwptracker_zone id="<?php echo $post->ID; ?>" mode="all" slider="yes"]' 
                       readonly 
                       class="widefat"
                       onclick="this.select();"
                       style="font-family: monospace; background: #f0f0f1; padding: 8px;">
                
                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e('Cliquez pour sélectionner et copier', 'adwptracker'); ?>
                </p>
            </div>
            <?php
        } else {
            ?>
            <p class="description">
                <?php esc_html_e('Le shortcode sera disponible après la publication', 'adwptracker'); ?>
            </p>
            <?php
        }
    }
    
    /**
     * Save zone meta
     */
    public function save_zone_meta($post_id, $post) {
        // Check nonce
        if (!isset($_POST['adwpt_zone_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['adwpt_zone_meta_box_nonce'], 'adwpt_zone_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save display mode
        if (isset($_POST['adwpt_display_mode'])) {
            update_post_meta($post_id, '_adwpt_display_mode', sanitize_text_field($_POST['adwpt_display_mode']));
        }
        
        // Save slider settings
        if (isset($_POST['adwpt_slider_enabled'])) {
            update_post_meta($post_id, '_adwpt_slider_enabled', sanitize_text_field($_POST['adwpt_slider_enabled']));
        }
        
        if (isset($_POST['adwpt_slider_speed'])) {
            $speed = absint($_POST['adwpt_slider_speed']);
            $speed = max(1, min(60, $speed)); // Clamp between 1 and 60
            update_post_meta($post_id, '_adwpt_slider_speed', $speed);
        }
        
        // Save dimensions
        if (isset($_POST['adwpt_max_width'])) {
            update_post_meta($post_id, '_adwpt_max_width', sanitize_text_field($_POST['adwpt_max_width']));
        }
        
        if (isset($_POST['adwpt_max_height'])) {
            update_post_meta($post_id, '_adwpt_max_height', sanitize_text_field($_POST['adwpt_max_height']));
        }
        
        // Save status
        if (isset($_POST['adwpt_status'])) {
            update_post_meta($post_id, '_adwpt_status', sanitize_text_field($_POST['adwpt_status']));
        }
    }
}
