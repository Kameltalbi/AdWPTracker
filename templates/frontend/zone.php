<?php
/**
 * Template for displaying ad zone
 * 
 * Available variables:
 * @var array $ads Array of WP_Post objects
 * @var int $zone_id Zone ID
 * @var bool $enable_slider Whether to enable slider
 * @var string $zone_max_width Max width from zone settings
 * @var string $zone_max_height Max height from zone settings
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine zone dimensions
$zone_width = !empty($zone_max_width) ? $zone_max_width : '100%';
$zone_height = !empty($zone_max_height) ? $zone_max_height : 'auto';

// Check if zone has fixed width (not percentage)
$has_fixed_width = !empty($zone_max_width) && strpos($zone_max_width, '%') === false;

// Build inline styles for zone container
$zone_inline_style = '';
if (!empty($zone_max_width)) {
    if ($has_fixed_width) {
        $zone_inline_style .= 'width: 100% !important; ';
    } else {
        $zone_inline_style .= 'width: ' . esc_attr($zone_width) . ' !important; ';
    }
    $zone_inline_style .= 'max-width: ' . esc_attr($zone_width) . ' !important; ';
    if ($has_fixed_width) {
        $zone_inline_style .= 'margin-left: auto !important; margin-right: auto !important; ';
    }
} else {
    $zone_inline_style .= 'width: 100% !important; max-width: 100% !important; ';
}

if (!empty($zone_max_height) && $zone_max_height !== 'auto') {
    $zone_inline_style .= 'max-height: ' . esc_attr($zone_height) . ' !important; overflow: hidden !important; ';
}

$zone_inline_style .= 'display: block !important; box-sizing: border-box !important;';
?>

<style>
/* Zone specific styles with high priority */
.adwptracker-zone-<?php echo esc_attr($zone_id); ?> {
    <?php if (!empty($zone_max_width)): ?>
    <?php if ($has_fixed_width): ?>
    width: 100% !important;
    <?php else: ?>
    width: <?php echo esc_attr($zone_width); ?> !important;
    <?php endif; ?>
    max-width: <?php echo esc_attr($zone_width); ?> !important;
    <?php if ($has_fixed_width): ?>
    margin-left: auto !important;
    margin-right: auto !important;
    <?php endif; ?>
    <?php else: ?>
    width: 100% !important;
    max-width: 100% !important;
    <?php endif; ?>
    
    <?php if (!empty($zone_max_height) && $zone_max_height !== 'auto'): ?>
    max-height: <?php echo esc_attr($zone_height); ?> !important;
    overflow: hidden !important;
    <?php endif; ?>
    
    display: block !important;
    box-sizing: border-box !important;
}

.adwptracker-zone-<?php echo esc_attr($zone_id); ?> .adwptracker-ad {
    width: 100% !important;
    max-width: 100% !important;
    display: block !important;
    box-sizing: border-box !important;
}

.adwptracker-zone-<?php echo esc_attr($zone_id); ?> .adwptracker-link {
    display: block !important;
    width: 100% !important;
}

.adwptracker-zone-<?php echo esc_attr($zone_id); ?> img {
    <?php if ($has_fixed_width): ?>
    width: auto !important;
    max-width: <?php echo esc_attr($zone_width); ?> !important;
    <?php else: ?>
    width: 100% !important;
    max-width: 100% !important;
    <?php endif; ?>
    height: auto !important;
    display: block !important;
    margin-left: auto !important;
    margin-right: auto !important;
    <?php if (!empty($zone_max_height) && $zone_max_height !== 'auto'): ?>
    max-height: <?php echo esc_attr($zone_height); ?> !important;
    object-fit: contain !important;
    <?php endif; ?>
}

/* Slider transitions */
.adwptracker-zone-<?php echo esc_attr($zone_id); ?>.enable-slider .adwptracker-ad {
    transition: opacity 0.5s ease-in-out;
}
.adwptracker-zone-<?php echo esc_attr($zone_id); ?>.enable-slider .adwptracker-ad:not(.active) {
    opacity: 0;
    position: absolute;
    top: 0;
    left: 0;
    pointer-events: none;
}
.adwptracker-zone-<?php echo esc_attr($zone_id); ?>.enable-slider .adwptracker-ad.active {
    opacity: 1;
    position: relative;
}

.adwptracker-zone-<?php echo esc_attr($zone_id); ?>.enable-slider {
    position: relative;
}
</style>

<div class="adwptracker-zone adwptracker-zone-<?php echo esc_attr($zone_id); ?><?php echo $enable_slider ? ' enable-slider' : ''; ?>" 
     data-zone-id="<?php echo esc_attr($zone_id); ?>" 
     data-slider="<?php echo $enable_slider ? 'true' : 'false'; ?>"
     data-slider-speed="<?php echo esc_attr($slider_speed); ?>"
     style="<?php echo $zone_inline_style; ?>">
    <?php foreach ($ads as $ad): 
        $ad_id = $ad->ID;
        
        // Check schedule dates
        $start_date = get_post_meta($ad_id, '_adwpt_start_date', true);
        $end_date = get_post_meta($ad_id, '_adwpt_end_date', true);
        $current_date = date('Y-m-d');
        
        // Skip if outside schedule period
        if (!empty($start_date) && $current_date < $start_date) {
            continue; // Not yet started
        }
        if (!empty($end_date) && $current_date > $end_date) {
            continue; // Already ended
        }
        
        // Check device display settings
        $show_on_mobile = get_post_meta($ad_id, '_adwpt_show_on_mobile', true) !== '0';
        $show_on_desktop = get_post_meta($ad_id, '_adwpt_show_on_desktop', true) !== '0';
        $sticky_enabled = get_post_meta($ad_id, '_adwpt_sticky_enabled', true);
        $sticky_position = get_post_meta($ad_id, '_adwpt_sticky_position', true) ?: 'top';
        
        // Build classes for device visibility
        $device_classes = [];
        if (!$show_on_mobile) {
            $device_classes[] = 'adwpt-hide-mobile';
        }
        if (!$show_on_desktop) {
            $device_classes[] = 'adwpt-hide-desktop';
        }
        if ($sticky_enabled) {
            $device_classes[] = 'adwpt-sticky-' . $sticky_position;
        }
        
        $type = get_post_meta($ad_id, '_adwpt_type', true) ?: 'image';
        $image_url = get_post_meta($ad_id, '_adwpt_image_url', true);
        $html_code = get_post_meta($ad_id, '_adwpt_html_code', true);
        $text_title = get_post_meta($ad_id, '_adwpt_text_title', true);
        $text_content = get_post_meta($ad_id, '_adwpt_text_content', true);
        $video_url = get_post_meta($ad_id, '_adwpt_video_url', true);
        $video_type = get_post_meta($ad_id, '_adwpt_video_type', true) ?: 'youtube';
        $link_url = get_post_meta($ad_id, '_adwpt_link_url', true);
        $link_target = get_post_meta($ad_id, '_adwpt_link_target', true) ?: '_blank';
    ?>
        <div class="adwptracker-ad <?php echo esc_attr(implode(' ', $device_classes)); ?>" 
             data-ad-id="<?php echo esc_attr($ad_id); ?>" 
             data-zone-id="<?php echo esc_attr($zone_id); ?>"
             style="width: 100% !important; max-width: 100% !important; display: block !important; box-sizing: border-box !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important;">
            <?php if ($type === 'image' && $image_url): ?>
                <?php if ($link_url): ?>
                    <a href="<?php echo esc_url($link_url); ?>" 
                       class="adwptracker-link" 
                       target="<?php echo esc_attr($link_target); ?>"
                       data-ad-id="<?php echo esc_attr($ad_id); ?>"
                       data-zone-id="<?php echo esc_attr($zone_id); ?>"
                       rel="noopener noreferrer"
                       style="display: block !important; text-align: center !important; box-sizing: border-box !important; margin: 0 !important; padding: 0 !important;">
                        <img src="<?php echo esc_url($image_url); ?>" 
                             alt="<?php echo esc_attr($ad->post_title); ?>" 
                             loading="lazy"
                             style="<?php if ($has_fixed_width): ?>width: auto !important; max-width: <?php echo esc_attr($zone_width); ?> !important;<?php else: ?>width: 100% !important; max-width: 100% !important;<?php endif; ?> height: auto !important; display: block !important; box-sizing: border-box !important; object-fit: contain !important; margin: 0 auto !important; padding: 0 !important; border: none !important; <?php if (!empty($zone_max_height) && $zone_max_height !== 'auto'): ?>max-height: <?php echo esc_attr($zone_height); ?> !important;<?php endif; ?>">
                    </a>
                <?php else: ?>
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($ad->post_title); ?>" 
                         loading="lazy"
                         style="<?php if ($has_fixed_width): ?>width: auto !important; max-width: <?php echo esc_attr($zone_width); ?> !important;<?php else: ?>width: 100% !important; max-width: 100% !important;<?php endif; ?> height: auto !important; display: block !important; box-sizing: border-box !important; object-fit: contain !important; margin: 0 auto !important; padding: 0 !important; border: none !important; <?php if (!empty($zone_max_height) && $zone_max_height !== 'auto'): ?>max-height: <?php echo esc_attr($zone_height); ?> !important;<?php endif; ?>">
                <?php endif; ?>
                
            <?php elseif ($type === 'html' && $html_code): ?>
                <?php if ($link_url): ?>
                    <a href="<?php echo esc_url($link_url); ?>" 
                       class="adwptracker-link adwptracker-html-wrapper" 
                       target="<?php echo esc_attr($link_target); ?>"
                       data-ad-id="<?php echo esc_attr($ad_id); ?>"
                       data-zone-id="<?php echo esc_attr($zone_id); ?>"
                       rel="noopener noreferrer"
                       style="display: block !important; width: 100% !important;">
                        <div class="adwptracker-html-content" style="width: 100% !important;">
                            <?php echo wp_kses_post($html_code); ?>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="adwptracker-html-content" style="width: 100% !important;">
                        <?php echo wp_kses_post($html_code); ?>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($type === 'text' && ($text_title || $text_content)): ?>
                <?php 
                $wrapper_style = 'display: block; padding: 20px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: inherit;';
                if ($link_url) {
                    echo '<a href="' . esc_url($link_url) . '" class="adwptracker-link" target="' . esc_attr($link_target) . '" rel="noopener noreferrer" data-ad-id="' . esc_attr($ad_id) . '" data-zone-id="' . esc_attr($zone_id) . '" style="' . esc_attr($wrapper_style) . '">';
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
                <?php 
                echo $link_url ? '</a>' : '</div>';
                ?>
                
            <?php elseif ($type === 'video' && $video_url): ?>
                <?php
                $embed = '';
                if ($video_type === 'youtube') {
                    // Support multiple YouTube formats
                    $youtube_id = '';
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&?\s]+)/', $video_url, $m)) {
                        $youtube_id = $m[1];
                    }
                    if ($youtube_id) {
                        $embed = '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;"><iframe src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '?autoplay=1&loop=1&playlist=' . esc_attr($youtube_id) . '&mute=1" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>';
                    }
                } elseif ($video_type === 'vimeo') {
                    if (preg_match('/vimeo\.com\/(\d+)/', $video_url, $m)) {
                        $embed = '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;"><iframe src="https://player.vimeo.com/video/' . esc_attr($m[1]) . '?autoplay=1&loop=1&muted=1&background=1" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" frameborder="0" allowfullscreen allow="autoplay; fullscreen; picture-in-picture"></iframe></div>';
                    }
                } elseif ($video_type === 'mp4') {
                    $embed = '<video autoplay loop muted playsinline style="width: 100% !important; max-width: 100% !important; height: auto !important; display: block !important; margin: 0 !important; padding: 0 !important;"><source src="' . esc_url($video_url) . '" type="video/mp4">Votre navigateur ne supporte pas la vidéo HTML5.</video>';
                }
                
                // Wrap with link if provided
                if ($embed) {
                    if ($link_url) {
                        echo '<a href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '" class="adwptracker-link" data-ad-id="' . esc_attr($ad_id) . '" data-zone-id="' . esc_attr($zone_id) . '" rel="noopener noreferrer" style="display: block !important; width: 100% !important;">';
                    }
                    echo $embed;
                    if ($link_url) {
                        echo '</a>';
                    }
                }
                ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
