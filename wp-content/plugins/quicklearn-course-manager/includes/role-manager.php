<?php
/**
 * Role-Based Access Control Manager for QuickLearn Course Manager
 * 
 * Manages custom user roles, permissions, and access control
 * 
 * @package QuickLearn_Course_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Role Manager Class
 * 
 * Handles custom user roles, granular permissions, and access control
 * for the QuickLearn Course Manager plugin.
 */
class QLCM_Role_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Custom role names
     */
    const INSTRUCTOR_ROLE = 'qlcm_instructor';
    const STUDENT_ROLE = 'qlcm_student';
    const COURSE_MODERATOR_ROLE = 'qlcm_course_moderator';
    
    /**
     * Custom capabilities
     */
    private $custom_capabilities = array(
        // Course management capabilities
        'create_courses' => 'Create Courses',
        'edit_courses' => 'Edit Courses',
        'edit_others_courses' => 'Edit Others\' Courses',
        'publish_courses' => 'Publish Courses',
        'delete_courses' => 'Delete Courses',
        'delete_others_courses' => 'Delete Others\' Courses',
        'read_private_courses' => 'Read Private Courses',
        'manage_course_categories' => 'Manage Course Categories',
        
        // Enrollment capabilities
        'view_enrollments' => 'View Enrollments',
        'manage_enrollments' => 'Manage Enrollments',
        'enroll_users' => 'Enroll Users',
        'unenroll_users' => 'Unenroll Users',
        
        // Rating and review capabilities
        'moderate_reviews' => 'Moderate Reviews',
        'delete_reviews' => 'Delete Reviews',
        'view_all_reviews' => 'View All Reviews',
        
        // Certificate capabilities
        'generate_certificates' => 'Generate Certificates',
        'manage_certificates' => 'Manage Certificates',
        'view_certificates' => 'View Certificates',
        
        // Forum capabilities
        'moderate_forums' => 'Moderate Forums',
        'create_forum_topics' => 'Create Forum Topics',
        'reply_to_forums' => 'Reply to Forums',
        'delete_forum_posts' => 'Delete Forum Posts',
        
        // Analytics capabilities
        'view_course_analytics' => 'View Course Analytics',
        'view_user_analytics' => 'View User Analytics',
        'export_analytics' => 'Export Analytics',
        
        // Security capabilities
        'view_security_logs' => 'View Security Logs',
        'manage_security_settings' => 'Manage Security Settings',
    );
    
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
        // Create roles on plugin activation
        register_activation_hook(QLCM_PLUGIN_FILE, array($this, 'create_custom_roles'));
        
        // Remove roles on plugin deactivation
        register_deactivation_hook(QLCM_PLUGIN_FILE, array($this, 'remove_custom_roles'));
        
        // Add role management to admin menu
        add_action('admin_menu', array($this, 'add_role_management_menu'));
        
        // Handle role assignment
        add_action('user_register', array($this, 'assign_default_role'));
        add_action('profile_update', array($this, 'handle_role_update'));
        
        // Add custom columns to users list
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'render_user_columns'), 10, 3);
        
        // Add role selection to user profile
        add_action('show_user_profile', array($this, 'add_role_selection_to_profile'));
        add_action('edit_user_profile', array($this, 'add_role_selection_to_profile'));
        add_action('personal_options_update', array($this, 'save_user_role_selection'));
        add_action('edit_user_profile_update', array($this, 'save_user_role_selection'));
        
        // Restrict access based on roles
        add_action('admin_init', array($this, 'restrict_admin_access'));
        add_action('wp', array($this, 'restrict_frontend_access'));
        
        // Add enrollment-based content restrictions
        add_filter('the_content', array($this, 'restrict_course_content'));
        
        // AJAX handlers for role management
        add_action('wp_ajax_qlcm_assign_user_role', array($this, 'ajax_assign_user_role'));
        add_action('wp_ajax_qlcm_bulk_assign_roles', array($this, 'ajax_bulk_assign_roles'));
    }
    
    /**
     * Create custom roles and capabilities (Requirement 1.4, 8.5)
     */
    public function create_custom_roles() {
        // Remove existing roles first to ensure clean setup
        $this->remove_custom_roles();
        
        // Create Instructor role
        add_role(
            self::INSTRUCTOR_ROLE,
            __('Course Instructor', 'quicklearn-course-manager'),
            array(
                'read' => true,
                'create_courses' => true,
                'edit_courses' => true,
                'publish_courses' => true,
                'delete_courses' => true,
                'read_private_courses' => true,
                'manage_course_categories' => true,
                'view_enrollments' => true,
                'manage_enrollments' => true,
                'moderate_reviews' => true,
                'view_all_reviews' => true,
                'generate_certificates' => true,
                'view_certificates' => true,
                'moderate_forums' => true,
                'create_forum_topics' => true,
                'reply_to_forums' => true,
                'view_course_analytics' => true,
                'upload_files' => true,
            )
        );
        
        // Create Student role
        add_role(
            self::STUDENT_ROLE,
            __('Course Student', 'quicklearn-course-manager'),
            array(
                'read' => true,
                'reply_to_forums' => true,
                'view_certificates' => true,
            )
        );
        
        // Create Course Moderator role
        add_role(
            self::COURSE_MODERATOR_ROLE,
            __('Course Moderator', 'quicklearn-course-manager'),
            array(
                'read' => true,
                'edit_courses' => true,
                'edit_others_courses' => true,
                'read_private_courses' => true,
                'view_enrollments' => true,
                'manage_enrollments' => true,
                'enroll_users' => true,
                'unenroll_users' => true,
                'moderate_reviews' => true,
                'delete_reviews' => true,
                'view_all_reviews' => true,
                'moderate_forums' => true,
                'delete_forum_posts' => true,
                'view_course_analytics' => true,
                'view_user_analytics' => true,
            )
        );
        
        // Add capabilities to Administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($this->custom_capabilities as $cap => $label) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add some capabilities to Editor role for course management
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_caps = array(
                'create_courses',
                'edit_courses',
                'edit_others_courses',
                'publish_courses',
                'delete_courses',
                'read_private_courses',
                'manage_course_categories',
                'view_enrollments',
                'moderate_reviews',
                'view_all_reviews'
            );
            
            foreach ($editor_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
        
        // Log role creation
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('custom_roles_created', array(
                'roles' => array(self::INSTRUCTOR_ROLE, self::STUDENT_ROLE, self::COURSE_MODERATOR_ROLE)
            ), 'low');
        }
    }
    
    /**
     * Remove custom roles and capabilities
     */
    public function remove_custom_roles() {
        // Remove custom roles
        remove_role(self::INSTRUCTOR_ROLE);
        remove_role(self::STUDENT_ROLE);
        remove_role(self::COURSE_MODERATOR_ROLE);
        
        // Remove capabilities from existing roles
        $roles_to_clean = array('administrator', 'editor');
        
        foreach ($roles_to_clean as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($this->custom_capabilities as $cap => $label) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Add role management menu to admin
     */
    public function add_role_management_menu() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Role Management', 'quicklearn-course-manager'),
            __('User Roles', 'quicklearn-course-manager'),
            'manage_options',
            'qlcm-roles',
            array($this, 'render_role_management_page')
        );
    }
    
    /**
     * Render role management page
     */
    public function render_role_management_page() {
        $users = get_users(array('fields' => 'all'));
        $roles = wp_roles()->roles;
        
        ?>
        <div class="wrap">
            <h1><?php _e('QuickLearn Role Management', 'quicklearn-course-manager'); ?></h1>
            
            <div class="qlcm-role-management">
                <!-- Role Statistics -->
                <div class="qlcm-role-stats">
                    <h2><?php _e('Role Statistics', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-stats-grid">
                        <?php foreach ($this->get_role_statistics() as $role => $count): ?>
                            <div class="qlcm-stat-card">
                                <h3><?php echo esc_html($this->get_role_display_name($role)); ?></h3>
                                <div class="qlcm-stat-number"><?php echo $count; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Bulk Role Assignment -->
                <div class="qlcm-bulk-actions">
                    <h2><?php _e('Bulk Role Assignment', 'quicklearn-course-manager'); ?></h2>
                    <form id="qlcm-bulk-role-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="bulk-role-select"><?php _e('Assign Role', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <select id="bulk-role-select" name="role">
                                        <option value=""><?php _e('Select Role', 'quicklearn-course-manager'); ?></option>
                                        <?php foreach ($this->get_custom_roles() as $role => $data): ?>
                                            <option value="<?php echo esc_attr($role); ?>">
                                                <?php echo esc_html($data['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="bulk-users-select"><?php _e('To Users', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <select id="bulk-users-select" name="users[]" multiple size="10" style="width: 300px;">
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user->ID; ?>">
                                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple users', 'quicklearn-course-manager'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Assign Roles', 'quicklearn-course-manager'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <!-- User Role Management Table -->
                <div class="qlcm-user-roles-table">
                    <h2><?php _e('User Role Management', 'quicklearn-course-manager'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Email', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Current Role', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Enrolled Courses', 'quicklearn-course-manager'); ?></th>
                                <th><?php _e('Actions', 'quicklearn-course-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($user->display_name); ?></strong>
                                        <br>
                                        <small><?php echo esc_html($user->user_login); ?></small>
                                    </td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td>
                                        <?php 
                                        $user_roles = $user->roles;
                                        echo esc_html(implode(', ', array_map(array($this, 'get_role_display_name'), $user_roles)));
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $this->get_user_enrollment_count($user->ID); ?>
                                    </td>
                                    <td>
                                        <select class="qlcm-user-role-select" data-user-id="<?php echo $user->ID; ?>">
                                            <option value=""><?php _e('Change Role...', 'quicklearn-course-manager'); ?></option>
                                            <?php foreach ($this->get_all_roles() as $role => $data): ?>
                                                <option value="<?php echo esc_attr($role); ?>" 
                                                    <?php selected(in_array($role, $user_roles)); ?>>
                                                    <?php echo esc_html($data['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Role Capabilities Matrix -->
                <div class="qlcm-capabilities-matrix">
                    <h2><?php _e('Role Capabilities Matrix', 'quicklearn-course-manager'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Capability', 'quicklearn-course-manager'); ?></th>
                                <?php foreach ($this->get_custom_roles() as $role => $data): ?>
                                    <th><?php echo esc_html($data['name']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->custom_capabilities as $cap => $label): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($label); ?></strong></td>
                                    <?php foreach ($this->get_custom_roles() as $role => $data): ?>
                                        <td>
                                            <?php if (isset($data['capabilities'][$cap]) && $data['capabilities'][$cap]): ?>
                                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle individual role changes
            $('.qlcm-user-role-select').on('change', function() {
                var userId = $(this).data('user-id');
                var newRole = $(this).val();
                
                if (!newRole) return;
                
                if (confirm('<?php _e('Are you sure you want to change this user\'s role?', 'quicklearn-course-manager'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'qlcm_assign_user_role',
                        user_id: userId,
                        role: newRole,
                        nonce: '<?php echo wp_create_nonce('qlcm_role_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error changing role', 'quicklearn-course-manager'); ?>');
                        }
                    });
                }
            });
            
            // Handle bulk role assignment
            $('#qlcm-bulk-role-form').on('submit', function(e) {
                e.preventDefault();
                
                var role = $('#bulk-role-select').val();
                var users = $('#bulk-users-select').val();
                
                if (!role || !users || users.length === 0) {
                    alert('<?php _e('Please select a role and at least one user', 'quicklearn-course-manager'); ?>');
                    return;
                }
                
                if (confirm('<?php _e('Are you sure you want to assign this role to the selected users?', 'quicklearn-course-manager'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'qlcm_bulk_assign_roles',
                        role: role,
                        users: users,
                        nonce: '<?php echo wp_create_nonce('qlcm_role_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Error assigning roles', 'quicklearn-course-manager'); ?>');
                        }
                    });
                }
            });
        });
        </script>
        
        <style>
        .qlcm-role-management {
            max-width: 1200px;
        }
        
        .qlcm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .qlcm-stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
        }
        
        .qlcm-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .qlcm-stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .qlcm-bulk-actions,
        .qlcm-user-roles-table,
        .qlcm-capabilities-matrix {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .qlcm-bulk-actions h2,
        .qlcm-user-roles-table h2,
        .qlcm-capabilities-matrix h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Get role statistics
     */
    private function get_role_statistics() {
        $stats = array();
        $users = get_users();
        
        foreach ($users as $user) {
            foreach ($user->roles as $role) {
                if (!isset($stats[$role])) {
                    $stats[$role] = 0;
                }
                $stats[$role]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get custom roles
     */
    private function get_custom_roles() {
        $roles = wp_roles()->roles;
        $custom_roles = array();
        
        $custom_role_keys = array(
            self::INSTRUCTOR_ROLE,
            self::STUDENT_ROLE,
            self::COURSE_MODERATOR_ROLE
        );
        
        foreach ($custom_role_keys as $role_key) {
            if (isset($roles[$role_key])) {
                $custom_roles[$role_key] = $roles[$role_key];
            }
        }
        
        return $custom_roles;
    }
    
    /**
     * Get all roles (including WordPress default roles)
     */
    private function get_all_roles() {
        return wp_roles()->roles;
    }
    
    /**
     * Get role display name
     */
    private function get_role_display_name($role) {
        $roles = wp_roles()->roles;
        return isset($roles[$role]['name']) ? $roles[$role]['name'] : ucfirst($role);
    }
    
    /**
     * Get user enrollment count
     */
    private function get_user_enrollment_count($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_enrollments';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
        
        return intval($count);
    }
    
    /**
     * Assign default role to new users
     */
    public function assign_default_role($user_id) {
        $user = get_user_by('id', $user_id);
        
        if ($user && empty($user->roles)) {
            $user->add_role(self::STUDENT_ROLE);
            
            // Log role assignment
            if (class_exists('QLCM_Security_Manager')) {
                $security_manager = QLCM_Security_Manager::get_instance();
                $security_manager->log_security_event('default_role_assigned', array(
                    'user_id' => $user_id,
                    'role' => self::STUDENT_ROLE
                ), 'low');
            }
        }
    }
    
    /**
     * Handle role updates
     */
    public function handle_role_update($user_id) {
        // Log role changes for security monitoring
        if (class_exists('QLCM_Security_Manager')) {
            $user = get_user_by('id', $user_id);
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('user_role_updated', array(
                'user_id' => $user_id,
                'roles' => $user->roles,
                'updated_by' => get_current_user_id()
            ), 'medium');
        }
    }
    
    /**
     * Add custom columns to users list
     */
    public function add_user_columns($columns) {
        $columns['qlcm_role'] = __('QuickLearn Role', 'quicklearn-course-manager');
        $columns['qlcm_enrollments'] = __('Enrollments', 'quicklearn-course-manager');
        return $columns;
    }
    
    /**
     * Render custom user columns
     */
    public function render_user_columns($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'qlcm_role':
                $user = get_user_by('id', $user_id);
                $custom_roles = array_intersect($user->roles, array(
                    self::INSTRUCTOR_ROLE,
                    self::STUDENT_ROLE,
                    self::COURSE_MODERATOR_ROLE
                ));
                
                if (!empty($custom_roles)) {
                    $role_names = array_map(array($this, 'get_role_display_name'), $custom_roles);
                    return implode(', ', $role_names);
                }
                return '—';
                
            case 'qlcm_enrollments':
                return $this->get_user_enrollment_count($user_id);
        }
        
        return $value;
    }
    
    /**
     * Add role selection to user profile
     */
    public function add_role_selection_to_profile($user) {
        if (!current_user_can('promote_users')) {
            return;
        }
        
        $custom_roles = $this->get_custom_roles();
        $user_custom_roles = array_intersect($user->roles, array_keys($custom_roles));
        
        ?>
        <h3><?php _e('QuickLearn Roles', 'quicklearn-course-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Course Role', 'quicklearn-course-manager'); ?></th>
                <td>
                    <select name="qlcm_user_role">
                        <option value=""><?php _e('No specific role', 'quicklearn-course-manager'); ?></option>
                        <?php foreach ($custom_roles as $role => $data): ?>
                            <option value="<?php echo esc_attr($role); ?>" 
                                <?php selected(in_array($role, $user_custom_roles)); ?>>
                                <?php echo esc_html($data['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select a QuickLearn-specific role for this user. This is in addition to their WordPress role.', 'quicklearn-course-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user role selection from profile
     */
    public function save_user_role_selection($user_id) {
        if (!current_user_can('promote_users') || !isset($_POST['qlcm_user_role'])) {
            return;
        }
        
        $new_role = sanitize_key($_POST['qlcm_user_role']);
        $user = get_user_by('id', $user_id);
        $custom_roles = array_keys($this->get_custom_roles());
        
        // Remove existing custom roles
        foreach ($custom_roles as $role) {
            $user->remove_role($role);
        }
        
        // Add new role if specified
        if (!empty($new_role) && in_array($new_role, $custom_roles)) {
            $user->add_role($new_role);
        }
        
        // Log role change
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('user_role_changed_via_profile', array(
                'user_id' => $user_id,
                'new_role' => $new_role,
                'changed_by' => get_current_user_id()
            ), 'medium');
        }
    }
    
    /**
     * Restrict admin access based on roles (Requirement 1.4)
     */
    public function restrict_admin_access() {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // Allow full access for administrators
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Restrict access for custom roles
        if (in_array(self::STUDENT_ROLE, $current_user->roles)) {
            // Students should not access admin area except for profile
            $allowed_pages = array('profile.php', 'user-edit.php', 'admin-ajax.php');
            $current_page = basename($_SERVER['PHP_SELF']);
            
            if (!in_array($current_page, $allowed_pages)) {
                wp_redirect(home_url());
                exit;
            }
        }
        
        // Log admin access attempts
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('admin_access_attempt', array(
                'user_id' => $current_user->ID,
                'user_roles' => $current_user->roles,
                'requested_page' => $_SERVER['REQUEST_URI']
            ), 'low');
        }
    }
    
    /**
     * Restrict frontend access based on enrollment (Requirement 8.5)
     */
    public function restrict_frontend_access() {
        if (is_admin()) {
            return;
        }
        
        // Check if this is a course page
        if (is_singular('quick_course')) {
            $course_id = get_the_ID();
            $user_id = get_current_user_id();
            
            // Allow access for administrators and instructors
            if (current_user_can('manage_options') || current_user_can('edit_courses')) {
                return;
            }
            
            // Check if course requires enrollment
            $requires_enrollment = get_post_meta($course_id, '_qlcm_requires_enrollment', true);
            
            if ($requires_enrollment && $user_id) {
                $enrollment_status = $this->check_user_enrollment($user_id, $course_id);
                
                if (!$enrollment_status) {
                    // Redirect to enrollment page or show access denied
                    wp_redirect(add_query_arg('enrollment_required', '1', get_permalink($course_id)));
                    exit;
                }
            }
        }
    }
    
    /**
     * Restrict course content based on enrollment (Requirement 8.5)
     */
    public function restrict_course_content($content) {
        if (!is_singular('quick_course')) {
            return $content;
        }
        
        $course_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Allow full access for administrators and instructors
        if (current_user_can('manage_options') || current_user_can('edit_courses')) {
            return $content;
        }
        
        // Check if course requires enrollment
        $requires_enrollment = get_post_meta($course_id, '_qlcm_requires_enrollment', true);
        
        if ($requires_enrollment) {
            if (!$user_id) {
                // Not logged in
                $restricted_content = '<div class="qlcm-access-restricted">';
                $restricted_content .= '<h3>' . __('Login Required', 'quicklearn-course-manager') . '</h3>';
                $restricted_content .= '<p>' . __('You must be logged in to view this course content.', 'quicklearn-course-manager') . '</p>';
                $restricted_content .= '<a href="' . wp_login_url(get_permalink()) . '" class="button">' . __('Login', 'quicklearn-course-manager') . '</a>';
                $restricted_content .= '</div>';
                
                return $restricted_content;
            }
            
            $enrollment_status = $this->check_user_enrollment($user_id, $course_id);
            
            if (!$enrollment_status) {
                // Not enrolled
                $restricted_content = '<div class="qlcm-access-restricted">';
                $restricted_content .= '<h3>' . __('Enrollment Required', 'quicklearn-course-manager') . '</h3>';
                $restricted_content .= '<p>' . __('You must be enrolled in this course to view the full content.', 'quicklearn-course-manager') . '</p>';
                
                // Show enrollment button if available
                if (has_action('quicklearn_after_course_content')) {
                    ob_start();
                    do_action('quicklearn_after_course_content');
                    $enrollment_button = ob_get_clean();
                    $restricted_content .= $enrollment_button;
                }
                
                $restricted_content .= '</div>';
                
                // Show only excerpt or first paragraph
                $excerpt = get_the_excerpt();
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words($content, 50, '...');
                }
                
                return $excerpt . $restricted_content;
            }
        }
        
        return $content;
    }
    
    /**
     * Check if user is enrolled in a course
     */
    private function check_user_enrollment($user_id, $course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_enrollments';
        
        $enrollment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id,
            $course_id
        ));
        
        return !empty($enrollment);
    }
    
    /**
     * AJAX handler for assigning user role
     */
    public function ajax_assign_user_role() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_role_nonce') || !current_user_can('promote_users')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $user_id = intval($_POST['user_id']);
        $new_role = sanitize_key($_POST['role']);
        
        if (!$user_id || !$new_role) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'quicklearn-course-manager')));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'quicklearn-course-manager')));
        }
        
        // Remove existing custom roles
        $custom_roles = array_keys($this->get_custom_roles());
        foreach ($custom_roles as $role) {
            $user->remove_role($role);
        }
        
        // Add new role
        if (in_array($new_role, $custom_roles)) {
            $user->add_role($new_role);
        }
        
        // Log role change
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('user_role_changed_via_ajax', array(
                'user_id' => $user_id,
                'new_role' => $new_role,
                'changed_by' => get_current_user_id()
            ), 'medium');
        }
        
        wp_send_json_success(array(
            'message' => __('Role updated successfully', 'quicklearn-course-manager')
        ));
    }
    
    /**
     * AJAX handler for bulk role assignment
     */
    public function ajax_bulk_assign_roles() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_role_nonce') || !current_user_can('promote_users')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $role = sanitize_key($_POST['role']);
        $user_ids = array_map('intval', $_POST['users']);
        
        if (!$role || empty($user_ids)) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'quicklearn-course-manager')));
        }
        
        $custom_roles = array_keys($this->get_custom_roles());
        if (!in_array($role, $custom_roles)) {
            wp_send_json_error(array('message' => __('Invalid role', 'quicklearn-course-manager')));
        }
        
        $updated_count = 0;
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                // Remove existing custom roles
                foreach ($custom_roles as $existing_role) {
                    $user->remove_role($existing_role);
                }
                
                // Add new role
                $user->add_role($role);
                $updated_count++;
            }
        }
        
        // Log bulk role change
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('bulk_role_assignment', array(
                'role' => $role,
                'user_count' => $updated_count,
                'user_ids' => $user_ids,
                'assigned_by' => get_current_user_id()
            ), 'medium');
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully assigned role to %d users', 'quicklearn-course-manager'), $updated_count)
        ));
    }
    
    /**
     * Check if current user has specific capability
     * 
     * @param string $capability The capability to check
     * @return bool True if user has capability, false otherwise
     */
    public function current_user_can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Get user's QuickLearn roles
     * 
     * @param int $user_id User ID (optional, defaults to current user)
     * @return array Array of QuickLearn roles
     */
    public function get_user_quicklearn_roles($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return array();
        }
        
        $custom_role_keys = array(
            self::INSTRUCTOR_ROLE,
            self::STUDENT_ROLE,
            self::COURSE_MODERATOR_ROLE
        );
        
        return array_intersect($user->roles, $custom_role_keys);
    }
}