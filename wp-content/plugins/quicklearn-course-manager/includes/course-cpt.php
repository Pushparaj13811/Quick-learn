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
     * Get post type slug
     */
    public static function get_post_type() {
        return self::POST_TYPE;
    }
}