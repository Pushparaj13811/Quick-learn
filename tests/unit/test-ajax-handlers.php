<?php
/**
 * Unit Tests for AJAX Handlers
 */

class Test_Ajax_Handlers extends WP_UnitTestCase {
    
    private $ajax_instance;
    
    public function setUp() {
        parent::setUp();
        $this->ajax_instance = QLCM_Ajax_Handlers::get_instance();
        
        // Set up AJAX environment
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        
        // Clean up $_POST data
        $_POST = array();
        
        parent::tearDown();
    }
    
    /**
     * Test AJAX handler registration
     * Requirements: 3.1, 3.2
     */
    public function test_ajax_handler_registration() {
        // Test that AJAX actions are registered
        $this->assertTrue(has_action('wp_ajax_filter_courses'));
        $this->assertTrue(has_action('wp_ajax_nopriv_filter_courses'));
        
        // Test that both logged-in and non-logged-in users can access
        $this->assertEquals(10, has_action('wp_ajax_filter_courses', array($this->ajax_instance, 'handle_course_filter')));
        $this->assertEquals(10, has_action('wp_ajax_nopriv_filter_courses', array($this->ajax_instance, 'handle_course_filter')));
    }
    
    /**
     * Test successful course filtering
     * Requirements: 3.1, 3.2, 3.3
     */
    public function test_successful_course_filtering() {
        // Create test data
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Web Development',
            'slug' => 'web-development',
        ));
        
        // Create courses
        for ($i = 1; $i <= 3; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Web Course {$i}",
                'post_excerpt' => "Excerpt for course {$i}",
            ));
            wp_set_object_terms($course_id, $category_id, 'course_category');
        }
        
        // Mock AJAX request
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'web-development',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals(3, $response['data']['found_posts']);
        $this->assertEquals('web-development', $response['data']['category']);
        $this->assertTrue($response['data']['has_courses']);
        $this->assertNotEmpty($response['data']['html']);
    }
    
    /**
     * Test filtering with no results
     * Requirements: 2.4, 7.1
     */
    public function test_filtering_with_no_results() {
        // Create category but no courses
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Empty Category',
            'slug' => 'empty-category',
        ));
        
        // Mock AJAX request
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'empty-category',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['data']['found_posts']);
        $this->assertFalse($response['data']['has_courses']);
        $this->assertContains('no-courses-found', $response['data']['html']);
        $this->assertContains('Empty Category', $response['data']['html']);
    }
    
    /**
     * Test nonce verification failure
     * Requirements: 5.2
     */
    public function test_nonce_verification_failure() {
        // Mock AJAX request with invalid nonce
        $_POST['action'] = 'filter_courses';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['category'] = 'test-category';
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertContains('Security check failed', $response['data']['message']);
    }
    
    /**
     * Test input sanitization
     * Requirements: 5.1
     */
    public function test_input_sanitization() {
        // Create test data
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Mock AJAX request with malicious input
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => '<script>alert("xss")</script>web-dev',
            'posts_per_page' => '100000', // Excessive number
            'paged' => '-5', // Negative number
        ));
        
        // Use reflection to test private sanitization methods
        $reflection = new ReflectionClass($this->ajax_instance);
        
        // Test category sanitization
        $sanitize_category = $reflection->getMethod('sanitize_category_input');
        $sanitize_category->setAccessible(true);
        $clean_category = $sanitize_category->invoke($this->ajax_instance, '<script>alert("xss")</script>web-dev');
        $this->assertEquals('web-dev', $clean_category);
        
        // Test posts per page sanitization
        $sanitize_posts = $reflection->getMethod('sanitize_posts_per_page');
        $sanitize_posts->setAccessible(true);
        $clean_posts = $sanitize_posts->invoke($this->ajax_instance, '100000');
        $this->assertEquals(50, $clean_posts); // Should be capped at 50
        
        // Test page number sanitization
        $sanitize_page = $reflection->getMethod('sanitize_page_number');
        $sanitize_page->setAccessible(true);
        $clean_page = $sanitize_page->invoke($this->ajax_instance, '-5');
        $this->assertEquals(1, $clean_page); // Should default to 1
    }
    
    /**
     * Test invalid category handling
     * Requirements: 5.1
     */
    public function test_invalid_category_handling() {
        // Mock AJAX request with non-existent category
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'non-existent-category',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
        $this->assertContains('Invalid category', $response['data']['message']);
    }
    
    /**
     * Test all categories filtering (empty category)
     * Requirements: 3.4
     */
    public function test_all_categories_filtering() {
        // Create test data
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create courses in different categories
        $category1_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Category 1',
            'slug' => 'category-1',
        ));
        
        $category2_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Category 2',
            'slug' => 'category-2',
        ));
        
        // Create courses
        $course1_id = QLCM_Test_Utilities::create_test_course(array('post_title' => 'Course 1'));
        $course2_id = QLCM_Test_Utilities::create_test_course(array('post_title' => 'Course 2'));
        
        wp_set_object_terms($course1_id, $category1_id, 'course_category');
        wp_set_object_terms($course2_id, $category2_id, 'course_category');
        
        // Mock AJAX request with empty category (all categories)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => '',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['found_posts']);
        $this->assertEquals('', $response['data']['category']);
        $this->assertTrue($response['data']['has_courses']);
    }
    
    /**
     * Test pagination functionality
     * Requirements: 3.3
     */
    public function test_pagination_functionality() {
        // Create test data
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Paginated Category',
            'slug' => 'paginated-category',
        ));
        
        // Create 15 courses
        for ($i = 1; $i <= 15; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Course {$i}",
            ));
            wp_set_object_terms($course_id, $category_id, 'course_category');
        }
        
        // Test first page (10 courses per page)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'paginated-category',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(15, $response['data']['found_posts']);
        $this->assertEquals(2, $response['data']['max_num_pages']);
        $this->assertEquals(1, $response['data']['current_page']);
        
        // Clean up for second request
        $_POST = array();
        
        // Test second page
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'paginated-category',
            'posts_per_page' => 10,
            'paged' => 2,
        ));
        
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(15, $response['data']['found_posts']);
        $this->assertEquals(2, $response['data']['max_num_pages']);
        $this->assertEquals(2, $response['data']['current_page']);
    }
    
    /**
     * Test performance and caching
     * Requirements: 7.2
     */
    public function test_performance_and_caching() {
        // Create test data
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Performance Test',
            'slug' => 'performance-test',
        ));
        
        $course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Performance Course',
        ));
        wp_set_object_terms($course_id, $category_id, 'course_category');
        
        // First request (should not be cached)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'performance-test',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output1 = ob_get_clean();
        $response1 = json_decode($output1, true);
        
        $this->assertNotNull($response1);
        $this->assertTrue($response1['success']);
        $this->assertFalse($response1['data']['cache_hit']);
        
        // Clean up for second request
        $_POST = array();
        
        // Second identical request (should be cached)
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'performance-test',
            'posts_per_page' => 10,
            'paged' => 1,
        ));
        
        ob_start();
        
        try {
            $this->ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output2 = ob_get_clean();
        $response2 = json_decode($output2, true);
        
        $this->assertNotNull($response2);
        $this->assertTrue($response2['success']);
        $this->assertTrue($response2['data']['cache_hit']);
        
        // Response time should be faster for cached request
        $this->assertLessThan($response1['data']['response_time'], $response2['data']['response_time']);
    }
    
    /**
     * Test rate limiting
     * Requirements: 5.2
     */
    public function test_rate_limiting() {
        // Test rate limiting by making multiple rapid requests
        for ($i = 1; $i <= 35; $i++) { // Exceed the limit of 30 requests per minute
            $result = QuickLearn_Course_Manager::check_rate_limit('filter_courses', 30, 60);
            
            if ($i <= 30) {
                $this->assertTrue($result, "Request {$i} should be allowed");
            } else {
                $this->assertFalse($result, "Request {$i} should be rate limited");
            }
        }
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Ajax_Handlers::get_instance();
        $instance2 = QLCM_Ajax_Handlers::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    /**
     * Test cache key generation
     */
    public function test_cache_key_generation() {
        $reflection = new ReflectionClass($this->ajax_instance);
        $method = $reflection->getMethod('generate_cache_key');
        $method->setAccessible(true);
        
        $post_data1 = array(
            'category' => 'test-category',
            'posts_per_page' => 10,
            'paged' => 1,
        );
        
        $post_data2 = array(
            'category' => 'test-category',
            'posts_per_page' => 10,
            'paged' => 1,
        );
        
        $post_data3 = array(
            'category' => 'different-category',
            'posts_per_page' => 10,
            'paged' => 1,
        );
        
        $key1 = $method->invoke($this->ajax_instance, $post_data1);
        $key2 = $method->invoke($this->ajax_instance, $post_data2);
        $key3 = $method->invoke($this->ajax_instance, $post_data3);
        
        // Same data should generate same key
        $this->assertEquals($key1, $key2);
        
        // Different data should generate different key
        $this->assertNotEquals($key1, $key3);
        
        // Keys should be properly formatted
        $this->assertStringStartsWith('qlcm_filter_', $key1);
    }
}