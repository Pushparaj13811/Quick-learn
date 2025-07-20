<?php
/**
 * Certificate System Test Functions
 * For testing certificate generation during development
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test certificate generation
 * This function can be called to test certificate generation
 */
function qlcm_test_certificate_generation() {
    // Only allow administrators to run tests
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to run this test.', 'quicklearn-course-manager'));
    }
    
    // Get current user
    $user_id = get_current_user_id();
    
    // Get first available course
    $courses = get_posts(array(
        'post_type' => 'quick_course',
        'post_status' => 'publish',
        'numberposts' => 1
    ));
    
    if (empty($courses)) {
        wp_die(__('No courses found. Please create a course first.', 'quicklearn-course-manager'));
    }
    
    $course_id = $courses[0]->ID;
    
    // Simulate course completion
    $enrollment_system = QLCM_User_Enrollment::get_instance();
    
    // Enroll user if not already enrolled
    $enrollment_id = $enrollment_system->enroll_user($user_id, $course_id);
    
    if (!$enrollment_id) {
        wp_die(__('Failed to enroll user in course.', 'quicklearn-course-manager'));
    }
    
    // Update enrollment to completed status
    global $wpdb;
    $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
    
    $result = $wpdb->update(
        $enrollments_table,
        array(
            'status' => 'completed',
            'progress_percentage' => 100,
            'completion_date' => current_time('mysql')
        ),
        array('id' => $enrollment_id),
        array('%s', '%d', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_die(__('Failed to update enrollment status.', 'quicklearn-course-manager'));
    }
    
    // Trigger certificate generation
    do_action('qlcm_course_completed', $user_id, $course_id);
    
    // Check if certificate was generated
    $certificate_system = QLCM_Certificate_System::get_instance();
    $certificate = $certificate_system->get_user_certificate($user_id, $course_id);
    
    if ($certificate) {
        $message = sprintf(
            __('Certificate generated successfully!<br>Certificate ID: %s<br>Verification Code: %s<br><a href="%s" target="_blank">Download Certificate</a><br><a href="%s" target="_blank">Verify Certificate</a>', 'quicklearn-course-manager'),
            $certificate->certificate_id,
            $certificate->verification_code,
            home_url('/certificate/download/' . $certificate->certificate_id),
            home_url('/certificate/verify/' . $certificate->verification_code)
        );
        
        wp_die($message, __('Certificate Test Successful', 'quicklearn-course-manager'));
    } else {
        wp_die(__('Certificate generation failed.', 'quicklearn-course-manager'));
    }
}

// Add test endpoint for administrators
add_action('wp_ajax_test_certificate_generation', 'qlcm_test_certificate_generation');

/**
 * Add certificate test link to admin bar (for development)
 */
function qlcm_add_certificate_test_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node(array(
        'id' => 'qlcm-test-certificate',
        'title' => __('Test Certificate', 'quicklearn-course-manager'),
        'href' => admin_url('admin-ajax.php?action=test_certificate_generation'),
        'meta' => array(
            'target' => '_blank'
        )
    ));
}

// Only add test link in development environment
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_bar_menu', 'qlcm_add_certificate_test_admin_bar', 100);
}