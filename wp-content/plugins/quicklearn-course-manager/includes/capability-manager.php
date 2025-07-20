<?php
/**
 * Capability Management Interface for QuickLearn Course Manager
 * 
 * Provides admin interface for managing granular permissions and capabilities
 * 
 * @package QuickLearn_Course_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capability Manager Class
 * 
 * Handles the admin interface for managing user capabilities and permissions
 */
class QLCM_Capability_Manager {
    
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
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_qlcm_update_role_capabilities', array($this, 'ajax_update_role_capabilities'));
        add_action('wp_ajax_qlcm_create_custom_capability', array($this, 'ajax_create_custom_capability'));
        add_action('wp_ajax_qlcm_reset_role_capabilities', array($this, 'ajax_reset_role_capabilities'));
    }
    
    /**
     * Add admin menu for capability management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Capability Management', 'quicklearn-course-manager'),
            __('Capabilities', 'quicklearn-course-manager'),
            'manage_options',
            'qlcm-capabilities',
            array($this, 'render_capability_management_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'qlcm-capabilities') !== false) {
            wp_enqueue_script(
                'qlcm-capability-manager',
                QLCM_PLUGIN_URL . 'assets/js/capability-manager.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-capability-manager',
                QLCM_PLUGIN_URL . 'assets/css/capability-manager.css',
                array(),
                QLCM_VERSION
            );
            
            wp_localize_script('qlcm-capability-manager', 'qlcm_capabilities', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_capability_nonce'),
                'i18n' => array(
                    'loading' => __('Loading...', 'quicklearn-course-manager'),
                    'saving' => __('Saving...', 'quicklearn-course-manager'),
                    'saved' => __('Capabilities updated successfully!', 'quicklearn-course-manager'),
                    'error' => __('Error updating capabilities', 'quicklearn-course-manager'),
                    'confirm_reset' => __('Are you sure you want to reset all capabilities for this role? This cannot be undone.', 'quicklearn-course-manager'),
                    'confirm_create' => __('Are you sure you want to create this custom capability?', 'quicklearn-course-manager'),
                )
            ));
        }
    }
    
    /**
     * Render capability management page
     */
    public function render_capability_management_page() {
        $roles = wp_roles()->roles;
        $role_manager = QLCM_Role_Manager::get_instance();
        
        ?>
        <div class="wrap">
            <h1><?php _e('QuickLearn Capability Management', 'quicklearn-course-manager'); ?></h1>
            
            <div class="qlcm-capability-management">
                <!-- Capability Overview -->
                <div class="qlcm-capability-overview">
                    <h2><?php _e('Capability Overview', 'quicklearn-course-manager'); ?></h2>
                    <p class="description">
                        <?php _e('Manage granular permissions for different user roles. Changes take effect immediately.', 'quicklearn-course-manager'); ?>
                    </p>
                    
                    <div class="qlcm-capability-stats">
                        <?php $this->render_capability_statistics(); ?>
                    </div>
                </div>
                
                <!-- Role Tabs -->
                <div class="qlcm-role-tabs">
                    <h2 class="nav-tab-wrapper">
                        <?php foreach ($roles as $role_key => $role_data): ?>
                            <a href="#role-<?php echo esc_attr($role_key); ?>" 
                               class="nav-tab <?php echo $role_key === 'administrator' ? 'nav-tab-active' : ''; ?>"
                               data-role="<?php echo esc_attr($role_key); ?>">
                                <?php echo esc_html($role_data['name']); ?>
                                <span class="qlcm-capability-count">
                                    (<?php echo count($role_data['capabilities']); ?>)
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </h2>
                </div>
                
                <!-- Role Capability Panels -->
                <?php foreach ($roles as $role_key => $role_data): ?>
                    <div id="role-<?php echo esc_attr($role_key); ?>" 
                         class="qlcm-role-panel <?php echo $role_key === 'administrator' ? 'active' : ''; ?>">
                        
                        <div class="qlcm-role-header">
                            <h3><?php echo esc_html($role_data['name']); ?> <?php _e('Capabilities', 'quicklearn-course-manager'); ?></h3>
                            <div class="qlcm-role-actions">
                                <button class="button qlcm-save-capabilities" data-role="<?php echo esc_attr($role_key); ?>">
                                    <?php _e('Save Changes', 'quicklearn-course-manager'); ?>
                                </button>
                                <button class="button button-secondary qlcm-reset-capabilities" data-role="<?php echo esc_attr($role_key); ?>">
                                    <?php _e('Reset to Default', 'quicklearn-course-manager'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <form class="qlcm-capabilities-form" data-role="<?php echo esc_attr($role_key); ?>">
                            <?php $this->render_capability_groups($role_key, $role_data['capabilities']); ?>
                        </form>
                    </div>
                <?php endforeach; ?>
                
                <!-- Custom Capability Creation -->
                <div class="qlcm-custom-capability-section">
                    <h2><?php _e('Create Custom Capability', 'quicklearn-course-manager'); ?></h2>
                    <form id="qlcm-custom-capability-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="custom-capability-name"><?php _e('Capability Name', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="custom-capability-name" name="capability_name" 
                                           class="regular-text" placeholder="e.g., manage_special_courses" />
                                    <p class="description">
                                        <?php _e('Use lowercase letters, numbers, and underscores only.', 'quicklearn-course-manager'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="custom-capability-label"><?php _e('Display Label', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="custom-capability-label" name="capability_label" 
                                           class="regular-text" placeholder="e.g., Manage Special Courses" />
                                    <p class="description">
                                        <?php _e('Human-readable name for this capability.', 'quicklearn-course-manager'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="custom-capability-description"><?php _e('Description', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <textarea id="custom-capability-description" name="capability_description" 
                                              class="large-text" rows="3" 
                                              placeholder="<?php _e('Describe what this capability allows users to do...', 'quicklearn-course-manager'); ?>"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="custom-capability-roles"><?php _e('Assign to Roles', 'quicklearn-course-manager'); ?></label>
                                </th>
                                <td>
                                    <?php foreach ($roles as $role_key => $role_data): ?>
                                        <label>
                                            <input type="checkbox" name="assign_to_roles[]" value="<?php echo esc_attr($role_key); ?>" />
                                            <?php echo esc_html($role_data['name']); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Create Capability', 'quicklearn-course-manager'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <!-- Capability Audit Log -->
                <div class="qlcm-capability-audit">
                    <h2><?php _e('Recent Capability Changes', 'quicklearn-course-manager'); ?></h2>
                    <div class="qlcm-audit-log">
                        <?php $this->render_capability_audit_log(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render capability statistics
     */
    private function render_capability_statistics() {
        $roles = wp_roles()->roles;
        $total_capabilities = 0;
        $custom_capabilities = 0;
        $role_count = count($roles);
        
        foreach ($roles as $role_data) {
            $total_capabilities += count($role_data['capabilities']);
        }
        
        // Count custom QuickLearn capabilities
        $role_manager = QLCM_Role_Manager::get_instance();
        $custom_caps = get_option('qlcm_custom_capabilities', array());
        $custom_capabilities = count($custom_caps);
        
        ?>
        <div class="qlcm-stats-grid">
            <div class="qlcm-stat-item">
                <div class="qlcm-stat-number"><?php echo $role_count; ?></div>
                <div class="qlcm-stat-label"><?php _e('User Roles', 'quicklearn-course-manager'); ?></div>
            </div>
            
            <div class="qlcm-stat-item">
                <div class="qlcm-stat-number"><?php echo $total_capabilities; ?></div>
                <div class="qlcm-stat-label"><?php _e('Total Capabilities', 'quicklearn-course-manager'); ?></div>
            </div>
            
            <div class="qlcm-stat-item">
                <div class="qlcm-stat-number"><?php echo $custom_capabilities; ?></div>
                <div class="qlcm-stat-label"><?php _e('Custom Capabilities', 'quicklearn-course-manager'); ?></div>
            </div>
            
            <div class="qlcm-stat-item">
                <div class="qlcm-stat-number"><?php echo $this->count_users_with_custom_roles(); ?></div>
                <div class="qlcm-stat-label"><?php _e('Users with Custom Roles', 'quicklearn-course-manager'); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render capability groups for a role
     */
    private function render_capability_groups($role_key, $role_capabilities) {
        $capability_groups = $this->get_capability_groups();
        
        foreach ($capability_groups as $group_key => $group_data) {
            echo '<div class="qlcm-capability-group">';
            echo '<h4>' . esc_html($group_data['label']) . '</h4>';
            echo '<p class="description">' . esc_html($group_data['description']) . '</p>';
            
            echo '<div class="qlcm-capabilities-grid">';
            
            foreach ($group_data['capabilities'] as $cap_key => $cap_label) {
                $has_capability = isset($role_capabilities[$cap_key]);
                $is_inherited = $this->is_capability_inherited($role_key, $cap_key);
                
                echo '<label class="qlcm-capability-item">';
                echo '<input type="checkbox" name="capabilities[]" value="' . esc_attr($cap_key) . '" ';
                checked($has_capability);
                if ($is_inherited) {
                    echo ' data-inherited="true"';
                }
                echo ' />';
                echo '<span class="qlcm-capability-label">' . esc_html($cap_label) . '</span>';
                
                if ($is_inherited) {
                    echo '<span class="qlcm-inherited-badge">' . __('Inherited', 'quicklearn-course-manager') . '</span>';
                }
                echo '</label>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        // Show other capabilities not in groups
        $other_capabilities = array_diff_key($role_capabilities, $this->get_all_grouped_capabilities());
        
        if (!empty($other_capabilities)) {
            echo '<div class="qlcm-capability-group">';
            echo '<h4>' . __('Other Capabilities', 'quicklearn-course-manager') . '</h4>';
            echo '<div class="qlcm-capabilities-grid">';
            
            foreach ($other_capabilities as $cap_key => $cap_value) {
                if ($cap_value) {
                    echo '<label class="qlcm-capability-item">';
                    echo '<input type="checkbox" name="capabilities[]" value="' . esc_attr($cap_key) . '" checked />';
                    echo '<span class="qlcm-capability-label">' . esc_html(ucwords(str_replace('_', ' ', $cap_key))) . '</span>';
                    echo '</label>';
                }
            }
            
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Get capability groups
     */
    private function get_capability_groups() {
        return array(
            'course_management' => array(
                'label' => __('Course Management', 'quicklearn-course-manager'),
                'description' => __('Capabilities related to creating, editing, and managing courses.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'create_courses' => __('Create Courses', 'quicklearn-course-manager'),
                    'edit_courses' => __('Edit Courses', 'quicklearn-course-manager'),
                    'edit_others_courses' => __('Edit Others\' Courses', 'quicklearn-course-manager'),
                    'publish_courses' => __('Publish Courses', 'quicklearn-course-manager'),
                    'delete_courses' => __('Delete Courses', 'quicklearn-course-manager'),
                    'delete_others_courses' => __('Delete Others\' Courses', 'quicklearn-course-manager'),
                    'read_private_courses' => __('Read Private Courses', 'quicklearn-course-manager'),
                    'manage_course_categories' => __('Manage Course Categories', 'quicklearn-course-manager'),
                )
            ),
            'enrollment_management' => array(
                'label' => __('Enrollment Management', 'quicklearn-course-manager'),
                'description' => __('Capabilities for managing student enrollments and progress.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'view_enrollments' => __('View Enrollments', 'quicklearn-course-manager'),
                    'manage_enrollments' => __('Manage Enrollments', 'quicklearn-course-manager'),
                    'enroll_users' => __('Enroll Users', 'quicklearn-course-manager'),
                    'unenroll_users' => __('Unenroll Users', 'quicklearn-course-manager'),
                )
            ),
            'content_moderation' => array(
                'label' => __('Content Moderation', 'quicklearn-course-manager'),
                'description' => __('Capabilities for moderating reviews, forums, and user-generated content.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'moderate_reviews' => __('Moderate Reviews', 'quicklearn-course-manager'),
                    'delete_reviews' => __('Delete Reviews', 'quicklearn-course-manager'),
                    'view_all_reviews' => __('View All Reviews', 'quicklearn-course-manager'),
                    'moderate_forums' => __('Moderate Forums', 'quicklearn-course-manager'),
                    'create_forum_topics' => __('Create Forum Topics', 'quicklearn-course-manager'),
                    'reply_to_forums' => __('Reply to Forums', 'quicklearn-course-manager'),
                    'delete_forum_posts' => __('Delete Forum Posts', 'quicklearn-course-manager'),
                )
            ),
            'certificates' => array(
                'label' => __('Certificate Management', 'quicklearn-course-manager'),
                'description' => __('Capabilities for generating and managing course certificates.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'generate_certificates' => __('Generate Certificates', 'quicklearn-course-manager'),
                    'manage_certificates' => __('Manage Certificates', 'quicklearn-course-manager'),
                    'view_certificates' => __('View Certificates', 'quicklearn-course-manager'),
                )
            ),
            'analytics' => array(
                'label' => __('Analytics & Reporting', 'quicklearn-course-manager'),
                'description' => __('Capabilities for viewing analytics and generating reports.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'view_course_analytics' => __('View Course Analytics', 'quicklearn-course-manager'),
                    'view_user_analytics' => __('View User Analytics', 'quicklearn-course-manager'),
                    'export_analytics' => __('Export Analytics', 'quicklearn-course-manager'),
                )
            ),
            'security' => array(
                'label' => __('Security Management', 'quicklearn-course-manager'),
                'description' => __('Capabilities for managing security settings and monitoring.', 'quicklearn-course-manager'),
                'capabilities' => array(
                    'view_security_logs' => __('View Security Logs', 'quicklearn-course-manager'),
                    'manage_security_settings' => __('Manage Security Settings', 'quicklearn-course-manager'),
                )
            )
        );
    }
    
    /**
     * Get all capabilities that are grouped
     */
    private function get_all_grouped_capabilities() {
        $grouped_caps = array();
        $groups = $this->get_capability_groups();
        
        foreach ($groups as $group) {
            $grouped_caps = array_merge($grouped_caps, $group['capabilities']);
        }
        
        return $grouped_caps;
    }
    
    /**
     * Check if capability is inherited from parent role
     */
    private function is_capability_inherited($role_key, $capability) {
        // For now, we'll consider administrator capabilities as inherited by other roles
        // This is a simplified implementation - you could expand this logic
        if ($role_key !== 'administrator') {
            $admin_role = get_role('administrator');
            return $admin_role && $admin_role->has_cap($capability);
        }
        
        return false;
    }
    
    /**
     * Count users with custom QuickLearn roles
     */
    private function count_users_with_custom_roles() {
        $custom_roles = array(
            QLCM_Role_Manager::INSTRUCTOR_ROLE,
            QLCM_Role_Manager::STUDENT_ROLE,
            QLCM_Role_Manager::COURSE_MODERATOR_ROLE
        );
        
        $count = 0;
        foreach ($custom_roles as $role) {
            $users = get_users(array('role' => $role, 'fields' => 'ID'));
            $count += count($users);
        }
        
        return $count;
    }
    
    /**
     * Render capability audit log
     */
    private function render_capability_audit_log() {
        global $wpdb;
        
        if (!class_exists('QLCM_Security_Manager')) {
            echo '<p>' . __('Security logging not available.', 'quicklearn-course-manager') . '</p>';
            return;
        }
        
        $security_table = $wpdb->prefix . 'qlcm_security_log';
        
        $capability_events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$security_table} 
             WHERE event_type IN ('user_role_changed_via_ajax', 'user_role_changed_via_profile', 'capability_updated', 'custom_capability_created')
             ORDER BY created_at DESC 
             LIMIT %d",
            10
        ));
        
        if (empty($capability_events)) {
            echo '<p>' . __('No recent capability changes.', 'quicklearn-course-manager') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Date', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Event', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('User', 'quicklearn-course-manager') . '</th>';
        echo '<th>' . __('Details', 'quicklearn-course-manager') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($capability_events as $event) {
            $event_data = json_decode($event->event_data, true);
            $user = get_user_by('id', $event->user_id);
            
            echo '<tr>';
            echo '<td>' . human_time_diff(strtotime($event->created_at), current_time('timestamp')) . ' ' . __('ago', 'quicklearn-course-manager') . '</td>';
            echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $event->event_type))) . '</td>';
            echo '<td>' . ($user ? esc_html($user->display_name) : __('Unknown', 'quicklearn-course-manager')) . '</td>';
            echo '<td>';
            
            if (isset($event_data['role'])) {
                echo __('Role:', 'quicklearn-course-manager') . ' ' . esc_html($event_data['role']);
            }
            if (isset($event_data['capability'])) {
                echo __('Capability:', 'quicklearn-course-manager') . ' ' . esc_html($event_data['capability']);
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * AJAX handler for updating role capabilities
     */
    public function ajax_update_role_capabilities() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_capability_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $role_key = sanitize_key($_POST['role']);
        $capabilities = isset($_POST['capabilities']) ? array_map('sanitize_key', $_POST['capabilities']) : array();
        
        $role = get_role($role_key);
        if (!$role) {
            wp_send_json_error(array('message' => __('Role not found', 'quicklearn-course-manager')));
        }
        
        // Get all possible capabilities for this role
        $all_capabilities = $this->get_all_grouped_capabilities();
        
        // Remove all QuickLearn capabilities first
        foreach ($all_capabilities as $cap => $label) {
            $role->remove_cap($cap);
        }
        
        // Add selected capabilities
        foreach ($capabilities as $capability) {
            if (array_key_exists($capability, $all_capabilities)) {
                $role->add_cap($capability);
            }
        }
        
        // Log capability change
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('role_capabilities_updated', array(
                'role' => $role_key,
                'capabilities' => $capabilities,
                'updated_by' => get_current_user_id()
            ), 'medium');
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Capabilities updated for %s role', 'quicklearn-course-manager'), $role->name)
        ));
    }
    
    /**
     * AJAX handler for creating custom capability
     */
    public function ajax_create_custom_capability() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_capability_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $capability_name = sanitize_key($_POST['capability_name']);
        $capability_label = sanitize_text_field($_POST['capability_label']);
        $capability_description = sanitize_textarea_field($_POST['capability_description']);
        $assign_to_roles = isset($_POST['assign_to_roles']) ? array_map('sanitize_key', $_POST['assign_to_roles']) : array();
        
        if (empty($capability_name) || empty($capability_label)) {
            wp_send_json_error(array('message' => __('Capability name and label are required', 'quicklearn-course-manager')));
        }
        
        // Check if capability already exists
        $admin_role = get_role('administrator');
        if ($admin_role && $admin_role->has_cap($capability_name)) {
            wp_send_json_error(array('message' => __('Capability already exists', 'quicklearn-course-manager')));
        }
        
        // Store custom capability metadata
        $custom_capabilities = get_option('qlcm_custom_capabilities', array());
        $custom_capabilities[$capability_name] = array(
            'label' => $capability_label,
            'description' => $capability_description,
            'created_by' => get_current_user_id(),
            'created_date' => current_time('mysql')
        );
        update_option('qlcm_custom_capabilities', $custom_capabilities);
        
        // Assign to selected roles
        foreach ($assign_to_roles as $role_key) {
            $role = get_role($role_key);
            if ($role) {
                $role->add_cap($capability_name);
            }
        }
        
        // Log capability creation
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('custom_capability_created', array(
                'capability' => $capability_name,
                'label' => $capability_label,
                'assigned_to_roles' => $assign_to_roles,
                'created_by' => get_current_user_id()
            ), 'medium');
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Custom capability "%s" created successfully', 'quicklearn-course-manager'), $capability_label)
        ));
    }
    
    /**
     * AJAX handler for resetting role capabilities
     */
    public function ajax_reset_role_capabilities() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_capability_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'quicklearn-course-manager')));
        }
        
        $role_key = sanitize_key($_POST['role']);
        
        $role = get_role($role_key);
        if (!$role) {
            wp_send_json_error(array('message' => __('Role not found', 'quicklearn-course-manager')));
        }
        
        // Get default capabilities for this role
        $default_capabilities = $this->get_default_role_capabilities($role_key);
        
        // Remove all current capabilities
        $current_capabilities = $role->capabilities;
        foreach ($current_capabilities as $cap => $value) {
            $role->remove_cap($cap);
        }
        
        // Add default capabilities
        foreach ($default_capabilities as $cap) {
            $role->add_cap($cap);
        }
        
        // Log capability reset
        if (class_exists('QLCM_Security_Manager')) {
            $security_manager = QLCM_Security_Manager::get_instance();
            $security_manager->log_security_event('role_capabilities_reset', array(
                'role' => $role_key,
                'reset_by' => get_current_user_id()
            ), 'high');
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Capabilities reset to default for %s role', 'quicklearn-course-manager'), $role->name)
        ));
    }
    
    /**
     * Get default capabilities for a role
     */
    private function get_default_role_capabilities($role_key) {
        $defaults = array(
            'administrator' => array('read', 'manage_options'),
            'editor' => array('read', 'edit_posts', 'edit_others_posts', 'publish_posts'),
            'author' => array('read', 'edit_posts', 'publish_posts'),
            'contributor' => array('read', 'edit_posts'),
            'subscriber' => array('read'),
            QLCM_Role_Manager::INSTRUCTOR_ROLE => array('read', 'create_courses', 'edit_courses'),
            QLCM_Role_Manager::STUDENT_ROLE => array('read'),
            QLCM_Role_Manager::COURSE_MODERATOR_ROLE => array('read', 'moderate_reviews', 'moderate_forums')
        );
        
        return isset($defaults[$role_key]) ? $defaults[$role_key] : array('read');
    }
}