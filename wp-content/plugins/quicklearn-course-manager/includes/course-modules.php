<?php
/**
 * Course Modules and Lessons for QuickLearn Course Manager
 * 
 * Handles modular course content structure including:
 * - Course modules custom post type
 * - Course lessons custom post type
 * - Drag-and-drop content organization
 * - Lesson progression and completion tracking
 * - Content scheduling and release management
 * 
 * @package QuickLearn_Course_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course Modules Handler Class
 */
class QLCM_Course_Modules {
    
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
        $this->create_database_tables();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register custom post types
        add_action('init', array($this, 'register_post_types'));
        
        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Save post data
        add_action('save_post', array($this, 'save_module_data'));
        add_action('save_post', array($this, 'save_lesson_data'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_qlcm_reorder_modules', array($this, 'ajax_reorder_modules'));
        add_action('wp_ajax_qlcm_reorder_lessons', array($this, 'ajax_reorder_lessons'));
        add_action('wp_ajax_qlcm_update_lesson_progress', array($this, 'ajax_update_lesson_progress'));
        add_action('wp_ajax_qlcm_schedule_content', array($this, 'ajax_schedule_content'));
        add_action('wp_ajax_qlcm_get_course_modules', array($this, 'ajax_get_course_modules'));
        add_action('wp_ajax_qlcm_submit_quiz', array($this, 'ajax_submit_quiz'));
        add_action('wp_ajax_nopriv_qlcm_submit_quiz', array($this, 'ajax_submit_quiz'));
        
        // Frontend display
        add_filter('the_content', array($this, 'add_course_modules_to_content'));
        
        // Admin columns
        add_filter('manage_course_module_posts_columns', array($this, 'module_admin_columns'));
        add_action('manage_course_module_posts_custom_column', array($this, 'module_admin_column_content'), 10, 2);
        add_filter('manage_course_lesson_posts_columns', array($this, 'lesson_admin_columns'));
        add_action('manage_course_lesson_posts_custom_column', array($this, 'lesson_admin_column_content'), 10, 2);
        
        // Content scheduling
        add_action('wp', array($this, 'check_scheduled_content'));
        
        // User progress tracking
        add_action('wp_footer', array($this, 'track_lesson_progress'));
    }
    
    /**
     * Create database tables for progress tracking
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Module progress table
        $module_progress_table = $wpdb->prefix . 'qlcm_module_progress';
        $module_progress_sql = "CREATE TABLE $module_progress_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            started_date datetime DEFAULT CURRENT_TIMESTAMP,
            completed_date datetime DEFAULT NULL,
            progress_percentage int(3) DEFAULT 0,
            last_accessed datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_course_module (user_id, course_id, module_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY module_id (module_id)
        ) $charset_collate;";
        
        // Lesson progress table
        $lesson_progress_table = $wpdb->prefix . 'qlcm_lesson_progress';
        $lesson_progress_sql = "CREATE TABLE $lesson_progress_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            lesson_id bigint(20) NOT NULL,
            started_date datetime DEFAULT CURRENT_TIMESTAMP,
            completed_date datetime DEFAULT NULL,
            time_spent int(11) DEFAULT 0,
            last_position text,
            status varchar(20) DEFAULT 'not_started',
            PRIMARY KEY (id),
            UNIQUE KEY user_lesson (user_id, lesson_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY module_id (module_id),
            KEY lesson_id (lesson_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($module_progress_sql);
        dbDelta($lesson_progress_sql);
    }
    
    /**
     * Register custom post types for modules and lessons
     */
    public function register_post_types() {
        // Register Course Module post type
        $module_args = array(
            'label' => __('Course Modules', 'quicklearn-course-manager'),
            'labels' => array(
                'name' => __('Course Modules', 'quicklearn-course-manager'),
                'singular_name' => __('Course Module', 'quicklearn-course-manager'),
                'add_new' => __('Add New Module', 'quicklearn-course-manager'),
                'add_new_item' => __('Add New Course Module', 'quicklearn-course-manager'),
                'edit_item' => __('Edit Course Module', 'quicklearn-course-manager'),
                'new_item' => __('New Course Module', 'quicklearn-course-manager'),
                'view_item' => __('View Course Module', 'quicklearn-course-manager'),
                'search_items' => __('Search Course Modules', 'quicklearn-course-manager'),
                'not_found' => __('No course modules found', 'quicklearn-course-manager'),
                'not_found_in_trash' => __('No course modules found in trash', 'quicklearn-course-manager'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=quick_course',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
            ),
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'page-attributes'),
            'menu_icon' => 'dashicons-list-view',
            'rewrite' => false,
        );
        
        register_post_type('course_module', $module_args);
        
        // Register Course Lesson post type
        $lesson_args = array(
            'label' => __('Course Lessons', 'quicklearn-course-manager'),
            'labels' => array(
                'name' => __('Course Lessons', 'quicklearn-course-manager'),
                'singular_name' => __('Course Lesson', 'quicklearn-course-manager'),
                'add_new' => __('Add New Lesson', 'quicklearn-course-manager'),
                'add_new_item' => __('Add New Course Lesson', 'quicklearn-course-manager'),
                'edit_item' => __('Edit Course Lesson', 'quicklearn-course-manager'),
                'new_item' => __('New Course Lesson', 'quicklearn-course-manager'),
                'view_item' => __('View Course Lesson', 'quicklearn-course-manager'),
                'search_items' => __('Search Course Lessons', 'quicklearn-course-manager'),
                'not_found' => __('No course lessons found', 'quicklearn-course-manager'),
                'not_found_in_trash' => __('No course lessons found in trash', 'quicklearn-course-manager'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=quick_course',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
            ),
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
            'menu_icon' => 'dashicons-media-document',
            'rewrite' => false,
        );
        
        register_post_type('course_lesson', $lesson_args);
    }
    
    /**
     * Add meta boxes for modules and lessons
     */
    public function add_meta_boxes() {
        // Course selection meta box for modules
        add_meta_box(
            'qlcm_module_course',
            __('Course Assignment', 'quicklearn-course-manager'),
            array($this, 'render_module_course_meta_box'),
            'course_module',
            'side',
            'high'
        );
        
        // Module settings meta box
        add_meta_box(
            'qlcm_module_settings',
            __('Module Settings', 'quicklearn-course-manager'),
            array($this, 'render_module_settings_meta_box'),
            'course_module',
            'normal',
            'high'
        );
        
        // Course and module selection for lessons
        add_meta_box(
            'qlcm_lesson_assignment',
            __('Course & Module Assignment', 'quicklearn-course-manager'),
            array($this, 'render_lesson_assignment_meta_box'),
            'course_lesson',
            'side',
            'high'
        );
        
        // Lesson settings meta box
        add_meta_box(
            'qlcm_lesson_settings',
            __('Lesson Settings', 'quicklearn-course-manager'),
            array($this, 'render_lesson_settings_meta_box'),
            'course_lesson',
            'normal',
            'high'
        );
        
        // Course modules management on course edit page
        add_meta_box(
            'qlcm_course_modules',
            __('Course Modules', 'quicklearn-course-manager'),
            array($this, 'render_course_modules_meta_box'),
            'quick_course',
            'normal',
            'high'
        );
    }
    
    /**
     * Render course assignment meta box for modules
     */
    public function render_module_course_meta_box($post) {
        wp_nonce_field('qlcm_module_course_nonce', 'qlcm_module_course_nonce');
        
        $selected_course = get_post_meta($post->ID, '_qlcm_module_course_id', true);
        $courses = get_posts(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        echo '<p><label for="qlcm_module_course_id">' . __('Select Course:', 'quicklearn-course-manager') . '</label></p>';
        echo '<select name="qlcm_module_course_id" id="qlcm_module_course_id" style="width: 100%;">';
        echo '<option value="">' . __('Select a course...', 'quicklearn-course-manager') . '</option>';
        
        foreach ($courses as $course) {
            $selected = selected($selected_course, $course->ID, false);
            echo '<option value="' . esc_attr($course->ID) . '"' . $selected . '>' . esc_html($course->post_title) . '</option>';
        }
        
        echo '</select>';
        
        if ($selected_course) {
            $module_order = get_post_meta($post->ID, '_qlcm_module_order', true);
            echo '<p style="margin-top: 15px;"><label for="qlcm_module_order">' . __('Module Order:', 'quicklearn-course-manager') . '</label></p>';
            echo '<input type="number" name="qlcm_module_order" id="qlcm_module_order" value="' . esc_attr($module_order) . '" min="1" style="width: 100%;" />';
        }
    }
    
    /**
     * Render module settings meta box
     */
    public function render_module_settings_meta_box($post) {
        wp_nonce_field('qlcm_module_settings_nonce', 'qlcm_module_settings_nonce');
        
        $is_free = get_post_meta($post->ID, '_qlcm_module_is_free', true);
        $duration = get_post_meta($post->ID, '_qlcm_module_duration', true);
        $difficulty = get_post_meta($post->ID, '_qlcm_module_difficulty', true);
        $release_date = get_post_meta($post->ID, '_qlcm_module_release_date', true);
        $prerequisites = get_post_meta($post->ID, '_qlcm_module_prerequisites', true);
        
        echo '<table class="form-table">';
        
        // Free module checkbox
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_module_is_free">' . __('Free Module', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="checkbox" name="qlcm_module_is_free" id="qlcm_module_is_free" value="1"' . checked($is_free, '1', false) . ' /> ';
        echo '<span class="description">' . __('Check if this module is available for free', 'quicklearn-course-manager') . '</span></td>';
        echo '</tr>';
        
        // Duration
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_module_duration">' . __('Estimated Duration (minutes)', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="number" name="qlcm_module_duration" id="qlcm_module_duration" value="' . esc_attr($duration) . '" min="1" class="regular-text" /></td>';
        echo '</tr>';
        
        // Difficulty level
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_module_difficulty">' . __('Difficulty Level', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><select name="qlcm_module_difficulty" id="qlcm_module_difficulty">';
        $difficulties = array('beginner' => __('Beginner', 'quicklearn-course-manager'), 'intermediate' => __('Intermediate', 'quicklearn-course-manager'), 'advanced' => __('Advanced', 'quicklearn-course-manager'));
        foreach ($difficulties as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($difficulty, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        
        // Release date
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_module_release_date">' . __('Release Date', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="datetime-local" name="qlcm_module_release_date" id="qlcm_module_release_date" value="' . esc_attr($release_date) . '" class="regular-text" />';
        echo '<p class="description">' . __('Leave empty to release immediately', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        // Prerequisites
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_module_prerequisites">' . __('Prerequisites', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><textarea name="qlcm_module_prerequisites" id="qlcm_module_prerequisites" rows="3" class="large-text">' . esc_textarea($prerequisites) . '</textarea>';
        echo '<p class="description">' . __('List any prerequisites for this module', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Render lesson assignment meta box
     */
    public function render_lesson_assignment_meta_box($post) {
        wp_nonce_field('qlcm_lesson_assignment_nonce', 'qlcm_lesson_assignment_nonce');
        
        $selected_course = get_post_meta($post->ID, '_qlcm_lesson_course_id', true);
        $selected_module = get_post_meta($post->ID, '_qlcm_lesson_module_id', true);
        
        $courses = get_posts(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        echo '<p><label for="qlcm_lesson_course_id">' . __('Select Course:', 'quicklearn-course-manager') . '</label></p>';
        echo '<select name="qlcm_lesson_course_id" id="qlcm_lesson_course_id" style="width: 100%;">';
        echo '<option value="">' . __('Select a course...', 'quicklearn-course-manager') . '</option>';
        
        foreach ($courses as $course) {
            $selected = selected($selected_course, $course->ID, false);
            echo '<option value="' . esc_attr($course->ID) . '"' . $selected . '>' . esc_html($course->post_title) . '</option>';
        }
        
        echo '</select>';
        
        echo '<div id="qlcm_module_selection" style="margin-top: 15px;">';
        if ($selected_course) {
            $this->render_module_dropdown($selected_course, $selected_module);
        }
        echo '</div>';
        
        if ($selected_module) {
            $lesson_order = get_post_meta($post->ID, '_qlcm_lesson_order', true);
            echo '<p style="margin-top: 15px;"><label for="qlcm_lesson_order">' . __('Lesson Order:', 'quicklearn-course-manager') . '</label></p>';
            echo '<input type="number" name="qlcm_lesson_order" id="qlcm_lesson_order" value="' . esc_attr($lesson_order) . '" min="1" style="width: 100%;" />';
        }
    }
    
    /**
     * Render module dropdown for lesson assignment
     */
    private function render_module_dropdown($course_id, $selected_module = '') {
        $modules = get_posts(array(
            'post_type' => 'course_module',
            'meta_key' => '_qlcm_module_course_id',
            'meta_value' => $course_id,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_module_order',
            'order' => 'ASC'
        ));
        
        echo '<p><label for="qlcm_lesson_module_id">' . __('Select Module:', 'quicklearn-course-manager') . '</label></p>';
        echo '<select name="qlcm_lesson_module_id" id="qlcm_lesson_module_id" style="width: 100%;">';
        echo '<option value="">' . __('Select a module...', 'quicklearn-course-manager') . '</option>';
        
        foreach ($modules as $module) {
            $selected = selected($selected_module, $module->ID, false);
            echo '<option value="' . esc_attr($module->ID) . '"' . $selected . '>' . esc_html($module->post_title) . '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Render lesson settings meta box
     */
    public function render_lesson_settings_meta_box($post) {
        wp_nonce_field('qlcm_lesson_settings_nonce', 'qlcm_lesson_settings_nonce');
        
        $lesson_type = get_post_meta($post->ID, '_qlcm_lesson_type', true);
        $duration = get_post_meta($post->ID, '_qlcm_lesson_duration', true);
        $is_free = get_post_meta($post->ID, '_qlcm_lesson_is_free', true);
        $video_url = get_post_meta($post->ID, '_qlcm_lesson_video_url', true);
        $audio_url = get_post_meta($post->ID, '_qlcm_lesson_audio_url', true);
        $downloadable_resources = get_post_meta($post->ID, '_qlcm_lesson_resources', true);
        $quiz_questions = get_post_meta($post->ID, '_qlcm_lesson_quiz', true);
        
        echo '<table class="form-table">';
        
        // Lesson type
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_lesson_type">' . __('Lesson Type', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><select name="qlcm_lesson_type" id="qlcm_lesson_type">';
        $types = array(
            'text' => __('Text/Reading', 'quicklearn-course-manager'),
            'video' => __('Video', 'quicklearn-course-manager'),
            'audio' => __('Audio', 'quicklearn-course-manager'),
            'quiz' => __('Quiz', 'quicklearn-course-manager'),
            'mixed' => __('Mixed Content', 'quicklearn-course-manager')
        );
        foreach ($types as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($lesson_type, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
        
        // Duration
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_lesson_duration">' . __('Estimated Duration (minutes)', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="number" name="qlcm_lesson_duration" id="qlcm_lesson_duration" value="' . esc_attr($duration) . '" min="1" class="regular-text" /></td>';
        echo '</tr>';
        
        // Free lesson
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_lesson_is_free">' . __('Free Lesson', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="checkbox" name="qlcm_lesson_is_free" id="qlcm_lesson_is_free" value="1"' . checked($is_free, '1', false) . ' /> ';
        echo '<span class="description">' . __('Check if this lesson is available for free', 'quicklearn-course-manager') . '</span></td>';
        echo '</tr>';
        
        // Video URL
        echo '<tr class="qlcm-lesson-video" style="display: ' . ($lesson_type === 'video' || $lesson_type === 'mixed' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="qlcm_lesson_video_url">' . __('Video URL', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="url" name="qlcm_lesson_video_url" id="qlcm_lesson_video_url" value="' . esc_attr($video_url) . '" class="large-text" />';
        echo '<p class="description">' . __('YouTube, Vimeo, or direct video file URL', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        // Audio URL
        echo '<tr class="qlcm-lesson-audio" style="display: ' . ($lesson_type === 'audio' || $lesson_type === 'mixed' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="qlcm_lesson_audio_url">' . __('Audio URL', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><input type="url" name="qlcm_lesson_audio_url" id="qlcm_lesson_audio_url" value="' . esc_attr($audio_url) . '" class="large-text" />';
        echo '<p class="description">' . __('Direct audio file URL (MP3, WAV, etc.)', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        // Downloadable resources
        echo '<tr>';
        echo '<th scope="row"><label for="qlcm_lesson_resources">' . __('Downloadable Resources', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><textarea name="qlcm_lesson_resources" id="qlcm_lesson_resources" rows="3" class="large-text">' . esc_textarea($downloadable_resources) . '</textarea>';
        echo '<p class="description">' . __('One URL per line for downloadable resources', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        // Quiz questions (for quiz type lessons)
        echo '<tr class="qlcm-lesson-quiz" style="display: ' . ($lesson_type === 'quiz' ? 'table-row' : 'none') . ';">';
        echo '<th scope="row"><label for="qlcm_lesson_quiz">' . __('Quiz Questions (JSON)', 'quicklearn-course-manager') . '</label></th>';
        echo '<td><textarea name="qlcm_lesson_quiz" id="qlcm_lesson_quiz" rows="5" class="large-text">' . esc_textarea($quiz_questions) . '</textarea>';
        echo '<p class="description">' . __('JSON format quiz questions and answers', 'quicklearn-course-manager') . '</p></td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Render course modules meta box on course edit page
     */
    public function render_course_modules_meta_box($post) {
        $modules = get_posts(array(
            'post_type' => 'course_module',
            'meta_key' => '_qlcm_module_course_id',
            'meta_value' => $post->ID,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_module_order',
            'order' => 'ASC'
        ));
        
        echo '<div id="qlcm-course-modules-container">';
        
        if (empty($modules)) {
            echo '<p>' . __('No modules found for this course.', 'quicklearn-course-manager') . '</p>';
        } else {
            echo '<div id="qlcm-modules-sortable" class="qlcm-sortable-list">';
            
            foreach ($modules as $module) {
                $this->render_module_item($module);
            }
            
            echo '</div>';
        }
        
        echo '<p><a href="' . admin_url('post-new.php?post_type=course_module&course_id=' . $post->ID) . '" class="button button-secondary">' . __('Add New Module', 'quicklearn-course-manager') . '</a></p>';
        echo '</div>';
    }
    
    /**
     * Render individual module item for drag-and-drop interface
     */
    private function render_module_item($module) {
        $lessons = get_posts(array(
            'post_type' => 'course_lesson',
            'meta_key' => '_qlcm_lesson_module_id',
            'meta_value' => $module->ID,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_lesson_order',
            'order' => 'ASC'
        ));
        
        $module_order = get_post_meta($module->ID, '_qlcm_module_order', true);
        $is_free = get_post_meta($module->ID, '_qlcm_module_is_free', true);
        $duration = get_post_meta($module->ID, '_qlcm_module_duration', true);
        
        echo '<div class="qlcm-module-item" data-module-id="' . esc_attr($module->ID) . '">';
        echo '<div class="qlcm-module-header">';
        echo '<span class="qlcm-drag-handle dashicons dashicons-menu"></span>';
        echo '<strong>' . esc_html($module->post_title) . '</strong>';
        echo '<span class="qlcm-module-meta">';
        if ($is_free) {
            echo '<span class="qlcm-free-badge">' . __('Free', 'quicklearn-course-manager') . '</span>';
        }
        if ($duration) {
            echo '<span class="qlcm-duration">' . sprintf(__('%d min', 'quicklearn-course-manager'), $duration) . '</span>';
        }
        echo '<span class="qlcm-lesson-count">' . sprintf(_n('%d lesson', '%d lessons', count($lessons), 'quicklearn-course-manager'), count($lessons)) . '</span>';
        echo '</span>';
        echo '<div class="qlcm-module-actions">';
        echo '<a href="' . get_edit_post_link($module->ID) . '" class="button button-small">' . __('Edit', 'quicklearn-course-manager') . '</a>';
        echo '</div>';
        echo '</div>';
        
        if (!empty($lessons)) {
            echo '<div class="qlcm-lessons-container">';
            echo '<div class="qlcm-lessons-sortable" data-module-id="' . esc_attr($module->ID) . '">';
            
            foreach ($lessons as $lesson) {
                $this->render_lesson_item($lesson);
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '<div class="qlcm-module-footer">';
        echo '<a href="' . admin_url('post-new.php?post_type=course_lesson&module_id=' . $module->ID) . '" class="button button-small">' . __('Add Lesson', 'quicklearn-course-manager') . '</a>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render individual lesson item for drag-and-drop interface
     */
    private function render_lesson_item($lesson) {
        $lesson_type = get_post_meta($lesson->ID, '_qlcm_lesson_type', true);
        $duration = get_post_meta($lesson->ID, '_qlcm_lesson_duration', true);
        $is_free = get_post_meta($lesson->ID, '_qlcm_lesson_is_free', true);
        
        echo '<div class="qlcm-lesson-item" data-lesson-id="' . esc_attr($lesson->ID) . '">';
        echo '<span class="qlcm-drag-handle dashicons dashicons-menu"></span>';
        echo '<span class="qlcm-lesson-type-icon dashicons ' . $this->get_lesson_type_icon($lesson_type) . '"></span>';
        echo '<span class="qlcm-lesson-title">' . esc_html($lesson->post_title) . '</span>';
        echo '<span class="qlcm-lesson-meta">';
        if ($is_free) {
            echo '<span class="qlcm-free-badge">' . __('Free', 'quicklearn-course-manager') . '</span>';
        }
        if ($duration) {
            echo '<span class="qlcm-duration">' . sprintf(__('%d min', 'quicklearn-course-manager'), $duration) . '</span>';
        }
        echo '</span>';
        echo '<div class="qlcm-lesson-actions">';
        echo '<a href="' . get_edit_post_link($lesson->ID) . '" class="button button-small">' . __('Edit', 'quicklearn-course-manager') . '</a>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get icon class for lesson type
     */
    private function get_lesson_type_icon($type) {
        $icons = array(
            'text' => 'dashicons-media-text',
            'video' => 'dashicons-video-alt3',
            'audio' => 'dashicons-media-audio',
            'quiz' => 'dashicons-forms',
            'mixed' => 'dashicons-portfolio'
        );
        
        return isset($icons[$type]) ? $icons[$type] : 'dashicons-media-document';
    }
    
    /**
     * Save module data
     */
    public function save_module_data($post_id) {
        if (get_post_type($post_id) !== 'course_module') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['qlcm_module_course_nonce']) || !wp_verify_nonce($_POST['qlcm_module_course_nonce'], 'qlcm_module_course_nonce')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save course assignment
        if (isset($_POST['qlcm_module_course_id'])) {
            update_post_meta($post_id, '_qlcm_module_course_id', absint($_POST['qlcm_module_course_id']));
        }
        
        // Save module order
        if (isset($_POST['qlcm_module_order'])) {
            update_post_meta($post_id, '_qlcm_module_order', absint($_POST['qlcm_module_order']));
        }
        
        // Save module settings
        if (isset($_POST['qlcm_module_settings_nonce']) && wp_verify_nonce($_POST['qlcm_module_settings_nonce'], 'qlcm_module_settings_nonce')) {
            update_post_meta($post_id, '_qlcm_module_is_free', isset($_POST['qlcm_module_is_free']) ? '1' : '0');
            update_post_meta($post_id, '_qlcm_module_duration', absint($_POST['qlcm_module_duration']));
            update_post_meta($post_id, '_qlcm_module_difficulty', sanitize_text_field($_POST['qlcm_module_difficulty']));
            update_post_meta($post_id, '_qlcm_module_release_date', sanitize_text_field($_POST['qlcm_module_release_date']));
            update_post_meta($post_id, '_qlcm_module_prerequisites', sanitize_textarea_field($_POST['qlcm_module_prerequisites']));
        }
    }
    
    /**
     * Save lesson data
     */
    public function save_lesson_data($post_id) {
        if (get_post_type($post_id) !== 'course_lesson') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['qlcm_lesson_assignment_nonce']) || !wp_verify_nonce($_POST['qlcm_lesson_assignment_nonce'], 'qlcm_lesson_assignment_nonce')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save course and module assignment
        if (isset($_POST['qlcm_lesson_course_id'])) {
            update_post_meta($post_id, '_qlcm_lesson_course_id', absint($_POST['qlcm_lesson_course_id']));
        }
        
        if (isset($_POST['qlcm_lesson_module_id'])) {
            update_post_meta($post_id, '_qlcm_lesson_module_id', absint($_POST['qlcm_lesson_module_id']));
        }
        
        // Save lesson order
        if (isset($_POST['qlcm_lesson_order'])) {
            update_post_meta($post_id, '_qlcm_lesson_order', absint($_POST['qlcm_lesson_order']));
        }
        
        // Save lesson settings
        if (isset($_POST['qlcm_lesson_settings_nonce']) && wp_verify_nonce($_POST['qlcm_lesson_settings_nonce'], 'qlcm_lesson_settings_nonce')) {
            update_post_meta($post_id, '_qlcm_lesson_type', sanitize_text_field($_POST['qlcm_lesson_type']));
            update_post_meta($post_id, '_qlcm_lesson_duration', absint($_POST['qlcm_lesson_duration']));
            update_post_meta($post_id, '_qlcm_lesson_is_free', isset($_POST['qlcm_lesson_is_free']) ? '1' : '0');
            update_post_meta($post_id, '_qlcm_lesson_video_url', esc_url_raw($_POST['qlcm_lesson_video_url']));
            update_post_meta($post_id, '_qlcm_lesson_audio_url', esc_url_raw($_POST['qlcm_lesson_audio_url']));
            update_post_meta($post_id, '_qlcm_lesson_resources', sanitize_textarea_field($_POST['qlcm_lesson_resources']));
            update_post_meta($post_id, '_qlcm_lesson_quiz', sanitize_textarea_field($_POST['qlcm_lesson_quiz']));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if (!in_array($post_type, array('quick_course', 'course_module', 'course_lesson'))) {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'qlcm-modules-admin',
            QLCM_PLUGIN_URL . 'assets/js/multimedia-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            QLCM_VERSION,
            true
        );
        
        wp_localize_script('qlcm-modules-admin', 'qlcm_modules_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qlcm_modules_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'quicklearn-course-manager'),
                'saving' => __('Saving...', 'quicklearn-course-manager'),
                'saved' => __('Saved!', 'quicklearn-course-manager'),
                'error' => __('Error occurred while saving.', 'quicklearn-course-manager')
            )
        ));
        
        wp_enqueue_style(
            'qlcm-modules-admin',
            QLCM_PLUGIN_URL . 'assets/css/multimedia-admin.css',
            array(),
            QLCM_VERSION
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_singular('quick_course') || is_page_template('page-dashboard.php')) {
            wp_enqueue_script(
                'qlcm-modules-frontend',
                QLCM_PLUGIN_URL . 'assets/js/multimedia-frontend.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_localize_script('qlcm-modules-frontend', 'qlcm_modules_frontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_modules_frontend_nonce'),
                'user_id' => get_current_user_id(),
                'strings' => array(
                    'loading' => __('Loading...', 'quicklearn-course-manager'),
                    'completed' => __('Completed!', 'quicklearn-course-manager'),
                    'error' => __('Error occurred.', 'quicklearn-course-manager')
                )
            ));
            
            wp_enqueue_style(
                'qlcm-modules-frontend',
                QLCM_PLUGIN_URL . 'assets/css/multimedia-frontend.css',
                array(),
                QLCM_VERSION
            );
        }
    }
    
    /**
     * AJAX handler for reordering modules
     */
    public function ajax_reorder_modules() {
        check_ajax_referer('qlcm_modules_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'quicklearn-course-manager'));
        }
        
        $module_ids = isset($_POST['module_ids']) ? array_map('absint', $_POST['module_ids']) : array();
        
        if (empty($module_ids)) {
            wp_send_json_error(__('No modules to reorder.', 'quicklearn-course-manager'));
        }
        
        foreach ($module_ids as $index => $module_id) {
            update_post_meta($module_id, '_qlcm_module_order', $index + 1);
        }
        
        wp_send_json_success(__('Module order updated successfully.', 'quicklearn-course-manager'));
    }
    
    /**
     * AJAX handler for reordering lessons
     */
    public function ajax_reorder_lessons() {
        check_ajax_referer('qlcm_modules_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'quicklearn-course-manager'));
        }
        
        $lesson_ids = isset($_POST['lesson_ids']) ? array_map('absint', $_POST['lesson_ids']) : array();
        
        if (empty($lesson_ids)) {
            wp_send_json_error(__('No lessons to reorder.', 'quicklearn-course-manager'));
        }
        
        foreach ($lesson_ids as $index => $lesson_id) {
            update_post_meta($lesson_id, '_qlcm_lesson_order', $index + 1);
        }
        
        wp_send_json_success(__('Lesson order updated successfully.', 'quicklearn-course-manager'));
    }
    
    /**
     * AJAX handler for updating lesson progress
     */
    public function ajax_update_lesson_progress() {
        check_ajax_referer('qlcm_modules_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to track progress.', 'quicklearn-course-manager'));
        }
        
        $user_id = get_current_user_id();
        $lesson_id = absint($_POST['lesson_id']);
        $course_id = absint($_POST['course_id']);
        $module_id = absint($_POST['module_id']);
        $progress_data = sanitize_text_field($_POST['progress_data']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!$lesson_id || !$course_id || !$module_id) {
            wp_send_json_error(__('Invalid lesson data.', 'quicklearn-course-manager'));
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_lesson_progress';
        
        // Check if progress record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND lesson_id = %d",
            $user_id,
            $lesson_id
        ));
        
        $data = array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'module_id' => $module_id,
            'lesson_id' => $lesson_id,
            'last_position' => $progress_data,
            'status' => $status
        );
        
        if ($existing) {
            // Update existing record
            $data['last_accessed'] = current_time('mysql');
            if ($status === 'completed' && !$existing->completed_date) {
                $data['completed_date'] = current_time('mysql');
            }
            
            $wpdb->update($table_name, $data, array('id' => $existing->id));
        } else {
            // Insert new record
            $data['started_date'] = current_time('mysql');
            if ($status === 'completed') {
                $data['completed_date'] = current_time('mysql');
            }
            
            $wpdb->insert($table_name, $data);
        }
        
        // Update module progress
        $this->update_module_progress($user_id, $course_id, $module_id);
        
        wp_send_json_success(__('Progress updated successfully.', 'quicklearn-course-manager'));
    }
    
    /**
     * Update module progress based on lesson completions
     */
    private function update_module_progress($user_id, $course_id, $module_id) {
        global $wpdb;
        
        // Get total lessons in module
        $total_lessons = get_posts(array(
            'post_type' => 'course_lesson',
            'meta_key' => '_qlcm_lesson_module_id',
            'meta_value' => $module_id,
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        if (empty($total_lessons)) {
            return;
        }
        
        // Get completed lessons
        $lesson_progress_table = $wpdb->prefix . 'qlcm_lesson_progress';
        $completed_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $lesson_progress_table 
             WHERE user_id = %d AND module_id = %d AND status = 'completed'",
            $user_id,
            $module_id
        ));
        
        $progress_percentage = round(($completed_lessons / count($total_lessons)) * 100);
        
        // Update module progress
        $module_progress_table = $wpdb->prefix . 'qlcm_module_progress';
        
        $existing_module_progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $module_progress_table WHERE user_id = %d AND module_id = %d",
            $user_id,
            $module_id
        ));
        
        $module_data = array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'module_id' => $module_id,
            'progress_percentage' => $progress_percentage,
            'last_accessed' => current_time('mysql')
        );
        
        if ($existing_module_progress) {
            if ($progress_percentage === 100 && !$existing_module_progress->completed_date) {
                $module_data['completed_date'] = current_time('mysql');
            }
            
            $wpdb->update($module_progress_table, $module_data, array('id' => $existing_module_progress->id));
        } else {
            $module_data['started_date'] = current_time('mysql');
            if ($progress_percentage === 100) {
                $module_data['completed_date'] = current_time('mysql');
            }
            
            $wpdb->insert($module_progress_table, $module_data);
        }
    }
    
    /**
     * AJAX handler for content scheduling
     */
    public function ajax_schedule_content() {
        check_ajax_referer('qlcm_modules_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'quicklearn-course-manager'));
        }
        
        $post_id = absint($_POST['post_id']);
        $release_date = sanitize_text_field($_POST['release_date']);
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID.', 'quicklearn-course-manager'));
        }
        
        $post_type = get_post_type($post_id);
        if (!in_array($post_type, array('course_module', 'course_lesson'))) {
            wp_send_json_error(__('Invalid post type.', 'quicklearn-course-manager'));
        }
        
        if ($post_type === 'course_module') {
            update_post_meta($post_id, '_qlcm_module_release_date', $release_date);
        } else {
            update_post_meta($post_id, '_qlcm_lesson_release_date', $release_date);
        }
        
        wp_send_json_success(__('Release date updated successfully.', 'quicklearn-course-manager'));
    }
    
    /**
     * AJAX handler for getting course modules
     */
    public function ajax_get_course_modules() {
        check_ajax_referer('qlcm_modules_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'quicklearn-course-manager'));
        }
        
        $course_id = absint($_POST['course_id']);
        
        if (!$course_id) {
            wp_send_json_error(__('Invalid course ID.', 'quicklearn-course-manager'));
        }
        
        $modules = get_posts(array(
            'post_type' => 'course_module',
            'meta_key' => '_qlcm_module_course_id',
            'meta_value' => $course_id,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_module_order',
            'order' => 'ASC'
        ));
        
        $html = '<p><label for="qlcm_lesson_module_id">' . __('Select Module:', 'quicklearn-course-manager') . '</label></p>';
        $html .= '<select name="qlcm_lesson_module_id" id="qlcm_lesson_module_id" style="width: 100%;">';
        $html .= '<option value="">' . __('Select a module...', 'quicklearn-course-manager') . '</option>';
        
        foreach ($modules as $module) {
            $html .= '<option value="' . esc_attr($module->ID) . '">' . esc_html($module->post_title) . '</option>';
        }
        
        $html .= '</select>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for quiz submission
     */
    public function ajax_submit_quiz() {
        check_ajax_referer('qlcm_modules_frontend_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to submit quizzes.', 'quicklearn-course-manager'));
        }
        
        $lesson_id = absint($_POST['lesson_id']);
        $course_id = absint($_POST['course_id']);
        $module_id = absint($_POST['module_id']);
        $quiz_data = sanitize_text_field($_POST['quiz_data']);
        
        if (!$lesson_id || !$course_id || !$module_id) {
            wp_send_json_error(__('Invalid quiz data.', 'quicklearn-course-manager'));
        }
        
        // Get quiz questions from lesson meta
        $quiz_questions = get_post_meta($lesson_id, '_qlcm_lesson_quiz', true);
        
        if (empty($quiz_questions)) {
            wp_send_json_error(__('No quiz questions found.', 'quicklearn-course-manager'));
        }
        
        // Parse quiz questions (assuming JSON format)
        $questions = json_decode($quiz_questions, true);
        
        if (!$questions) {
            wp_send_json_error(__('Invalid quiz format.', 'quicklearn-course-manager'));
        }
        
        // Parse submitted answers
        parse_str($quiz_data, $submitted_answers);
        
        // Calculate score
        $total_questions = count($questions);
        $correct_answers = 0;
        
        foreach ($questions as $index => $question) {
            $question_key = 'question_' . $index;
            $submitted_answer = isset($submitted_answers[$question_key]) ? $submitted_answers[$question_key] : '';
            $correct_answer = isset($question['correct_answer']) ? $question['correct_answer'] : '';
            
            if ($submitted_answer === $correct_answer) {
                $correct_answers++;
            }
        }
        
        $score_percentage = round(($correct_answers / $total_questions) * 100);
        $passing_score = 70; // Default passing score
        $passed = $score_percentage >= $passing_score;
        
        // Save quiz result
        $user_id = get_current_user_id();
        global $wpdb;
        
        $quiz_results_table = $wpdb->prefix . 'qlcm_quiz_results';
        
        // Create quiz results table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $quiz_results_sql = "CREATE TABLE IF NOT EXISTS $quiz_results_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            lesson_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            score int(3) NOT NULL,
            total_questions int(3) NOT NULL,
            percentage int(3) NOT NULL,
            passed tinyint(1) DEFAULT 0,
            submitted_date datetime DEFAULT CURRENT_TIMESTAMP,
            quiz_data longtext,
            PRIMARY KEY (id),
            KEY user_lesson (user_id, lesson_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($quiz_results_sql);
        
        // Insert quiz result
        $wpdb->insert(
            $quiz_results_table,
            array(
                'user_id' => $user_id,
                'lesson_id' => $lesson_id,
                'course_id' => $course_id,
                'module_id' => $module_id,
                'score' => $correct_answers,
                'total_questions' => $total_questions,
                'percentage' => $score_percentage,
                'passed' => $passed ? 1 : 0,
                'quiz_data' => wp_json_encode($submitted_answers)
            )
        );
        
        wp_send_json_success(array(
            'score' => $correct_answers,
            'total' => $total_questions,
            'percentage' => $score_percentage,
            'passed' => $passed,
            'passing_score' => $passing_score
        ));
    }
    
    /**
     * Add course modules to course content display
     */
    public function add_course_modules_to_content($content) {
        if (!is_singular('quick_course') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        $modules = get_posts(array(
            'post_type' => 'course_module',
            'meta_key' => '_qlcm_module_course_id',
            'meta_value' => $post->ID,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_module_order',
            'order' => 'ASC'
        ));
        
        if (empty($modules)) {
            return $content;
        }
        
        $modules_html = '<div class="qlcm-course-modules">';
        $modules_html .= '<h3>' . __('Course Modules', 'quicklearn-course-manager') . '</h3>';
        
        foreach ($modules as $module) {
            $modules_html .= $this->render_module_frontend($module);
        }
        
        $modules_html .= '</div>';
        
        return $content . $modules_html;
    }
    
    /**
     * Render module for frontend display
     */
    private function render_module_frontend($module) {
        $is_free = get_post_meta($module->ID, '_qlcm_module_is_free', true);
        $duration = get_post_meta($module->ID, '_qlcm_module_duration', true);
        $difficulty = get_post_meta($module->ID, '_qlcm_module_difficulty', true);
        $release_date = get_post_meta($module->ID, '_qlcm_module_release_date', true);
        
        // Check if module is released
        $is_released = true;
        if ($release_date && strtotime($release_date) > current_time('timestamp')) {
            $is_released = false;
        }
        
        $lessons = get_posts(array(
            'post_type' => 'course_lesson',
            'meta_key' => '_qlcm_lesson_module_id',
            'meta_value' => $module->ID,
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_qlcm_lesson_order',
            'order' => 'ASC'
        ));
        
        $user_progress = $this->get_user_module_progress(get_current_user_id(), $module->ID);
        
        $html = '<div class="qlcm-module" data-module-id="' . esc_attr($module->ID) . '">';
        $html .= '<div class="qlcm-module-header">';
        $html .= '<h4>' . esc_html($module->post_title) . '</h4>';
        
        $html .= '<div class="qlcm-module-meta">';
        if ($is_free) {
            $html .= '<span class="qlcm-free-badge">' . __('Free', 'quicklearn-course-manager') . '</span>';
        }
        if ($duration) {
            $html .= '<span class="qlcm-duration">' . sprintf(__('%d minutes', 'quicklearn-course-manager'), $duration) . '</span>';
        }
        if ($difficulty) {
            $html .= '<span class="qlcm-difficulty qlcm-difficulty-' . esc_attr($difficulty) . '">' . ucfirst($difficulty) . '</span>';
        }
        $html .= '<span class="qlcm-lesson-count">' . sprintf(_n('%d lesson', '%d lessons', count($lessons), 'quicklearn-course-manager'), count($lessons)) . '</span>';
        $html .= '</div>';
        
        if ($user_progress && $user_progress->progress_percentage > 0) {
            $html .= '<div class="qlcm-progress-bar">';
            $html .= '<div class="qlcm-progress-fill" style="width: ' . esc_attr($user_progress->progress_percentage) . '%"></div>';
            $html .= '<span class="qlcm-progress-text">' . sprintf(__('%d%% Complete', 'quicklearn-course-manager'), $user_progress->progress_percentage) . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        if ($module->post_content) {
            $html .= '<div class="qlcm-module-description">' . wpautop($module->post_content) . '</div>';
        }
        
        if (!$is_released) {
            $html .= '<div class="qlcm-module-scheduled">';
            $html .= '<p>' . sprintf(__('This module will be available on %s', 'quicklearn-course-manager'), date_i18n(get_option('date_format'), strtotime($release_date))) . '</p>';
            $html .= '</div>';
        } elseif (!empty($lessons)) {
            $html .= '<div class="qlcm-lessons-list">';
            
            foreach ($lessons as $lesson) {
                $html .= $this->render_lesson_frontend($lesson);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render lesson for frontend display
     */
    private function render_lesson_frontend($lesson) {
        $lesson_type = get_post_meta($lesson->ID, '_qlcm_lesson_type', true);
        $duration = get_post_meta($lesson->ID, '_qlcm_lesson_duration', true);
        $is_free = get_post_meta($lesson->ID, '_qlcm_lesson_is_free', true);
        
        $user_progress = $this->get_user_lesson_progress(get_current_user_id(), $lesson->ID);
        $is_completed = $user_progress && $user_progress->status === 'completed';
        
        $html = '<div class="qlcm-lesson" data-lesson-id="' . esc_attr($lesson->ID) . '">';
        $html .= '<div class="qlcm-lesson-header">';
        $html .= '<span class="qlcm-lesson-icon dashicons ' . $this->get_lesson_type_icon($lesson_type) . '"></span>';
        $html .= '<span class="qlcm-lesson-title">' . esc_html($lesson->post_title) . '</span>';
        
        $html .= '<div class="qlcm-lesson-meta">';
        if ($is_free) {
            $html .= '<span class="qlcm-free-badge">' . __('Free', 'quicklearn-course-manager') . '</span>';
        }
        if ($duration) {
            $html .= '<span class="qlcm-duration">' . sprintf(__('%d min', 'quicklearn-course-manager'), $duration) . '</span>';
        }
        if ($is_completed) {
            $html .= '<span class="qlcm-completed-badge dashicons dashicons-yes-alt"></span>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get user module progress
     */
    private function get_user_module_progress($user_id, $module_id) {
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_module_progress';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND module_id = %d",
            $user_id,
            $module_id
        ));
    }
    
    /**
     * Get user lesson progress
     */
    private function get_user_lesson_progress($user_id, $lesson_id) {
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_lesson_progress';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND lesson_id = %d",
            $user_id,
            $lesson_id
        ));
    }
    
    /**
     * Admin columns for modules
     */
    public function module_admin_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['course'] = __('Course', 'quicklearn-course-manager');
        $new_columns['lessons'] = __('Lessons', 'quicklearn-course-manager');
        $new_columns['order'] = __('Order', 'quicklearn-course-manager');
        $new_columns['duration'] = __('Duration', 'quicklearn-course-manager');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Admin column content for modules
     */
    public function module_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'course':
                $course_id = get_post_meta($post_id, '_qlcm_module_course_id', true);
                if ($course_id) {
                    $course = get_post($course_id);
                    if ($course) {
                        echo '<a href="' . get_edit_post_link($course_id) . '">' . esc_html($course->post_title) . '</a>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'lessons':
                $lessons = get_posts(array(
                    'post_type' => 'course_lesson',
                    'meta_key' => '_qlcm_lesson_module_id',
                    'meta_value' => $post_id,
                    'numberposts' => -1,
                    'fields' => 'ids'
                ));
                echo count($lessons);
                break;
                
            case 'order':
                $order = get_post_meta($post_id, '_qlcm_module_order', true);
                echo $order ? esc_html($order) : '—';
                break;
                
            case 'duration':
                $duration = get_post_meta($post_id, '_qlcm_module_duration', true);
                echo $duration ? sprintf(__('%d min', 'quicklearn-course-manager'), $duration) : '—';
                break;
        }
    }
    
    /**
     * Admin columns for lessons
     */
    public function lesson_admin_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['course'] = __('Course', 'quicklearn-course-manager');
        $new_columns['module'] = __('Module', 'quicklearn-course-manager');
        $new_columns['type'] = __('Type', 'quicklearn-course-manager');
        $new_columns['order'] = __('Order', 'quicklearn-course-manager');
        $new_columns['duration'] = __('Duration', 'quicklearn-course-manager');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Admin column content for lessons
     */
    public function lesson_admin_column_content($column, $post_id) {
        switch ($column) {
            case 'course':
                $course_id = get_post_meta($post_id, '_qlcm_lesson_course_id', true);
                if ($course_id) {
                    $course = get_post($course_id);
                    if ($course) {
                        echo '<a href="' . get_edit_post_link($course_id) . '">' . esc_html($course->post_title) . '</a>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'module':
                $module_id = get_post_meta($post_id, '_qlcm_lesson_module_id', true);
                if ($module_id) {
                    $module = get_post($module_id);
                    if ($module) {
                        echo '<a href="' . get_edit_post_link($module_id) . '">' . esc_html($module->post_title) . '</a>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'type':
                $type = get_post_meta($post_id, '_qlcm_lesson_type', true);
                if ($type) {
                    $types = array(
                        'text' => __('Text', 'quicklearn-course-manager'),
                        'video' => __('Video', 'quicklearn-course-manager'),
                        'audio' => __('Audio', 'quicklearn-course-manager'),
                        'quiz' => __('Quiz', 'quicklearn-course-manager'),
                        'mixed' => __('Mixed', 'quicklearn-course-manager')
                    );
                    echo isset($types[$type]) ? esc_html($types[$type]) : esc_html($type);
                } else {
                    echo '—';
                }
                break;
                
            case 'order':
                $order = get_post_meta($post_id, '_qlcm_lesson_order', true);
                echo $order ? esc_html($order) : '—';
                break;
                
            case 'duration':
                $duration = get_post_meta($post_id, '_qlcm_lesson_duration', true);
                echo $duration ? sprintf(__('%d min', 'quicklearn-course-manager'), $duration) : '—';
                break;
        }
    }
    
    /**
     * Check scheduled content and update post status
     */
    public function check_scheduled_content() {
        if (is_admin()) {
            return;
        }
        
        // This would typically be run via cron job
        // For now, we'll check on frontend page loads
        $current_time = current_time('mysql');
        
        // Check scheduled modules
        $scheduled_modules = get_posts(array(
            'post_type' => 'course_module',
            'post_status' => 'draft',
            'meta_query' => array(
                array(
                    'key' => '_qlcm_module_release_date',
                    'value' => $current_time,
                    'compare' => '<=',
                    'type' => 'DATETIME'
                )
            ),
            'numberposts' => -1
        ));
        
        foreach ($scheduled_modules as $module) {
            wp_update_post(array(
                'ID' => $module->ID,
                'post_status' => 'publish'
            ));
        }
        
        // Check scheduled lessons
        $scheduled_lessons = get_posts(array(
            'post_type' => 'course_lesson',
            'post_status' => 'draft',
            'meta_query' => array(
                array(
                    'key' => '_qlcm_lesson_release_date',
                    'value' => $current_time,
                    'compare' => '<=',
                    'type' => 'DATETIME'
                )
            ),
            'numberposts' => -1
        ));
        
        foreach ($scheduled_lessons as $lesson) {
            wp_update_post(array(
                'ID' => $lesson->ID,
                'post_status' => 'publish'
            ));
        }
    }
    
    /**
     * Track lesson progress on frontend
     */
    public function track_lesson_progress() {
        if (!is_singular('quick_course') || !is_user_logged_in()) {
            return;
        }
        
        // Add JavaScript for progress tracking
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Track lesson clicks and time spent
            $('.qlcm-lesson').on('click', function() {
                var lessonId = $(this).data('lesson-id');
                var courseId = <?php echo get_the_ID(); ?>;
                var moduleId = $(this).closest('.qlcm-module').data('module-id');
                
                // Mark lesson as started
                $.ajax({
                    url: qlcm_modules_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'qlcm_update_lesson_progress',
                        nonce: qlcm_modules_frontend.nonce,
                        lesson_id: lessonId,
                        course_id: courseId,
                        module_id: moduleId,
                        status: 'in_progress',
                        progress_data: 'started'
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the course modules system
QLCM_Course_Modules::get_instance();