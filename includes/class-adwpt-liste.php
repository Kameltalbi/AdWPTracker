<?php
/**
 * Liste complète class
 * Affiche toutes les annonces et zones avec shortcodes et dates
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Liste {
    
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>📋 <?php esc_html_e('Liste Complète - Ads & Zones', 'adwptracker'); ?></h1>
            
            <style>
                .adwpt-liste-table {
                    width: 100%;
                    margin-top: 20px;
                    border-collapse: collapse;
                    background: white;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .adwpt-liste-table th {
                    background: #f8f9fa;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    border-bottom: 2px solid #dee2e6;
                }
                .adwpt-liste-table td {
                    padding: 12px;
                    border-bottom: 1px solid #dee2e6;
                }
                .adwpt-liste-table tr:hover {
                    background: #f8f9fa;
                }
                .adwpt-shortcode-box {
                    background: #1f2937;
                    color: #10b981;
                    padding: 6px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-family: monospace;
                    cursor: pointer;
                    display: inline-block;
                }
                .adwpt-shortcode-box:hover {
                    background: #374151;
                }
                .adwpt-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .adwpt-badge-ad {
                    background: #e3f2fd;
                    color: #1976d2;
                }
                .adwpt-badge-zone {
                    background: #f3e5f5;
                    color: #7b1fa2;
                }
            </style>
            
            <h2>📢 Annonces</h2>
            <table class="adwpt-liste-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Shortcode</th>
                        <th>Type</th>
                        <th>Zone</th>
                        <th>Date Début</th>
                        <th>Date Fin</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ads = get_posts([
                        'post_type' => 'adwpt_ad',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ]);
                    
                    if (empty($ads)) {
                        echo '<tr><td colspan="7" style="text-align: center; padding: 40px;">Aucune annonce trouvée</td></tr>';
                    } else {
                        foreach ($ads as $ad) {
                            $ad_id = $ad->ID;
                            $shortcode = '[adwptracker_ad id="' . $ad_id . '"]';
                            $type = get_post_meta($ad_id, '_adwpt_type', true) ?: 'image';
                            $zone_id = get_post_meta($ad_id, '_adwpt_zone_id', true);
                            $zone_name = $zone_id ? get_the_title($zone_id) : '-';
                            $start_date = get_post_meta($ad_id, '_adwpt_start_date', true);
                            $end_date = get_post_meta($ad_id, '_adwpt_end_date', true);
                            $status = get_post_meta($ad_id, '_adwpt_status', true) ?: 'active';
                            
                            $type_icons = [
                                'image' => '🖼️ Image',
                                'html' => '💻 HTML',
                                'text' => '📝 Text',
                                'video' => '🎥 Video',
                            ];
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($ad->post_title); ?></strong></td>
                                <td>
                                    <code class="adwpt-shortcode-box" onclick="copyToClipboard('<?php echo esc_js($shortcode); ?>', this)" title="Cliquer pour copier">
                                        <?php echo esc_html($shortcode); ?>
                                    </code>
                                </td>
                                <td><?php echo $type_icons[$type] ?? $type; ?></td>
                                <td><?php echo esc_html($zone_name); ?></td>
                                <td><?php echo $start_date ? date('d/m/Y', strtotime($start_date)) : '-'; ?></td>
                                <td><?php echo $end_date ? date('d/m/Y', strtotime($end_date)) : '-'; ?></td>
                                <td>
                                    <span style="color: <?php echo $status === 'active' ? '#10b981' : '#ef4444'; ?>">
                                        <?php echo $status === 'active' ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <h2 style="margin-top: 40px;">🎯 Zones</h2>
            <table class="adwpt-liste-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Shortcode</th>
                        <th>Annonces</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $zones = get_posts([
                        'post_type' => 'adwpt_zone',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ]);
                    
                    if (empty($zones)) {
                        echo '<tr><td colspan="4" style="text-align: center; padding: 40px;">Aucune zone trouvée</td></tr>';
                    } else {
                        foreach ($zones as $zone) {
                            $zone_id = $zone->ID;
                            $shortcode = '[adwptracker_zone id="' . $zone_id . '"]';
                            $status = get_post_meta($zone_id, '_adwpt_status', true) ?: 'active';
                            
                            // Count active ads in this zone
                            $zone_ads = get_posts([
                                'post_type' => 'adwpt_ad',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'meta_query' => [
                                    [
                                        'key' => '_adwpt_zone_id',
                                        'value' => $zone_id,
                                        'compare' => '='
                                    ]
                                ],
                                'fields' => 'ids'
                            ]);
                            
                            $active_ads = array_filter($zone_ads, function($ad_id) {
                                $status = get_post_meta($ad_id, '_adwpt_status', true);
                                return empty($status) || $status === 'active';
                            });
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($zone->post_title); ?></strong></td>
                                <td>
                                    <code class="adwpt-shortcode-box" onclick="copyToClipboard('<?php echo esc_js($shortcode); ?>', this)" title="Cliquer pour copier">
                                        <?php echo esc_html($shortcode); ?>
                                    </code>
                                </td>
                                <td><strong><?php echo count($active_ads); ?></strong> annonce(s)</td>
                                <td>
                                    <span style="color: <?php echo $status === 'active' ? '#10b981' : '#ef4444'; ?>">
                                        <?php echo $status === 'active' ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <script>
            function copyToClipboard(text, element) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        showCopyFeedback(element);
                    }).catch(function() {
                        fallbackCopy(text, element);
                    });
                } else {
                    fallbackCopy(text, element);
                }
            }
            
            function fallbackCopy(text, element) {
                var input = document.createElement('input');
                input.value = text;
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                try {
                    document.execCommand('copy');
                    showCopyFeedback(element);
                } catch (err) {
                    console.error('Copy failed:', err);
                }
                document.body.removeChild(input);
            }
            
            function showCopyFeedback(element) {
                var originalText = element.textContent;
                element.textContent = '✓ Copié !';
                element.style.background = '#10b981';
                element.style.color = 'white';
                setTimeout(function() {
                    element.textContent = originalText;
                    element.style.background = '#1f2937';
                    element.style.color = '#10b981';
                }, 2000);
            }
            </script>
        </div>
        <?php
    }
}
