<?php
/**
 * Test Utilities for QuickLearn Course Manager
 */

class QLCM_Test_Utilities {
    
    /**
     * Create a test course
     */
    public static function create_test_course($args = array()) {
        $defaults = array(
            'post_title' => 'Test Course',
            'post_content' => 'This is a test course content.',
            'post_excerpt' => 'Test course excerpt.',
            'post_status' => 'publish',
            'post_type' => 'quick_course',
            'post_author' => 1,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $course_id = wp_insert_post($args);
        
        if (is_wp_error($course_id)) {
            return false;
        }
        
        return $course_id;
    }
    
    /**
     * Create a test course category
     */
    public static function create_test_category($args = array()) {
        $defaults = array(
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Test category description',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $term = wp_insert_term(
            $args['name'],
            'course_category',
            array(
                'slug' => $args['slug'],
                'description' => $args['description'],
            )
        );
        
        if (is_wp_error($term)) {
            return false;
        }
        
        return $term['term_id'];
    }
    
    /**
     * Create admin user for testing
     */
    public static function create_admin_user() {
        return self::factory()->user->create(array(
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@test.com',
        ));
    }
    
    /**
     * Create regular user for testing
     */
    public static function create_regular_user() {
        return self::factory()->user->create(array(
            'role' => 'subscriber',
            'user_login' => 'test_user',
            'user_email' => 'user@test.com',
        ));
    }
    
    /**
     * Clean up test data
     */
    public static function cleanup_test_data() {
        // Delete test courses
        $courses = get_posts(array(
            'post_type' => 'quick_course',
            'post_status' => 'any',
            'numberposts' => -1,
        ));
        
        foreach ($courses as $course) {
            wp_delete_post($course->ID, true);
        }
        
        // Delete test categories
        $categories = get_terms(array(
            'taxonomy' => 'course_category',
            'hide_empty' => false,
        ));
        
        foreach ($categories as $category) {
            wp_delete_term($category->term_id, 'course_category');
        }
    }
    
    /**
     * Mock AJAX request
     */
    public static function mock_ajax_request($action, $data = array()) {
        $_POST['action'] = $action;
        foreach ($data as $key => $value) {
            $_POST[$key] = $value;
        }
        
        // Set up AJAX environment
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        
        // Create nonce
        $_POST['nonce'] = wp_create_nonce('quicklearn_filter_nonce');
    }
    
    /**
     * Create test attachment/image
     */
    public static function create_test_attachment($args = array()) {
        $defaults = array(
            'post_title' => 'Test Image',
            'post_content' => 'Test image description',
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $attachment_id = wp_insert_post($args);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // Add some meta data
        update_post_meta($attachment_id, '_wp_attachment_metadata', array(
            'width' => 800,
            'height' => 600,
            'file' => 'test-image.jpg',
            'sizes' => array(
                'thumbnail' => array(
                    'file' => 'test-image-150x150.jpg',
                    'width' => 150,
                    'height' => 150,
                ),
                'medium' => array(
                    'file' => 'test-image-300x225.jpg',
                    'width' => 300,
                    'height' => 225,
                ),
            ),
        ));
        
        return $attachment_id;
    }
    
    /**
     * Set featured image for a post
     */
    public static function set_featured_image($post_id, $attachment_id = null) {
        if (!$attachment_id) {
            $attachment_id = self::create_test_attachment();
        }
        
        if ($attachment_id) {
            return set_post_thumbnail($post_id, $attachment_id);
        }
        
        return false;
    }
    
    /**
     * Create multiple test courses with different statuses
     */
    public static function create_test_courses_batch($count = 5, $args = array()) {
        $course_ids = array();
        
        for ($i = 1; $i <= $count; $i++) {
            $course_args = wp_parse_args($args, array(
                'post_title' => "Test Course {$i}",
                'post_content' => "Content for test course {$i}.",
                'post_excerpt' => "Excerpt for test course {$i}.",
                'post_status' => 'publish',
            ));
            
            $course_id = self::create_test_course($course_args);
            if ($course_id) {
                $course_ids[] = $course_id;
            }
        }
        
        return $course_ids;
    }
    
    /**
     * Create test data for performance testing
     */
    public static function create_performance_test_data($courses_count = 50, $categories_count = 10) {
        $category_ids = array();
        $course_ids = array();
        
        // Create categories
        for ($i = 1; $i <= $categories_count; $i++) {
            $category_id = self::create_test_category(array(
                'name' => "Performance Category {$i}",
                'slug' => "performance-category-{$i}",
                'description' => "Performance test category {$i}",
            ));
            
            if ($category_id) {
                $category_ids[] = $category_id;
            }
        }
        
        // Create courses and assign random categories
        for ($i = 1; $i <= $courses_count; $i++) {
            $course_id = self::create_test_course(array(
                'post_title' => "Performance Course {$i}",
                'post_content' => "Content for performance test course {$i}. This is a longer content to simulate real-world scenarios.",
                'post_excerpt' => "Excerpt for performance course {$i}.",
                'post_status' => 'publish',
            ));
            
            if ($course_id && !empty($category_ids)) {
                // Assign 1-3 random categories to each course
                $random_categories = array_rand($category_ids, rand(1, min(3, count($category_ids))));
                if (!is_array($random_categories)) {
                    $random_categories = array($random_categories);
                }
                
                $assigned_categories = array();
                foreach ($random_categories as $index) {
                    $assigned_categories[] = $category_ids[$index];
                }
                
                wp_set_object_terms($course_id, $assigned_categories, 'course_category');
                $course_ids[] = $course_id;
            }
        }
        
        return array(
            'categories' => $category_ids,
            'courses' => $course_ids,
        );
    }
    
    /**
     * Measure execution time of a callback
     */
    public static function measure_execution_time($callback, $args = array()) {
        $start_time = microtime(true);
        
        $result = call_user_func_array($callback, $args);
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        return array(
            'result' => $result,
            'execution_time' => $execution_time,
        );
    }
    
    /**
     * Assert execution time is within acceptable limits
     */
    public static function assert_performance($test_case, $callback, $max_time = 1.0, $args = array()) {
        $measurement = self::measure_execution_time($callback, $args);
        
        $test_case->assertLessThan(
            $max_time,
            $measurement['execution_time'],
            "Execution time ({$measurement['execution_time']}s) exceeded maximum allowed time ({$max_time}s)"
        );
        
        return $measurement['result'];
    }
    
    /**
     * Get factory instance
     */
    public static function factory() {
        global $wp_test_factory;
        return $wp_test_factory;
    }
}