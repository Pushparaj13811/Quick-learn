<?php
/**
 * Admin Pages for QuickLearn Course Manager
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling admin pages
 */
class QLCM_Admin_Pages {
    
    /**
     * Instance of this class
     *
     * @var QLCM_Admin_Pages
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_Admin_Pages Instance of this class
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
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin pages
     */
    public function add_admin_pages() {
        // Main settings page
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Course Settings', 'quicklearn-course-manager'),
            __('Settings', 'quicklearn-course-manager'),
            'manage_options',
            'course-settings',
            array($this, 'render_settings_page')
        );
        
        // Enrollments page
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Course Enrollments', 'quicklearn-course-manager'),
            __('Enrollments', 'quicklearn-course-manager'),
            'manage_options',
            'course-enrollments',
            array($this, 'render_enrollments_page')
        );
        
        // Ratings page
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Course Ratings', 'quicklearn-course-manager'),
            __('Ratings', 'quicklearn-course-manager'),
            'manage_options',
            'course-ratings',
            array($this, 'render_ratings_page')
        );
        
        // Analytics page
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Course Analytics', 'quicklearn-course-manager'),
            __('Analytics', 'quicklearn-course-manager'),
            'manage_options',
            'course-analytics',
            array($this, 'render_analytics_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'quick_course') === false) {
            return;
        }
        
        wp_enqueue_style(
            'qlcm-admin',
            QLCM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            QLCM_VERSION
        );
        
        wp_enqueue_script(
            'qlcm-admin',
            QLCM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            QLCM_VERSION,
            true
        );
        
        wp_localize_script('qlcm-admin', 'qlcm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qlcm_admin_nonce'),
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = get_option('qlcm_settings', array());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Course Settings', 'quicklearn-course-manager'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('qlcm_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="courses_per_page"><?php esc_html_e('Courses Per Page', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="courses_per_page" name="courses_per_page" 
                                   value="<?php echo esc_attr($settings['courses_per_page'] ?? 12); ?>" 
                                   min="1" max="50" />
                            <p class="description"><?php esc_html_e('Number of courses to display per page (1-50)', 'quicklearn-course-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_ajax_filtering"><?php esc_html_e('Enable AJAX Filtering', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_ajax_filtering" name="enable_ajax_filtering" value="1" 
                                   <?php checked($settings['enable_ajax_filtering'] ?? true); ?> />
                            <p class="description"><?php esc_html_e('Enable dynamic course filtering without page refresh', 'quicklearn-course-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="show_course_excerpts"><?php esc_html_e('Show Course Excerpts', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="show_course_excerpts" name="show_course_excerpts" value="1" 
                                   <?php checked($settings['show_course_excerpts'] ?? true); ?> />
                            <p class="description"><?php esc_html_e('Display course excerpts on course listing pages', 'quicklearn-course-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="course_image_size"><?php esc_html_e('Course Image Size', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <select id="course_image_size" name="course_image_size">
                                <option value="thumbnail" <?php selected($settings['course_image_size'] ?? 'medium', 'thumbnail'); ?>><?php esc_html_e('Thumbnail', 'quicklearn-course-manager'); ?></option>
                                <option value="medium" <?php selected($settings['course_image_size'] ?? 'medium', 'medium'); ?>><?php esc_html_e('Medium', 'quicklearn-course-manager'); ?></option>
                                <option value="large" <?php selected($settings['course_image_size'] ?? 'medium', 'large'); ?>><?php esc_html_e('Large', 'quicklearn-course-manager'); ?></option>
                                <option value="full" <?php selected($settings['course_image_size'] ?? 'medium', 'full'); ?>><?php esc_html_e('Full Size', 'quicklearn-course-manager'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Default image size for course thumbnails', 'quicklearn-course-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'qlcm_settings_nonce')) {
            wp_die(__('Security check failed', 'quicklearn-course-manager'));
        }
        
        $settings = array(
            'courses_per_page' => absint($_POST['courses_per_page'] ?? 12),
            'enable_ajax_filtering' => isset($_POST['enable_ajax_filtering']),
            'show_course_excerpts' => isset($_POST['show_course_excerpts']),
            'course_image_size' => sanitize_key($_POST['course_image_size'] ?? 'medium')
        );
        
        // Validate settings
        if ($settings['courses_per_page'] < 1 || $settings['courses_per_page'] > 50) {
            $settings['courses_per_page'] = 12;
        }
        
        $allowed_sizes = array('thumbnail', 'medium', 'large', 'full');
        if (!in_array($settings['course_image_size'], $allowed_sizes)) {
            $settings['course_image_size'] = 'medium';
        }
        
        update_option('qlcm_settings', $settings);
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'quicklearn-course-manager') . '</p></div>';
    }
    
    /**
     * Render enrollments page
     */
    public function render_enrollments_page() {
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        
        // Get enrollments with user and course info
        $enrollments = $wpdb->get_results("
            SELECT e.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $enrollments_table e
            JOIN {$wpdb->users} u ON e.user_id = u.ID
            JOIN {$wpdb->posts} p ON e.course_id = p.ID
            WHERE p.post_status = 'publish'
            ORDER BY e.enrollment_date DESC
            LIMIT 100
        ");
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Course Enrollments', 'quicklearn-course-manager'); ?></h1>
            
            <?php if (empty($enrollments)) : ?>
                <p><?php esc_html_e('No enrollments found.', 'quicklearn-course-manager'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('User', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Status', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Enrolled', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Completed', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Progress', 'quicklearn-course-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment) : 
                            $progress = $this->get_enrollment_progress($enrollment->id);
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($enrollment->display_name); ?></strong><br>
                                    <small><?php echo esc_html($enrollment->user_email); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(get_permalink($enrollment->course_id)); ?>" target="_blank">
                                        <?php echo esc_html($enrollment->course_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-<?php echo esc_attr($enrollment->status); ?>">
                                        <?php echo esc_html(ucfirst($enrollment->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment->enrollment_date))); ?></td>
                                <td>
                                    <?php if ($enrollment->completion_date) : ?>
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($enrollment->completion_date))); ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                                    </div>
                                    <span class="progress-text"><?php echo esc_html($progress); ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render ratings page
     */
    public function render_ratings_page() {
        global $wpdb;
        
        $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
        
        // Get ratings with user and course info
        $ratings = $wpdb->get_results("
            SELECT r.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $ratings_table r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            JOIN {$wpdb->posts} p ON r.course_id = p.ID
            WHERE p.post_status = 'publish'
            ORDER BY r.created_date DESC
            LIMIT 100
        ");
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Course Ratings', 'quicklearn-course-manager'); ?></h1>
            
            <?php if (empty($ratings)) : ?>
                <p><?php esc_html_e('No ratings found.', 'quicklearn-course-manager'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('User', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Rating', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Review', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Date', 'quicklearn-course-manager'); ?></th>
                            <th><?php esc_html_e('Status', 'quicklearn-course-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings as $rating) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($rating->display_name); ?></strong><br>
                                    <small><?php echo esc_html($rating->user_email); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(get_permalink($rating->course_id)); ?>" target="_blank">
                                        <?php echo esc_html($rating->course_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                                            <span class="star <?php echo $i <= $rating->rating ? 'filled' : 'empty'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-number"><?php echo esc_html($rating->rating); ?>/5</span>
                                </td>
                                <td>
                                    <?php if ($rating->review_title) : ?>
                                        <strong><?php echo esc_html($rating->review_title); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if ($rating->review_content) : ?>
                                        <div class="review-content">
                                            <?php echo esc_html(wp_trim_words($rating->review_content, 20)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($rating->created_date))); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($rating->status); ?>">
                                        <?php echo esc_html(ucfirst($rating->status)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        global $wpdb;
        
        // Get analytics data
        $total_courses = wp_count_posts('quick_course')->publish;
        $total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}qlcm_enrollments");
        $completed_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}qlcm_enrollments WHERE status = 'completed'");
        $total_ratings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}qlcm_course_ratings WHERE status = 'approved'");
        $average_rating = $wpdb->get_var("SELECT AVG(rating) FROM {$wpdb->prefix}qlcm_course_ratings WHERE status = 'approved'");
        
        // Get popular courses
        $popular_courses = $wpdb->get_results("
            SELECT p.post_title, COUNT(e.id) as enrollment_count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}qlcm_enrollments e ON p.ID = e.course_id
            WHERE p.post_type = 'quick_course' AND p.post_status = 'publish'
            GROUP BY p.ID
            ORDER BY enrollment_count DESC
            LIMIT 10
        ");
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Course Analytics', 'quicklearn-course-manager'); ?></h1>
            
            <div class="qlcm-analytics-grid">
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Total Courses', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($total_courses); ?></div>
                </div>
                
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Total Enrollments', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($total_enrollments); ?></div>
                </div>
                
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Completed Courses', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($completed_enrollments); ?></div>
                    <?php if ($total_enrollments > 0) : ?>
                        <div class="stat-percentage"><?php echo esc_html(round(($completed_enrollments / $total_enrollments) * 100, 1)); ?>% completion rate</div>
                    <?php endif; ?>
                </div>
                
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Average Rating', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo $average_rating ? esc_html(number_format($average_rating, 1)) : '—'; ?></div>
                    <?php if ($total_ratings > 0) : ?>
                        <div class="stat-percentage"><?php echo esc_html($total_ratings); ?> reviews</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($popular_courses)) : ?>
                <div class="qlcm-popular-courses">
                    <h2><?php esc_html_e('Most Popular Courses', 'quicklearn-course-manager'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Enrollments', 'quicklearn-course-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($popular_courses as $course) : ?>
                                <tr>
                                    <td><?php echo esc_html($course->post_title); ?></td>
                                    <td><?php echo esc_html($course->enrollment_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get enrollment progress
     */
    private function get_enrollment_progress($enrollment_id) {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        
        $progress = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(progress_percentage) FROM $progress_table WHERE enrollment_id = %d",
            $enrollment_id
        ));
        
        return $progress ? (int) $progress : 0;
    }
}

// Initialize admin pages
QLCM_Admin_Pages::get_instance();