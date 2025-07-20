<?php
/**
 * Integration Tests for Complete Course Workflow
 */

class Test_Course_Workflow extends WP_UnitTestCase {
    
    private $admin_user_id;
    private $regular_user_id;
    
    public function setUp() {
        parent::setUp();
        
        // Create test users
        $this->admin_user_id = QLCM_Test_Utilities::create_admin_user();
        $this->regular_user_id = QLCM_Test_Utilities::create_regular_user();
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test complete admin course management workflow
     * Requirements: 1.1, 1.2, 1.3, 1.4, 6.1, 6.2, 6.3
     */
    public function test_admin_course_management_workflow() {
        // Set admin user
        wp_set_current_user($this->admin_user_id);
        
        // Step 1: Create course categories
        $web_dev_category = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Courses related to web development',
        ));
        
        $design_category = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Design',
            'slug' => 'design',
            'description' => 'Design and UI/UX courses',
        ));
        
        $this->assertNotFalse($web_dev_category);
        $this->assertNotFalse($design_category);
        
        // Step 2: Create courses with categories
        $course1_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'HTML & CSS Fundamentals',
            'post_content' => 'Learn the basics of HTML and CSS for web development.',
            'post_excerpt' => 'A comprehensive course on HTML and CSS basics.',
            'post_status' => 'publish',
        ));
        
        $course2_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'UI/UX Design Principles',
            'post_content' => 'Master the principles of user interface and user experience design.',
            'post_excerpt' => 'Learn essential UI/UX design principles.',
            'post_status' => 'publish',
        ));
        
        $this->assertNotFalse($course1_id);
        $this->assertNotFalse($course2_id);
        
        // Step 3: Assign categories to courses
        $result1 = wp_set_object_terms($course1_id, $web_dev_category, 'course_category');
        $result2 = wp_set_object_terms($course2_id, $design_category, 'course_category');
        
        $this->assertNotWPError($result1);
        $this->assertNotWPError($result2);
        
        // Step 4: Verify course-category associations
        $course1_categories = wp_get_object_terms($course1_id, 'course_category');
        $course2_categories = wp_get_object_terms($course2_id, 'course_category');
        
        $this->assertCount(1, $course1_categories);
        $this->assertCount(1, $course2_categories);
        $this->assertEquals('Web Development', $course1_categories[0]->name);
        $this->assertEquals('Design', $course2_categories[0]->name);
        
        // Step 5: Test course editing
        $updated_course_data = array(
            'ID' => $course1_id,
            'post_title' => 'Advanced HTML & CSS',
            'post_content' => 'Advanced techniques in HTML and CSS development.',
        );
        
        $update_result = wp_update_post($updated_course_data);
        $this->assertNotWPError($update_result);
        
        $updated_course = get_post($course1_id);
        $this->assertEquals('Advanced HTML & CSS', $updated_course->post_title);
        
        // Step 6: Test course deletion
        $delete_result = wp_delete_post($course2_id, true);
        $this->assertNotFalse($delete_result);
        
        $deleted_course = get_post($course2_id);
        $this->assertNull($deleted_course);
    }
    
    /**
     * Test visitor course browsing workflow
     * Requirements: 2.1, 2.2, 2.3, 2.4
     */
    public function test_visitor_course_browsing_workflow() {
        // Set up test data as admin
        wp_set_current_user($this->admin_user_id);
        
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Programming',
            'slug' => 'programming',
        ));
        
        // Create multiple courses
        $courses = array();
        for ($i = 1; $i <= 5; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Programming Course {$i}",
                'post_content' => "Content for programming course {$i}.",
                'post_excerpt' => "Excerpt for course {$i}.",
                'post_status' => 'publish',
            ));
            wp_set_object_terms($course_id, $category_id, 'course_category');
            $courses[] = $course_id;
        }
        
        // Switch to visitor (no user)
        wp_set_current_user(0);
        
        // Step 1: Test course listing query
        $courses_query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        ));
        
        $this->assertTrue($courses_query->have_posts());
        $this->assertEquals(5, $courses_query->found_posts);
        
        // Step 2: Test category filtering query
        $filtered_query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => 'programming',
                ),
            ),
        ));
        
        $this->assertTrue($filtered_query->have_posts());
        $this->assertEquals(5, $filtered_query->found_posts);
        
        // Step 3: Test individual course access
        $single_course = get_post($courses[0]);
        $this->assertNotNull($single_course);
        $this->assertEquals('quick_course', $single_course->post_type);
        $this->assertEquals('publish', $single_course->post_status);
        
        // Step 4: Test course categories display
        $course_categories = wp_get_object_terms($courses[0], 'course_category');
        $this->assertNotWPError($course_categories);
        $this->assertCount(1, $course_categories);
        $this->assertEquals('Programming', $course_categories[0]->name);
        
        wp_reset_postdata();
    }
    
    /**
     * Test AJAX filtering workflow
     * Requirements: 3.1, 3.2, 3.3, 3.4
     */
    public function test_ajax_filtering_workflow() {
        // Set up test data as admin
        wp_set_current_user($this->admin_user_id);
        
        // Create multiple categories
        $frontend_category = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Frontend',
            'slug' => 'frontend',
        ));
        
        $backend_category = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Backend',
            'slug' => 'backend',
        ));
        
        // Create courses in different categories
        $frontend_courses = array();
        for ($i = 1; $i <= 3; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Frontend Course {$i}",
                'post_status' => 'publish',
            ));
            wp_set_object_terms($course_id, $frontend_category, 'course_category');
            $frontend_courses[] = $course_id;
        }
        
        $backend_courses = array();
        for ($i = 1; $i <= 2; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Backend Course {$i}",
                'post_status' => 'publish',
            ));
            wp_set_object_terms($course_id, $backend_category, 'course_category');
            $backend_courses[] = $course_id;
        }
        
        // Switch to visitor for AJAX testing
        wp_set_current_user(0);
        
        $ajax_handler = QLCM_Ajax_Handlers::get_instance();
        
        // Test 1: Filter by frontend category
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'frontend',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(3, $response['data']['found_posts']);
        $this->assertEquals('frontend', $response['data']['category']);
        
        // Clean up for next test
        $_POST = array();
        
        // Test 2: Filter by backend category
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'backend',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['found_posts']);
        $this->assertEquals('backend', $response['data']['category']);
        
        // Clean up for next test
        $_POST = array();
        
        // Test 3: Show all courses (empty category)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => '',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(5, $response['data']['found_posts']); // All courses
        $this->assertEquals('', $response['data']['category']);
    }
    
    /**
     * Test security and permissions workflow
     * Requirements: 5.1, 5.2, 5.3, 5.4
     */
    public function test_security_and_permissions_workflow() {
        // Test 1: Regular user cannot create courses
        wp_set_current_user($this->regular_user_id);
        
        $course_cpt = QLCM_Course_CPT::get_instance();
        $this->assertFalse($course_cpt->current_user_can_manage_courses());
        $this->assertFalse($course_cpt->validate_course_permissions());
        
        // Test 2: Regular user cannot manage categories
        $taxonomy = QLCM_Course_Taxonomy::get_instance();
        $this->assertFalse($taxonomy->current_user_can_manage_categories());
        $this->assertFalse($taxonomy->validate_category_permissions('create'));
        
        // Test 3: Admin user can manage everything
        wp_set_current_user($this->admin_user_id);
        
        $this->assertTrue($course_cpt->current_user_can_manage_courses());
        $this->assertTrue($course_cpt->validate_course_permissions());
        $this->assertTrue($taxonomy->current_user_can_manage_categories());
        $this->assertTrue($taxonomy->validate_category_permissions('create'));
        
        // Test 4: Input sanitization
        $dirty_course_data = array(
            'post_title' => '<script>alert("xss")</script>Test Course',
            'post_content' => '<p>Valid content</p><script>alert("xss")</script>',
            'post_excerpt' => 'Test excerpt with <script>alert("xss")</script>',
            'post_status' => 'invalid_status',
        );
        
        $sanitized = QuickLearn_Course_Manager::sanitize_course_data($dirty_course_data);
        
        $this->assertEquals('Test Course', $sanitized['post_title']);
        $this->assertNotContains('<script>', $sanitized['post_content']);
        $this->assertNotContains('<script>', $sanitized['post_excerpt']);
        $this->assertEquals('draft', $sanitized['post_status']); // Should default to draft
        
        // Test 5: Output escaping
        $test_data = '<script>alert("xss")</script>Test Data';
        
        $escaped_html = QuickLearn_Course_Manager::escape_course_data($test_data, 'html');
        $escaped_attr = QuickLearn_Course_Manager::escape_course_data($test_data, 'attr');
        $escaped_url = QuickLearn_Course_Manager::escape_course_data('http://example.com', 'url');
        
        $this->assertNotContains('<script>', $escaped_html);
        $this->assertNotContains('<script>', $escaped_attr);
        $this->assertEquals('http://example.com', $escaped_url);
        
        // Test 6: Rate limiting
        $rate_limit_result = QuickLearn_Course_Manager::check_rate_limit('test_action', 5, 60);
        $this->assertTrue($rate_limit_result);
        
        // Exceed rate limit
        for ($i = 1; $i <= 6; $i++) {
            $result = QuickLearn_Course_Manager::check_rate_limit('test_action_2', 5, 60);
            if ($i <= 5) {
                $this->assertTrue($result);
            } else {
                $this->assertFalse($result);
            }
        }
    }
    
    /**
     * Test responsive design and performance workflow
     * Requirements: 7.1, 7.2, 7.3, 7.4
     */
    public function test_performance_workflow() {
        // Set up test data
        wp_set_current_user($this->admin_user_id);
        
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Performance Test',
            'slug' => 'performance-test',
        ));
        
        // Create multiple courses for performance testing
        $course_ids = array();
        for ($i = 1; $i <= 20; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Performance Course {$i}",
                'post_status' => 'publish',
            ));
            wp_set_object_terms($course_id, $category_id, 'course_category');
            $course_ids[] = $course_id;
        }
        
        // Test 1: Query performance
        $start_time = microtime(true);
        
        $query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => 'performance-test',
                ),
            ),
        ));
        
        $query_time = microtime(true) - $start_time;
        
        $this->assertTrue($query->have_posts());
        $this->assertEquals(20, $query->found_posts);
        $this->assertLessThan(1.0, $query_time); // Should complete within 1 second
        
        wp_reset_postdata();
        
        // Test 2: AJAX performance with caching
        wp_set_current_user(0); // Switch to visitor
        
        $ajax_handler = QLCM_Ajax_Handlers::get_instance();
        
        // First request (not cached)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'performance-test',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $first_request_time = microtime(true) - $start_time;
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertFalse($response['data']['cache_hit']);
        
        // Clean up for second request
        $_POST = array();
        
        // Second identical request (should be cached)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'performance-test',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $second_request_time = microtime(true) - $start_time;
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['cache_hit']);
        
        // Cached request should be faster
        $this->assertLessThan($first_request_time, $second_request_time);
        
        // Both requests should complete within 2 seconds (Requirement 7.2)
        $this->assertLessThan(2.0, $first_request_time);
        $this->assertLessThan(2.0, $second_request_time);
    }
    
    /**
     * Test complete user experience workflow
     * Requirements: All requirements validation
     */
    public function test_complete_user_experience_workflow() {
        // This test simulates a complete user journey from course creation to browsing
        
        // Phase 1: Admin creates content
        wp_set_current_user($this->admin_user_id);
        
        // Create categories
        $categories = array(
            array('name' => 'Web Development', 'slug' => 'web-development'),
            array('name' => 'Mobile Development', 'slug' => 'mobile-development'),
            array('name' => 'Data Science', 'slug' => 'data-science'),
        );
        
        $category_ids = array();
        foreach ($categories as $category) {
            $category_id = QLCM_Test_Utilities::create_test_category($category);
            $this->assertNotFalse($category_id);
            $category_ids[$category['slug']] = $category_id;
        }
        
        // Create courses
        $courses_data = array(
            array(
                'title' => 'HTML & CSS Basics',
                'content' => 'Learn the fundamentals of HTML and CSS.',
                'excerpt' => 'A beginner-friendly course on web markup and styling.',
                'category' => 'web-development',
            ),
            array(
                'title' => 'JavaScript Fundamentals',
                'content' => 'Master the basics of JavaScript programming.',
                'excerpt' => 'Essential JavaScript concepts for web development.',
                'category' => 'web-development',
            ),
            array(
                'title' => 'React Native Basics',
                'content' => 'Build mobile apps with React Native.',
                'excerpt' => 'Cross-platform mobile development with React Native.',
                'category' => 'mobile-development',
            ),
            array(
                'title' => 'Python for Data Science',
                'content' => 'Use Python for data analysis and machine learning.',
                'excerpt' => 'Data science fundamentals with Python.',
                'category' => 'data-science',
            ),
        );
        
        $course_ids = array();
        foreach ($courses_data as $course_data) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => $course_data['title'],
                'post_content' => $course_data['content'],
                'post_excerpt' => $course_data['excerpt'],
                'post_status' => 'publish',
            ));
            
            $this->assertNotFalse($course_id);
            
            // Assign category
            $result = wp_set_object_terms($course_id, $category_ids[$course_data['category']], 'course_category');
            $this->assertNotWPError($result);
            
            $course_ids[] = $course_id;
        }
        
        // Phase 2: Visitor browses courses
        wp_set_current_user(0);
        
        // Test all courses view
        $all_courses_query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 10,
        ));
        
        $this->assertTrue($all_courses_query->have_posts());
        $this->assertEquals(4, $all_courses_query->found_posts);
        
        // Test category filtering
        $web_dev_query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => 'web-development',
                ),
            ),
        ));
        
        $this->assertTrue($web_dev_query->have_posts());
        $this->assertEquals(2, $web_dev_query->found_posts);
        
        // Phase 3: Test AJAX filtering
        $ajax_handler = QLCM_Ajax_Handlers::get_instance();
        
        // Filter by web development
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'web-development',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        try {
            $ajax_handler->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['found_posts']);
        $this->assertEquals('Web Development', $response['data']['category_name']);
        $this->assertContains('HTML & CSS Basics', $response['data']['html']);
        $this->assertContains('JavaScript Fundamentals', $response['data']['html']);
        
        // Phase 4: Test individual course access
        $single_course = get_post($course_ids[0]);
        $this->assertNotNull($single_course);
        $this->assertEquals('HTML & CSS Basics', $single_course->post_title);
        $this->assertEquals('Learn the fundamentals of HTML and CSS.', $single_course->post_content);
        
        // Test course categories
        $course_categories = wp_get_object_terms($course_ids[0], 'course_category');
        $this->assertCount(1, $course_categories);
        $this->assertEquals('Web Development', $course_categories[0]->name);
        
        wp_reset_postdata();
        
        // Phase 5: Verify all requirements are met
        $this->assertTrue(post_type_exists('quick_course')); // Requirement 1.1
        $this->assertTrue(taxonomy_exists('course_category')); // Requirement 6.1
        
        // All courses should be accessible to visitors
        foreach ($course_ids as $course_id) {
            $course = get_post($course_id);
            $this->assertEquals('publish', $course->post_status); // Requirement 2.1
        }
        
        // Categories should be properly associated
        foreach ($course_ids as $course_id) {
            $categories = wp_get_object_terms($course_id, 'course_category');
            $this->assertNotEmpty($categories); // Requirement 6.2
        }
    }
}