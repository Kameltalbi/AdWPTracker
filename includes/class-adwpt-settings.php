<?php
/**
 * Settings Page - Professional Version
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADWPT_Settings {
    
    public static function render() {
        // Handle form submissions
        self::handle_submissions();
        
        // Get current settings
        $settings = self::get_settings();
        
        // Get system info
        $system_info = self::get_system_info();
        
        ?>
        <div class="wrap adwpt-settings-wrap">
            
            <!-- Header -->
            <div class="adwpt-settings-header">
                <h1>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Paramètres Avancés', 'adwptracker'); ?>
                </h1>
                <p class="subtitle"><?php esc_html_e('Configuration et optimisation de votre système publicitaire', 'adwptracker'); ?></p>
            </div>
            
            <!-- Tabs Navigation -->
            <nav class="adwpt-tabs-nav">
                <button class="adwpt-tab-btn active" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span>
                    Général
                </button>
                <button class="adwpt-tab-btn" data-tab="performance">
                    <span class="dashicons dashicons-performance"></span>
                    Performance
                </button>
                <button class="adwpt-tab-btn" data-tab="privacy">
                    <span class="dashicons dashicons-shield"></span>
                    Confidentialité
                </button>
                <button class="adwpt-tab-btn" data-tab="advanced">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Avancé
                </button>
                <button class="adwpt-tab-btn" data-tab="system">
                    <span class="dashicons dashicons-info"></span>
                    Système
                </button>
            </nav>
            
            <form method="post" action="" class="adwpt-settings-form">
                <?php wp_nonce_field('adwpt_settings_nonce'); ?>
                
                <!-- General Tab -->
                <div class="adwpt-tab-content active" id="tab-general">
                    <div class="adwpt-settings-grid">
                        
                        <!-- Tracking Settings -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-chart-line"></span> Suivi des Statistiques</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="tracking_enabled" value="1" <?php checked($settings['tracking_enabled'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Activer le suivi</strong>
                                        <p>Collecte des impressions et clics en temps réel</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="track_user_agent" value="1" <?php checked($settings['track_user_agent'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Enregistrer User-Agent</strong>
                                        <p>Détection précise des navigateurs et appareils</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notifications -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-email"></span> Notifications</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <label>Email de notification</label>
                                    <input type="email" name="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text">
                                    <p class="description">Rapports hebdomadaires automatiques</p>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="email_reports" value="1" <?php checked($settings['email_reports'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Rapports par email</strong>
                                        <p>Recevoir un résumé hebdomadaire</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Performance Tab -->
                <div class="adwpt-tab-content" id="tab-performance">
                    <div class="adwpt-settings-grid">
                        
                        <!-- Cache Settings -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-update"></span> Cache & Optimisation</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="cache_enabled" value="1" <?php checked($settings['cache_enabled'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Activer le cache</strong>
                                        <p>Améliore les performances de chargement</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label>Durée du cache (secondes)</label>
                                    <select name="cache_duration" class="regular-text">
                                        <option value="1800" <?php selected($settings['cache_duration'], '1800'); ?>>30 minutes</option>
                                        <option value="3600" <?php selected($settings['cache_duration'], '3600'); ?>>1 heure</option>
                                        <option value="7200" <?php selected($settings['cache_duration'], '7200'); ?>>2 heures</option>
                                        <option value="21600" <?php selected($settings['cache_duration'], '21600'); ?>>6 heures</option>
                                        <option value="86400" <?php selected($settings['cache_duration'], '86400'); ?>>24 heures</option>
                                    </select>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="lazy_load" value="1" <?php checked($settings['lazy_load'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Lazy Loading</strong>
                                        <p>Chargement différé des annonces</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="minify_output" value="1" <?php checked($settings['minify_output'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Minifier le code</strong>
                                        <p>Réduire la taille du HTML/CSS/JS</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Database Optimization -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-database"></span> Base de Données</h3>
                            </div>
                            <div class="card-body">
                                <div class="stat-box">
                                    <div class="stat-label">Entrées statistiques</div>
                                    <div class="stat-value"><?php echo number_format($system_info['stats_count']); ?></div>
                                </div>
                                
                                <div class="stat-box">
                                    <div class="stat-label">Taille base de données</div>
                                    <div class="stat-value"><?php echo $system_info['db_size']; ?> MB</div>
                                </div>
                                
                                <div class="setting-item">
                                    <label>Rétention des données (jours)</label>
                                    <select name="data_retention" class="regular-text">
                                        <option value="30" <?php selected($settings['data_retention'], '30'); ?>>30 jours</option>
                                        <option value="60" <?php selected($settings['data_retention'], '60'); ?>>60 jours</option>
                                        <option value="90" <?php selected($settings['data_retention'], '90'); ?>>90 jours</option>
                                        <option value="180" <?php selected($settings['data_retention'], '180'); ?>>6 mois</option>
                                        <option value="365" <?php selected($settings['data_retention'], '365'); ?>>1 an</option>
                                        <option value="0" <?php selected($settings['data_retention'], '0'); ?>>Illimité</option>
                                    </select>
                                    <p class="description">Suppression automatique des anciennes données</p>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Privacy Tab -->
                <div class="adwpt-tab-content" id="tab-privacy">
                    <div class="adwpt-settings-grid">
                        
                        <!-- GDPR Compliance -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-shield-alt"></span> Conformité RGPD</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="gdpr_mode" value="1" <?php checked($settings['gdpr_mode'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Mode RGPD</strong>
                                        <p>Anonymisation des données utilisateurs</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="anonymize_ip" value="1" <?php checked($settings['anonymize_ip'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Anonymiser les IP</strong>
                                        <p>Masquer les derniers octets des adresses IP</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="respect_dnt" value="1" <?php checked($settings['respect_dnt'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Respecter Do Not Track</strong>
                                        <p>Ne pas suivre si DNT activé</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Data Export -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-download"></span> Export de Données</h3>
                            </div>
                            <div class="card-body">
                                <p>Exportez vos paramètres et données pour sauvegarde ou migration.</p>
                                
                                <div class="button-group">
                                    <button type="submit" name="adwpt_export_settings" class="button button-secondary">
                                        <span class="dashicons dashicons-download"></span>
                                        Exporter les paramètres
                                    </button>
                                    
                                    <button type="submit" name="adwpt_export_stats" class="button button-secondary">
                                        <span class="dashicons dashicons-chart-bar"></span>
                                        Exporter les statistiques
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Advanced Tab -->
                <div class="adwpt-tab-content" id="tab-advanced">
                    <div class="adwpt-settings-grid">
                        
                        <!-- Developer Options -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-editor-code"></span> Options Développeur</h3>
                            </div>
                            <div class="card-body">
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="debug_mode" value="1" <?php checked($settings['debug_mode'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>Mode Debug</strong>
                                        <p>Afficher les logs dans la console</p>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="enable_api" value="1" <?php checked($settings['enable_api'], '1'); ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div class="setting-info">
                                        <strong>API REST</strong>
                                        <p>Activer l'API pour intégrations tierces</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Danger Zone -->
                        <div class="adwpt-settings-card danger-zone">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-warning"></span> Zone de Danger</h3>
                            </div>
                            <div class="card-body">
                                <div class="danger-action">
                                    <div>
                                        <strong>Réinitialiser les statistiques</strong>
                                        <p>Supprime toutes les données de tracking (irréversible)</p>
                                    </div>
                                    <button type="button" class="button button-danger" onclick="if(confirm('Êtes-vous sûr ? Cette action est irréversible !')) { document.getElementById('reset-stats-form').submit(); }">
                                        <span class="dashicons dashicons-trash"></span>
                                        Réinitialiser
                                    </button>
                                </div>
                                
                                <div class="danger-action">
                                    <div>
                                        <strong>Vider le cache</strong>
                                        <p>Supprime tous les fichiers de cache</p>
                                    </div>
                                    <button type="submit" name="adwpt_clear_cache" class="button button-secondary">
                                        <span class="dashicons dashicons-update"></span>
                                        Vider le cache
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- System Tab -->
                <div class="adwpt-tab-content" id="tab-system">
                    <div class="adwpt-settings-grid">
                        
                        <!-- System Information -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-admin-site"></span> Informations Système</h3>
                            </div>
                            <div class="card-body">
                                <table class="system-info-table">
                                    <tr>
                                        <td><strong>Version Plugin</strong></td>
                                        <td><?php echo ADWPT_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>WordPress</strong></td>
                                        <td><?php echo get_bloginfo('version'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>PHP</strong></td>
                                        <td><?php echo PHP_VERSION; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>MySQL</strong></td>
                                        <td><?php echo $system_info['mysql_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Serveur</strong></td>
                                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mémoire PHP</strong></td>
                                        <td><?php echo ini_get('memory_limit'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Features -->
                        <div class="adwpt-settings-card">
                            <div class="card-header">
                                <h3><span class="dashicons dashicons-star-filled"></span> Fonctionnalités</h3>
                            </div>
                            <div class="card-body">
                                <ul class="features-list">
                                    <li><span class="dashicons dashicons-yes"></span> Zones & Annonces illimitées</li>
                                    <li><span class="dashicons dashicons-yes"></span> Statistiques temps réel</li>
                                    <li><span class="dashicons dashicons-yes"></span> Sticky footer mobile</li>
                                    <li><span class="dashicons dashicons-yes"></span> Ciblage par appareil</li>
                                    <li><span class="dashicons dashicons-yes"></span> Export CSV</li>
                                    <li><span class="dashicons dashicons-yes"></span> 4 types d'annonces</li>
                                    <li><span class="dashicons dashicons-yes"></span> Vidéos autoplay</li>
                                    <li><span class="dashicons dashicons-yes"></span> Planification avancée</li>
                                </ul>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Save Button (Fixed Bottom) -->
                <div class="adwpt-settings-footer">
                    <button type="submit" name="adwpt_settings_submit" class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved"></span>
                        Enregistrer les Paramètres
                    </button>
                </div>
                
            </form>
            
            <!-- Hidden form for reset stats -->
            <form id="reset-stats-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('adwpt_reset_stats_nonce'); ?>
                <input type="hidden" name="adwpt_reset_stats" value="1">
            </form>
            
        </div>
        
        <style>
            .adwpt-settings-wrap {
                max-width: 1400px;
                margin: 20px auto;
                padding: 0 20px;
            }
            
            .adwpt-settings-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 12px;
                margin-bottom: 30px;
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            }
            
            .adwpt-settings-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 12px;
                color: white;
            }
            
            .adwpt-settings-header .subtitle {
                margin: 10px 0 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            
            .adwpt-tabs-nav {
                display: flex;
                gap: 10px;
                margin-bottom: 30px;
                border-bottom: 2px solid #e5e7eb;
                padding-bottom: 0;
            }
            
            .adwpt-tab-btn {
                background: transparent;
                border: none;
                padding: 12px 20px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                color: #6b7280;
                border-bottom: 3px solid transparent;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .adwpt-tab-btn:hover {
                color: #667eea;
                background: #f3f4f6;
            }
            
            .adwpt-tab-btn.active {
                color: #667eea;
                border-bottom-color: #667eea;
            }
            
            .adwpt-tab-content {
                display: none;
            }
            
            .adwpt-tab-content.active {
                display: block;
            }
            
            .adwpt-settings-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
                gap: 20px;
                margin-bottom: 80px;
            }
            
            .adwpt-settings-card {
                background: white;
                border-radius: 12px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .adwpt-settings-card .card-header {
                background: #f9fafb;
                padding: 20px;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .adwpt-settings-card .card-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .adwpt-settings-card .card-body {
                padding: 20px;
            }
            
            .setting-item {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                padding: 15px 0;
                border-bottom: 1px solid #f3f4f6;
            }
            
            .setting-item:last-child {
                border-bottom: none;
            }
            
            .setting-info {
                flex: 1;
            }
            
            .setting-info strong {
                display: block;
                margin-bottom: 4px;
                color: #111827;
            }
            
            .setting-info p {
                margin: 0;
                font-size: 13px;
                color: #6b7280;
            }
            
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 26px;
                flex-shrink: 0;
            }
            
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #cbd5e1;
                transition: 0.3s;
                border-radius: 26px;
            }
            
            .slider:before {
                position: absolute;
                content: "";
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: 0.3s;
                border-radius: 50%;
            }
            
            input:checked + .slider {
                background-color: #667eea;
            }
            
            input:checked + .slider:before {
                transform: translateX(24px);
            }
            
            .stat-box {
                background: #f9fafb;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            
            .stat-label {
                font-size: 12px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }
            
            .stat-value {
                font-size: 24px;
                font-weight: 700;
                color: #111827;
            }
            
            .button-group {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .danger-zone {
                border: 2px solid #ef4444;
            }
            
            .danger-zone .card-header {
                background: #fef2f2;
                color: #991b1b;
            }
            
            .danger-action {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 0;
                border-bottom: 1px solid #fee2e2;
            }
            
            .danger-action:last-child {
                border-bottom: none;
            }
            
            .button-danger {
                background: #ef4444 !important;
                color: white !important;
                border-color: #dc2626 !important;
            }
            
            .button-danger:hover {
                background: #dc2626 !important;
            }
            
            .system-info-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .system-info-table tr {
                border-bottom: 1px solid #f3f4f6;
            }
            
            .system-info-table td {
                padding: 12px 0;
            }
            
            .features-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .features-list li {
                padding: 10px 0;
                display: flex;
                align-items: center;
                gap: 10px;
                border-bottom: 1px solid #f3f4f6;
            }
            
            .features-list li:last-child {
                border-bottom: none;
            }
            
            .features-list .dashicons {
                color: #10b981;
            }
            
            .adwpt-settings-footer {
                position: fixed;
                bottom: 0;
                left: 160px;
                right: 0;
                background: white;
                padding: 20px;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                z-index: 100;
            }
            
            .button-hero {
                padding: 12px 40px !important;
                font-size: 16px !important;
                height: auto !important;
            }
            
            @media (max-width: 782px) {
                .adwpt-settings-grid {
                    grid-template-columns: 1fr;
                }
                
                .adwpt-settings-footer {
                    left: 0;
                }
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.adwpt-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                
                $('.adwpt-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.adwpt-tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });
        });
        </script>
        
        <?php
    }
    
    private static function handle_submissions() {
        if (isset($_POST['adwpt_settings_submit'])) {
            check_admin_referer('adwpt_settings_nonce');
            
            // Save all settings
            $settings = [
                'tracking_enabled' => isset($_POST['tracking_enabled']) ? '1' : '0',
                'track_user_agent' => isset($_POST['track_user_agent']) ? '1' : '0',
                'notification_email' => sanitize_email($_POST['notification_email']),
                'email_reports' => isset($_POST['email_reports']) ? '1' : '0',
                'cache_enabled' => isset($_POST['cache_enabled']) ? '1' : '0',
                'cache_duration' => intval($_POST['cache_duration']),
                'lazy_load' => isset($_POST['lazy_load']) ? '1' : '0',
                'minify_output' => isset($_POST['minify_output']) ? '1' : '0',
                'data_retention' => intval($_POST['data_retention']),
                'gdpr_mode' => isset($_POST['gdpr_mode']) ? '1' : '0',
                'anonymize_ip' => isset($_POST['anonymize_ip']) ? '1' : '0',
                'respect_dnt' => isset($_POST['respect_dnt']) ? '1' : '0',
                'debug_mode' => isset($_POST['debug_mode']) ? '1' : '0',
                'enable_api' => isset($_POST['enable_api']) ? '1' : '0',
            ];
            
            foreach ($settings as $key => $value) {
                update_option('adwpt_' . $key, $value);
            }
            
            add_settings_error('adwpt_settings', 'settings_updated', 'Paramètres enregistrés avec succès !', 'success');
        }
        
        if (isset($_POST['adwpt_reset_stats'])) {
            check_admin_referer('adwpt_reset_stats_nonce');
            
            global $wpdb;
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}adwpt_stats");
            
            add_settings_error('adwpt_settings', 'stats_reset', 'Statistiques réinitialisées !', 'success');
        }
        
        if (isset($_POST['adwpt_export_settings'])) {
            check_admin_referer('adwpt_settings_nonce');
            
            $settings = self::get_settings();
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="adwptracker-settings-' . date('Y-m-d') . '.json"');
            echo json_encode($settings, JSON_PRETTY_PRINT);
            exit;
        }
        
        if (isset($_POST['adwpt_clear_cache'])) {
            check_admin_referer('adwpt_settings_nonce');
            
            // Clear WordPress cache
            wp_cache_flush();
            
            add_settings_error('adwpt_settings', 'cache_cleared', 'Cache vidé !', 'success');
        }
    }
    
    private static function get_settings() {
        return [
            'tracking_enabled' => get_option('adwpt_tracking_enabled', '1'),
            'track_user_agent' => get_option('adwpt_track_user_agent', '1'),
            'notification_email' => get_option('adwpt_notification_email', get_option('admin_email')),
            'email_reports' => get_option('adwpt_email_reports', '0'),
            'cache_enabled' => get_option('adwpt_cache_enabled', '0'),
            'cache_duration' => get_option('adwpt_cache_duration', '3600'),
            'lazy_load' => get_option('adwpt_lazy_load', '0'),
            'minify_output' => get_option('adwpt_minify_output', '0'),
            'data_retention' => get_option('adwpt_data_retention', '90'),
            'gdpr_mode' => get_option('adwpt_gdpr_mode', '0'),
            'anonymize_ip' => get_option('adwpt_anonymize_ip', '0'),
            'respect_dnt' => get_option('adwpt_respect_dnt', '0'),
            'debug_mode' => get_option('adwpt_debug_mode', '0'),
            'enable_api' => get_option('adwpt_enable_api', '0'),
        ];
    }
    
    private static function get_system_info() {
        global $wpdb;
        
        $stats_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}adwpt_stats");
        $db_size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) as size FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->prefix}adwpt_stats'");
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        
        return [
            'stats_count' => $stats_count ?: 0,
            'db_size' => $db_size ?: 0,
            'mysql_version' => $mysql_version,
        ];
    }
}
