<?php
/**
 * Unit Tests for User Enrollment System
 */

class Test_User_Enrollment extends WP_UnitTestCase {
    
    private $enrollment_instance;
    private $admin_id;
    private $user_id;
    private $course_id;
    
    public function setUp() {
        parent::setUp();
        $this->enrollment_instance = QLCM_User_Enrollment::get_instance();
        
        // Create test users
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        $this->user_id = QLCM_Test_Utilities::create_regular_user();
        
        // Create test course
        wp_set_current_user($this->admin_id);
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Enrollment Test Course',
            'post_content' => 'Course content for enrollment testing.',
        ));
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        $this->cleanup_enrollment_data();
        parent::tearDown();
    }
    
    private function cleanup_enrollment_data() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_enrollments");
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_course_progress");
    }
    
    /**
     * Test enrollment database tables creation
     * Requirements: 8.1, 8.2
     */
    public function test_enrollment_tables_creation() {
        global $wpdb;
        
        // Check if enrollment table exists
        $enrollment_table = $wpdb->prefix . 'qlcm_enrollments';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$enrollment_table}'");
        $this->assertEquals($enrollment_table, $table_exists);
        
        // Check if progress table exists
        $progress_table = $wpdb->prefix . 'qlcm_course_progress';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$progress_table}'");
        $this->assertEquals($progress_table, $table_exists);
        
        // Verify table structure
        $enrollment_columns = $wpdb->get_results("DESCRIBE {$enrollment_table}");
        $column_names = wp_list_pluck($enrollment_columns, 'Field');
        
        $expected_columns = array('id', 'user_id', 'course_id', 'enrollment_date', 'status', 'completion_date', 'progress_percentage');
        foreach ($expected_columns as $column) {
            $this->assertContains($column, $column_names);
        }
    }
    
    /**
     * Test user enrollment functionality
     * Requirements: 8.1
     */
    public function test_user_enrollment() {
        wp_set_current_user($this->user_id);
        
        // Test enrollment
        $result = $this->enrollment_instance->enroll_user($this->user_id, $this->course_id);
        $this->assertTrue($result);
        
        // Verify enrollment in database
        $enrollment = $this->enrollment_instance->get_enrollment($this->user_id, $this->course_id);
        $this->assertNotNull($enrollment);
        $this->assertEquals($this->user_id, $enrollment->user_id);
        $this->assertEquals($this->course_id, $enrollment->course_id);
        $this->assertEquals('active', $enrollment->status);
        $this->assertEquals(0, $enrollment->progress_percentage);
        
        // Test duplicate enrollment prevention
        $duplicate_result = $this->enrollment_instance->enroll_user($this->user_id, $this->course_id);
        $this->assertFalse($duplicate_result);
    }
    
    /**
     * Test enrollment status checking
     * Requirements: 8.1, 8.2
     */
    public function test_enrollment_status() {
        wp_set_current_user($this->user_id);
        
        // User not enrolled initially
        $this->assertFalse($this->enrollment_instance->is_user_enrolled($this->user_id, $this->course_id));
        
        // Enroll user
        $this->enrollment_instance->enroll_user($this->user_id, $this->course_id);
        
        // User should be enrolled now
        $this->assertTrue($this->enrollment_instance->is_user_enrolled($this->user_id, $this->course_id));
        
        // Test enrollment status
        $status = $this->enrollment_instance->get_enrollment_status($this->user_id, $this->course_id);
        $this->assertEquals('active', $status);
    }
    
    /**
     * Test progress tracking
     * Requirements: 8.2, 8.4
     */
    public function test_progress_tracking() {
        wp_set_current_user($this->user_id);
        
        // Enroll user first
        $this->enrollment_instance->enroll_user($this->user_id, $this->course_id);
        
        // Update progress
        $result = $this->enrollment_instance->update_progress($this->user_id, $this->course_id, 'module_1', 25);
        $this->assertTrue($result);
        
        // Check progress
        $progress = $this->enrollment_instance->get_course_progress($this->user_id, $this->course_id);
        $this->assertEquals(25, $progress);
        
        // Update more progress
        $this->enrollment_instance->update_progress($this->user_id, $this->course_id, 'module_2', 50);
        $this->enrollment_instance->update_progress($this->user_id, $this->course_id, 'module_3', 75);
        $this->enrollment_instance->update_progress($this->user_id, $this->course_id, 'module_4', 100);
        
        // Check final progress
        $final_progress = $this->enrollment_instance->get_course_progress($this->user_id, $this->course_id);
        $this->assertEquals(100, $final_progress);
        
        // Check completion status
        $enrollment = $this->enrollment_instance->get_enrollment($this->user_id, $this->course_id);
        $this->assertEquals('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completion_date);
    }
    
    /**
     * Test user dashboard data
     * Requirements: 8.3, 8.4
     */
    public function test_user_dashboard_data() {
        wp_set_current_user($this->user_id);
        
        // Create multiple courses and enroll user
        $course_ids = array();
        for ($i = 1; $i <= 3; $i++) {
            wp_set_current_user($this->admin_id);
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Dashboard Course {$i}",
            ));
            $course_ids[] = $course_id;
            
            wp_set_current_user($this->user_id);
            $this->enrollment_instance->enroll_user($this->user_id, $course_id);
            
            // Set different progress levels
            $this->enrollment_instance->update_progress($this->user_id, $course_id, 'module_1', $i * 25);
        }
        
        // Get dashboard data
        $dashboard_data = $this->enrollment_instance->get_user_dashboard_data($this->user_id);
        
        $this->assertArrayHasKey('enrolled_courses', $dashboard_data);
        $this->assertArrayHasKey('completed_courses', $dashboard_data);
        $this->assertArrayHasKey('in_progress_courses', $dashboard_data);
        $this->assertArrayHasKey('total_enrollments', $dashboard_data);
        
        $this->assertEquals(4, $dashboard_data['total_enrollments']); // Including the initial course
        $this->assertEquals(1, $dashboard_data['completed_courses']); // Course with 100% progress
        $this->assertEquals(3, $dashboard_data['in_progress_courses']); // Courses with < 100% progress
    }
    
    /**
     * Test AJAX enrollment handler
     * Requirements: 8.1, 8.2
     */
    public function test_ajax_enrollment_handler() {
        wp_set_current_user($this->user_id);
        
        // Mock AJAX request
        QLCM_Test_Utilities::mock_ajax_request('enroll_course', array(
            'course_id' => $this->course_id,
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->enrollment_instance->handle_enrollment_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('enrolled', $response['data']['status']);
        
        // Verify enrollment in database
        $this->assertTrue($this->enrollment_instance->is_user_enrolled($this->user_id, $this->course_id));
    }
    
    /**
     * Test enrollment security and permissions
     * Requirements: 8.5
     */
    public function test_enrollment_security() {
        // Test unauthenticated user
        wp_set_current_user(0);
        $result = $this->enrollment_instance->enroll_user(0, $this->course_id);
        $this->assertFalse($result);
        
        // Test invalid course ID
        wp_set_current_user($this->user_id);
        $result = $this->enrollment_instance->enroll_user($this->user_id, 99999);
        $this->assertFalse($result);
        
        // Test AJAX security with invalid nonce
        $_POST = array(
            'action' => 'enroll_course',
            'course_id' => $this->course_id,
            'nonce' => 'invalid_nonce',
        );
        
        ob_start();
        
        try {
            $this->enrollment_instance->handle_enrollment_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
        $this->assertContains('Security check failed', $response['data']['message']);
    }
    
    /**
     * Test enrollment statistics
     * Requirements: 11.1, 11.3
     */
    public function test_enrollment_statistics() {
        // Create multiple users and courses
        $user_ids = array();
        $course_ids = array();
        
        for ($i = 1; $i <= 3; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        wp_set_current_user($this->admin_id);
        for ($i = 1; $i <= 2; $i++) {
            $course_ids[] = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Stats Course {$i}",
            ));
        }
        
        // Enroll users in courses
        foreach ($user_ids as $user_id) {
            foreach ($course_ids as $course_id) {
                $this->enrollment_instance->enroll_user($user_id, $course_id);
                
                // Set random progress
                $progress = rand(0, 100);
                $this->enrollment_instance->update_progress($user_id, $course_id, 'module_1', $progress);
            }
        }
        
        // Get enrollment statistics
        $stats = $this->enrollment_instance->get_enrollment_statistics();
        
        $this->assertArrayHasKey('total_enrollments', $stats);
        $this->assertArrayHasKey('active_enrollments', $stats);
        $this->assertArrayHasKey('completed_enrollments', $stats);
        $this->assertArrayHasKey('completion_rate', $stats);
        
        $this->assertGreaterThan(0, $stats['total_enrollments']);
        $this->assertGreaterThanOrEqual(0, $stats['completion_rate']);
        $this->assertLessThanOrEqual(100, $stats['completion_rate']);
    }
    
    /**
     * Test course popularity tracking
     * Requirements: 11.1, 11.3
     */
    public function test_course_popularity() {
        wp_set_current_user($this->admin_id);
        
        // Create courses
        $popular_course = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Popular Course',
        ));
        
        $unpopular_course = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Unpopular Course',
        ));
        
        // Create users and enroll them
        for ($i = 1; $i <= 5; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->enrollment_instance->enroll_user($user_id, $popular_course);
        }
        
        // Only one user enrolls in unpopular course
        $user_id = QLCM_Test_Utilities::create_regular_user();
        $this->enrollment_instance->enroll_user($user_id, $unpopular_course);
        
        // Get popular courses
        $popular_courses = $this->enrollment_instance->get_popular_courses(10);
        
        $this->assertNotEmpty($popular_courses);
        $this->assertEquals($popular_course, $popular_courses[0]->course_id);
        $this->assertEquals(5, $popular_courses[0]->enrollment_count);
    }
    
    /**
     * Test enrollment performance
     * Requirements: 7.2
     */
    public function test_enrollment_performance() {
        // Test enrollment performance with multiple operations
        $start_time = microtime(true);
        
        // Perform multiple enrollments
        for ($i = 1; $i <= 10; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->enrollment_instance->enroll_user($user_id, $this->course_id);
        }
        
        $enrollment_time = microtime(true) - $start_time;
        $this->assertLessThan(2.0, $enrollment_time, 'Enrollment operations should complete within 2 seconds');
        
        // Test dashboard data retrieval performance
        $start_time = microtime(true);
        $dashboard_data = $this->enrollment_instance->get_user_dashboard_data($this->user_id);
        $dashboard_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $dashboard_time, 'Dashboard data retrieval should complete within 1 second');
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_User_Enrollment::get_instance();
        $instance2 = QLCM_User_Enrollment::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}