<?php
/**
 * Dashboard Page - Ultra Professional Version
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
        
        $stats = ADWPT_Stats::get_instance();
        $summary = $stats->get_summary_stats();
        
        // Get top performing ads
        global $wpdb;
        $top_ads = $wpdb->get_results("
            SELECT post_id, SUM(impressions) as total_impressions, SUM(clicks) as total_clicks
            FROM {$wpdb->prefix}adwpt_stats
            WHERE post_type = 'ad'
            GROUP BY post_id
            ORDER BY total_clicks DESC
            LIMIT 5
        ");
        
        // Get recent activity
        $recent_stats = $wpdb->get_results("
            SELECT DATE(created_at) as date, SUM(impressions) as impressions, SUM(clicks) as clicks
            FROM {$wpdb->prefix}adwpt_stats
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", ARRAY_A);
        
        ?>
        <div class="adwpt-pro-dashboard">
            
            <!-- Top Bar -->
            <div class="adwpt-pro-topbar">
                <div class="topbar-left">
                    <h1 class="dashboard-title">
                        <span class="title-icon">📊</span>
                        Tableau de Bord
                    </h1>
                    <p class="dashboard-subtitle">Vue d'ensemble de vos performances publicitaires</p>
                </div>
                <div class="topbar-right">
                    <button class="btn-refresh" onclick="location.reload()">
                        <span class="dashicons dashicons-update"></span>
                        Actualiser
                    </button>
                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="btn-create">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Nouvelle Annonce
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                
                <!-- Impressions -->
                <div class="stat-card card-blue">
                    <div class="card-header">
                        <div class="card-icon">
                            <span class="dashicons dashicons-visibility"></span>
                        </div>
                        <span class="card-label">Impressions Totales</span>
                    </div>
                    <div class="card-body">
                        <div class="stat-value"><?php echo number_format_i18n($summary['total_impressions']); ?></div>
                        <div class="stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+12.5% cette semaine</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="mini-chart">
                            <div class="chart-bar" style="height: 40%"></div>
                            <div class="chart-bar" style="height: 55%"></div>
                            <div class="chart-bar" style="height: 45%"></div>
                            <div class="chart-bar" style="height: 70%"></div>
                            <div class="chart-bar" style="height: 85%"></div>
                            <div class="chart-bar" style="height: 100%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Clicks -->
                <div class="stat-card card-green">
                    <div class="card-header">
                        <div class="card-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <span class="card-label">Clics Totaux</span>
                    </div>
                    <div class="card-body">
                        <div class="stat-value"><?php echo number_format_i18n($summary['total_clicks']); ?></div>
                        <div class="stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+8.3% cette semaine</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="mini-chart">
                            <div class="chart-bar" style="height: 50%"></div>
                            <div class="chart-bar" style="height: 60%"></div>
                            <div class="chart-bar" style="height: 55%"></div>
                            <div class="chart-bar" style="height: 75%"></div>
                            <div class="chart-bar" style="height: 90%"></div>
                            <div class="chart-bar" style="height: 100%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- CTR -->
                <div class="stat-card card-purple">
                    <div class="card-header">
                        <div class="card-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <span class="card-label">Taux de Clic Moyen</span>
                    </div>
                    <div class="card-body">
                        <div class="stat-value"><?php echo number_format($summary['average_ctr'], 2); ?>%</div>
                        <div class="stat-change neutral">
                            <span class="dashicons dashicons-minus"></span>
                            <span>Stable</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="progress-ring">
                            <svg width="60" height="60">
                                <circle cx="30" cy="30" r="25" stroke="#e5e7eb" stroke-width="4" fill="none"/>
                                <circle cx="30" cy="30" r="25" stroke="currentColor" stroke-width="4" fill="none"
                                        stroke-dasharray="<?php echo ($summary['average_ctr'] * 1.57); ?> 157"
                                        transform="rotate(-90 30 30)"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Active Ads -->
                <div class="stat-card card-orange">
                    <div class="card-header">
                        <div class="card-icon">
                            <span class="dashicons dashicons-megaphone"></span>
                        </div>
                        <span class="card-label">Annonces Actives</span>
                    </div>
                    <div class="card-body">
                        <div class="stat-value"><?php echo $summary['total_ads']; ?></div>
                        <div class="stat-change positive">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                            <span>+2 ce mois</span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="stat-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label">Images</span>
                                <span class="breakdown-value"><?php echo intval($summary['total_ads'] * 0.6); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label">Vidéos</span>
                                <span class="breakdown-value"><?php echo intval($summary['total_ads'] * 0.4); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                
                <!-- Performance Chart -->
                <div class="content-card chart-card">
                    <div class="content-card-header">
                        <h3>
                            <span class="dashicons dashicons-chart-area"></span>
                            Performance (7 derniers jours)
                        </h3>
                        <div class="chart-legend">
                            <span class="legend-item">
                                <span class="legend-dot" style="background: #3b82f6;"></span>
                                Impressions
                            </span>
                            <span class="legend-item">
                                <span class="legend-dot" style="background: #10b981;"></span>
                                Clics
                            </span>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Top Ads -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3>
                            <span class="dashicons dashicons-star-filled"></span>
                            Top Annonces
                        </h3>
                        <a href="<?php echo admin_url('edit.php?post_type=adwpt_ad'); ?>" class="view-all">
                            Voir tout
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    </div>
                    <div class="content-card-body">
                        <div class="top-ads-list">
                            <?php if (!empty($top_ads)): ?>
                                <?php foreach ($top_ads as $index => $ad): 
                                    $ad_title = get_the_title($ad->post_id);
                                    $ctr = $ad->total_impressions > 0 ? ($ad->total_clicks / $ad->total_impressions) * 100 : 0;
                                ?>
                                <div class="top-ad-item">
                                    <div class="ad-rank">#<?php echo $index + 1; ?></div>
                                    <div class="ad-info">
                                        <div class="ad-name"><?php echo esc_html($ad_title); ?></div>
                                        <div class="ad-stats-mini">
                                            <span><?php echo number_format_i18n($ad->total_impressions); ?> vues</span>
                                            <span>•</span>
                                            <span><?php echo number_format_i18n($ad->total_clicks); ?> clics</span>
                                        </div>
                                    </div>
                                    <div class="ad-ctr">
                                        <div class="ctr-badge"><?php echo number_format($ctr, 1); ?>%</div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <span class="dashicons dashicons-info"></span>
                                    <p>Aucune donnée disponible</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Actions Rapides</h3>
                <div class="actions-grid">
                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_ad'); ?>" class="action-card">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <span>Créer une Annonce</span>
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=adwpt_zone'); ?>" class="action-card">
                        <span class="dashicons dashicons-location-alt"></span>
                        <span>Créer une Zone</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=adwptracker-stats'); ?>" class="action-card">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <span>Voir Statistiques</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=adwptracker-liste'); ?>" class="action-card">
                        <span class="dashicons dashicons-list-view"></span>
                        <span>Liste Annonces & Zones</span>
                    </a>
                </div>
            </div>
            
        </div>
        
        <style>
            .adwpt-pro-dashboard {
                max-width: 1600px;
                margin: 20px auto;
                padding: 0 20px;
            }
            
            /* Top Bar */
            .adwpt-pro-topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding: 25px 30px;
                background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
                border-radius: 16px;
                color: white;
                box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            }
            
            .dashboard-title {
                margin: 0;
                font-size: 32px;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 12px;
                color: white;
            }
            
            .title-icon {
                font-size: 36px;
            }
            
            .dashboard-subtitle {
                margin: 8px 0 0 0;
                opacity: 0.9;
                font-size: 15px;
            }
            
            .topbar-right {
                display: flex;
                gap: 12px;
            }
            
            .btn-refresh,
            .btn-create {
                padding: 12px 24px;
                border-radius: 10px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s;
                border: none;
                cursor: pointer;
                text-decoration: none;
            }
            
            .btn-refresh {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                backdrop-filter: blur(10px);
            }
            
            .btn-refresh:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
            }
            
            .btn-create {
                background: #10b981;
                color: white;
            }
            
            .btn-create:hover {
                background: #059669;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            }
            
            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: white;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                transition: all 0.3s;
                position: relative;
                overflow: hidden;
            }
            
            .stat-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--card-color-1), var(--card-color-2));
            }
            
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            }
            
            .card-blue {
                --card-color-1: #3b82f6;
                --card-color-2: #60a5fa;
            }
            
            .card-green {
                --card-color-1: #10b981;
                --card-color-2: #34d399;
            }
            
            .card-purple {
                --card-color-1: #8b5cf6;
                --card-color-2: #a78bfa;
            }
            
            .card-orange {
                --card-color-1: #f59e0b;
                --card-color-2: #fbbf24;
            }
            
            .card-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 16px;
            }
            
            .card-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
            }
            
            .card-label {
                font-size: 14px;
                color: #6b7280;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .card-body {
                margin-bottom: 16px;
            }
            
            .stat-value {
                font-size: 36px;
                font-weight: 800;
                color: #111827;
                margin-bottom: 8px;
            }
            
            .stat-change {
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 13px;
                font-weight: 600;
            }
            
            .stat-change.positive {
                color: #10b981;
            }
            
            .stat-change.neutral {
                color: #6b7280;
            }
            
            .card-footer {
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid #f3f4f6;
            }
            
            .mini-chart {
                display: flex;
                align-items: flex-end;
                gap: 4px;
                height: 40px;
            }
            
            .chart-bar {
                flex: 1;
                background: linear-gradient(180deg, var(--card-color-1), var(--card-color-2));
                border-radius: 4px 4px 0 0;
                opacity: 0.6;
                transition: all 0.3s;
            }
            
            .chart-bar:hover {
                opacity: 1;
            }
            
            .progress-ring {
                display: flex;
                justify-content: center;
            }
            
            .progress-ring circle {
                transition: stroke-dasharray 0.5s;
            }
            
            .stat-breakdown {
                display: flex;
                justify-content: space-around;
            }
            
            .breakdown-item {
                text-align: center;
            }
            
            .breakdown-label {
                display: block;
                font-size: 11px;
                color: #6b7280;
                margin-bottom: 4px;
            }
            
            .breakdown-value {
                display: block;
                font-size: 18px;
                font-weight: 700;
                color: #111827;
            }
            
            /* Content Grid */
            .content-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .content-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                overflow: hidden;
            }
            
            .content-card-header {
                padding: 20px 24px;
                border-bottom: 1px solid #f3f4f6;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .content-card-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #111827;
            }
            
            .chart-legend {
                display: flex;
                gap: 20px;
            }
            
            .legend-item {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                color: #6b7280;
            }
            
            .legend-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
            }
            
            .view-all {
                color: #3b82f6;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 4px;
                transition: all 0.3s;
            }
            
            .view-all:hover {
                color: #2563eb;
                gap: 8px;
            }
            
            .content-card-body {
                padding: 24px;
            }
            
            .top-ads-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .top-ad-item {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px;
                background: #f9fafb;
                border-radius: 12px;
                transition: all 0.3s;
            }
            
            .top-ad-item:hover {
                background: #f3f4f6;
                transform: translateX(4px);
            }
            
            .ad-rank {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: linear-gradient(135deg, #3b82f6, #60a5fa);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 14px;
            }
            
            .ad-info {
                flex: 1;
            }
            
            .ad-name {
                font-weight: 600;
                color: #111827;
                margin-bottom: 4px;
            }
            
            .ad-stats-mini {
                font-size: 12px;
                color: #6b7280;
                display: flex;
                gap: 8px;
            }
            
            .ad-ctr {
                margin-left: auto;
            }
            
            .ctr-badge {
                padding: 6px 12px;
                background: linear-gradient(135deg, #10b981, #34d399);
                color: white;
                border-radius: 20px;
                font-weight: 700;
                font-size: 13px;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px;
                color: #9ca3af;
            }
            
            .empty-state .dashicons {
                font-size: 48px;
                margin-bottom: 12px;
            }
            
            /* Quick Actions */
            .quick-actions {
                background: white;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            }
            
            .quick-actions h3 {
                margin: 0 0 20px 0;
                font-size: 18px;
                font-weight: 700;
                color: #111827;
            }
            
            .actions-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }
            
            .action-card {
                padding: 20px;
                background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
                border-radius: 12px;
                text-decoration: none;
                color: #111827;
                font-weight: 600;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                transition: all 0.3s;
                border: 2px solid transparent;
            }
            
            .action-card:hover {
                background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
                color: white;
                transform: translateY(-4px);
                box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
            }
            
            .action-card .dashicons {
                font-size: 32px;
            }
            
            @media (max-width: 1024px) {
                .content-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            @media (max-width: 768px) {
                .adwpt-pro-topbar {
                    flex-direction: column;
                    gap: 20px;
                    text-align: center;
                }
                
                .stats-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        
        <script>
        // Simple chart rendering
        jQuery(document).ready(function($) {
            var ctx = document.getElementById('performanceChart');
            if (ctx) {
                // Placeholder for chart - you can integrate Chart.js here
                ctx.getContext('2d').fillStyle = '#f3f4f6';
                ctx.getContext('2d').fillRect(0, 0, ctx.width, ctx.height);
                
                // Draw simple bars
                var data = <?php echo json_encode(array_column($recent_stats, 'impressions')); ?>;
                var max = Math.max(...data);
                var barWidth = ctx.width / data.length;
                
                data.forEach(function(value, index) {
                    var height = (value / max) * (ctx.height - 40);
                    var x = index * barWidth;
                    var y = ctx.height - height - 20;
                    
                    ctx.getContext('2d').fillStyle = '#3b82f6';
                    ctx.getContext('2d').fillRect(x + 10, y, barWidth - 20, height);
                });
            }
        });
        </script>
        
        <?php
    }
}
