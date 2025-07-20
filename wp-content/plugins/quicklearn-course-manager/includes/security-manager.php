<?php
/**
 * Security Manager for QuickLearn Course Manager
 * 
 * Comprehensive security hardening and input validation system
 * 
 * @package QuickLearn_Course_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Manager Class
 * 
 * Handles comprehensive input validation, CSRF protection, rate limiting,
 * and security monitoring for the QuickLearn Course Manager plugin.
 */
class QLCM_Security_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Security log table name
     */
    private $security_log_table;
    
    /**
     * Rate limit table name
     */
    private $rate_limit_table;
    
    /**
     * Maximum login attempts per IP
     */
    const MAX_LOGIN_ATTEMPTS = 5;
    
    /**
     * Login attempt window in seconds (15 minutes)
     */
    const LOGIN_ATTEMPT_WINDOW = 900;
    
    /**
     * Maximum AJAX requests per minute
     */
    const MAX_AJAX_REQUESTS = 30;
    
    /**
     * AJAX rate limit window in seconds
     */
    const AJAX_RATE_WINDOW = 60;
    
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
        global $wpdb;
        $this->security_log_table = $wpdb->prefix . 'qlcm_security_log';
        $this->rate_limit_table = $wpdb->prefix . 'qlcm_rate_limits';
        
        $this->init_hooks();
        $this->create_security_tables();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Security monitoring hooks
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_action('wp_login', array($this, 'log_successful_login'), 10, 2);
        
        // Input validation hooks
        add_filter('pre_comment_content', array($this, 'validate_comment_content'));
        add_action('wp_ajax_*', array($this, 'validate_ajax_request'), 1);
        add_action('wp_ajax_nopriv_*', array($this, 'validate_ajax_request'), 1);
        
        // Admin security hooks
        add_action('admin_init', array($this, 'check_admin_security'));
        
        // Cleanup old logs daily
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_logs'));
        
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
    }
    
    /**
     * Create security-related database tables
     */
    private function create_security_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Security log table
        $sql_log = "CREATE TABLE IF NOT EXISTS {$this->security_log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            severity enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY created_at (created_at),
            KEY severity (severity)
        ) $charset_collate;";
        
        // Rate limit table
        $sql_rate = "CREATE TABLE IF NOT EXISTS {$this->rate_limit_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            attempts int(11) DEFAULT 1,
            first_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_action (identifier, action),
            KEY blocked_until (blocked_until)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_log);
        dbDelta($sql_rate);
    }
    
    /**
     * Comprehensive input sanitization (Requirement 5.1)
     * 
     * @param mixed $input The input to sanitize
     * @param string $type The type of sanitization to apply
     * @param array $options Additional sanitization options
     * @return mixed Sanitized input
     */
    public function sanitize_input($input, $type = 'text', $options = array()) {
        if (is_null($input)) {
            return null;
        }
        
        // Handle arrays recursively
        if (is_array($input)) {
            return array_map(function($item) use ($type, $options) {
                return $this->sanitize_input($item, $type, $options);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($input);
                
            case 'url':
                return esc_url_raw($input);
                
            case 'int':
                return absint($input);
                
            case 'float':
                return floatval($input);
                
            case 'slug':
                return sanitize_title($input);
                
            case 'key':
                return sanitize_key($input);
                
            case 'textarea':
                return sanitize_textarea_field($input);
                
            case 'html':
                $allowed_tags = isset($options['allowed_tags']) ? $options['allowed_tags'] : 'post';
                return wp_kses($input, $allowed_tags);
                
            case 'filename':
                return sanitize_file_name($input);
                
            case 'user':
                return sanitize_user($input);
                
            case 'sql_orderby':
                return $this->sanitize_sql_orderby($input);
                
            case 'course_data':
                return $this->sanitize_course_data($input);
                
            case 'rating':
                return $this->sanitize_rating($input);
                
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Sanitize SQL ORDER BY clause
     * 
     * @param string $orderby The ORDER BY clause to sanitize
     * @return string Sanitized ORDER BY clause
     */
    private function sanitize_sql_orderby($orderby) {
        $allowed_keys = array(
            'date', 'title', 'menu_order', 'rand', 'comment_count',
            'ID', 'author', 'name', 'type', 'modified'
        );
        
        $allowed_orders = array('ASC', 'DESC');
        
        $parts = explode(' ', trim($orderby));
        $key = isset($parts[0]) ? $parts[0] : 'date';
        $order = isset($parts[1]) ? strtoupper($parts[1]) : 'DESC';
        
        if (!in_array($key, $allowed_keys)) {
            $key = 'date';
        }
        
        if (!in_array($order, $allowed_orders)) {
            $order = 'DESC';
        }
        
        return $key . ' ' . $order;
    }
    
    /**
     * Sanitize course-specific data
     * 
     * @param array $data Course data to sanitize
     * @return array Sanitized course data
     */
    private function sanitize_course_data($data) {
        $sanitized = array();
        
        $field_types = array(
            'post_title' => 'text',
            'post_content' => 'html',
            'post_excerpt' => 'textarea',
            'post_status' => 'key',
            'course_price' => 'float',
            'course_duration' => 'int',
            'course_level' => 'text',
            'course_instructor' => 'text',
            'course_categories' => 'array',
            'course_tags' => 'array'
        );
        
        foreach ($data as $key => $value) {
            $type = isset($field_types[$key]) ? $field_types[$key] : 'text';
            $sanitized[$key] = $this->sanitize_input($value, $type);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize rating data
     * 
     * @param mixed $rating Rating value to sanitize
     * @return int Sanitized rating (1-5)
     */
    private function sanitize_rating($rating) {
        $rating = absint($rating);
        return max(1, min(5, $rating));
    }
    
    /**
     * Enhanced nonce verification with logging (Requirement 5.2)
     * 
     * @param string $nonce The nonce to verify
     * @param string $action The action associated with the nonce
     * @param bool $log_failure Whether to log failures
     * @return bool True if nonce is valid, false otherwise
     */
    public function verify_nonce($nonce, $action, $log_failure = true) {
        $is_valid = wp_verify_nonce($nonce, $action);
        
        if (!$is_valid && $log_failure) {
            $this->log_security_event('nonce_verification_failed', array(
                'action' => $action,
                'nonce_partial' => substr($nonce, 0, 8) . '...',
                'referer' => wp_get_referer()
            ), 'medium');
        }
        
        return $is_valid;
    }
    
    /**
     * Advanced rate limiting with database persistence (Requirement 5.2)
     * 
     * @param string $action The action being rate limited
     * @param int $max_attempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @param string $identifier Custom identifier (defaults to IP)
     * @return bool True if request is allowed, false if rate limited
     */
    public function check_rate_limit($action, $max_attempts = 30, $window = 60, $identifier = null) {
        global $wpdb;
        
        if (is_null($identifier)) {
            $identifier = $this->get_client_ip();
        }
        
        $identifier = sanitize_text_field($identifier);
        $action = sanitize_key($action);
        
        // Clean up expired entries first
        $wpdb->delete(
            $this->rate_limit_table,
            array(
                'blocked_until' => array('<=', current_time('mysql'))
            ),
            array('%s')
        );
        
        // Get current rate limit record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->rate_limit_table} 
             WHERE identifier = %s AND action = %s",
            $identifier,
            $action
        ));
        
        $now = current_time('mysql');
        
        if (!$record) {
            // First attempt - create new record
            $wpdb->insert(
                $this->rate_limit_table,
                array(
                    'identifier' => $identifier,
                    'action' => $action,
                    'attempts' => 1,
                    'first_attempt' => $now,
                    'last_attempt' => $now
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );
            return true;
        }
        
        // Check if currently blocked
        if ($record->blocked_until && strtotime($record->blocked_until) > time()) {
            $this->log_security_event('rate_limit_blocked_request', array(
                'action' => $action,
                'identifier' => $identifier,
                'attempts' => $record->attempts,
                'blocked_until' => $record->blocked_until
            ), 'high');
            return false;
        }
        
        // Check if window has expired
        $window_start = strtotime($record->first_attempt);
        if ((time() - $window_start) > $window) {
            // Reset counter for new window
            $wpdb->update(
                $this->rate_limit_table,
                array(
                    'attempts' => 1,
                    'first_attempt' => $now,
                    'last_attempt' => $now,
                    'blocked_until' => null
                ),
                array('id' => $record->id),
                array('%d', '%s', '%s', '%s'),
                array('%d')
            );
            return true;
        }
        
        // Increment attempt counter
        $new_attempts = $record->attempts + 1;
        
        if ($new_attempts > $max_attempts) {
            // Block for progressively longer periods based on violations
            $block_duration = min(3600, 300 * pow(2, floor($new_attempts / $max_attempts) - 1));
            $blocked_until = date('Y-m-d H:i:s', time() + $block_duration);
            
            $wpdb->update(
                $this->rate_limit_table,
                array(
                    'attempts' => $new_attempts,
                    'last_attempt' => $now,
                    'blocked_until' => $blocked_until
                ),
                array('id' => $record->id),
                array('%d', '%s', '%s'),
                array('%d')
            );
            
            $this->log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'identifier' => $identifier,
                'attempts' => $new_attempts,
                'blocked_until' => $blocked_until
            ), 'high');
            
            return false;
        }
        
        // Update attempt counter
        $wpdb->update(
            $this->rate_limit_table,
            array(
                'attempts' => $new_attempts,
                'last_attempt' => $now
            ),
            array('id' => $record->id),
            array('%d', '%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Comprehensive error logging and monitoring (Requirement 5.2)
     * 
     * @param string $event_type Type of security event
     * @param array $event_data Additional event data
     * @param string $severity Event severity level
     */
    public function log_security_event($event_type, $event_data = array(), $severity = 'medium') {
        global $wpdb;
        
        $event_data = array_merge($event_data, array(
            'timestamp' => current_time('mysql'),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'referer' => wp_get_referer(),
            'session_id' => session_id()
        ));
        
        $wpdb->insert(
            $this->security_log_table,
            array(
                'event_type' => sanitize_key($event_type),
                'event_data' => wp_json_encode($event_data),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'severity' => $severity
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Also log to WordPress error log for critical events
        if (in_array($severity, array('high', 'critical'))) {
            error_log(sprintf(
                'QLCM Security Event [%s]: %s - IP: %s - User: %d - Data: %s',
                strtoupper($severity),
                $event_type,
                $this->get_client_ip(),
                get_current_user_id(),
                wp_json_encode($event_data)
            ));
        }
    }
    
    /**
     * Get client IP address with proxy support
     * 
     * @return string Client IP address
     */
    public function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return sanitize_text_field($ip);
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
    }
    
    /**
     * Validate AJAX requests (Requirement 5.2)
     */
    public function validate_ajax_request() {
        // Only validate our plugin's AJAX requests
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        
        $our_actions = array(
            'filter_courses',
            'enroll_in_course',
            'update_course_progress',
            'submit_course_rating',
            'load_course_modules',
            'submit_forum_post',
            'submit_qa_question'
        );
        
        if (!in_array($action, $our_actions)) {
            return;
        }
        
        // Rate limiting for AJAX requests
        if (!$this->check_rate_limit('ajax_' . $action, self::MAX_AJAX_REQUESTS, self::AJAX_RATE_WINDOW)) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment and try again.', 'quicklearn-course-manager')
            ));
            wp_die();
        }
        
        // Log AJAX request for monitoring
        $this->log_security_event('ajax_request', array(
            'action' => $action,
            'data_size' => strlen(serialize($_REQUEST))
        ), 'low');
    }
    
    /**
     * Log failed login attempts
     * 
     * @param string $username The username that failed to log in
     */
    public function log_failed_login($username) {
        $this->log_security_event('login_failed', array(
            'username' => sanitize_user($username),
            'attempt_number' => $this->get_login_attempts($this->get_client_ip()) + 1
        ), 'medium');
        
        // Check for brute force attempts
        if (!$this->check_rate_limit('login_attempt', self::MAX_LOGIN_ATTEMPTS, self::LOGIN_ATTEMPT_WINDOW)) {
            $this->log_security_event('brute_force_detected', array(
                'username' => sanitize_user($username),
                'ip' => $this->get_client_ip()
            ), 'critical');
        }
    }
    
    /**
     * Log successful login
     * 
     * @param string $user_login The username that logged in
     * @param WP_User $user The user object
     */
    public function log_successful_login($user_login, $user) {
        $this->log_security_event('login_success', array(
            'username' => sanitize_user($user_login),
            'user_id' => $user->ID,
            'user_roles' => $user->roles
        ), 'low');
    }
    
    /**
     * Get number of login attempts for an IP
     * 
     * @param string $ip IP address
     * @return int Number of attempts
     */
    private function get_login_attempts($ip) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT attempts FROM {$this->rate_limit_table} 
             WHERE identifier = %s AND action = 'login_attempt'
             AND first_attempt > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $ip,
            self::LOGIN_ATTEMPT_WINDOW
        ));
        
        return intval($count);
    }
    
    /**
     * Validate comment content
     * 
     * @param string $content Comment content
     * @return string Validated content
     */
    public function validate_comment_content($content) {
        // Check for spam patterns
        $spam_patterns = array(
            '/\b(?:viagra|cialis|casino|poker|lottery)\b/i',
            '/\b(?:buy now|click here|limited time)\b/i',
            '/(?:http[s]?:\/\/){2,}/',  // Multiple URLs
            '/[A-Z]{10,}/',             // Excessive caps
        );
        
        foreach ($spam_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->log_security_event('spam_comment_detected', array(
                    'pattern' => $pattern,
                    'content_length' => strlen($content)
                ), 'medium');
                
                return ''; // Block spam content
            }
        }
        
        return $content;
    }
    
    /**
     * Check admin security
     */
    public function check_admin_security() {
        if (!is_admin()) {
            return;
        }
        
        // Check for suspicious admin activities
        $suspicious_actions = array(
            'delete_plugins',
            'delete_themes',
            'edit_plugins',
            'edit_themes'
        );
        
        $current_action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        
        if (in_array($current_action, $suspicious_actions)) {
            $this->log_security_event('suspicious_admin_action', array(
                'action' => $current_action,
                'page' => isset($_GET['page']) ? sanitize_key($_GET['page']) : ''
            ), 'high');
        }
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * Clean up old security logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // Delete logs older than 30 days
        $wpdb->delete(
            $this->security_log_table,
            array(
                'created_at' => array('<', date('Y-m-d H:i:s', strtotime('-30 days')))
            ),
            array('%s')
        );
        
        // Delete old rate limit records
        $wpdb->delete(
            $this->rate_limit_table,
            array(
                'first_attempt' => array('<', date('Y-m-d H:i:s', strtotime('-7 days')))
            ),
            array('%s')
        );
    }
    
    /**
     * Get security statistics for admin dashboard
     * 
     * @return array Security statistics
     */
    public function get_security_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Get event counts by type (last 24 hours)
        $stats['events_24h'] = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->security_log_table} 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY event_type
             ORDER BY count DESC"
        ));
        
        // Get top IPs by failed attempts
        $stats['top_failed_ips'] = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as count 
             FROM {$this->security_log_table} 
             WHERE event_type = 'login_failed' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY ip_address
             ORDER BY count DESC
             LIMIT 10"
        ));
        
        // Get blocked IPs count
        $stats['blocked_ips'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT identifier) 
             FROM {$this->rate_limit_table} 
             WHERE blocked_until > NOW()"
        ));
        
        return $stats;
    }
}