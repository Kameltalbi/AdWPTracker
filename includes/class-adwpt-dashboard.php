<?php
/**
 * Dashboard page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Dashboard {

    public static function render() {
        if (!class_exists('ADWPT_Stats')) {
            echo '<div class="wrap"><h1>Erreur</h1><p>La classe ADWPT_Stats n\'est pas chargée.</p></div>';
            return;
        }

        global $wpdb;

        $range = isset($_GET['range']) ? absint($_GET['range']) : 30;
        if (!in_array($range, [7, 30, 90, 365], true)) {
            $range = 30;
        }

        $stats = ADWPT_Stats::get_instance();
        $summary = $stats->get_summary_stats();
        $stats_table = $wpdb->prefix . 'adwptracker_stats';
        $since_sql = gmdate('Y-m-d H:i:s', strtotime('-' . $range . ' days'));

        $top_ads = $wpdb->get_results($wpdb->prepare(
            "SELECT ad_id,
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) AS clicks
            FROM {$stats_table}
            WHERE created_at >= %s
            GROUP BY ad_id
            ORDER BY clicks DESC, impressions DESC
            LIMIT 5",
            $since_sql
        ));

        $daily_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS stat_date,
                SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) AS clicks
            FROM {$stats_table}
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY stat_date ASC",
            $since_sql
        ), ARRAY_A);

        $type_counts = self::get_ad_type_counts();
        $recent_activities = self::get_recent_activities();
        $chart_labels = array_map(function($row) { return $row['stat_date']; }, $daily_rows);
        $chart_impressions = array_map(function($row) { return (int) $row['impressions']; }, $daily_rows);
        $chart_clicks = array_map(function($row) { return (int) $row['clicks']; }, $daily_rows);
        $type_labels = array_keys($type_counts);
        $type_values = array_values($type_counts);
        ?>
        <div class="wrap">
            <div class="adwpt-admin">
                <div class="adwpt-page-header">
                    <div>
                        <h1 class="adwpt-page-title"><?php esc_html_e('Tableau de bord', 'adwptracker'); ?></h1>
                        <p class="adwpt-page-subtitle"><?php esc_html_e('Vue d\'ensemble de vos publicités et performances', 'adwptracker'); ?></p>
                    </div>
                    <form method="get" class="adwpt-toolbar">
                        <input type="hidden" name="page" value="adwptracker">
                        <select name="range" onchange="this.form.submit()">
                            <option value="7" <?php selected($range, 7); ?>>7 derniers jours</option>
                            <option value="30" <?php selected($range, 30); ?>>30 derniers jours</option>
                            <option value="90" <?php selected($range, 90); ?>>90 derniers jours</option>
                            <option value="365" <?php selected($range, 365); ?>>12 derniers mois</option>
                        </select>
                        <a class="adwpt-button adwpt-button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=adwpt_ad')); ?>"><?php esc_html_e('Ajouter une publicité', 'adwptracker'); ?></a>
                    </form>
                </div>

                <div class="adwpt-grid adwpt-grid-4">
                    <?php self::render_kpi(__('Impressions', 'adwptracker'), number_format_i18n($summary['total_impressions']), __('Total enregistré', 'adwptracker')); ?>
                    <?php self::render_kpi(__('Clics', 'adwptracker'), number_format_i18n($summary['total_clicks']), __('Total enregistré', 'adwptracker')); ?>
                    <?php self::render_kpi(__('Taux de clic - CTR', 'adwptracker'), number_format_i18n($summary['average_ctr'], 2) . '%', __('Clics / impressions', 'adwptracker')); ?>
                    <?php self::render_kpi(__('Publicités actives', 'adwptracker'), number_format_i18n($summary['active_ads']), __('Publiées et actives', 'adwptracker')); ?>
                </div>

                <div class="adwpt-grid adwpt-grid-2" style="margin-top:16px;">
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Évolution des performances', 'adwptracker'); ?></h2>
                        <canvas id="adwptPerformanceChart" height="140"></canvas>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Types de publicités', 'adwptracker'); ?></h2>
                        <canvas id="adwptTypeChart" height="140"></canvas>
                    </div>
                </div>

                <div class="adwpt-grid adwpt-grid-2" style="margin-top:16px;">
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Dernières activités', 'adwptracker'); ?></h2>
                        <?php if ($recent_activities): ?>
                            <ul style="margin:0;">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <li style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eef2f7;">
                                        <span><?php echo esc_html($activity['label']); ?></span>
                                        <span style="color:#6b7280;"><?php echo esc_html($activity['date']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="adwpt-page-subtitle"><?php esc_html_e('Aucune activité récente.', 'adwptracker'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="adwpt-card">
                        <h2 class="adwpt-section-title"><?php esc_html_e('Actions rapides', 'adwptracker'); ?></h2>
                        <div class="adwpt-grid">
                            <a class="adwpt-button adwpt-button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=adwpt_ad')); ?>"><?php esc_html_e('Ajouter une publicité', 'adwptracker'); ?></a>
                            <a class="adwpt-button" href="<?php echo esc_url(admin_url('admin.php?page=adwptracker-stats')); ?>"><?php esc_html_e('Voir les statistiques', 'adwptracker'); ?></a>
                            <a class="adwpt-button" href="<?php echo esc_url(admin_url('post-new.php?post_type=adwpt_zone')); ?>"><?php esc_html_e('Configurer une zone', 'adwptracker'); ?></a>
                        </div>
                    </div>
                </div>

                <div class="adwpt-card" style="margin-top:16px;">
                    <h2 class="adwpt-section-title"><?php esc_html_e('Top publicités', 'adwptracker'); ?></h2>
                    <div class="adwpt-table-wrap">
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('Publicité', 'adwptracker'); ?></th><th><?php esc_html_e('Impressions', 'adwptracker'); ?></th><th><?php esc_html_e('Clics', 'adwptracker'); ?></th><th><?php esc_html_e('CTR', 'adwptracker'); ?></th></tr></thead>
                            <tbody>
                                <?php if ($top_ads): ?>
                                    <?php foreach ($top_ads as $ad): $ctr = $ad->impressions > 0 ? ($ad->clicks / $ad->impressions) * 100 : 0; ?>
                                        <tr>
                                            <td><a href="<?php echo esc_url(get_edit_post_link($ad->ad_id)); ?>"><?php echo esc_html(get_the_title($ad->ad_id)); ?></a></td>
                                            <td><?php echo number_format_i18n($ad->impressions); ?></td>
                                            <td><?php echo number_format_i18n($ad->clicks); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($ctr, 2)); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4"><?php esc_html_e('Aucune donnée statistique pour cette période.', 'adwptracker'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') {
                return;
            }

            var performance = document.getElementById('adwptPerformanceChart');
            if (performance) {
                new Chart(performance, {
                    type: 'line',
                    data: {
                        labels: <?php echo wp_json_encode($chart_labels); ?>,
                        datasets: [
                            {label: 'Impressions', data: <?php echo wp_json_encode($chart_impressions); ?>, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)', tension: .3},
                            {label: 'Clics', data: <?php echo wp_json_encode($chart_clicks); ?>, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.08)', tension: .3}
                        ]
                    },
                    options: {responsive: true, plugins: {legend: {position: 'bottom'}}, scales: {y: {beginAtZero: true}}}
                });
            }

            var typeChart = document.getElementById('adwptTypeChart');
            if (typeChart) {
                new Chart(typeChart, {
                    type: 'doughnut',
                    data: {labels: <?php echo wp_json_encode($type_labels); ?>, datasets: [{data: <?php echo wp_json_encode($type_values); ?>, backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#7c3aed']}]},
                    options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
                });
            }
        });
        </script>
        <?php
    }

    private static function render_kpi($label, $value, $note) {
        ?>
        <div class="adwpt-card">
            <div class="adwpt-kpi-label"><?php echo esc_html($label); ?></div>
            <div class="adwpt-kpi-value"><?php echo esc_html($value); ?></div>
            <div class="adwpt-kpi-note"><?php echo esc_html($note); ?></div>
        </div>
        <?php
    }

    private static function get_ad_type_counts() {
        $counts = ['Image' => 0, 'HTML' => 0, 'Texte' => 0, 'Vidéo' => 0];
        $ads = get_posts([
            'post_type' => 'adwpt_ad',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
        ]);

        foreach ($ads as $ad_id) {
            $type = get_post_meta($ad_id, '_adwpt_type', true) ?: 'image';
            if ($type === 'html') {
                $counts['HTML']++;
            } elseif ($type === 'text') {
                $counts['Texte']++;
            } elseif ($type === 'video') {
                $counts['Vidéo']++;
            } else {
                $counts['Image']++;
            }
        }

        return $counts;
    }

    private static function get_recent_activities() {
        $items = [];
        $posts = get_posts([
            'post_type' => ['adwpt_ad', 'adwpt_zone'],
            'posts_per_page' => 5,
            'post_status' => ['publish', 'draft'],
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        foreach ($posts as $post) {
            $type = $post->post_type === 'adwpt_zone' ? __('Zone', 'adwptracker') : __('Publicité', 'adwptracker');
            $items[] = [
                'label' => sprintf('%s : %s', $type, $post->post_title),
                'date' => get_the_modified_date(get_option('date_format'), $post),
            ];
        }

        return $items;
    }
}
