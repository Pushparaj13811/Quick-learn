<?php
/**
 * Database Optimization and Performance Enhancement
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling database optimization and performance
 */
class QLCM_Database_Optimization {
    
    /**
     * Instance of this class
     *
     * @var QLCM_Database_Optimization
     */
    private static $instance = null;
    
    /**
     * Cache group for query caching
     */
    const CACHE_GROUP = 'qlcm_queries';
    
    /**
     * Cache expiration time (1 hour)
     */
    const CACHE_EXPIRATION = 3600;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_Database_Optimization Instance of this class
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
        // Add database optimization on plugin activation
        register_activation_hook(QLCM_PLUGIN_FILE, array($this, 'optimize_database_tables'));
        
        // Schedule cleanup routines
        add_action('init', array($this, 'schedule_cleanup_routines'));
        
        // Add cleanup hooks
        add_action('qlcm_daily_cleanup', array($this, 'daily_cleanup_routine'));
        add_action('qlcm_weekly_cleanup', array($this, 'weekly_cleanup_routine'));
        
        // Add query optimization hooks
        add_action('wp_loaded', array($this, 'init_query_optimization'));
        
        // Add admin page for database optimization
        add_action('admin_menu', array($this, 'add_optimization_admin_page'));
        
        // Add AJAX handlers for optimization tasks
        add_action('wp_ajax_qlcm_optimize_database', array($this, 'handle_database_optimization'));
        add_action('wp_ajax_qlcm_cleanup_old_data', array($this, 'handle_data_cleanup'));
        
        // Clear cache when data changes
        add_action('qlcm_user_enrolled', array($this, 'clear_enrollment_cache'));
        add_action('qlcm_rating_submitted', array($this, 'clear_rating_cache'));
        add_action('qlcm_course_completed', array($this, 'clear_progress_cache'));
    }
    
    /**
     * Optimize database tables with proper indexes
     */
    public function optimize_database_tables() {
        global $wpdb;
        
        // Get table names
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        $analytics_table = $wpdb->prefix . 'qlcm_analytics';
        
        // Check if tables exist before adding indexes
        $tables_to_optimize = array();
        
        if ($this->table_exists($enrollments_table)) {
            $tables_to_optimize['enrollments'] = $enrollments_table;
        }
        
        if ($this->table_exists($progress_table)) {
            $tables_to_optimize['progress'] = $progress_table;
        }
        
        if ($this->table_exists($ratings_table)) {
            $tables_to_optimize['ratings'] = $ratings_table;
        }
        
        if ($this->table_exists($certificates_table)) {
            $tables_to_optimize['certificates'] = $certificates_table;
        }
        
        if ($this->table_exists($analytics_table)) {
            $tables_to_optimize['analytics'] = $analytics_table;
        }
        
        // Add indexes for enrollments table
        if (isset($tables_to_optimize['enrollments'])) {
            $this->add_table_indexes($enrollments_table, array(
                'idx_user_course' => 'user_id, course_id',
                'idx_user_status' => 'user_id, status',
                'idx_course_status' => 'course_id, status',
                'idx_enrollment_date' => 'enrollment_date',
                'idx_completion_date' => 'completion_date',
                'idx_progress' => 'progress_percentage',
                'idx_status_progress' => 'status, progress_percentage'
            ));
        }
        
        // Add indexes for progress table
        if (isset($tables_to_optimize['progress'])) {
            $this->add_table_indexes($progress_table, array(
                'idx_enrollment_module' => 'enrollment_id, module_id',
                'idx_completion_date' => 'completion_date',
                'idx_progress_percentage' => 'progress_percentage'
            ));
        }
        
        // Add indexes for ratings table
        if (isset($tables_to_optimize['ratings'])) {
            $this->add_table_indexes($ratings_table, array(
                'idx_course_rating' => 'course_id, rating',
                'idx_user_course' => 'user_id, course_id',
                'idx_status_date' => 'status, created_date',
                'idx_rating_date' => 'rating, created_date'
            ));
        }
        
        // Add indexes for certificates table
        if (isset($tables_to_optimize['certificates'])) {
            $this->add_table_indexes($certificates_table, array(
                'idx_user_course' => 'user_id, course_id',
                'idx_certificate_id' => 'certificate_id',
                'idx_issue_date' => 'issue_date'
            ));
        }
        
        // Add indexes for analytics table
        if (isset($tables_to_optimize['analytics'])) {
            $this->add_table_indexes($analytics_table, array(
                'idx_event_date' => 'event_date',
                'idx_event_type' => 'event_type',
                'idx_course_event' => 'course_id, event_type',
                'idx_user_event' => 'user_id, event_type'
            ));
        }
        
        // Optimize table storage
        foreach ($tables_to_optimize as $table_name) {
            $wpdb->query("OPTIMIZE TABLE $table_name");
        }
        
        // Update optimization timestamp
        update_option('qlcm_last_optimization', current_time('mysql'));
    }
    
    /**
     * Check if table exists
     *
     * @param string $table_name Table name
     * @return bool True if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        return $wpdb->get_var($query) === $table_name;
    }
    
    /**
     * Add indexes to table if they don't exist
     *
     * @param string $table_name Table name
     * @param array $indexes Array of index_name => columns
     */
    private function add_table_indexes($table_name, $indexes) {
        global $wpdb;
        
        // Get existing indexes
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $table_name", ARRAY_A);
        $existing_index_names = array_column($existing_indexes, 'Key_name');
        
        foreach ($indexes as $index_name => $columns) {
            // Skip if index already exists
            if (in_array($index_name, $existing_index_names)) {
                continue;
            }
            
            // Add index
            $sql = "ALTER TABLE $table_name ADD INDEX $index_name ($columns)";
            $wpdb->query($sql);
        }
    }
    
    /**
     * Schedule cleanup routines
     */
    public function schedule_cleanup_routines() {
        // Schedule daily cleanup
        if (!wp_next_scheduled('qlcm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'qlcm_daily_cleanup');
        }
        
        // Schedule weekly cleanup
        if (!wp_next_scheduled('qlcm_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'qlcm_weekly_cleanup');
        }
    }
    
    /**
     * Daily cleanup routine
     */
    public function daily_cleanup_routine() {
        // Clean up expired transients
        $this->cleanup_expired_transients();
        
        // Clean up old analytics data (older than 90 days)
        $this->cleanup_old_analytics_data(90);
        
        // Clean up orphaned progress records
        $this->cleanup_orphaned_progress_records();
        
        // Update optimization log
        $this->log_cleanup_activity('daily_cleanup_completed');
    }
    
    /**
     * Weekly cleanup routine
     */
    public function weekly_cleanup_routine() {
        // Optimize database tables
        $this->optimize_database_tables();
        
        // Clean up old log entries (older than 30 days)
        $this->cleanup_old_log_entries(30);
        
        // Clean up orphaned ratings
        $this->cleanup_orphaned_ratings();
        
        // Clean up orphaned certificates
        $this->cleanup_orphaned_certificates();
        
        // Update optimization log
        $this->log_cleanup_activity('weekly_cleanup_completed');
    }
    
    /**
     * Clean up expired transients
     */
    private function cleanup_expired_transients() {
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT IN (SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%')");
    }
    
    /**
     * Clean up old analytics data
     *
     * @param int $days Number of days to keep
     */
    private function cleanup_old_analytics_data($days) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'qlcm_analytics';
        
        if (!$this->table_exists($analytics_table)) {
            return;
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $analytics_table WHERE event_date < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Clean up orphaned progress records
     */
    private function cleanup_orphaned_progress_records() {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        if (!$this->table_exists($progress_table) || !$this->table_exists($enrollments_table)) {
            return;
        }
        
        // Delete progress records for non-existent enrollments
        $wpdb->query("
            DELETE p FROM $progress_table p
            LEFT JOIN $enrollments_table e ON p.enrollment_id = e.id
            WHERE e.id IS NULL
        ");
    }
    
    /**
     * Clean up orphaned ratings
     */
    private function cleanup_orphaned_ratings() {
        global $wpdb;
        
        $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
        
        if (!$this->table_exists($ratings_table)) {
            return;
        }
        
        // Delete ratings for non-existent courses
        $wpdb->query("
            DELETE r FROM $ratings_table r
            LEFT JOIN {$wpdb->posts} p ON r.course_id = p.ID
            WHERE p.ID IS NULL OR p.post_type != 'quick_course'
        ");
        
        // Delete ratings for non-existent users
        $wpdb->query("
            DELETE r FROM $ratings_table r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE u.ID IS NULL
        ");
    }
    
    /**
     * Clean up orphaned certificates
     */
    private function cleanup_orphaned_certificates() {
        global $wpdb;
        
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        if (!$this->table_exists($certificates_table)) {
            return;
        }
        
        // Delete certificates for non-existent courses
        $wpdb->query("
            DELETE c FROM $certificates_table c
            LEFT JOIN {$wpdb->posts} p ON c.course_id = p.ID
            WHERE p.ID IS NULL OR p.post_type != 'quick_course'
        ");
        
        // Delete certificates for non-existent users
        $wpdb->query("
            DELETE c FROM $certificates_table c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE u.ID IS NULL
        ");
    }
    
    /**
     * Clean up old log entries
     *
     * @param int $days Number of days to keep
     */
    private function cleanup_old_log_entries($days) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Clean up optimization logs
        $logs = get_option('qlcm_optimization_logs', array());
        $logs = array_filter($logs, function($log) use ($cutoff_date) {
            return $log['timestamp'] >= $cutoff_date;
        });
        
        update_option('qlcm_optimization_logs', $logs);
    }
    
    /**
     * Initialize query optimization
     */
    public function init_query_optimization() {
        // Add object cache support if available
        if (function_exists('wp_cache_add_global_groups')) {
            wp_cache_add_global_groups(array(self::CACHE_GROUP));
        }
    }
    
    /**
     * Get cached query result
     *
     * @param string $cache_key Cache key
     * @param callable $query_callback Callback to execute if cache miss
     * @param int $expiration Cache expiration time
     * @return mixed Query result
     */
    public function get_cached_query($cache_key, $query_callback, $expiration = null) {
        if ($expiration === null) {
            $expiration = self::CACHE_EXPIRATION;
        }
        
        // Try to get from cache
        $cached_result = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Execute query
        $result = call_user_func($query_callback);
        
        // Cache the result
        wp_cache_set($cache_key, $result, self::CACHE_GROUP, $expiration);
        
        return $result;
    }
    
    /**
     * Get optimized course enrollments
     *
     * @param int $course_id Course ID
     * @param array $args Query arguments
     * @return array Enrollment data
     */
    public function get_optimized_course_enrollments($course_id, $args = array()) {
        $cache_key = 'course_enrollments_' . $course_id . '_' . md5(serialize($args));
        
        return $this->get_cached_query($cache_key, function() use ($course_id, $args) {
            global $wpdb;
            
            $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
            
            $defaults = array(
                'status' => 'all',
                'limit' => 50,
                'offset' => 0,
                'orderby' => 'enrollment_date',
                'order' => 'DESC'
            );
            
            $args = wp_parse_args($args, $defaults);
            
            // Build WHERE clause
            $where_clauses = array("course_id = %d");
            $where_values = array($course_id);
            
            if ($args['status'] !== 'all') {
                $where_clauses[] = "status = %s";
                $where_values[] = $args['status'];
            }
            
            $where_clause = implode(' AND ', $where_clauses);
            
            // Build ORDER BY clause
            $allowed_orderby = array('enrollment_date', 'completion_date', 'progress_percentage');
            $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'enrollment_date';
            $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
            
            // Build LIMIT clause
            $limit_clause = '';
            if ($args['limit'] > 0) {
                $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
            }
            
            $query = $wpdb->prepare(
                "SELECT e.*, u.display_name, u.user_email 
                FROM $enrollments_table e 
                JOIN {$wpdb->users} u ON e.user_id = u.ID 
                WHERE $where_clause 
                ORDER BY $orderby $order 
                $limit_clause",
                ...$where_values
            );
            
            return $wpdb->get_results($query);
        });
    }
    
    /**
     * Get optimized course ratings
     *
     * @param int $course_id Course ID
     * @param array $args Query arguments
     * @return array Rating data
     */
    public function get_optimized_course_ratings($course_id, $args = array()) {
        $cache_key = 'course_ratings_' . $course_id . '_' . md5(serialize($args));
        
        return $this->get_cached_query($cache_key, function() use ($course_id, $args) {
            global $wpdb;
            
            $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
            
            $defaults = array(
                'status' => 'approved',
                'limit' => 20,
                'offset' => 0,
                'include_stats' => false
            );
            
            $args = wp_parse_args($args, $defaults);
            
            if ($args['include_stats']) {
                // Get rating statistics
                $stats_query = $wpdb->prepare(
                    "SELECT 
                        COUNT(*) as total_ratings,
                        AVG(rating) as average_rating,
                        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                    FROM $ratings_table 
                    WHERE course_id = %d AND status = %s",
                    $course_id,
                    $args['status']
                );
                
                $stats = $wpdb->get_row($stats_query);
                
                // Get individual ratings
                $ratings_query = $wpdb->prepare(
                    "SELECT r.*, u.display_name, u.user_email 
                    FROM $ratings_table r 
                    JOIN {$wpdb->users} u ON r.user_id = u.ID 
                    WHERE r.course_id = %d AND r.status = %s 
                    ORDER BY r.created_date DESC 
                    LIMIT %d OFFSET %d",
                    $course_id,
                    $args['status'],
                    $args['limit'],
                    $args['offset']
                );
                
                $ratings = $wpdb->get_results($ratings_query);
                
                return array(
                    'stats' => $stats,
                    'ratings' => $ratings
                );
            } else {
                // Get only individual ratings
                $query = $wpdb->prepare(
                    "SELECT r.*, u.display_name, u.user_email 
                    FROM $ratings_table r 
                    JOIN {$wpdb->users} u ON r.user_id = u.ID 
                    WHERE r.course_id = %d AND r.status = %s 
                    ORDER BY r.created_date DESC 
                    LIMIT %d OFFSET %d",
                    $course_id,
                    $args['status'],
                    $args['limit'],
                    $args['offset']
                );
                
                return $wpdb->get_results($query);
            }
        });
    }
    
    /**
     * Get optimized user progress
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID (optional)
     * @return array Progress data
     */
    public function get_optimized_user_progress($user_id, $course_id = null) {
        $cache_key = 'user_progress_' . $user_id . '_' . ($course_id ?: 'all');
        
        return $this->get_cached_query($cache_key, function() use ($user_id, $course_id) {
            global $wpdb;
            
            $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
            $progress_table = $wpdb->prefix . 'qlcm_course_progress';
            
            if ($course_id) {
                // Get progress for specific course
                $query = $wpdb->prepare(
                    "SELECT e.*, p.module_id, p.progress_percentage as module_progress, p.completion_date as module_completion
                    FROM $enrollments_table e
                    LEFT JOIN $progress_table p ON e.id = p.enrollment_id
                    WHERE e.user_id = %d AND e.course_id = %d
                    ORDER BY p.completion_date DESC",
                    $user_id,
                    $course_id
                );
            } else {
                // Get progress for all courses
                $query = $wpdb->prepare(
                    "SELECT e.*, p.module_id, p.progress_percentage as module_progress, p.completion_date as module_completion
                    FROM $enrollments_table e
                    LEFT JOIN $progress_table p ON e.id = p.enrollment_id
                    WHERE e.user_id = %d
                    ORDER BY e.enrollment_date DESC, p.completion_date DESC",
                    $user_id
                );
            }
            
            return $wpdb->get_results($query);
        });
    }
    
    /**
     * Clear enrollment cache
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     */
    public function clear_enrollment_cache($user_id = null, $course_id = null) {
        // Clear specific cache entries
        if ($user_id && $course_id) {
            wp_cache_delete('user_progress_' . $user_id . '_' . $course_id, self::CACHE_GROUP);
            wp_cache_delete('user_progress_' . $user_id . '_all', self::CACHE_GROUP);
        }
        
        // Clear course enrollment cache
        if ($course_id) {
            $this->clear_cache_by_pattern('course_enrollments_' . $course_id . '_');
        }
        
        // Clear user progress cache
        if ($user_id) {
            $this->clear_cache_by_pattern('user_progress_' . $user_id . '_');
        }
    }
    
    /**
     * Clear rating cache
     *
     * @param int $course_id Course ID
     */
    public function clear_rating_cache($course_id = null) {
        if ($course_id) {
            $this->clear_cache_by_pattern('course_ratings_' . $course_id . '_');
        }
    }
    
    /**
     * Clear progress cache
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     */
    public function clear_progress_cache($user_id = null, $course_id = null) {
        $this->clear_enrollment_cache($user_id, $course_id);
    }
    
    /**
     * Clear cache entries by pattern
     *
     * @param string $pattern Cache key pattern
     */
    private function clear_cache_by_pattern($pattern) {
        // This is a simplified implementation
        // In production, you might want to use a more sophisticated cache invalidation strategy
        wp_cache_flush_group(self::CACHE_GROUP);
    }
    
    /**
     * Log cleanup activity
     *
     * @param string $activity Activity description
     */
    private function log_cleanup_activity($activity) {
        $logs = get_option('qlcm_optimization_logs', array());
        
        $logs[] = array(
            'activity' => $activity,
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
        
        // Keep only last 100 log entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('qlcm_optimization_logs', $logs);
    }
    
    /**
     * Add optimization admin page
     */
    public function add_optimization_admin_page() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Database Optimization', 'quicklearn-course-manager'),
            __('Optimization', 'quicklearn-course-manager'),
            'manage_options',
            'qlcm-optimization',
            array($this, 'render_optimization_page')
        );
    }
    
    /**
     * Render optimization admin page
     */
    public function render_optimization_page() {
        global $wpdb;
        
        // Get optimization statistics
        $last_optimization = get_option('qlcm_last_optimization', 'Never');
        $logs = get_option('qlcm_optimization_logs', array());
        
        // Get table sizes
        $table_sizes = $this->get_table_sizes();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Database Optimization', 'quicklearn-course-manager'); ?></h1>
            
            <div class="qlcm-optimization-dashboard">
                <div class="qlcm-optimization-stats">
                    <h2><?php _e('Optimization Status', 'quicklearn-course-manager'); ?></h2>
                    
                    <table class="widefat">
                        <tr>
                            <td><strong><?php _e('Last Optimization:', 'quicklearn-course-manager'); ?></strong></td>
                            <td><?php echo esc_html($last_optimization); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Total Log Entries:', 'quicklearn-course-manager'); ?></strong></td>
                            <td><?php echo count($logs); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Cache Status:', 'quicklearn-course-manager'); ?></strong></td>
                            <td><?php echo wp_using_ext_object_cache() ? __('External Cache Active', 'quicklearn-course-manager') : __('WordPress Default Cache', 'quicklearn-course-manager'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="qlcm-table-sizes">
                    <h2><?php _e('Table Sizes', 'quicklearn-course-manager'); ?></h2>
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Table', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Rows', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Size', 'quicklearn-course-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($table_sizes as $table => $data): ?>
                            <tr>
                                <td><?php echo esc_html($table); ?></td>
                                <td><?php echo number_format($data['rows']); ?></td>
                                <td><?php echo esc_html($data['size']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="qlcm-optimization-actions">
                    <h2><?php _e('Optimization Actions', 'quicklearn-course-manager'); ?></h2>
                    
                    <p>
                        <button type="button" class="button button-primary" id="qlcm-optimize-database">
                            <?php _e('Optimize Database Tables', 'quicklearn-course-manager'); ?>
                        </button>
                        <span class="description"><?php _e('Add indexes and optimize table structure for better performance.', 'quicklearn-course-manager'); ?></span>
                    </p>
                    
                    <p>
                        <button type="button" class="button" id="qlcm-cleanup-data">
                            <?php _e('Clean Up Old Data', 'quicklearn-course-manager'); ?>
                        </button>
                        <span class="description"><?php _e('Remove orphaned records and old analytics data.', 'quicklearn-course-manager'); ?></span>
                    </p>
                    
                    <p>
                        <button type="button" class="button" id="qlcm-clear-cache">
                            <?php _e('Clear Query Cache', 'quicklearn-course-manager'); ?>
                        </button>
                        <span class="description"><?php _e('Clear all cached query results.', 'quicklearn-course-manager'); ?></span>
                    </p>
                </div>
                
                <div class="qlcm-optimization-logs">
                    <h2><?php _e('Recent Activity', 'quicklearn-course-manager'); ?></h2>
                    
                    <?php if (empty($logs)): ?>
                        <p><?php _e('No optimization activity logged yet.', 'quicklearn-course-manager'); ?></p>
                    <?php else: ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Activity', 'quicklearn-course-manager'); ?></th>
                                    <th><?php _e('Timestamp', 'quicklearn-course-manager'); ?></th>
                                    <th><?php _e('Memory Usage', 'quicklearn-course-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse(array_slice($logs, -10)) as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log['activity']); ?></td>
                                    <td><?php echo esc_html($log['timestamp']); ?></td>
                                    <td><?php echo esc_html(size_format($log['memory_usage'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#qlcm-optimize-database').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Optimizing...', 'quicklearn-course-manager'); ?>');
                
                $.post(ajaxurl, {
                    action: 'qlcm_optimize_database',
                    nonce: '<?php echo wp_create_nonce('qlcm_optimization'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Database optimization completed successfully.', 'quicklearn-course-manager'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Optimization failed: ', 'quicklearn-course-manager'); ?>' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php _e('Optimize Database Tables', 'quicklearn-course-manager'); ?>');
                });
            });
            
            $('#qlcm-cleanup-data').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Cleaning...', 'quicklearn-course-manager'); ?>');
                
                $.post(ajaxurl, {
                    action: 'qlcm_cleanup_old_data',
                    nonce: '<?php echo wp_create_nonce('qlcm_optimization'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Data cleanup completed successfully.', 'quicklearn-course-manager'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Cleanup failed: ', 'quicklearn-course-manager'); ?>' + response.data.message);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('<?php _e('Clean Up Old Data', 'quicklearn-course-manager'); ?>');
                });
            });
            
            $('#qlcm-clear-cache').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Clearing...', 'quicklearn-course-manager'); ?>');
                
                // Clear cache by flushing the cache group
                wp_cache_flush_group('<?php echo self::CACHE_GROUP; ?>');
                
                setTimeout(function() {
                    alert('<?php _e('Query cache cleared successfully.', 'quicklearn-course-manager'); ?>');
                    button.prop('disabled', false).text('<?php _e('Clear Query Cache', 'quicklearn-course-manager'); ?>');
                }, 1000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get table sizes
     *
     * @return array Table size information
     */
    private function get_table_sizes() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'qlcm_enrollments',
            $wpdb->prefix . 'qlcm_course_progress',
            $wpdb->prefix . 'qlcm_course_ratings',
            $wpdb->prefix . 'qlcm_certificates',
            $wpdb->prefix . 'qlcm_analytics'
        );
        
        $sizes = array();
        
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $result = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        table_rows as rows,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = %s AND table_name = %s",
                    DB_NAME,
                    $table
                ));
                
                if ($result) {
                    $sizes[str_replace($wpdb->prefix, '', $table)] = array(
                        'rows' => $result->rows ?: 0,
                        'size' => $result->size_mb ? $result->size_mb . ' MB' : '< 1 MB'
                    );
                }
            }
        }
        
        return $sizes;
    }
    
    /**
     * Handle database optimization AJAX request
     */
    public function handle_database_optimization() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'qlcm_optimization')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn-course-manager')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'quicklearn-course-manager')));
        }
        
        try {
            $this->optimize_database_tables();
            $this->log_cleanup_activity('manual_optimization_completed');
            
            wp_send_json_success(array('message' => __('Database optimization completed', 'quicklearn-course-manager')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Handle data cleanup AJAX request
     */
    public function handle_data_cleanup() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'qlcm_optimization')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn-course-manager')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'quicklearn-course-manager')));
        }
        
        try {
            $this->cleanup_expired_transients();
            $this->cleanup_old_analytics_data(90);
            $this->cleanup_orphaned_progress_records();
            $this->cleanup_orphaned_ratings();
            $this->cleanup_orphaned_certificates();
            $this->log_cleanup_activity('manual_cleanup_completed');
            
            wp_send_json_success(array('message' => __('Data cleanup completed', 'quicklearn-course-manager')));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}

// Initialize database optimization
QLCM_Database_Optimization::get_instance();