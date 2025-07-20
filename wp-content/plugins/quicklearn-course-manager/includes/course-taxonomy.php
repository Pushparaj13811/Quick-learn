<?php
/**
 * Course Taxonomy Handler
 * 
 * Handles the registration and management of course categories taxonomy.
 * Provides admin interface for category management and associates with course post type.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course Taxonomy Class
 * 
 * Manages the course_category taxonomy registration and functionality
 */
class QLCM_Course_Taxonomy {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Taxonomy name
     */
    const TAXONOMY_NAME = 'course_category';
    
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
        add_action('init', array($this, 'register_taxonomy'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('manage_edit-' . self::TAXONOMY_NAME . '_columns', array($this, 'add_taxonomy_columns'));
        add_filter('manage_' . self::TAXONOMY_NAME . '_custom_column', array($this, 'populate_taxonomy_columns'), 10, 3);
        add_action('delete_term', array($this, 'handle_category_deletion'), 10, 2);
    }
    
    /**
     * Register the course category taxonomy
     * 
     * Requirements: 6.1 - Register custom taxonomy for course categories
     */
    public function register_taxonomy() {
        $labels = array(
            'name'                       => _x('Course Categories', 'taxonomy general name', 'quicklearn-course-manager'),
            'singular_name'              => _x('Course Category', 'taxonomy singular name', 'quicklearn-course-manager'),
            'search_items'               => __('Search Course Categories', 'quicklearn-course-manager'),
            'popular_items'              => __('Popular Course Categories', 'quicklearn-course-manager'),
            'all_items'                  => __('All Course Categories', 'quicklearn-course-manager'),
            'parent_item'                => __('Parent Course Category', 'quicklearn-course-manager'),
            'parent_item_colon'          => __('Parent Course Category:', 'quicklearn-course-manager'),
            'edit_item'                  => __('Edit Course Category', 'quicklearn-course-manager'),
            'update_item'                => __('Update Course Category', 'quicklearn-course-manager'),
            'add_new_item'               => __('Add New Course Category', 'quicklearn-course-manager'),
            'new_item_name'              => __('New Course Category Name', 'quicklearn-course-manager'),
            'separate_items_with_commas' => __('Separate course categories with commas', 'quicklearn-course-manager'),
            'add_or_remove_items'        => __('Add or remove course categories', 'quicklearn-course-manager'),
            'choose_from_most_used'      => __('Choose from the most used course categories', 'quicklearn-course-manager'),
            'not_found'                  => __('No course categories found.', 'quicklearn-course-manager'),
            'menu_name'                  => __('Course Categories', 'quicklearn-course-manager'),
        );
        
        $args = array(
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_in_nav_menus'     => true,
            'show_tagcloud'         => true,
            'show_in_rest'          => true,
            'rest_base'             => 'course-categories',
            'rewrite'               => array(
                'slug'         => 'course-category',
                'with_front'   => false,
                'hierarchical' => true,
            ),
            'capabilities'          => array(
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ),
        );
        
        // Requirements: 6.2 - Associate taxonomy with course post type
        register_taxonomy(self::TAXONOMY_NAME, array('quick_course'), $args);
    }
    
    /**
     * Add admin menu for category management
     * 
     * Requirements: 6.3 - Add admin interface for category management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Course Categories', 'quicklearn-course-manager'),
            __('Categories', 'quicklearn-course-manager'),
            'manage_options',
            'edit-tags.php?taxonomy=' . self::TAXONOMY_NAME . '&post_type=quick_course'
        );
    }
    
    /**
     * Add custom columns to taxonomy admin table
     * 
     * Requirements: 6.3 - Enhanced admin interface for category management
     */
    public function add_taxonomy_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['name'] = $columns['name'];
        $new_columns['course_count'] = __('Course Count', 'quicklearn-course-manager');
        $new_columns['slug'] = $columns['slug'];
        $new_columns['posts'] = $columns['posts'];
        
        return $new_columns;
    }
    
    /**
     * Populate custom taxonomy columns
     * 
     * Requirements: 6.3 - Enhanced admin interface for category management
     */
    public function populate_taxonomy_columns($content, $column_name, $term_id) {
        switch ($column_name) {
            case 'course_count':
                $term = get_term($term_id, self::TAXONOMY_NAME);
                if ($term && !is_wp_error($term)) {
                    $course_count = $this->get_course_count_for_category($term_id);
                    $content = sprintf(
                        '<a href="%s">%d</a>',
                        esc_url(admin_url('edit.php?post_type=quick_course&' . self::TAXONOMY_NAME . '=' . $term->slug)),
                        absint($course_count)
                    );
                }
                break;
        }
        
        return $content;
    }
    
    /**
     * Get course count for a specific category
     * 
     * Requirements: 6.4 - Handle course reassignment appropriately
     */
    private function get_course_count_for_category($term_id) {
        $args = array(
            'post_type'      => 'quick_course',
            'post_status'    => array('publish', 'private', 'draft'),
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => self::TAXONOMY_NAME,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Get all course categories
     * 
     * Utility method for frontend filtering
     */
    public function get_all_categories() {
        return get_terms(array(
            'taxonomy'   => self::TAXONOMY_NAME,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));
    }
    
    /**
     * Get courses by category
     * 
     * Utility method for filtering functionality
     */
    public function get_courses_by_category($category_id, $args = array()) {
        $default_args = array(
            'post_type'      => 'quick_course',
            'post_status'    => 'publish',
            'posts_per_page' => get_option('posts_per_page', 10),
            'tax_query'      => array(
                array(
                    'taxonomy' => self::TAXONOMY_NAME,
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                ),
            ),
        );
        
        $query_args = wp_parse_args($args, $default_args);
        return new WP_Query($query_args);
    }
    
    /**
     * Handle category deletion and course reassignment
     * 
     * Requirements: 6.4 - Handle course reassignment appropriately
     */
    public function handle_category_deletion($term_id, $taxonomy) {
        if ($taxonomy !== self::TAXONOMY_NAME) {
            return;
        }
        
        // Check user permissions before proceeding (Requirement 5.3)
        if (!current_user_can('manage_options') && !current_user_can('delete_terms', self::TAXONOMY_NAME)) {
            return;
        }
        
        // Sanitize term ID (Requirement 5.1)
        $term_id = absint($term_id);
        if ($term_id <= 0) {
            return;
        }
        
        // Get all courses in this category
        $courses = get_posts(array(
            'post_type'      => 'quick_course',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => self::TAXONOMY_NAME,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        ));
        
        // Remove the category from all courses
        foreach ($courses as $course) {
            wp_remove_object_terms($course->ID, $term_id, self::TAXONOMY_NAME);
        }
    }
    
    /**
     * Check if current user can manage course categories (Requirement 5.3)
     * 
     * @return bool True if user can manage categories, false otherwise
     */
    public function current_user_can_manage_categories() {
        return current_user_can('manage_options') || current_user_can('manage_terms', self::TAXONOMY_NAME);
    }
    
    /**
     * Validate category management permissions (Requirement 5.3)
     * 
     * @param string $action The action being performed (create, edit, delete)
     * @param int $term_id Optional term ID for specific term checks
     * @return bool True if user has permission, false otherwise
     */
    public function validate_category_permissions($action = 'manage', $term_id = 0) {
        // Sanitize inputs (Requirement 5.1)
        $action = sanitize_key($action);
        $term_id = absint($term_id);
        
        // Check basic category management capability
        if (!$this->current_user_can_manage_categories()) {
            return false;
        }
        
        // Check specific action permissions
        switch ($action) {
            case 'create':
            case 'edit':
                return current_user_can('edit_terms', self::TAXONOMY_NAME);
            case 'delete':
                return current_user_can('delete_terms', self::TAXONOMY_NAME);
            case 'assign':
                return current_user_can('assign_terms', self::TAXONOMY_NAME);
            default:
                return current_user_can('manage_terms', self::TAXONOMY_NAME);
        }
    }
    
    /**
     * Sanitize category data for database operations (Requirement 5.1)
     * 
     * @param array $category_data Raw category data
     * @return array Sanitized category data
     */
    public function sanitize_category_data($category_data) {
        $sanitized = array();
        
        if (isset($category_data['name'])) {
            $sanitized['name'] = sanitize_text_field($category_data['name']);
        }
        
        if (isset($category_data['slug'])) {
            $sanitized['slug'] = sanitize_title($category_data['slug']);
        }
        
        if (isset($category_data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($category_data['description']);
        }
        
        if (isset($category_data['parent'])) {
            $sanitized['parent'] = absint($category_data['parent']);
        }
        
        return $sanitized;
    }
}