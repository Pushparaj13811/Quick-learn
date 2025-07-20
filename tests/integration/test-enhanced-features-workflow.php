<?php
/**
 * Integration Tests for Enhanced Features Workflow
 */

class Test_Enhanced_Features_Workflow extends WP_UnitTestCase {
    
    private $admin_id;
    private $instructor_id;
    private $student_id;
    private $course_id;
    private $category_id;
    
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        $this->instructor_id = $this->factory()->user->create(array(
            'role' => 'instructor',
            'user_login' => 'test_instructor',
            'user_email' => 'instructor@test.com',
        ));
        $this->student_id = QLCM_Test_Utilities::create_regular_user();
        
        // Create test course and category
        wp_set_current_user($this->admin_id);
        $this->category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Integration Test Category',
            'slug' => 'integration-test-category',
        ));
        
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Complete Integration Test Course',
            'post_content' => 'This is a comprehensive course for integration testing.',
            'post_excerpt' => 'Learn everything about integration testing.',
        ));
        
        wp_set_object_terms($this->course_id, $this->category_id, 'course_category');
        QLCM_Test_Utilities::set_featured_image($this->course_id);
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        $this->cleanup_all_test_data();
        parent::tearDown();
    }
    
    private function cleanup_all_test_data() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_enrollments");
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_course_progress");
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_course_ratings");
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_certificates");
    }
    
    /**
     * Test complete student learning workflow
     * Requirements: 8.1, 8.2, 8.3, 8.4, 9.1, 9.2, 12.1, 12.3
     */
    public function test_complete_student_learning_workflow() {
        wp_set_current_user($this->student_id);
        
        // Step 1: Student discovers course through SEO-optimized listing
        $seo_instance = QLCM_SEO_Optimization::get_instance();
        $structured_data = $seo_instance->generate_course_structured_data($this->course_id);
        $this->assertNotEmpty($structured_data);
        $this->assertStringContains('"@type": "Course"', $structured_data);
        
        // Step 2: Student enrolls in course
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        $enrollment_result = $enrollment_instance->enroll_user($this->student_id, $this->course_id);
        $this->assertTrue($enrollment_result);
        
        // Verify enrollment
        $is_enrolled = $enrollment_instance->is_user_enrolled($this->student_id, $this->course_id);
        $this->assertTrue($is_enrolled);
        
        // Step 3: Student progresses through course modules
        $progress_updates = array(
            'module_1' => 25,
            'module_2' => 50,
            'module_3' => 75,
            'module_4' => 100,
        );
        
        foreach ($progress_updates as $module => $progress) {
            $progress_result = $enrollment_instance->update_progress($this->student_id, $this->course_id, $module, $progress);
            $this->assertTrue($progress_result);
        }
        
        // Verify final progress
        $final_progress = $enrollment_instance->get_course_progress($this->student_id, $this->course_id);
        $this->assertEquals(100, $final_progress);
        
        // Step 4: Student receives certificate upon completion
        $certificate_instance = QLCM_Certificate_System::get_instance();
        $certificate_id = $certificate_instance->generate_certificate($this->student_id, $this->course_id);
        $this->assertNotEmpty($certificate_id);
        
        // Verify certificate
        $certificate = $certificate_instance->get_certificate($this->student_id, $this->course_id);
        $this->assertNotNull($certificate);
        $this->assertEquals($this->student_id, $certificate->user_id);
        $this->assertEquals($this->course_id, $certificate->course_id);
        
        // Step 5: Student rates and reviews the course
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $rating_result = $ratings_instance->submit_rating($this->student_id, $this->course_id, 5, 'Excellent course! Learned a lot.');
        $this->assertTrue($rating_result);
        
        // Verify rating
        $user_rating = $ratings_instance->get_user_rating($this->student_id, $this->course_id);
        $this->assertEquals(5, $user_rating->rating);
        $this->assertEquals('Excellent course! Learned a lot.', $user_rating->review_text);
        
        // Step 6: Student accesses dashboard to view achievements
        $dashboard_data = $enrollment_instance->get_user_dashboard_data($this->student_id);
        $this->assertArrayHasKey('enrolled_courses', $dashboard_data);
        $this->assertArrayHasKey('completed_courses', $dashboard_data);
        $this->assertEquals(1, $dashboard_data['completed_courses']);
        
        // Step 7: Student downloads certificate
        $can_download = $certificate_instance->can_user_download_certificate($this->student_id, $certificate_id);
        $this->assertTrue($can_download);
        
        $download_url = $certificate_instance->get_download_url($certificate_id);
        $this->assertNotEmpty($download_url);
        $this->assertStringContains($certificate_id, $download_url);
    }
    
    /**
     * Test instructor course management workflow
     * Requirements: 1.1, 1.2, 13.1, 13.2, 11.2, 11.4
     */
    public function test_instructor_course_management_workflow() {
        wp_set_current_user($this->instructor_id);
        
        // Step 1: Instructor creates a new course
        $instructor_course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Instructor Created Course',
            'post_content' => 'Course created by instructor for testing.',
            'post_author' => $this->instructor_id,
        ));
        
        $this->assertNotFalse($instructor_course_id);
        
        // Step 2: Instructor adds multimedia content
        $multimedia_instance = QLCM_Multimedia_Content::get_instance();
        
        // Add video content
        $upload_dir = wp_upload_dir();
        $video_path = $upload_dir['basedir'] . '/instructor-video.mp4';
        file_put_contents($video_path, 'instructor video content');
        
        $video_id = $multimedia_instance->create_video_attachment($video_path, array(
            'post_title' => 'Course Introduction Video',
            'post_parent' => $instructor_course_id,
        ));
        
        $this->assertNotFalse($video_id);
        
        // Step 3: Instructor organizes course modules
        $modules_instance = QLCM_Course_Modules::get_instance();
        $module_id = $modules_instance->create_module($instructor_course_id, array(
            'title' => 'Introduction Module',
            'description' => 'Course introduction and overview',
            'order' => 1,
        ));
        
        $this->assertNotFalse($module_id);
        
        // Add lesson to module
        $lesson_id = $modules_instance->create_lesson($module_id, array(
            'title' => 'Welcome Lesson',
            'content' => 'Welcome to the course!',
            'video_id' => $video_id,
            'order' => 1,
        ));
        
        $this->assertNotFalse($lesson_id);
        
        // Step 4: Students enroll and progress
        $student_ids = array();
        for ($i = 1; $i <= 5; $i++) {
            $student_id = QLCM_Test_Utilities::create_regular_user();
            $student_ids[] = $student_id;
            
            $enrollment_instance = QLCM_User_Enrollment::get_instance();
            $enrollment_instance->enroll_user($student_id, $instructor_course_id);
            
            // Simulate different progress levels
            $progress = rand(25, 100);
            $enrollment_instance->update_progress($student_id, $instructor_course_id, 'module_1', $progress);
            
            // Some students rate the course
            if ($i <= 3) {
                $ratings_instance = QLCM_Course_Ratings::get_instance();
                $ratings_instance->submit_rating($student_id, $instructor_course_id, rand(4, 5), "Great course by instructor!");
            }
        }
        
        // Step 5: Instructor views analytics and reports
        $analytics_instance = QLCM_Analytics_Reporting::get_instance();
        $course_analytics = $analytics_instance->get_course_analytics($instructor_course_id);
        
        $this->assertArrayHasKey('enrollment_count', $course_analytics);
        $this->assertArrayHasKey('completion_rate', $course_analytics);
        $this->assertArrayHasKey('average_rating', $course_analytics);
        $this->assertEquals(5, $course_analytics['enrollment_count']);
        $this->assertGreaterThan(0, $course_analytics['average_rating']);
        
        // Step 6: Instructor moderates reviews
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $course_ratings = $ratings_instance->get_course_ratings($instructor_course_id);
        
        foreach ($course_ratings as $rating) {
            $moderation_result = $ratings_instance->moderate_rating($rating->id, 'approved');
            $this->assertTrue($moderation_result);
        }
    }
    
    /**
     * Test admin management and oversight workflow
     * Requirements: 11.2, 11.4, 19.1, 19.2
     */
    public function test_admin_management_workflow() {
        wp_set_current_user($this->admin_id);
        
        // Step 1: Admin views system-wide analytics
        $analytics_instance = QLCM_Analytics_Reporting::get_instance();
        $system_stats = $analytics_instance->get_system_statistics();
        
        $this->assertArrayHasKey('total_courses', $system_stats);
        $this->assertArrayHasKey('total_enrollments', $system_stats);
        $this->assertArrayHasKey('total_users', $system_stats);
        $this->assertArrayHasKey('completion_rate', $system_stats);
        
        // Step 2: Admin manages user roles and capabilities
        $role_manager = QLCM_Role_Manager::get_instance();
        
        // Create instructor role if not exists
        $instructor_role_created = $role_manager->create_instructor_role();
        $this->assertTrue($instructor_role_created);
        
        // Assign capabilities
        $capability_result = $role_manager->assign_course_capabilities($this->instructor_id);
        $this->assertTrue($capability_result);
        
        // Step 3: Admin monitors security
        $security_manager = QLCM_Security_Manager::get_instance();
        $security_report = $security_manager->generate_security_report();
        
        $this->assertArrayHasKey('failed_login_attempts', $security_report);
        $this->assertArrayHasKey('suspicious_activities', $security_report);
        $this->assertArrayHasKey('security_score', $security_report);
        
        // Step 4: Admin optimizes performance
        $optimization_instance = QLCM_Database_Optimization::get_instance();
        $optimization_result = $optimization_instance->optimize_database();
        $this->assertTrue($optimization_result);
        
        // Step 5: Admin manages certificates
        $certificate_instance = QLCM_Certificate_System::get_instance();
        $certificate_stats = $certificate_instance->get_certificate_statistics();
        
        $this->assertArrayHasKey('total_certificates', $certificate_stats);
        $this->assertArrayHasKey('certificates_this_month', $certificate_stats);
        
        // Step 6: Admin reviews and moderates content
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $pending_reviews = $ratings_instance->get_pending_reviews();
        
        foreach ($pending_reviews as $review) {
            $moderation_result = $ratings_instance->moderate_rating($review->id, 'approved');
            $this->assertTrue($moderation_result);
        }
    }
    
    /**
     * Test SEO and marketing workflow
     * Requirements: 10.1, 10.2, 10.3, 10.4
     */
    public function test_seo_marketing_workflow() {
        // Step 1: Course gets SEO optimization
        $seo_instance = QLCM_SEO_Optimization::get_instance();
        
        // Generate structured data
        $structured_data = $seo_instance->generate_course_structured_data($this->course_id);
        $this->assertNotEmpty($structured_data);
        
        // Generate meta tags
        $meta_tags = $seo_instance->generate_all_meta_tags($this->course_id);
        $this->assertArrayHasKey('description', $meta_tags);
        $this->assertArrayHasKey('og:title', $meta_tags);
        $this->assertArrayHasKey('twitter:card', $meta_tags);
        
        // Step 2: Course appears in XML sitemap
        $sitemap_entries = $seo_instance->get_sitemap_entries();
        $course_in_sitemap = false;
        
        foreach ($sitemap_entries as $entry) {
            if ($entry['id'] == $this->course_id && $entry['type'] == 'quick_course') {
                $course_in_sitemap = true;
                break;
            }
        }
        
        $this->assertTrue($course_in_sitemap);
        
        // Step 3: Course gets rated and reviews improve SEO
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        
        // Add multiple ratings
        for ($i = 1; $i <= 5; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $ratings_instance->submit_rating($user_id, $this->course_id, rand(4, 5), "SEO test review {$i}");
        }
        
        // Verify structured data includes ratings
        $structured_data_with_ratings = $seo_instance->generate_course_structured_data($this->course_id);
        $this->assertStringContains('aggregateRating', $structured_data_with_ratings);
        
        // Step 4: Social sharing optimization
        $social_tags = $seo_instance->generate_social_sharing_tags($this->course_id);
        $this->assertArrayHasKey('og:image', $social_tags);
        $this->assertArrayHasKey('og:description', $social_tags);
        $this->assertArrayHasKey('twitter:image', $social_tags);
    }
    
    /**
     * Test performance under load
     * Requirements: 7.1, 7.2, 7.3, 7.4
     */
    public function test_performance_under_load() {
        // Create multiple courses and users
        $course_ids = array();
        $user_ids = array();
        
        // Create 10 courses
        wp_set_current_user($this->admin_id);
        for ($i = 1; $i <= 10; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Performance Test Course {$i}",
            ));
            $course_ids[] = $course_id;
            wp_set_object_terms($course_id, $this->category_id, 'course_category');
        }
        
        // Create 20 users
        for ($i = 1; $i <= 20; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        // Test enrollment performance
        $start_time = microtime(true);
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        
        foreach ($user_ids as $user_id) {
            foreach ($course_ids as $course_id) {
                $enrollment_instance->enroll_user($user_id, $course_id);
            }
        }
        
        $enrollment_time = microtime(true) - $start_time;
        $this->assertLessThan(10.0, $enrollment_time, 'Mass enrollment should complete within 10 seconds');
        
        // Test AJAX filtering performance with many courses
        $ajax_instance = QLCM_Ajax_Handlers::get_instance();
        
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'integration-test-category',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        ob_get_clean();
        
        $ajax_time = microtime(true) - $start_time;
        $this->assertLessThan(2.0, $ajax_time, 'AJAX filtering should complete within 2 seconds');
        
        // Test analytics performance
        $analytics_instance = QLCM_Analytics_Reporting::get_instance();
        
        $start_time = microtime(true);
        $system_stats = $analytics_instance->get_system_statistics();
        $analytics_time = microtime(true) - $start_time;
        
        $this->assertLessThan(3.0, $analytics_time, 'Analytics generation should complete within 3 seconds');
        $this->assertEquals(11, $system_stats['total_courses']); // 10 + original course
        $this->assertEquals(200, $system_stats['total_enrollments']); // 20 users * 10 courses
    }
    
    /**
     * Test security across all features
     * Requirements: 5.1, 5.2, 5.3, 5.4, 19.1, 19.2
     */
    public function test_comprehensive_security() {
        // Test unauthorized access prevention
        wp_set_current_user(0); // Logout
        
        // Test enrollment security
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        $unauthorized_enrollment = $enrollment_instance->enroll_user(0, $this->course_id);
        $this->assertFalse($unauthorized_enrollment);
        
        // Test rating security
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $unauthorized_rating = $ratings_instance->submit_rating(0, $this->course_id, 5, 'Unauthorized review');
        $this->assertFalse($unauthorized_rating);
        
        // Test certificate security
        $certificate_instance = QLCM_Certificate_System::get_instance();
        $unauthorized_certificate = $certificate_instance->generate_certificate(0, $this->course_id);
        $this->assertFalse($unauthorized_certificate);
        
        // Test AJAX security with invalid nonces
        $_POST = array(
            'action' => 'enroll_course',
            'course_id' => $this->course_id,
            'nonce' => 'invalid_nonce',
        );
        
        ob_start();
        try {
            $enrollment_instance->handle_enrollment_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
        
        // Test XSS prevention
        wp_set_current_user($this->student_id);
        $malicious_review = '<script>alert("xss")</script>Malicious content';
        $xss_result = $ratings_instance->submit_rating($this->student_id, $this->course_id, 5, $malicious_review);
        $this->assertTrue($xss_result);
        
        $stored_rating = $ratings_instance->get_user_rating($this->student_id, $this->course_id);
        $this->assertNotContains('<script>', $stored_rating->review_text);
        
        // Test SQL injection prevention
        $malicious_course_id = "1'; DROP TABLE courses; --";
        $sql_injection_result = $enrollment_instance->enroll_user($this->student_id, $malicious_course_id);
        $this->assertFalse($sql_injection_result);
        
        // Verify tables still exist
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}posts'");
        $this->assertNotEmpty($table_exists);
    }
    
    /**
     * Test data consistency across features
     * Requirements: All enhanced requirements
     */
    public function test_data_consistency() {
        wp_set_current_user($this->student_id);
        
        // Enroll student
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        $enrollment_instance->enroll_user($this->student_id, $this->course_id);
        
        // Complete course
        $enrollment_instance->update_progress($this->student_id, $this->course_id, 'module_1', 100);
        
        // Generate certificate
        $certificate_instance = QLCM_Certificate_System::get_instance();
        $certificate_id = $certificate_instance->generate_certificate($this->student_id, $this->course_id);
        
        // Rate course
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $ratings_instance->submit_rating($this->student_id, $this->course_id, 5, 'Consistent data test');
        
        // Verify data consistency across all systems
        
        // Check enrollment status
        $enrollment = $enrollment_instance->get_enrollment($this->student_id, $this->course_id);
        $this->assertEquals('completed', $enrollment->status);
        $this->assertEquals(100, $enrollment->progress_percentage);
        
        // Check certificate data matches enrollment
        $certificate = $certificate_instance->get_certificate($this->student_id, $this->course_id);
        $certificate_data = json_decode($certificate->certificate_data, true);
        $this->assertEquals($this->student_id, $certificate->user_id);
        $this->assertEquals($this->course_id, $certificate->course_id);
        
        // Check rating is associated with correct course and user
        $rating = $ratings_instance->get_user_rating($this->student_id, $this->course_id);
        $this->assertEquals($this->student_id, $rating->user_id);
        $this->assertEquals($this->course_id, $rating->course_id);
        
        // Check analytics reflect all activities
        $analytics_instance = QLCM_Analytics_Reporting::get_instance();
        $course_analytics = $analytics_instance->get_course_analytics($this->course_id);
        
        $this->assertGreaterThan(0, $course_analytics['enrollment_count']);
        $this->assertGreaterThan(0, $course_analytics['completion_rate']);
        $this->assertGreaterThan(0, $course_analytics['average_rating']);
        
        // Check SEO data includes rating information
        $seo_instance = QLCM_SEO_Optimization::get_instance();
        $structured_data = $seo_instance->generate_course_structured_data($this->course_id);
        $this->assertStringContains('aggregateRating', $structured_data);
    }
}