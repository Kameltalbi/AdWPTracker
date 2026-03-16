<?php
/**
 * Frontend class
 * Handles frontend display and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('adwptracker_zone', [$this, 'render_zone_shortcode']);
        add_shortcode('adwptracker_ad', [$this, 'render_ad_shortcode']);
        
        // Sticky footer mobile
        add_action('wp_footer', [$this, 'render_sticky_mobile_footer']);
        
        // AJAX handlers
        add_action('wp_ajax_adwptracker_track_impression', [$this, 'track_impression']);
        add_action('wp_ajax_nopriv_adwptracker_track_impression', [$this, 'track_impression']);
        add_action('wp_ajax_adwptracker_track_click', [$this, 'track_click']);
        add_action('wp_ajax_nopriv_adwptracker_track_click', [$this, 'track_click']);
    }
    
    /**
     * Render single ad shortcode
     */
    public function render_ad_shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $ad_id = absint($atts['id']);
        
        if (!$ad_id) {
            return '';
        }
        
        // Get ad
        $ad = get_post($ad_id);
        if (!$ad || $ad->post_type !== 'adwpt_ad' || $ad->post_status !== 'publish') {
            return '';
        }
        
        // Check status
        $status = get_post_meta($ad_id, '_adwpt_status', true);
        if ($status === 'inactive') {
            return '';
        }
        
        // Check dates
        $current_date = current_time('Y-m-d');
        $start_date = get_post_meta($ad_id, '_adwpt_start_date', true);
        $end_date = get_post_meta($ad_id, '_adwpt_end_date', true);
        
        if ($start_date && $start_date > $current_date) {
            return '';
        }
        
        if ($end_date && $end_date < $current_date) {
            return '';
        }
        
        // Check device
        $device = get_post_meta($ad_id, '_adwpt_device', true) ?: 'all';
        if ($device !== 'all') {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            
            // Improved tablet detection
            $is_tablet = preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))|kindle/i', $user_agent);
            
            // Improved mobile detection
            $is_mobile = wp_is_mobile() || preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $user_agent);
            
            // Tablet is mobile but not phone
            if ($is_tablet) {
                $is_mobile = false;
            }
            
            if ($device === 'desktop' && ($is_mobile || $is_tablet)) return '';
            if ($device === 'mobile' && !$is_mobile) return '';
            if ($device === 'tablet' && !$is_tablet) return '';
        }
        
        // Get ad data
        $type = get_post_meta($ad_id, '_adwpt_type', true) ?: 'image';
        $image_url = get_post_meta($ad_id, '_adwpt_image_url', true);
        $html_code = get_post_meta($ad_id, '_adwpt_html_code', true);
        $text_title = get_post_meta($ad_id, '_adwpt_text_title', true);
        $text_content = get_post_meta($ad_id, '_adwpt_text_content', true);
        $video_url = get_post_meta($ad_id, '_adwpt_video_url', true);
        $video_type = get_post_meta($ad_id, '_adwpt_video_type', true) ?: 'youtube';
        $link_url = get_post_meta($ad_id, '_adwpt_link_url', true);
        $link_target = get_post_meta($ad_id, '_adwpt_link_target', true) ?: '_blank';
        $zone_id = get_post_meta($ad_id, '_adwpt_zone_id', true);
        
        // Start output
        ob_start();
        ?>
        <div class="adwptracker-single-ad" data-ad-id="<?php echo esc_attr($ad_id); ?>" data-zone-id="<?php echo esc_attr($zone_id); ?>" style="display: block; width: 100%; max-width: 100%;">
            <?php if ($type === 'image' && $image_url): ?>
                <?php if ($link_url): ?>
                    <a href="<?php echo esc_url($link_url); ?>" class="adwptracker-link" target="<?php echo esc_attr($link_target); ?>" rel="noopener" data-ad-id="<?php echo esc_attr($ad_id); ?>" data-zone-id="<?php echo esc_attr($zone_id); ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($ad->post_title); ?>" style="width: 100%; height: auto; display: block;">
                    </a>
                <?php else: ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($ad->post_title); ?>" style="width: 100%; height: auto; display: block;">
                <?php endif; ?>
                
            <?php elseif ($type === 'html' && $html_code): ?>
                <div class="adwptracker-html-content">
                    <?php echo wp_kses_post($html_code); ?>
                </div>
                
            <?php elseif ($type === 'text' && ($text_title || $text_content)): ?>
                <?php 
                $wrapper_style = 'display: block; padding: 20px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: inherit;';
                if ($link_url) {
                    echo '<a href="' . esc_url($link_url) . '" class="adwptracker-link" target="' . esc_attr($link_target) . '" rel="noopener" data-ad-id="' . esc_attr($ad_id) . '" data-zone-id="' . esc_attr($zone_id) . '" style="' . esc_attr($wrapper_style) . '">';
                } else {
                    echo '<div style="padding: 20px; background: #f8f9fa; border-radius: 8px;">';
                }
                ?>
                    <?php if ($text_title): ?>
                        <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2271b1; font-weight: 600;"><?php echo esc_html($text_title); ?></h3>
                    <?php endif; ?>
                    <?php if ($text_content): ?>
                        <p style="margin: 0; color: #50575e; line-height: 1.6;"><?php echo nl2br(esc_html($text_content)); ?></p>
                    <?php endif; ?>
                <?php echo $link_url ? '</a>' : '</div>'; ?>
                
            <?php elseif ($type === 'video' && $video_url): ?>
                <?php
                $embed = '';
                if ($video_type === 'youtube') {
                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/', $video_url, $m);
                    if (isset($m[1])) {
                        $embed = '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . esc_attr($m[1]) . '" frameborder="0" allowfullscreen style="max-width: 100%;"></iframe>';
                    }
                } elseif ($video_type === 'vimeo') {
                    preg_match('/vimeo\.com\/(\d+)/', $video_url, $m);
                    if (isset($m[1])) {
                        $embed = '<iframe src="https://player.vimeo.com/video/' . esc_attr($m[1]) . '" width="100%" height="315" frameborder="0" allowfullscreen style="max-width: 100%;"></iframe>';
                    }
                } elseif ($video_type === 'mp4') {
                    $embed = '<video controls style="width: 100%; max-width: 100%; height: auto;"><source src="' . esc_url($video_url) . '" type="video/mp4"></video>';
                }
                echo $embed;
                ?>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Track impression
            var adId = <?php echo esc_js($ad_id); ?>;
            var zoneId = <?php echo esc_js($zone_id); ?>;
            
            if (typeof adwptrackerData !== 'undefined') {
                $.ajax({
                    url: adwptrackerData.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'adwptracker_track_impression',
                        nonce: adwptrackerData.nonce,
                        ad_id: adId,
                        zone_id: zoneId
                    }
                });
            }
            
            // Track click
            $('.adwptracker-single-ad [data-ad-id="' + adId + '"]').on('click', function() {
                if (typeof adwptrackerData !== 'undefined') {
                    $.ajax({
                        url: adwptrackerData.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'adwptracker_track_click',
                            nonce: adwptrackerData.nonce,
                            ad_id: adId,
                            zone_id: zoneId
                        }
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Critical CSS pour sticky mobile - chargé en premier
        wp_enqueue_style(
            'adwptracker-critical',
            ADWPT_PLUGIN_URL . 'assets/css/sticky-mobile-critical.css',
            [],
            ADWPT_VERSION,
            'all'
        );
        
        wp_enqueue_style(
            'adwptracker-frontend',
            ADWPT_PLUGIN_URL . 'assets/css/frontend.css',
            ['adwptracker-critical'],
            ADWPT_VERSION
        );
        
        wp_enqueue_script(
            'adwptracker-tracker',
            ADWPT_PLUGIN_URL . 'assets/js/tracker.js',
            [],
            ADWPT_VERSION,
            true
        );
        
        wp_enqueue_script(
            'adwptracker-slider',
            ADWPT_PLUGIN_URL . 'assets/js/slider.js',
            [],
            ADWPT_VERSION,
            true
        );
        
        wp_enqueue_script(
            'adwptracker-sticky-mobile',
            ADWPT_PLUGIN_URL . 'assets/js/sticky-mobile.js',
            [],
            ADWPT_VERSION,
            true
        );
        
        wp_enqueue_script(
            'adwptracker-force-responsive',
            ADWPT_PLUGIN_URL . 'assets/js/force-responsive.js',
            [],
            ADWPT_VERSION,
            true
        );
        
        wp_localize_script('adwptracker-tracker', 'adwptrackerData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adwptracker_tracking'),
        ]);
        
        wp_localize_script('adwptracker-sticky-mobile', 'adwptrackerData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adwptracker_tracking'),
        ]);
    }
    
    /**
     * Render zone shortcode
     */
    public function render_zone_shortcode($atts) {
        // Don't render in admin
        if (is_admin()) {
            return '';
        }
        
        $atts = shortcode_atts([
            'id' => 0,
            'mode' => '', // Empty = use zone setting
            'slider' => '', // Empty = use zone setting
        ], $atts, 'adwptracker_zone');
        
        $zone_id = absint($atts['id']);
        
        if (!$zone_id) {
            return '<!-- AdWPtracker: Zone ID missing -->';
        }
        
        // Check if zone exists and is active
        $zone = get_post($zone_id);
        if (!$zone || $zone->post_type !== 'adwpt_zone') {
            return '<!-- AdWPtracker: Zone not found -->';
        }
        
        $zone_status = get_post_meta($zone_id, '_adwpt_status', true);
        if ($zone_status === 'inactive') {
            return '<!-- AdWPtracker: Zone inactive -->';
        }
        
        // Get zone settings
        $zone_mode = get_post_meta($zone_id, '_adwpt_display_mode', true) ?: 'random';
        $zone_slider = get_post_meta($zone_id, '_adwpt_slider_enabled', true) ?: 'auto';
        $zone_slider_speed = get_post_meta($zone_id, '_adwpt_slider_speed', true) ?: '5';
        $zone_max_width = get_post_meta($zone_id, '_adwpt_max_width', true);
        $zone_max_height = get_post_meta($zone_id, '_adwpt_max_height', true);
        
        // Use shortcode params or zone defaults
        $mode = !empty($atts['mode']) ? sanitize_text_field($atts['mode']) : $zone_mode;
        $slider = !empty($atts['slider']) ? sanitize_text_field($atts['slider']) : $zone_slider;
        $slider_speed = absint($zone_slider_speed) * 1000; // Convert to milliseconds
        
        // Get ads for this zone
        $args = [
            'post_type' => 'adwpt_ad',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_adwpt_zone_id',
                    'value' => $zone_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ],
            ],
            'orderby' => 'rand',
            'no_found_rows' => true,
        ];
        
        $ads = get_posts($args);
        
        // Filter inactive ads manually (more reliable than meta_query)
        $ads = array_filter($ads, function($ad) {
            $status = get_post_meta($ad->ID, '_adwpt_status', true);
            return empty($status) || $status === 'active';
        });
        
        // Reindex after filter
        $ads = array_values($ads);
        
        if (empty($ads)) {
            return '<!-- AdWPtracker: No active ads in this zone -->';
        }
        
        // Filter by date
        $current_date = current_time('Y-m-d');
        $ads = array_filter($ads, function($ad) use ($current_date) {
            $start_date = get_post_meta($ad->ID, '_adwpt_start_date', true);
            $end_date = get_post_meta($ad->ID, '_adwpt_end_date', true);
            
            if ($start_date && $start_date > $current_date) {
                return false;
            }
            
            if ($end_date && $end_date < $current_date) {
                return false;
            }
            
            return true;
        });
        
        // Reindex array after filter (IMPORTANT!)
        $ads = array_values($ads);
        
        if (empty($ads)) {
            return '<!-- AdWPtracker: No ads matching date criteria -->';
        }
        
        // Select ads based on mode and slider setting
        if ($mode === 'random' && $slider !== 'yes' && count($ads) > 0) {
            // Random mode: pick one ad (unless slider is forced)
            $random_key = array_rand($ads);
            $ads = [$ads[$random_key]];
        }
        
        // Determine if slider should be active
        $enable_slider = false;
        if ($slider === 'yes' || ($slider === 'auto' && $mode === 'all' && count($ads) > 1)) {
            $enable_slider = true;
        }
        
        // Render ads
        ob_start();
        
        // Check if template exists
        $template = ADWPT_PLUGIN_DIR . 'templates/frontend/zone.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<!-- AdWPtracker: Template not found -->';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Track impression via AJAX
     */
    public function track_impression() {
        check_ajax_referer('adwptracker_tracking', 'nonce');
        
        $ad_id = isset($_POST['ad_id']) ? absint($_POST['ad_id']) : 0;
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        
        if (!$ad_id || !$zone_id) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        
        if (!class_exists('ADWPT_Stats')) {
            wp_send_json_error(['message' => 'Stats class not loaded']);
        }
        
        $stats = ADWPT_Stats::get_instance();
        $result = $stats->record_impression($ad_id, $zone_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Impression recorded']);
        } else {
            wp_send_json_error(['message' => 'Failed to record impression']);
        }
    }
    
    /**
     * Track click via AJAX
     */
    public function track_click() {
        check_ajax_referer('adwptracker_tracking', 'nonce');
        
        $ad_id = isset($_POST['ad_id']) ? absint($_POST['ad_id']) : 0;
        $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
        
        if (!$ad_id || !$zone_id) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        
        if (!class_exists('ADWPT_Stats')) {
            wp_send_json_error(['message' => 'Stats class not loaded']);
        }
        
        $stats = ADWPT_Stats::get_instance();
        $stats->record_click($ad_id, $zone_id);
        
        $redirect_url = get_post_meta($ad_id, '_adwpt_link_url', true);
        
        wp_send_json_success([
            'message' => 'Click recorded',
            'redirect_url' => $redirect_url,
        ]);
    }
    
    /**
     * Render sticky mobile footer
     */
    public function render_sticky_mobile_footer() {
        // Find all sticky bottom ads for mobile
        $args = [
            'post_type' => 'adwpt_ad',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Get all
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_adwpt_sticky_enabled',
                    'value' => '1',
                    'compare' => '='
                ],
                [
                    'key' => '_adwpt_sticky_position',
                    'value' => 'bottom',
                    'compare' => '='
                ],
                [
                    'key' => '_adwpt_show_on_mobile',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        
        $sticky_ads = get_posts($args);
        
        if (empty($sticky_ads)) {
            return;
        }
        
        // Filter ads manually
        $valid_ad = null;
        $current_date = current_time('Y-m-d');
        
        foreach ($sticky_ads as $ad) {
            $ad_id = $ad->ID;
            
            // Check status
            $status = get_post_meta($ad_id, '_adwpt_status', true);
            if ($status === 'inactive') {
                continue;
            }
            
            // Check schedule dates
            $start_date = get_post_meta($ad_id, '_adwpt_start_date', true);
            $end_date = get_post_meta($ad_id, '_adwpt_end_date', true);
            
            if (!empty($start_date) && $current_date < $start_date) {
                continue;
            }
            if (!empty($end_date) && $current_date > $end_date) {
                continue;
            }
            
            // Check desktop display
            $show_on_desktop = get_post_meta($ad_id, '_adwpt_show_on_desktop', true);
            if ($show_on_desktop === '1') {
                continue; // Skip if also showing on desktop
            }
            
            // Valid ad found
            $valid_ad = $ad;
            break;
        }
        
        if (!$valid_ad) {
            return;
        }
        
        $ad_id = $valid_ad->ID;
        $zone_id = get_post_meta($ad_id, '_adwpt_zone_id', true);
        $type = get_post_meta($ad_id, '_adwpt_type', true) ?: 'image';
        $image_url = get_post_meta($ad_id, '_adwpt_image_url', true);
        $html_code = get_post_meta($ad_id, '_adwpt_html_code', true);
        $link_url = get_post_meta($ad_id, '_adwpt_link_url', true);
        $link_target = get_post_meta($ad_id, '_adwpt_link_target', true) ?: '_blank';
        
        ?>
        <div id="adwptracker-sticky-footer" class="adwptracker-sticky-footer-mobile" style="box-sizing: border-box; overflow: visible !important; margin: 0; padding: 0; position: relative;">
            <button class="adwptracker-sticky-close" 
                    type="button"
                    onclick="var footer = document.getElementById('adwptracker-sticky-footer'); if(footer) { footer.style.display='none'; footer.remove(); } sessionStorage.setItem('adwptracker_sticky_closed','1');"
                    style="position: absolute !important; 
                           top: -15px !important; 
                           right: 5px !important; 
                           width: 50px !important; 
                           height: 50px !important; 
                           background: #f44336 !important; 
                           color: white !important; 
                           border: 3px solid white !important; 
                           border-radius: 50% !important; 
                           font-size: 30px !important; 
                           font-weight: bold !important; 
                           line-height: 44px !important; 
                           text-align: center !important; 
                           cursor: pointer !important; 
                           z-index: 9999999 !important; 
                           display: block !important;
                           box-shadow: 0 4px 8px rgba(0,0,0,0.3) !important;
                           touch-action: manipulation !important;
                           -webkit-tap-highlight-color: transparent !important;">×</button>
            <div class="adwptracker-sticky-content" 
                 data-ad-id="<?php echo esc_attr($ad_id); ?>" 
                 data-zone-id="<?php echo esc_attr($zone_id); ?>"
                 style="width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; margin: 0; padding: 0;">
                <?php if ($type === 'image' && $image_url): ?>
                    <?php if ($link_url): ?>
                        <a href="<?php echo esc_url($link_url); ?>" 
                           class="adwptracker-link" 
                           target="<?php echo esc_attr($link_target); ?>"
                           data-ad-id="<?php echo esc_attr($ad_id); ?>"
                           data-zone-id="<?php echo esc_attr($zone_id); ?>"
                           style="display: block; width: 100%; max-width: 100%; box-sizing: border-box; margin: 0; padding: 0; text-decoration: none;">
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 alt="<?php echo esc_attr($valid_ad->post_title); ?>"
                                 style="width: 100%; max-width: 100%; height: auto; display: block; margin: 0; padding: 0; box-sizing: border-box; object-fit: contain; border: none;">
                        </a>
                    <?php else: ?>
                        <img src="<?php echo esc_url($image_url); ?>" 
                             alt="<?php echo esc_attr($valid_ad->post_title); ?>"
                             style="width: 100%; max-width: 100%; height: auto; display: block; margin: 0; padding: 0; box-sizing: border-box; object-fit: contain; border: none;">
                    <?php endif; ?>
                <?php elseif ($type === 'html' && $html_code): ?>
                    <?php echo wp_kses_post($html_code); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
