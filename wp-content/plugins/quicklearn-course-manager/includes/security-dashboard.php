<?php
/**
 * Security Dashboard for QuickLearn Course Manager
 * 
 * Provides comprehensive security monitoring and reporting interface
 * 
 * @package QuickLearn_Course_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Dashboard Class
 * 
 * Handles security monitoring dashboard and reporting functionality
 */
class QLCM_Security_Dashboard {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for dashboard
        add_action('wp_ajax_qlcm_get_security_stats', array($this, 'ajax_get_security_stats'));
        add_action('wp_ajax_qlcm_clear_security_logs', array($this, 'ajax_clear_security_logs'));
        add_action('wp_ajax_qlcm_unblock_ip', array($this, 'ajax_unblock_ip'));
    }
    
    /**
     * Add admin menu for security dashboard
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Security Dashboard', 'quicklearn-course-manager'),
            __('Security', 'quicklearn-course-manager'),
            'manage_options',
            'qlcm-security',
            array($this, 'render_security_dashboard')
        );
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'qlcm_security_widget',
            __('QuickLearn Security Status', 'quicklearn-course-manager'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'qlcm-security') !== false || $hook === 'index.php') {
            wp_enqueue_script(
                'qlcm-security-dashboard',
                QLCM_PLUGIN_URL . 'assets/js/security-dashboard.js',
                array('jquery', 'chart-js'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-security-dashboard',
                QLCM_PLUGIN_URL . 'assets/css/security-dashboard.css',
                array(),
                QLCM_VERSION
            );
            
            wp_localize_script('qlcm-security-dashboard', 'qlcm_security', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_security_nonce'),
                'i18n' => array(
                    'loading' => __('Loading...', 'quicklearn-course-manager'),
                    'error' => __('Error loading data', 'quicklearn-course-manager'),
                    'confirm_clear' => __('Are you sure you want to clear all security logs?', 'quicklearn-course-manager'),
                    'confirm_unblock' => __('Are you sure you want to unblock this IP address?', 'quicklearn-course-manager'),
                )
            ));
        }
    }
    
    /**
     * Render security dashboard page
     */
    public function render_security_dashboard() {
        $security_manager = QLCM_Security_Manager::get_instance();
        $stats = $security_manager->get_security_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('QuickLearn Security Dashboard', 'quicklearn-course-manager'); ?></h1>
            
            <div class="qlcm-security-dashboard">
                <!-- Security Overview Cards -->
                <div class="qlcm-security-cards">
                    <div class="qlcm-security-card">
                        <div class="qlcm-card-icon">
                            <span class="dashicons dashicons-shield-alt"></span>
                        </div>
                        <div class="qlcm-card-content">
                            <h3><?php _e('Security Events (24h)', 'quicklearn-course-manager'); ?></h3>
                            <div class="qlcm-card-number" id="total-events-24h">
                                <?php echo array_sum(wp_list_pluck($stats['events_24h'], 'count')); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="qlcm-security-card">
                        <div class="qlcm-card-icon">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <div class="qlcm-card-content">
                            <h3><?php _e('Failed Logins (24h)', 'quicklearn-course-manager'); ?></h3>
                            <div class="qlcm-card-number" id="failed-logins-24h">
                                <?php 
                                $failed_logins = array_filter($stats['events_24h'], function($event) {
                                    return $event->event_type === 'login_failed';
                                });
                                echo !empty($failed_logins) ? $failed_logins[0]->count : 0;
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="qlcm-security-card">
                        <div class="qlcm-card-icon">
                            <span class="dashicons dashicons-block-default"></span>
                        </div>
                        <div class="qlcm-card-content">
                            <h3><?php _e('Blocked IPs', 'quicklearn-course-manager'); ?></h3>
                            <div class="qlcm-card-number" id="blocked-ips">
                                <?php echo $stats['blocked_ips']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="qlcm-security-card">
                        <div class="qlcm-card-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <div class="qlcm-card-content">
                            <h3><?php _e('Rate Limit Hits', 'quicklearn-course-manager'); ?></h3>
                            <div class="qlcm-card-number" id="rate-limit-hits">
                                <?php 
                                $rate_limits = array_filter($stats['events_24h'], function($event) {
                                    return $event->event_type === 'rate_limit_exceeded';
                                });
                                echo !empty($rate_limits) ? $rate_limits[0]->count : 0;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Events Chart -->
                <div class="qlcm-security-section">
                    <h2><?php _e('Security Events Timeline', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-chart-container">
                        <canvas id="security-events-chart"></canvas>
                    </div>
                </div>
                
                <!-- Top Failed Login IPs -->
                <div class="qlcm-security-section">
                    <h2><?php _e('Top Failed Login IPs (24h)', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-table-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('IP Address', 'quicklearn-course-manager'); ?></th>
                                    <th><?php _e('Failed Attempts', 'quicklearn-course-manager'); ?></th>
                                    <th><?php _e('Status', 'quicklearn-course-manager'); ?></th>
                                    <th><?php _e('Actions', 'quicklearn-course-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stats['top_failed_ips'])): ?>
                                    <?php foreach ($stats['top_failed_ips'] as $ip_data): ?>
                                        <tr>
                                            <td><?php echo esc_html($ip_data->ip_address); ?></td>
                                            <td><?php echo esc_html($ip_data->count); ?></td>
                                            <td>
                                                <?php if ($this->is_ip_blocked($ip_data->ip_address)): ?>
                                                    <span class="qlcm-status-blocked"><?php _e('Blocked', 'quicklearn-course-manager'); ?></span>
                                                <?php else: ?>
                                                    <span class="qlcm-status-active"><?php _e('Active', 'quicklearn-course-manager'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($this->is_ip_blocked($ip_data->ip_address)): ?>
                                                    <button class="button qlcm-unblock-ip" data-ip="<?php echo esc_attr($ip_data->ip_address); ?>">
                                                        <?php _e('Unblock', 'quicklearn-course-manager'); ?>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="button qlcm-block-ip" data-ip="<?php echo esc_attr($ip_data->ip_address); ?>">
                                                        <?php _e('Block', 'quicklearn-course-manager'); ?>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><?php _e('No failed login attempts in the last 24 hours.', 'quicklearn-course-manager'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Security Events -->
                <div class="qlcm-security-section">
                    <h2><?php _e('Recent Security Events', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-events-container">
                        <?php $this->render_recent_events(); ?>
                    </div>
                </div>
                
                <!-- Security Actions -->
                <div class="qlcm-security-section">
                    <h2><?php _e('Security Actions', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-actions-container">
                        <button class="button button-secondary" id="refresh-stats">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh Statistics', 'quicklearn-course-manager'); ?>
                        </button>
                        
                        <button class="button button-secondary" id="export-logs">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Security Logs', 'quicklearn-course-manager'); ?>
                        </button>
                        
                        <button class="button button-secondary" id="clear-logs">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Clear Old Logs', 'quicklearn-course-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $security_manager = QLCM_Security_Manager::get_instance();
        $stats = $security_manager->get_security_stats();
        
        ?>
        <div class="qlcm-dashboard-widget">
            <div class="qlcm-widget-stats">
                <div class="qlcm-stat-item">
                    <span class="qlcm-stat-label"><?php _e('Security Events (24h):', 'quicklearn-course-manager'); ?></span>
                    <span class="qlcm-stat-value"><?php echo array_sum(wp_list_pluck($stats['events_24h'], 'count')); ?></span>
                </div>
                
                <div class="qlcm-stat-item">
                    <span class="qlcm-stat-label"><?php _e('Blocked IPs:', 'quicklearn-course-manager'); ?></span>
                    <span class="qlcm-stat-value"><?php echo $stats['blocked_ips']; ?></span>
                </div>
                
                <?php if (!empty($stats['top_failed_ips'])): ?>
                    <div class="qlcm-stat-item">
                        <span class="qlcm-stat-label"><?php _e('Top Failed IP:', 'quicklearn-course-manager'); ?></span>
                        <span class="qlcm-stat-value"><?php echo esc_html($stats['top_failed_ips'][0]->ip_address); ?> (<?php echo $stats['top_failed_ips'][0]->count; ?>)</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="qlcm-widget-actions">
                <a href="<?php echo admin_url('edit.php?post_type=quick_course&page=qlcm-security'); ?>" class="button button-primary">
                    <?php _e('View Security Dashboard', 'quicklearn-course-manager'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent security events
     */
    private function render_recent_events() {
        global $wpdb;
        
        $security_manager = QLCM_Security_Manager::get_instance();
        $table_name = $wpdb->prefix . 'qlcm_security_log';
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            20
        ));
        
        if (empty($events)) {
            echo '<p>' . __('No recent security events.', 'quicklearn-course-manager') . '</p>';
            return;
        }
        
        echo '<div class="qlcm-events-list">';
        foreach ($events as $event) {
            $severity_class = 'qlcm-severity-' . $event->severity;
            $event_data = json_decode($event->event_data, true);
            
            echo '<div class="qlcm-event-item ' . $severity_class . '">';
            echo '<div class="qlcm-event-header">';
            echo '<span class="qlcm-event-type">' . esc_html($event->event_type) . '</span>';
            echo '<span class="qlcm-event-time">' . human_time_diff(strtotime($event->created_at), current_time('timestamp')) . ' ' . __('ago', 'quicklearn-course-manager') . '</span>';
            echo '<span class="qlcm-event-severity qlcm-severity-' . $event->severity . '">' . ucfirst($event->severity) . '</span>';
            echo '</div>';
            
            echo '<div class="qlcm-event-details">';
            echo '<span class="qlcm-event-ip">' . __('IP:', 'quicklearn-course-manager') . ' ' . esc_html($event->ip_address) . '</span>';
            if ($event->user_id) {
                $user = get_user_by('id', $event->user_id);
                if ($user) {
                    echo '<span class="qlcm-event-user">' . __('User:', 'quicklearn-course-manager') . ' ' . esc_html($user->display_name) . '</span>';
                }
            }
            echo '</div>';
            
            if (!empty($event_data)) {
                echo '<div class="qlcm-event-data">';
                foreach ($event_data as $key => $value) {
                    if (is_string($value) && strlen($value) < 100) {
                        echo '<span class="qlcm-data-item">' . esc_html($key) . ': ' . esc_html($value) . '</span>';
                    }
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Check if IP is currently blocked
     * 
     * @param string $ip IP address to check
     * @return bool True if blocked, false otherwise
     */
    private function is_ip_blocked($ip) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_rate_limits';
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE identifier = %s AND blocked_until > NOW()",
            $ip
        ));
        
        return $blocked > 0;
    }
    
    /**
     * AJAX handler for getting security statistics
     */
    public function ajax_get_security_stats() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_security_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $security_manager = QLCM_Security_Manager::get_instance();
        $stats = $security_manager->get_security_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for clearing security logs
     */
    public function ajax_clear_security_logs() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_security_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_security_log';
        
        // Delete logs older than 7 days
        $deleted = $wpdb->delete(
            $table_name,
            array(
                'created_at' => array('<', date('Y-m-d H:i:s', strtotime('-7 days')))
            ),
            array('%s')
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Deleted %d old security log entries.', 'quicklearn-course-manager'), $deleted)
        ));
    }
    
    /**
     * AJAX handler for unblocking IP addresses
     */
    public function ajax_unblock_ip() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_security_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $ip = sanitize_text_field($_POST['ip']);
        
        if (empty($ip)) {
            wp_send_json_error(array('message' => __('Invalid IP address', 'quicklearn-course-manager')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_rate_limits';
        
        // Remove block by setting blocked_until to NULL
        $updated = $wpdb->update(
            $table_name,
            array('blocked_until' => null),
            array('identifier' => $ip),
            array('%s'),
            array('%s')
        );
        
        if ($updated !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('IP address %s has been unblocked.', 'quicklearn-course-manager'), $ip)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to unblock IP address', 'quicklearn-course-manager')));
        }
    }
}