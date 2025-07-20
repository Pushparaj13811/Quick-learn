<?php
/**
 * User Enrollment and Course Progress Tracking
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling user enrollment and course progress
 */
class QLCM_User_Enrollment {
    
    /**
     * Instance of this class
     *
     * @var QLCM_User_Enrollment
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_User_Enrollment Instance of this class
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
        // Create database tables on plugin activation
        register_activation_hook(QLCM_PLUGIN_FILE, array($this, 'create_database_tables'));
        
        // Add enrollment button to course page
        add_action('quicklearn_after_course_content', array($this, 'add_enrollment_button'));
        
        // Handle enrollment AJAX
        add_action('wp_ajax_enroll_in_course', array($this, 'handle_course_enrollment'));
        add_action('wp_ajax_nopriv_enroll_in_course', array($this, 'handle_guest_enrollment'));
        
        // Add user dashboard shortcode
        add_shortcode('quicklearn_dashboard', array($this, 'user_dashboard_shortcode'));
        
        // Add progress tracking
        add_action('wp_ajax_update_course_progress', array($this, 'update_course_progress'));
        
        // Add enrollment meta box to course edit screen
        add_action('add_meta_boxes', array($this, 'add_enrollment_meta_box'));
        
        // Add user profile section for enrolled courses
        add_action('show_user_profile', array($this, 'add_enrolled_courses_section'));
        add_action('edit_user_profile', array($this, 'add_enrolled_courses_section'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Create database tables for enrollment and progress tracking
     */
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for course enrollments
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        $sql = "CREATE TABLE $enrollments_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            enrollment_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            completion_date datetime DEFAULT NULL,
            progress_percentage int(3) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status),
            UNIQUE KEY user_course (user_id,course_id)
        ) $charset_collate;";
        
        // Table for course progress
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        
        $sql .= "CREATE TABLE $progress_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            enrollment_id bigint(20) NOT NULL,
            module_id varchar(50) NOT NULL,
            completion_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            progress_percentage int(3) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY enrollment_id (enrollment_id),
            KEY module_id (module_id),
            UNIQUE KEY enrollment_module (enrollment_id,module_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add database version option
        add_option('qlcm_db_version', '1.0');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on course pages or user dashboard
        if (is_singular('quick_course') || has_shortcode(get_post()->post_content ?? '', 'quicklearn_dashboard')) {
            wp_enqueue_style(
                'qlcm-enrollment',
                QLCM_PLUGIN_URL . 'assets/css/enrollment.css',
                array(),
                QLCM_VERSION
            );
            
            wp_enqueue_script(
                'qlcm-enrollment',
                QLCM_PLUGIN_URL . 'assets/js/enrollment.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_localize_script('qlcm-enrollment', 'qlcm_enrollment', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_enrollment_nonce'),
                'i18n' => array(
                    'enrolling' => __('Enrolling...', 'quicklearn-course-manager'),
                    'enrolled' => __('Enrolled', 'quicklearn-course-manager'),
                    'continue' => __('Continue Learning', 'quicklearn-course-manager'),
                    'complete' => __('Completed', 'quicklearn-course-manager'),
                    'login_required' => __('Please log in to enroll', 'quicklearn-course-manager'),
                    'error' => __('Error occurred. Please try again.', 'quicklearn-course-manager'),
                )
            ));
        }
    }
    
    /**
     * Add enrollment button to course page
     */
    public function add_enrollment_button() {
        if (!is_singular('quick_course')) {
            return;
        }
        
        $course_id = get_the_ID();
        $user_id = get_current_user_id();
        $enrollment_status = $this->get_enrollment_status($user_id, $course_id);
        $progress = $this->get_course_progress($user_id, $course_id);
        
        echo '<div class="qlcm-enrollment-container">';
        
        if (!$user_id) {
            // Not logged in
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="qlcm-button qlcm-login-required">';
            echo __('Log in to Enroll', 'quicklearn-course-manager');
            echo '</a>';
        } elseif (!$enrollment_status) {
            // Not enrolled
            echo '<button class="qlcm-button qlcm-enroll-button" data-course-id="' . esc_attr($course_id) . '">';
            echo __('Enroll Now', 'quicklearn-course-manager');
            echo '</button>';
        } elseif ($enrollment_status === 'completed') {
            // Completed
            echo '<div class="qlcm-enrollment-status qlcm-completed">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ';
            echo __('Course Completed', 'quicklearn-course-manager');
            echo '</div>';
            echo '<a href="' . esc_url(get_permalink($course_id)) . '" class="qlcm-button qlcm-review-button">';
            echo __('Review Course', 'quicklearn-course-manager');
            echo '</a>';
        } else {
            // Enrolled, in progress
            echo '<div class="qlcm-enrollment-status qlcm-in-progress">';
            echo '<div class="qlcm-progress-bar">';
            echo '<div class="qlcm-progress-fill" style="width:' . esc_attr($progress) . '%"></div>';
            echo '</div>';
            echo '<span class="qlcm-progress-text">' . esc_html($progress) . '% ' . __('Complete', 'quicklearn-course-manager') . '</span>';
            echo '</div>';
            echo '<a href="' . esc_url(get_permalink($course_id)) . '#course-content" class="qlcm-button qlcm-continue-button">';
            echo __('Continue Learning', 'quicklearn-course-manager');
            echo '</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Handle course enrollment AJAX request
     */
    public function handle_course_enrollment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'qlcm_enrollment_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn-course-manager')));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('You must be logged in to enroll', 'quicklearn-course-manager'),
                'redirect' => wp_login_url()
            ));
        }
        
        // Get course ID
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        
        // Validate course
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error(array('message' => __('Invalid course', 'quicklearn-course-manager')));
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Check if already enrolled
        if ($this->get_enrollment_status($user_id, $course_id)) {
            wp_send_json_error(array('message' => __('Already enrolled in this course', 'quicklearn-course-manager')));
        }
        
        // Enroll user
        $enrollment_id = $this->enroll_user($user_id, $course_id);
        
        if ($enrollment_id) {
            wp_send_json_success(array(
                'message' => __('Successfully enrolled', 'quicklearn-course-manager'),
                'enrollment_id' => $enrollment_id,
                'progress' => 0
            ));
        } else {
            wp_send_json_error(array('message' => __('Enrollment failed', 'quicklearn-course-manager')));
        }
    }
    
    /**
     * Handle guest enrollment (redirect to login/registration)
     */
    public function handle_guest_enrollment() {
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $redirect = $course_id ? get_permalink($course_id) : home_url();
        
        wp_send_json_error(array(
            'message' => __('Please log in or register to enroll in this course', 'quicklearn-course-manager'),
            'redirect' => wp_login_url($redirect)
        ));
    }
    
    /**
     * Enroll a user in a course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return int|false Enrollment ID or false on failure
     */
    public function enroll_user($user_id, $course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_enrollments';
        
        // Check if already enrolled
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Insert new enrollment
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'enrollment_date' => current_time('mysql'),
                'status' => 'active',
                'progress_percentage' => 0
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result) {
            $enrollment_id = $wpdb->insert_id;
            
            // Trigger action for other plugins
            do_action('qlcm_user_enrolled', $user_id, $course_id, $enrollment_id);
            
            return $enrollment_id;
        }
        
        return false;
    }
    
    /**
     * Get enrollment status for a user and course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return string|false Status or false if not enrolled
     */
    public function get_enrollment_status($user_id, $course_id) {
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_enrollments';
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        return $status;
    }
    
    /**
     * Get course progress percentage
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return int Progress percentage (0-100)
     */
    public function get_course_progress($user_id, $course_id) {
        if (!$user_id) {
            return 0;
        }
        
        global $wpdb;
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        $progress = $wpdb->get_var($wpdb->prepare(
            "SELECT progress_percentage FROM $enrollments_table WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        return $progress ? (int) $progress : 0;
    }
    
    /**
     * Update course progress
     */
    public function update_course_progress() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'qlcm_enrollment_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn-course-manager')));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in', 'quicklearn-course-manager')));
        }
        
        // Get parameters
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        $progress = isset($_POST['progress']) ? absint($_POST['progress']) : 0;
        
        // Validate
        if (!$course_id || !$module_id || $progress < 0 || $progress > 100) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'quicklearn-course-manager')));
        }
        
        $user_id = get_current_user_id();
        
        // Get enrollment
        global $wpdb;
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        
        $enrollment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $enrollments_table WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        if (!$enrollment_id) {
            // Auto-enroll if not already enrolled
            $enrollment_id = $this->enroll_user($user_id, $course_id);
            
            if (!$enrollment_id) {
                wp_send_json_error(array('message' => __('Enrollment failed', 'quicklearn-course-manager')));
            }
        }
        
        // Update progress
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $progress_table WHERE enrollment_id = %d AND module_id = %s",
            $enrollment_id,
            $module_id
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $progress_table,
                array(
                    'progress_percentage' => $progress,
                    'completion_date' => current_time('mysql')
                ),
                array('id' => $existing),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $progress_table,
                array(
                    'enrollment_id' => $enrollment_id,
                    'module_id' => $module_id,
                    'progress_percentage' => $progress,
                    'completion_date' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s')
            );
        }
        
        // Calculate overall course progress
        $overall_progress = $this->calculate_overall_progress($enrollment_id);
        
        // Update enrollment progress
        $wpdb->update(
            $enrollments_table,
            array('progress_percentage' => $overall_progress),
            array('id' => $enrollment_id),
            array('%d'),
            array('%d')
        );
        
        // Update enrollment status if completed
        if ($overall_progress >= 100) {
            $wpdb->update(
                $enrollments_table,
                array(
                    'status' => 'completed',
                    'completion_date' => current_time('mysql')
                ),
                array('id' => $enrollment_id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Trigger course completion action for certificate generation
            do_action('qlcm_course_completed', $user_id, $course_id);
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Progress updated', 'quicklearn-course-manager'),
                'progress' => $overall_progress
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update progress', 'quicklearn-course-manager')));
        }
    }
    
    /**
     * Calculate overall course progress
     *
     * @param int $enrollment_id Enrollment ID
     * @return int Overall progress percentage
     */
    private function calculate_overall_progress($enrollment_id) {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        
        // Get all module progress entries
        $modules = $wpdb->get_results($wpdb->prepare(
            "SELECT module_id, MAX(progress_percentage) as progress 
            FROM $progress_table 
            WHERE enrollment_id = %d 
            GROUP BY module_id",
            $enrollment_id
        ));
        
        if (!$modules) {
            return 0;
        }
        
        // Calculate average progress
        $total_progress = 0;
        foreach ($modules as $module) {
            $total_progress += $module->progress;
        }
        
        $overall_progress = round($total_progress / count($modules));
        
        return min(100, $overall_progress); // Cap at 100%
    }
    
    /**
     * User dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function user_dashboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_completed' => 'yes',
        ), $atts, 'quicklearn_dashboard');
        
        if (!is_user_logged_in()) {
            return '<div class="qlcm-login-required">' . 
                   '<p>' . __('Please log in to view your enrolled courses.', 'quicklearn-course-manager') . '</p>' .
                   '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="qlcm-button">' .
                   __('Log In', 'quicklearn-course-manager') .
                   '</a></div>';
        }
        
        $user_id = get_current_user_id();
        $enrolled_courses = $this->get_user_enrolled_courses($user_id, $atts['show_completed'] === 'yes');
        
        ob_start();
        
        echo '<div class="qlcm-user-dashboard">';
        
        if (empty($enrolled_courses)) {
            echo '<div class="qlcm-no-courses">';
            echo '<p>' . __('You are not enrolled in any courses yet.', 'quicklearn-course-manager') . '</p>';
            echo '<a href="' . esc_url(get_post_type_archive_link('quick_course')) . '" class="qlcm-button">';
            echo __('Browse Courses', 'quicklearn-course-manager');
            echo '</a>';
            echo '</div>';
        } else {
            echo '<div class="qlcm-enrolled-courses">';
            echo '<h2>' . __('My Courses', 'quicklearn-course-manager') . '</h2>';
            
            echo '<div class="qlcm-course-grid">';
            foreach ($enrolled_courses as $course) {
                echo '<div class="qlcm-course-card">';
                
                // Featured image
                if (has_post_thumbnail($course->course_id)) {
                    echo '<div class="qlcm-course-image">';
                    echo '<a href="' . esc_url(get_permalink($course->course_id)) . '">';
                    echo get_the_post_thumbnail($course->course_id, 'medium');
                    echo '</a>';
                    echo '</div>';
                }
                
                echo '<div class="qlcm-course-content">';
                
                // Title
                echo '<h3 class="qlcm-course-title">';
                echo '<a href="' . esc_url(get_permalink($course->course_id)) . '">';
                echo esc_html(get_the_title($course->course_id));
                echo '</a>';
                echo '</h3>';
                
                // Progress bar
                echo '<div class="qlcm-progress-bar">';
                echo '<div class="qlcm-progress-fill" style="width:' . esc_attr($course->progress_percentage) . '%"></div>';
                echo '</div>';
                echo '<span class="qlcm-progress-text">' . esc_html($course->progress_percentage) . '% ' . __('Complete', 'quicklearn-course-manager') . '</span>';
                
                // Status and action button
                echo '<div class="qlcm-course-status">';
                if ($course->status === 'completed') {
                    echo '<span class="qlcm-status-completed">';
                    echo '<span class="dashicons dashicons-yes-alt"></span> ';
                    echo __('Completed', 'quicklearn-course-manager');
                    echo '</span>';
                    echo '<a href="' . esc_url(get_permalink($course->course_id)) . '" class="qlcm-button qlcm-review-button">';
                    echo __('Review', 'quicklearn-course-manager');
                    echo '</a>';
                } else {
                    echo '<span class="qlcm-status-active">';
                    echo '<span class="dashicons dashicons-clock"></span> ';
                    echo __('In Progress', 'quicklearn-course-manager');
                    echo '</span>';
                    echo '<a href="' . esc_url(get_permalink($course->course_id)) . '" class="qlcm-button qlcm-continue-button">';
                    echo __('Continue', 'quicklearn-course-manager');
                    echo '</a>';
                }
                echo '</div>'; // .qlcm-course-status
                
                echo '</div>'; // .qlcm-course-content
                echo '</div>'; // .qlcm-course-card
            }
            echo '</div>'; // .qlcm-course-grid
            echo '</div>'; // .qlcm-enrolled-courses
        }
        
        // Add certificates section
        do_action('qlcm_user_dashboard_after_courses');
        
        echo '</div>'; // .qlcm-user-dashboard
        
        return ob_get_clean();
    }
    
    /**
     * Get user enrolled courses
     *
     * @param int $user_id User ID
     * @param bool $include_completed Whether to include completed courses
     * @return array Array of enrolled courses with progress info
     */
    public function get_user_enrolled_courses($user_id, $include_completed = true) {
        global $wpdb;
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        $status_clause = $include_completed ? "" : "AND status != 'completed'";
        
        $query = $wpdb->prepare(
            "SELECT * FROM $enrollments_table 
            WHERE user_id = %d $status_clause
            ORDER BY enrollment_date DESC",
            $user_id
        );
        
        $courses = $wpdb->get_results($query);
        
        // Filter out courses that no longer exist
        return array_filter($courses, function($course) {
            return get_post_type($course->course_id) === 'quick_course';
        });
    }
    
    /**
     * Add enrollment meta box to course edit screen
     */
    public function add_enrollment_meta_box() {
        add_meta_box(
            'qlcm_course_enrollments',
            __('Course Enrollments', 'quicklearn-course-manager'),
            array($this, 'render_enrollment_meta_box'),
            'quick_course',
            'side',
            'default'
        );
    }
    
    /**
     * Render enrollment meta box
     *
     * @param WP_Post $post Current post object
     */
    public function render_enrollment_meta_box($post) {
        $course_id = $post->ID;
        $enrollments = $this->get_course_enrollments($course_id);
        $total = count($enrollments);
        $completed = count(array_filter($enrollments, function($e) {
            return $e->status === 'completed';
        }));
        
        echo '<div class="qlcm-enrollment-stats">';
        echo '<p><strong>' . __('Total Enrollments:', 'quicklearn-course-manager') . '</strong> ' . $total . '</p>';
        echo '<p><strong>' . __('Completed:', 'quicklearn-course-manager') . '</strong> ' . $completed . '</p>';
        echo '<p><strong>' . __('In Progress:', 'quicklearn-course-manager') . '</strong> ' . ($total - $completed) . '</p>';
        echo '</div>';
        
        if ($total > 0) {
            echo '<p><a href="' . admin_url('edit.php?post_type=quick_course&page=course-enrollments&course_id=' . $course_id) . '" class="button">';
            echo __('View Enrolled Users', 'quicklearn-course-manager');
            echo '</a></p>';
        }
    }
    
    /**
     * Get course enrollments
     *
     * @param int $course_id Course ID
     * @return array Array of enrollment objects
     */
    public function get_course_enrollments($course_id) {
        global $wpdb;
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        $query = $wpdb->prepare(
            "SELECT e.*, u.display_name as user_name
            FROM $enrollments_table e
            JOIN {$wpdb->users} u ON e.user_id = u.ID
            WHERE e.course_id = %d
            ORDER BY e.enrollment_date DESC",
            $course_id
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Add enrolled courses section to user profile
     *
     * @param WP_User $user User object
     */
    public function add_enrolled_courses_section($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $enrolled_courses = $this->get_user_enrolled_courses($user->ID, true);
        
        echo '<h2>' . __('Enrolled Courses', 'quicklearn-course-manager') . '</h2>';
        
        if (empty($enrolled_courses)) {
            echo '<p>' . __('This user is not enrolled in any courses.', 'quicklearn-course-manager') . '</p>';
            return;
        }
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th>' . __('Course', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Status', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Progress', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Enrollment Date', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Completion Date', 'quicklearn-course-manager') . '</th>';
        echo '</tr>';
        
        foreach ($enrolled_courses as $course) {
            echo '<tr>';
            
            // Course title with link
            echo '<td>';
            echo '<a href="' . esc_url(get_permalink($course->course_id)) . '" target="_blank">';
            echo esc_html(get_the_title($course->course_id));
            echo '</a>';
            echo '</td>';
            
            // Status
            echo '<td>';
            if ($course->status === 'completed') {
                echo '<span class="qlcm-status-completed">' . __('Completed', 'quicklearn-course-manager') . '</span>';
            } else {
                echo '<span class="qlcm-status-active">' . __('In Progress', 'quicklearn-course-manager') . '</span>';
            }
            echo '</td>';
            
            // Progress
            echo '<td>';
            echo '<div class="qlcm-progress-bar" style="width:100px;">';
            echo '<div class="qlcm-progress-fill" style="width:' . esc_attr($course->progress_percentage) . '%"></div>';
            echo '</div>';
            echo '<span class="qlcm-progress-text">' . esc_html($course->progress_percentage) . '%</span>';
            echo '</td>';
            
            // Enrollment date
            echo '<td>' . date_i18n(get_option('date_format'), strtotime($course->enrollment_date)) . '</td>';
            
            // Completion date
            echo '<td>';
            if ($course->completion_date) {
                echo date_i18n(get_option('date_format'), strtotime($course->completion_date));
            } else {
                echo '—';
            }
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</table>';
    }
}

// Initialize user enrollment
QLCM_User_Enrollment::get_instance();