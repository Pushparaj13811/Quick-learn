<?php
/**
 * Plugin Name:       QuickLearn Course Manager
 * Plugin URI:        https://www.hpm.com.np/projects/quicklearn-course-manager
 * Description:       A powerful course management plugin for WordPress. Enables managing e-learning courses, categories, and AJAX-based filtering.
 * Version:           1.0.1
 * Author:            Pushparaj Mehta
 * Author URI:        https://www.hpm.com.np
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quicklearn-course-manager
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      6.5
 * Requires PHP:      7.4
 * Network:           false
 */


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('QLCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QLCM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('QLCM_PLUGIN_FILE', __FILE__);
define('QLCM_VERSION', '1.0.1');

/**
 * Main QuickLearn Course Manager Class
 */
class QuickLearn_Course_Manager {
    
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
        $this->include_files();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        // Load security manager first
        require_once QLCM_PLUGIN_PATH . 'includes/security-manager.php';
        require_once QLCM_PLUGIN_PATH . 'includes/security-dashboard.php';
        
        require_once QLCM_PLUGIN_PATH . 'includes/course-cpt.php';
        require_once QLCM_PLUGIN_PATH . 'includes/course-taxonomy.php';
        require_once QLCM_PLUGIN_PATH . 'includes/ajax-handlers.php';
        require_once QLCM_PLUGIN_PATH . 'includes/seo-optimization.php';
        require_once QLCM_PLUGIN_PATH . 'includes/user-enrollment.php';
        require_once QLCM_PLUGIN_PATH . 'includes/course-ratings.php';
        require_once QLCM_PLUGIN_PATH . 'includes/certificate-system.php';
        require_once QLCM_PLUGIN_PATH . 'includes/certificate-test.php';
        require_once QLCM_PLUGIN_PATH . 'includes/multimedia-content.php';
        require_once QLCM_PLUGIN_PATH . 'includes/course-modules.php';
        require_once QLCM_PLUGIN_PATH . 'includes/course-forums.php';
        require_once QLCM_PLUGIN_PATH . 'includes/course-qa.php';
        require_once QLCM_PLUGIN_PATH . 'includes/user-profiles.php';
        require_once QLCM_PLUGIN_PATH . 'includes/admin-pages.php';
        require_once QLCM_PLUGIN_PATH . 'includes/database-optimization.php';
    }
    
    /**
     * Plugin activation hook (Requirements 5.1, 5.3)
     */
    public function activate() {
        // Security check - only allow administrators to activate plugins
        if (!current_user_can('activate_plugins')) {
            wp_die(__('You do not have sufficient permissions to activate this plugin.', 'quicklearn-course-manager'));
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(__('QuickLearn Course Manager requires WordPress 5.0 or higher.', 'quicklearn-course-manager'));
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(__('QuickLearn Course Manager requires PHP 7.4 or higher.', 'quicklearn-course-manager'));
        }
        
        // Verify required WordPress functions exist
        if (!function_exists('register_post_type') || !function_exists('register_taxonomy')) {
            wp_die(__('Required WordPress functions are not available.', 'quicklearn-course-manager'));
        }
        
        // Register post types and taxonomies
        $this->init();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
        
        // Log activation for security audit
        error_log('QuickLearn Course Manager activated by user ID: ' . get_current_user_id());
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin functionality
     */
    public function init() {
        // Initialize security manager first
        if (class_exists('QLCM_Security_Manager')) {
            QLCM_Security_Manager::get_instance();
        }
        
        // Initialize security dashboard
        if (class_exists('QLCM_Security_Dashboard')) {
            QLCM_Security_Dashboard::get_instance();
        }
        
        // Initialize course post type
        if (class_exists('QLCM_Course_CPT')) {
            QLCM_Course_CPT::get_instance();
        }
        
        // Initialize course taxonomy
        if (class_exists('QLCM_Course_Taxonomy')) {
            QLCM_Course_Taxonomy::get_instance();
        }
        
        // Initialize AJAX handlers
        if (class_exists('QLCM_Ajax_Handlers')) {
            QLCM_Ajax_Handlers::get_instance();
        }
        
        // Initialize multimedia content
        if (class_exists('QLCM_Multimedia_Content')) {
            QLCM_Multimedia_Content::get_instance();
        }
        
        // Initialize course modules
        if (class_exists('QLCM_Course_Modules')) {
            QLCM_Course_Modules::get_instance();
        }
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'quicklearn-course-manager',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Set default plugin options (Requirement 5.1 - Input sanitization)
     */
    private function set_default_options() {
        $default_options = array(
            'courses_per_page' => absint(12),
            'enable_ajax_filtering' => (bool) true,
            'show_course_excerpts' => (bool) true,
            'course_image_size' => sanitize_key('medium')
        );
        
        add_option('qlcm_settings', $default_options);
    }
    
    /**
     * Security and validation methods (Requirements 5.1, 5.2, 5.3, 5.4)
     */
    
    /**
     * Validate plugin settings (Requirement 5.1)
     * 
     * @param array $settings Raw settings data
     * @return array Sanitized settings data
     */
    public static function validate_plugin_settings($settings) {
        $validated = array();
        
        // Sanitize courses per page
        if (isset($settings['courses_per_page'])) {
            $validated['courses_per_page'] = absint($settings['courses_per_page']);
            if ($validated['courses_per_page'] < 1 || $validated['courses_per_page'] > 100) {
                $validated['courses_per_page'] = 12; // Default fallback
            }
        }
        
        // Sanitize boolean options
        $boolean_options = array('enable_ajax_filtering', 'show_course_excerpts');
        foreach ($boolean_options as $option) {
            if (isset($settings[$option])) {
                $validated[$option] = (bool) $settings[$option];
            }
        }
        
        // Sanitize image size
        if (isset($settings['course_image_size'])) {
            $allowed_sizes = array('thumbnail', 'medium', 'large', 'full');
            $size = sanitize_key($settings['course_image_size']);
            $validated['course_image_size'] = in_array($size, $allowed_sizes) ? $size : 'medium';
        }
        
        return $validated;
    }
    
    /**
     * Check if current user can manage plugin settings (Requirement 5.3)
     * 
     * @return bool True if user can manage settings, false otherwise
     */
    public static function current_user_can_manage_plugin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Sanitize course data for database operations (Requirement 5.1)
     * 
     * @param array $course_data Raw course data
     * @return array Sanitized course data
     */
    public static function sanitize_course_data($course_data) {
        $sanitized = array();
        
        if (isset($course_data['post_title'])) {
            $sanitized['post_title'] = sanitize_text_field($course_data['post_title']);
        }
        
        if (isset($course_data['post_content'])) {
            $sanitized['post_content'] = wp_kses_post($course_data['post_content']);
        }
        
        if (isset($course_data['post_excerpt'])) {
            $sanitized['post_excerpt'] = sanitize_textarea_field($course_data['post_excerpt']);
        }
        
        if (isset($course_data['post_status'])) {
            $allowed_statuses = array('publish', 'draft', 'private', 'pending');
            $status = sanitize_key($course_data['post_status']);
            $sanitized['post_status'] = in_array($status, $allowed_statuses) ? $status : 'draft';
        }
        
        return $sanitized;
    }
    
    /**
     * Escape course data for output (Requirement 5.4)
     * 
     * @param mixed $data The data to escape
     * @param string $context The context for escaping
     * @return mixed Escaped data
     */
    public static function escape_course_data($data, $context = 'html') {
        switch ($context) {
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            case 'js':
                return esc_js($data);
            case 'textarea':
                return esc_textarea($data);
            case 'sql':
                global $wpdb;
                return $wpdb->prepare('%s', $data);
            case 'html':
            default:
                return esc_html($data);
        }
    }
    
    /**
     * Log security events (Requirement 5.2)
     * 
     * @param string $event The security event to log
     * @param array $context Additional context data
     */
    public static function log_security_event($event, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => sanitize_text_field($event),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'context' => $context
        );
        
        error_log('QuickLearn Security Event: ' . wp_json_encode($log_entry));
    }
    
    /**
     * Get client IP address safely (Requirement 5.1)
     * 
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
    }
    
    /**
     * Rate limiting for AJAX requests (Requirement 5.2)
     * 
     * @param string $action The action being rate limited
     * @param int $limit Number of requests allowed per time window
     * @param int $window Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public static function check_rate_limit($action, $limit = 60, $window = 60) {
        $ip = self::get_client_ip();
        $key = 'qlcm_rate_limit_' . sanitize_key($action) . '_' . md5($ip);
        
        $current_requests = get_transient($key);
        
        if ($current_requests === false) {
            // First request in this window
            set_transient($key, 1, $window);
            return true;
        } elseif ($current_requests < $limit) {
            // Within limit, increment counter
            set_transient($key, $current_requests + 1, $window);
            return true;
        } else {
            // Rate limit exceeded
            self::log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'ip' => $ip,
                'requests' => $current_requests
            ));
            return false;
        }
    }
}

/**
 * Security helper functions (Requirements 5.1, 5.2, 5.3, 5.4)
 */

/**
 * Validate nonce with additional security checks (Requirement 5.2)
 * 
 * @param string $nonce The nonce to validate
 * @param string $action The action associated with the nonce
 * @return bool True if nonce is valid, false otherwise
 */
function qlcm_verify_nonce($nonce, $action) {
    // Basic nonce verification
    if (!wp_verify_nonce($nonce, $action)) {
        QuickLearn_Course_Manager::log_security_event('invalid_nonce', array(
            'action' => $action,
            'nonce' => substr($nonce, 0, 10) . '...' // Log partial nonce for debugging
        ));
        return false;
    }
    
    return true;
}

/**
 * Check if request is from admin area (Requirement 5.3)
 * 
 * @return bool True if request is from admin, false otherwise
 */
function qlcm_is_admin_request() {
    return is_admin() && current_user_can('manage_options');
}

/**
 * Sanitize array recursively (Requirement 5.1)
 * 
 * @param array $array The array to sanitize
 * @param string $sanitize_function The sanitization function to use
 * @return array Sanitized array
 */
function qlcm_sanitize_array($array, $sanitize_function = 'sanitize_text_field') {
    $sanitized = array();
    
    foreach ($array as $key => $value) {
        $clean_key = sanitize_key($key);
        
        if (is_array($value)) {
            $sanitized[$clean_key] = qlcm_sanitize_array($value, $sanitize_function);
        } else {
            $sanitized[$clean_key] = call_user_func($sanitize_function, $value);
        }
    }
    
    return $sanitized;
}

// Initialize the plugin
QuickLearn_Course_Manager::get_instance();