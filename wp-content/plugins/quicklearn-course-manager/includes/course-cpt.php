<?php
/**
 * Course Custom Post Type Handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course Custom Post Type Class
 */
class QLCM_Course_CPT {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Post type slug
     */
    const POST_TYPE = 'quick_course';
    
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
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_init', array($this, 'add_capabilities'));
        add_filter('post_updated_messages', array($this, 'updated_messages'));
        add_filter('bulk_post_updated_messages', array($this, 'bulk_updated_messages'), 10, 2);
        
        // Add enrollment settings meta box
        add_action('add_meta_boxes', array($this, 'add_enrollment_meta_box'));
        add_action('save_post', array($this, 'save_enrollment_settings'));
    }
    
    /**
     * Register the course custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Courses', 'Post type general name', 'quicklearn-course-manager'),
            'singular_name'         => _x('Course', 'Post type singular name', 'quicklearn-course-manager'),
            'menu_name'             => _x('Courses', 'Admin Menu text', 'quicklearn-course-manager'),
            'name_admin_bar'        => _x('Course', 'Add New on Toolbar', 'quicklearn-course-manager'),
            'add_new'               => __('Add New', 'quicklearn-course-manager'),
            'add_new_item'          => __('Add New Course', 'quicklearn-course-manager'),
            'new_item'              => __('New Course', 'quicklearn-course-manager'),
            'edit_item'             => __('Edit Course', 'quicklearn-course-manager'),
            'view_item'             => __('View Course', 'quicklearn-course-manager'),
            'all_items'             => __('All Courses', 'quicklearn-course-manager'),
            'search_items'          => __('Search Courses', 'quicklearn-course-manager'),
            'parent_item_colon'     => __('Parent Courses:', 'quicklearn-course-manager'),
            'not_found'             => __('No courses found.', 'quicklearn-course-manager'),
            'not_found_in_trash'    => __('No courses found in Trash.', 'quicklearn-course-manager'),
            'featured_image'        => _x('Course Featured Image', 'Overrides the "Featured Image" phrase', 'quicklearn-course-manager'),
            'set_featured_image'    => _x('Set course image', 'Overrides the "Set featured image" phrase', 'quicklearn-course-manager'),
            'remove_featured_image' => _x('Remove course image', 'Overrides the "Remove featured image" phrase', 'quicklearn-course-manager'),
            'use_featured_image'    => _x('Use as course image', 'Overrides the "Use as featured image" phrase', 'quicklearn-course-manager'),
            'archives'              => _x('Course archives', 'The post type archive label', 'quicklearn-course-manager'),
            'insert_into_item'      => _x('Insert into course', 'Overrides the "Insert into post" phrase', 'quicklearn-course-manager'),
            'uploaded_to_this_item' => _x('Uploaded to this course', 'Overrides the "Uploaded to this post" phrase', 'quicklearn-course-manager'),
            'filter_items_list'     => _x('Filter courses list', 'Screen reader text for the filter links', 'quicklearn-course-manager'),
            'items_list_navigation' => _x('Courses list navigation', 'Screen reader text for the pagination', 'quicklearn-course-manager'),
            'items_list'            => _x('Courses list', 'Screen reader text for the items list', 'quicklearn-course-manager'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'courses'),
            'capability_type'    => 'post',
            'capabilities'       => array(
                'create_posts'       => 'manage_options', // Only admins can create courses
                'edit_posts'         => 'manage_options', // Only admins can edit courses
                'edit_others_posts'  => 'manage_options', // Only admins can edit others' courses
                'publish_posts'      => 'manage_options', // Only admins can publish courses
                'read_private_posts' => 'manage_options', // Only admins can read private courses
                'delete_posts'       => 'manage_options', // Only admins can delete courses
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options',
            ),
            'map_meta_cap'       => true,
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-book-alt',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author'),
            'taxonomies'         => array('course_category'),
            'can_export'         => true,
            'delete_with_user'   => false,
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Add capabilities to administrator role (Requirement 5.3)
     */
    public function add_capabilities() {
        // Only allow administrators to manage capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $role = get_role('administrator');
        
        if ($role) {
            $capabilities = array(
                'edit_quick_course',
                'read_quick_course',
                'delete_quick_course',
                'edit_quick_courses',
                'edit_others_quick_courses',
                'publish_quick_courses',
                'read_private_quick_courses',
                'delete_quick_courses',
                'delete_private_quick_courses',
                'delete_published_quick_courses',
                'delete_others_quick_courses',
                'edit_private_quick_courses',
                'edit_published_quick_courses',
            );
            
            foreach ($capabilities as $cap) {
                $role->add_cap($cap);
            }
        }
    }
    
    /**
     * Check if current user can manage courses (Requirement 5.3)
     * 
     * @return bool True if user can manage courses, false otherwise
     */
    public function current_user_can_manage_courses() {
        return current_user_can('manage_options') || current_user_can('edit_quick_courses');
    }
    
    /**
     * Validate course management permissions (Requirement 5.3)
     * 
     * @param int $course_id Optional course ID for specific course checks
     * @return bool True if user has permission, false otherwise
     */
    public function validate_course_permissions($course_id = 0) {
        // Check basic course management capability
        if (!$this->current_user_can_manage_courses()) {
            return false;
        }
        
        // If specific course ID provided, check edit permissions for that course
        if ($course_id > 0) {
            return current_user_can('edit_post', $course_id);
        }
        
        return true;
    }
    
    /**
     * Custom post updated messages
     */
    public function updated_messages($messages) {
        $post             = get_post();
        $post_type        = get_post_type($post);
        $post_type_object = get_post_type_object($post_type);
        
        if (self::POST_TYPE !== $post_type) {
            return $messages;
        }
        
        $messages[self::POST_TYPE] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => __('Course updated.', 'quicklearn-course-manager'),
            2  => __('Custom field updated.', 'quicklearn-course-manager'),
            3  => __('Custom field deleted.', 'quicklearn-course-manager'),
            4  => __('Course updated.', 'quicklearn-course-manager'),
            5  => isset($_GET['revision']) ? sprintf(__('Course restored to revision from %s', 'quicklearn-course-manager'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Course published.', 'quicklearn-course-manager'),
            7  => __('Course saved.', 'quicklearn-course-manager'),
            8  => __('Course submitted.', 'quicklearn-course-manager'),
            9  => sprintf(
                __('Course scheduled for: <strong>%1$s</strong>.', 'quicklearn-course-manager'),
                date_i18n(__('M j, Y @ G:i', 'quicklearn-course-manager'), strtotime($post->post_date))
            ),
            10 => __('Course draft updated.', 'quicklearn-course-manager')
        );
        
        if ($post_type_object->publicly_queryable && self::POST_TYPE === $post_type) {
            $permalink = get_permalink($post->ID);
            
            $view_link = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View course', 'quicklearn-course-manager'));
            $messages[self::POST_TYPE][1] .= $view_link;
            $messages[self::POST_TYPE][6] .= $view_link;
            $messages[self::POST_TYPE][9] .= $view_link;
            
            $preview_permalink = add_query_arg('preview', 'true', $permalink);
            $preview_link = sprintf(' <a target="_blank" href="%s">%s</a>', esc_url($preview_permalink), __('Preview course', 'quicklearn-course-manager'));
            $messages[self::POST_TYPE][8]  .= $preview_link;
            $messages[self::POST_TYPE][10] .= $preview_link;
        }
        
        return $messages;
    }
    
    /**
     * Custom bulk updated messages
     */
    public function bulk_updated_messages($bulk_messages, $bulk_counts) {
        $bulk_messages[self::POST_TYPE] = array(
            'updated'   => _n('%s course updated.', '%s courses updated.', $bulk_counts['updated'], 'quicklearn-course-manager'),
            'locked'    => _n('%s course not updated, somebody is editing it.', '%s courses not updated, somebody is editing them.', $bulk_counts['locked'], 'quicklearn-course-manager'),
            'deleted'   => _n('%s course permanently deleted.', '%s courses permanently deleted.', $bulk_counts['deleted'], 'quicklearn-course-manager'),
            'trashed'   => _n('%s course moved to the Trash.', '%s courses moved to the Trash.', $bulk_counts['trashed'], 'quicklearn-course-manager'),
            'untrashed' => _n('%s course restored from the Trash.', '%s courses restored from the Trash.', $bulk_counts['untrashed'], 'quicklearn-course-manager'),
        );
        
        return $bulk_messages;
    }
    
    /**
     * Add enrollment settings meta box (Requirement 8.5)
     */
    public function add_enrollment_meta_box() {
        add_meta_box(
            'qlcm_enrollment_settings',
            __('Enrollment Settings', 'quicklearn-course-manager'),
            array($this, 'render_enrollment_meta_box'),
            self::POST_TYPE,
            'side',
            'high'
        );
    }
    
    /**
     * Render enrollment settings meta box
     */
    public function render_enrollment_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('qlcm_enrollment_settings', 'qlcm_enrollment_nonce');
        
        // Get current settings
        $requires_enrollment = get_post_meta($post->ID, '_qlcm_requires_enrollment', true);
        $enrollment_limit = get_post_meta($post->ID, '_qlcm_enrollment_limit', true);
        $enrollment_start_date = get_post_meta($post->ID, '_qlcm_enrollment_start_date', true);
        $enrollment_end_date = get_post_meta($post->ID, '_qlcm_enrollment_end_date', true);
        $prerequisite_courses = get_post_meta($post->ID, '_qlcm_prerequisite_courses', true);
        
        ?>
        <table class="form-table">
            <tr>
                <td>
                    <label>
                        <input type="checkbox" name="qlcm_requires_enrollment" value="1" 
                               <?php checked($requires_enrollment, '1'); ?> />
                        <?php _e('Require enrollment to view content', 'quicklearn-course-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('If checked, users must be enrolled to view the full course content.', 'quicklearn-course-manager'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="qlcm_enrollment_limit"><?php _e('Enrollment Limit', 'quicklearn-course-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="qlcm_enrollment_limit" name="qlcm_enrollment_limit" 
                           value="<?php echo esc_attr($enrollment_limit); ?>" min="0" class="small-text" />
                    <p class="description">
                        <?php _e('Maximum number of students that can enroll. Leave empty for unlimited.', 'quicklearn-course-manager'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="qlcm_enrollment_start_date"><?php _e('Enrollment Start Date', 'quicklearn-course-manager'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" id="qlcm_enrollment_start_date" name="qlcm_enrollment_start_date" 
                           value="<?php echo esc_attr($enrollment_start_date); ?>" />
                    <p class="description">
                        <?php _e('When enrollment opens. Leave empty to allow immediate enrollment.', 'quicklearn-course-manager'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="qlcm_enrollment_end_date"><?php _e('Enrollment End Date', 'quicklearn-course-manager'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" id="qlcm_enrollment_end_date" name="qlcm_enrollment_end_date" 
                           value="<?php echo esc_attr($enrollment_end_date); ?>" />
                    <p class="description">
                        <?php _e('When enrollment closes. Leave empty for no end date.', 'quicklearn-course-manager'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="qlcm_prerequisite_courses"><?php _e('Prerequisite Courses', 'quicklearn-course-manager'); ?></label>
                </th>
                <td>
                    <?php
                    $courses = get_posts(array(
                        'post_type' => self::POST_TYPE,
                        'post_status' => 'publish',
                        'numberposts' => -1,
                        'exclude' => array($post->ID),
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    
                    if (!empty($courses)) {
                        $selected_prerequisites = is_array($prerequisite_courses) ? $prerequisite_courses : array();
                        
                        echo '<select id="qlcm_prerequisite_courses" name="qlcm_prerequisite_courses[]" multiple size="5" style="width: 100%;">';
                        foreach ($courses as $course) {
                            $selected = in_array($course->ID, $selected_prerequisites) ? 'selected' : '';
                            echo '<option value="' . esc_attr($course->ID) . '" ' . $selected . '>';
                            echo esc_html($course->post_title);
                            echo '</option>';
                        }
                        echo '</select>';
                        echo '<p class="description">' . __('Hold Ctrl/Cmd to select multiple courses that must be completed before enrolling.', 'quicklearn-course-manager') . '</p>';
                    } else {
                        echo '<p>' . __('No other courses available as prerequisites.', 'quicklearn-course-manager') . '</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        
        <style>
        #qlcm_enrollment_settings .form-table th {
            width: 120px;
            padding-left: 0;
        }
        
        #qlcm_enrollment_settings .form-table td {
            padding-left: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Save enrollment settings (Requirement 8.5)
     */
    public function save_enrollment_settings($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['qlcm_enrollment_nonce']) || 
            !wp_verify_nonce($_POST['qlcm_enrollment_nonce'], 'qlcm_enrollment_settings')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get security manager for input sanitization
        $security_manager = class_exists('QLCM_Security_Manager') ? QLCM_Security_Manager::get_instance() : null;
        
        // Save requires enrollment setting
        $requires_enrollment = isset($_POST['qlcm_requires_enrollment']) ? '1' : '0';
        update_post_meta($post_id, '_qlcm_requires_enrollment', $requires_enrollment);
        
        // Save enrollment limit
        if (isset($_POST['qlcm_enrollment_limit'])) {
            $enrollment_limit = $security_manager ? 
                $security_manager->sanitize_input($_POST['qlcm_enrollment_limit'], 'int') : 
                absint($_POST['qlcm_enrollment_limit']);
            
            if ($enrollment_limit > 0) {
                update_post_meta($post_id, '_qlcm_enrollment_limit', $enrollment_limit);
            } else {
                delete_post_meta($post_id, '_qlcm_enrollment_limit');
            }
        }
        
        // Save enrollment dates
        if (isset($_POST['qlcm_enrollment_start_date'])) {
            $start_date = $security_manager ? 
                $security_manager->sanitize_input($_POST['qlcm_enrollment_start_date'], 'text') : 
                sanitize_text_field($_POST['qlcm_enrollment_start_date']);
            
            if (!empty($start_date)) {
                update_post_meta($post_id, '_qlcm_enrollment_start_date', $start_date);
            } else {
                delete_post_meta($post_id, '_qlcm_enrollment_start_date');
            }
        }
        
        if (isset($_POST['qlcm_enrollment_end_date'])) {
            $end_date = $security_manager ? 
                $security_manager->sanitize_input($_POST['qlcm_enrollment_end_date'], 'text') : 
                sanitize_text_field($_POST['qlcm_enrollment_end_date']);
            
            if (!empty($end_date)) {
                update_post_meta($post_id, '_qlcm_enrollment_end_date', $end_date);
            } else {
                delete_post_meta($post_id, '_qlcm_enrollment_end_date');
            }
        }
        
        // Save prerequisite courses
        if (isset($_POST['qlcm_prerequisite_courses']) && is_array($_POST['qlcm_prerequisite_courses'])) {
            $prerequisite_courses = array_map('absint', $_POST['qlcm_prerequisite_courses']);
            $prerequisite_courses = array_filter($prerequisite_courses); // Remove empty values
            
            if (!empty($prerequisite_courses)) {
                update_post_meta($post_id, '_qlcm_prerequisite_courses', $prerequisite_courses);
            } else {
                delete_post_meta($post_id, '_qlcm_prerequisite_courses');
            }
        } else {
            delete_post_meta($post_id, '_qlcm_prerequisite_courses');
        }
        
        // Log enrollment settings change for security monitoring
        if ($security_manager) {
            $security_manager->log_security_event('course_enrollment_settings_updated', array(
                'course_id' => $post_id,
                'requires_enrollment' => $requires_enrollment,
                'enrollment_limit' => isset($enrollment_limit) ? $enrollment_limit : null,
                'updated_by' => get_current_user_id()
            ), 'low');
        }
    }
    
    /**
     * Check if enrollment is allowed for a course
     * 
     * @param int $course_id Course ID
     * @param int $user_id User ID (optional, defaults to current user)
     * @return array Array with 'allowed' boolean and 'message' string
     */
    public function check_enrollment_allowed($course_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check if enrollment is required
        $requires_enrollment = get_post_meta($course_id, '_qlcm_requires_enrollment', true);
        if (!$requires_enrollment) {
            return array('allowed' => true, 'message' => '');
        }
        
        // Check enrollment dates
        $start_date = get_post_meta($course_id, '_qlcm_enrollment_start_date', true);
        $end_date = get_post_meta($course_id, '_qlcm_enrollment_end_date', true);
        $current_time = current_time('mysql');
        
        if (!empty($start_date) && $current_time < $start_date) {
            return array(
                'allowed' => false, 
                'message' => sprintf(__('Enrollment opens on %s', 'quicklearn-course-manager'), 
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start_date)))
            );
        }
        
        if (!empty($end_date) && $current_time > $end_date) {
            return array(
                'allowed' => false, 
                'message' => __('Enrollment has closed for this course', 'quicklearn-course-manager')
            );
        }
        
        // Check enrollment limit
        $enrollment_limit = get_post_meta($course_id, '_qlcm_enrollment_limit', true);
        if (!empty($enrollment_limit)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'qlcm_enrollments';
            
            $current_enrollments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE course_id = %d AND status = 'active'",
                $course_id
            ));
            
            if ($current_enrollments >= $enrollment_limit) {
                return array(
                    'allowed' => false, 
                    'message' => __('This course has reached its enrollment limit', 'quicklearn-course-manager')
                );
            }
        }
        
        // Check prerequisites
        $prerequisite_courses = get_post_meta($course_id, '_qlcm_prerequisite_courses', true);
        if (!empty($prerequisite_courses) && is_array($prerequisite_courses)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'qlcm_enrollments';
            
            foreach ($prerequisite_courses as $prereq_id) {
                $completed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} 
                     WHERE user_id = %d AND course_id = %d AND status = 'completed'",
                    $user_id,
                    $prereq_id
                ));
                
                if (!$completed) {
                    $prereq_title = get_the_title($prereq_id);
                    return array(
                        'allowed' => false, 
                        'message' => sprintf(__('You must complete "%s" before enrolling in this course', 'quicklearn-course-manager'), $prereq_title)
                    );
                }
            }
        }
        
        return array('allowed' => true, 'message' => '');
    }
    
    /**
     * Get post type slug
     */
    public static function get_post_type() {
        return self::POST_TYPE;
    }
}