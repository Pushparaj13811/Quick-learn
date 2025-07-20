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
        
        // Forum Management page
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Forum Management', 'quicklearn-course-manager'),
            __('Forums', 'quicklearn-course-manager'),
            'manage_options',
            'course-forums',
            array($this, 'render_forums_page')
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
     * Render forums management page
     */
    public function render_forums_page() {
        global $wpdb;
        
        // Handle form submissions
        if (isset($_POST['action'])) {
            $this->handle_forum_actions();
        }
        
        $forum_posts_table = $wpdb->prefix . 'qlcm_forum_posts';
        $forum_moderators_table = $wpdb->prefix . 'qlcm_forum_moderators';
        
        // Get recent forum posts
        $recent_posts = $wpdb->get_results("
            SELECT fp.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $forum_posts_table fp
            JOIN {$wpdb->users} u ON fp.user_id = u.ID
            JOIN {$wpdb->posts} p ON fp.course_id = p.ID
            WHERE p.post_status = 'publish' AND fp.parent_id = 0
            ORDER BY fp.created_date DESC
            LIMIT 20
        ");
        
        // Get forum statistics
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $forum_posts_table WHERE parent_id = 0");
        $total_replies = $wpdb->get_var("SELECT COUNT(*) FROM $forum_posts_table WHERE parent_id > 0");
        $hidden_posts = $wpdb->get_var("SELECT COUNT(*) FROM $forum_posts_table WHERE status = 'hidden'");
        
        // Get courses for moderator assignment
        $courses = get_posts(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Forum Management', 'quicklearn-course-manager'); ?></h1>
            
            <!-- Forum Statistics -->
            <div class="qlcm-analytics-grid">
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Total Discussions', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($total_posts); ?></div>
                </div>
                
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Total Replies', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($total_replies); ?></div>
                </div>
                
                <div class="qlcm-stat-card">
                    <h3><?php esc_html_e('Hidden Posts', 'quicklearn-course-manager'); ?></h3>
                    <div class="stat-number"><?php echo esc_html($hidden_posts); ?></div>
                </div>
            </div>
            
            <!-- Moderator Management -->
            <div class="qlcm-forum-section">
                <h2><?php esc_html_e('Assign Forum Moderators', 'quicklearn-course-manager'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('qlcm_forum_moderator_nonce'); ?>
                    <input type="hidden" name="action" value="assign_moderator" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="course_id"><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></label>
                            </th>
                            <td>
                                <select id="course_id" name="course_id" required>
                                    <option value=""><?php esc_html_e('Select a course...', 'quicklearn-course-manager'); ?></option>
                                    <?php foreach ($courses as $course) : ?>
                                        <option value="<?php echo esc_attr($course->ID); ?>">
                                            <?php echo esc_html($course->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="user_search"><?php esc_html_e('User', 'quicklearn-course-manager'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="user_search" name="user_search" placeholder="<?php esc_attr_e('Search for user...', 'quicklearn-course-manager'); ?>" />
                                <input type="hidden" id="user_id" name="user_id" />
                                <div id="user_search_results"></div>
                                <p class="description"><?php esc_html_e('Start typing to search for users', 'quicklearn-course-manager'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Assign Moderator', 'quicklearn-course-manager')); ?>
                </form>
            </div>
            
            <!-- Current Moderators -->
            <div class="qlcm-forum-section">
                <h2><?php esc_html_e('Current Moderators', 'quicklearn-course-manager'); ?></h2>
                <?php
                $moderators = $wpdb->get_results("
                    SELECT fm.*, u.display_name, u.user_email, p.post_title as course_title
                    FROM $forum_moderators_table fm
                    JOIN {$wpdb->users} u ON fm.user_id = u.ID
                    JOIN {$wpdb->posts} p ON fm.course_id = p.ID
                    WHERE p.post_status = 'publish'
                    ORDER BY p.post_title, u.display_name
                ");
                
                if (empty($moderators)) : ?>
                    <p><?php esc_html_e('No moderators assigned yet.', 'quicklearn-course-manager'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Moderator', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Assigned Date', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Actions', 'quicklearn-course-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moderators as $moderator) : ?>
                                <tr>
                                    <td><?php echo esc_html($moderator->course_title); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($moderator->display_name); ?></strong><br>
                                        <small><?php echo esc_html($moderator->user_email); ?></small>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($moderator->assigned_date))); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('qlcm_forum_moderator_nonce'); ?>
                                            <input type="hidden" name="action" value="remove_moderator" />
                                            <input type="hidden" name="course_id" value="<?php echo esc_attr($moderator->course_id); ?>" />
                                            <input type="hidden" name="user_id" value="<?php echo esc_attr($moderator->user_id); ?>" />
                                            <button type="submit" class="button button-small" 
                                                    onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this moderator?', 'quicklearn-course-manager'); ?>')">
                                                <?php esc_html_e('Remove', 'quicklearn-course-manager'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Forum Posts -->
            <div class="qlcm-forum-section">
                <h2><?php esc_html_e('Recent Forum Posts', 'quicklearn-course-manager'); ?></h2>
                <?php if (empty($recent_posts)) : ?>
                    <p><?php esc_html_e('No forum posts found.', 'quicklearn-course-manager'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Title', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Course', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Author', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Replies', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Status', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Date', 'quicklearn-course-manager'); ?></th>
                                <th><?php esc_html_e('Actions', 'quicklearn-course-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_posts as $post) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($post->title); ?></strong>
                                        <?php if ($post->is_pinned) : ?>
                                            <span class="pinned-badge">📌</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($post->course_title); ?></td>
                                    <td>
                                        <?php echo esc_html($post->display_name); ?><br>
                                        <small><?php echo esc_html($post->user_email); ?></small>
                                    </td>
                                    <td><?php echo esc_html($post->reply_count); ?></td>
                                    <td>
                                        <span class="status-<?php echo esc_attr($post->status); ?>">
                                            <?php echo esc_html(ucfirst($post->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($post->created_date))); ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('qlcm_forum_moderator_nonce'); ?>
                                            <input type="hidden" name="action" value="moderate_post" />
                                            <input type="hidden" name="post_id" value="<?php echo esc_attr($post->id); ?>" />
                                            
                                            <?php if ($post->status === 'published') : ?>
                                                <button type="submit" name="moderate_action" value="hide" class="button button-small">
                                                    <?php esc_html_e('Hide', 'quicklearn-course-manager'); ?>
                                                </button>
                                            <?php else : ?>
                                                <button type="submit" name="moderate_action" value="show" class="button button-small">
                                                    <?php esc_html_e('Show', 'quicklearn-course-manager'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($post->is_pinned) : ?>
                                                <button type="submit" name="moderate_action" value="unpin" class="button button-small">
                                                    <?php esc_html_e('Unpin', 'quicklearn-course-manager'); ?>
                                                </button>
                                            <?php else : ?>
                                                <button type="submit" name="moderate_action" value="pin" class="button button-small">
                                                    <?php esc_html_e('Pin', 'quicklearn-course-manager'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // User search functionality
            $('#user_search').on('input', function() {
                var searchTerm = $(this).val();
                if (searchTerm.length < 3) {
                    $('#user_search_results').empty();
                    return;
                }
                
                $.ajax({
                    url: qlcm_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'search_users',
                        search: searchTerm,
                        nonce: qlcm_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<ul class="user-search-results">';
                            response.data.forEach(function(user) {
                                html += '<li data-user-id="' + user.ID + '">' + user.display_name + ' (' + user.user_email + ')</li>';
                            });
                            html += '</ul>';
                            $('#user_search_results').html(html);
                        }
                    }
                });
            });
            
            // Handle user selection
            $(document).on('click', '.user-search-results li', function() {
                var userId = $(this).data('user-id');
                var userName = $(this).text();
                $('#user_id').val(userId);
                $('#user_search').val(userName);
                $('#user_search_results').empty();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle forum management actions
     */
    private function handle_forum_actions() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'qlcm_forum_moderator_nonce')) {
            wp_die(__('Security check failed', 'quicklearn-course-manager'));
        }
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'assign_moderator':
                $this->assign_forum_moderator();
                break;
            case 'remove_moderator':
                $this->remove_forum_moderator();
                break;
            case 'moderate_post':
                $this->moderate_forum_post_admin();
                break;
        }
    }
    
    /**
     * Assign forum moderator
     */
    private function assign_forum_moderator() {
        $course_id = intval($_POST['course_id']);
        $user_id = intval($_POST['user_id']);
        
        if (!$course_id || !$user_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please select both a course and a user.', 'quicklearn-course-manager') . '</p></div>';
            return;
        }
        
        // Check if user exists and course exists
        if (!get_userdata($user_id) || get_post_type($course_id) !== 'quick_course') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Invalid user or course selected.', 'quicklearn-course-manager') . '</p></div>';
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_moderators';
        
        // Check if already assigned
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE course_id = %d AND user_id = %d",
            $course_id,
            $user_id
        ));
        
        if ($existing > 0) {
            echo '<div class="notice notice-error"><p>' . esc_html__('This user is already a moderator for this course.', 'quicklearn-course-manager') . '</p></div>';
            return;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Moderator assigned successfully!', 'quicklearn-course-manager') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to assign moderator.', 'quicklearn-course-manager') . '</p></div>';
        }
    }
    
    /**
     * Remove forum moderator
     */
    private function remove_forum_moderator() {
        $course_id = intval($_POST['course_id']);
        $user_id = intval($_POST['user_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_moderators';
        
        $result = $wpdb->delete(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Moderator removed successfully!', 'quicklearn-course-manager') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to remove moderator.', 'quicklearn-course-manager') . '</p></div>';
        }
    }
    
    /**
     * Moderate forum post from admin
     */
    private function moderate_forum_post_admin() {
        $post_id = intval($_POST['post_id']);
        $moderate_action = sanitize_text_field($_POST['moderate_action']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        switch ($moderate_action) {
            case 'pin':
                $result = $wpdb->update(
                    $table_name,
                    array('is_pinned' => 1),
                    array('id' => $post_id),
                    array('%d'),
                    array('%d')
                );
                break;
            case 'unpin':
                $result = $wpdb->update(
                    $table_name,
                    array('is_pinned' => 0),
                    array('id' => $post_id),
                    array('%d'),
                    array('%d')
                );
                break;
            case 'hide':
                $result = $wpdb->update(
                    $table_name,
                    array('status' => 'hidden'),
                    array('id' => $post_id),
                    array('%s'),
                    array('%d')
                );
                break;
            case 'show':
                $result = $wpdb->update(
                    $table_name,
                    array('status' => 'published'),
                    array('id' => $post_id),
                    array('%s'),
                    array('%d')
                );
                break;
            default:
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid moderation action.', 'quicklearn-course-manager') . '</p></div>';
                return;
        }
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Post moderated successfully!', 'quicklearn-course-manager') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to moderate post.', 'quicklearn-course-manager') . '</p></div>';
        }
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