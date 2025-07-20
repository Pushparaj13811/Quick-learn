<?php
/**
 * Unit Tests for Course Rating System
 */

class Test_Course_Ratings extends WP_UnitTestCase {
    
    private $ratings_instance;
    private $admin_id;
    private $user_id;
    private $course_id;
    
    public function setUp() {
        parent::setUp();
        $this->ratings_instance = QLCM_Course_Ratings::get_instance();
        
        // Create test users
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        $this->user_id = QLCM_Test_Utilities::create_regular_user();
        
        // Create test course
        wp_set_current_user($this->admin_id);
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Rating Test Course',
            'post_content' => 'Course content for rating testing.',
        ));
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        $this->cleanup_rating_data();
        parent::tearDown();
    }
    
    private function cleanup_rating_data() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_course_ratings");
    }
    
    /**
     * Test rating database table creation
     * Requirements: 9.1, 9.2, 9.5
     */
    public function test_rating_table_creation() {
        global $wpdb;
        
        // Check if rating table exists
        $rating_table = $wpdb->prefix . 'qlcm_course_ratings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$rating_table}'");
        $this->assertEquals($rating_table, $table_exists);
        
        // Verify table structure
        $rating_columns = $wpdb->get_results("DESCRIBE {$rating_table}");
        $column_names = wp_list_pluck($rating_columns, 'Field');
        
        $expected_columns = array('id', 'user_id', 'course_id', 'rating', 'review_text', 'created_date', 'updated_date', 'status');
        foreach ($expected_columns as $column) {
            $this->assertContains($column, $column_names);
        }
    }
    
    /**
     * Test course rating submission
     * Requirements: 9.1, 9.2
     */
    public function test_course_rating_submission() {
        wp_set_current_user($this->user_id);
        
        // Submit rating
        $result = $this->ratings_instance->submit_rating($this->user_id, $this->course_id, 5, 'Excellent course!');
        $this->assertTrue($result);
        
        // Verify rating in database
        $rating = $this->ratings_instance->get_user_rating($this->user_id, $this->course_id);
        $this->assertNotNull($rating);
        $this->assertEquals($this->user_id, $rating->user_id);
        $this->assertEquals($this->course_id, $rating->course_id);
        $this->assertEquals(5, $rating->rating);
        $this->assertEquals('Excellent course!', $rating->review_text);
        $this->assertEquals('approved', $rating->status);
        
        // Test duplicate rating prevention
        $duplicate_result = $this->ratings_instance->submit_rating($this->user_id, $this->course_id, 4, 'Updated review');
        $this->assertTrue($duplicate_result); // Should update existing rating
        
        // Verify rating was updated
        $updated_rating = $this->ratings_instance->get_user_rating($this->user_id, $this->course_id);
        $this->assertEquals(4, $updated_rating->rating);
        $this->assertEquals('Updated review', $updated_rating->review_text);
    }
    
    /**
     * Test rating validation
     * Requirements: 9.1, 9.5
     */
    public function test_rating_validation() {
        wp_set_current_user($this->user_id);
        
        // Test invalid rating values
        $this->assertFalse($this->ratings_instance->submit_rating($this->user_id, $this->course_id, 0, 'Invalid rating'));
        $this->assertFalse($this->ratings_instance->submit_rating($this->user_id, $this->course_id, 6, 'Invalid rating'));
        $this->assertFalse($this->ratings_instance->submit_rating($this->user_id, $this->course_id, -1, 'Invalid rating'));
        
        // Test valid rating values
        for ($rating = 1; $rating <= 5; $rating++) {
            $result = $this->ratings_instance->submit_rating($this->user_id, $this->course_id, $rating, "Rating {$rating}");
            $this->assertTrue($result);
        }
        
        // Test invalid user/course IDs
        $this->assertFalse($this->ratings_instance->submit_rating(0, $this->course_id, 5, 'Invalid user'));
        $this->assertFalse($this->ratings_instance->submit_rating($this->user_id, 99999, 5, 'Invalid course'));
    }
    
    /**
     * Test average rating calculation
     * Requirements: 9.3, 9.4
     */
    public function test_average_rating_calculation() {
        // Create multiple users and ratings
        $user_ids = array();
        $ratings = array(5, 4, 5, 3, 4); // Average should be 4.2
        
        for ($i = 0; $i < count($ratings); $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $user_ids[] = $user_id;
            
            $this->ratings_instance->submit_rating($user_id, $this->course_id, $ratings[$i], "Review {$i}");
        }
        
        // Calculate average rating
        $average = $this->ratings_instance->get_average_rating($this->course_id);
        $this->assertEquals(4.2, $average);
        
        // Get rating count
        $count = $this->ratings_instance->get_rating_count($this->course_id);
        $this->assertEquals(5, $count);
        
        // Get rating distribution
        $distribution = $this->ratings_instance->get_rating_distribution($this->course_id);
        $this->assertEquals(2, $distribution[5]); // Two 5-star ratings
        $this->assertEquals(2, $distribution[4]); // Two 4-star ratings
        $this->assertEquals(1, $distribution[3]); // One 3-star rating
        $this->assertEquals(0, $distribution[2]); // No 2-star ratings
        $this->assertEquals(0, $distribution[1]); // No 1-star ratings
    }
    
    /**
     * Test rating display and pagination
     * Requirements: 9.3, 9.4
     */
    public function test_rating_display_pagination() {
        // Create multiple ratings
        for ($i = 1; $i <= 15; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->ratings_instance->submit_rating($user_id, $this->course_id, rand(1, 5), "Review {$i}");
        }
        
        // Test pagination
        $page1_ratings = $this->ratings_instance->get_course_ratings($this->course_id, 1, 10);
        $this->assertCount(10, $page1_ratings);
        
        $page2_ratings = $this->ratings_instance->get_course_ratings($this->course_id, 2, 10);
        $this->assertCount(5, $page2_ratings);
        
        // Test sorting by date (newest first)
        $newest_first = $this->ratings_instance->get_course_ratings($this->course_id, 1, 5, 'newest');
        $oldest_first = $this->ratings_instance->get_course_ratings($this->course_id, 1, 5, 'oldest');
        
        $this->assertNotEquals($newest_first[0]->id, $oldest_first[0]->id);
        
        // Test sorting by rating
        $highest_first = $this->ratings_instance->get_course_ratings($this->course_id, 1, 5, 'highest');
        $lowest_first = $this->ratings_instance->get_course_ratings($this->course_id, 1, 5, 'lowest');
        
        $this->assertGreaterThanOrEqual($lowest_first[0]->rating, $highest_first[0]->rating);
    }
    
    /**
     * Test AJAX rating submission
     * Requirements: 9.1, 9.2
     */
    public function test_ajax_rating_submission() {
        wp_set_current_user($this->user_id);
        
        // Mock AJAX request
        QLCM_Test_Utilities::mock_ajax_request('submit_rating', array(
            'course_id' => $this->course_id,
            'rating' => 5,
            'review_text' => 'Great course via AJAX!',
        ));
        
        // Capture output
        ob_start();
        
        try {
            $this->ratings_instance->handle_rating_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals(5, $response['data']['rating']);
        $this->assertEquals('Great course via AJAX!', $response['data']['review_text']);
        
        // Verify rating in database
        $rating = $this->ratings_instance->get_user_rating($this->user_id, $this->course_id);
        $this->assertEquals(5, $rating->rating);
        $this->assertEquals('Great course via AJAX!', $rating->review_text);
    }
    
    /**
     * Test rating security and permissions
     * Requirements: 9.5
     */
    public function test_rating_security() {
        // Test unauthenticated user
        wp_set_current_user(0);
        $result = $this->ratings_instance->submit_rating(0, $this->course_id, 5, 'Unauthorized rating');
        $this->assertFalse($result);
        
        // Test AJAX security with invalid nonce
        $_POST = array(
            'action' => 'submit_rating',
            'course_id' => $this->course_id,
            'rating' => 5,
            'review_text' => 'Insecure rating',
            'nonce' => 'invalid_nonce',
        );
        
        ob_start();
        
        try {
            $this->ratings_instance->handle_rating_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior for wp_die() in AJAX
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);
        
        $this->assertNotNull($response);
        $this->assertFalse($response['success']);
        $this->assertContains('Security check failed', $response['data']['message']);
        
        // Test XSS prevention in review text
        wp_set_current_user($this->user_id);
        $malicious_review = '<script>alert("xss")</script>Malicious review';
        $result = $this->ratings_instance->submit_rating($this->user_id, $this->course_id, 5, $malicious_review);
        $this->assertTrue($result);
        
        $rating = $this->ratings_instance->get_user_rating($this->user_id, $this->course_id);
        $this->assertNotContains('<script>', $rating->review_text);
        $this->assertContains('Malicious review', $rating->review_text);
    }
    
    /**
     * Test rating moderation
     * Requirements: 9.5
     */
    public function test_rating_moderation() {
        wp_set_current_user($this->user_id);
        
        // Submit rating
        $this->ratings_instance->submit_rating($this->user_id, $this->course_id, 5, 'Test review for moderation');
        $rating = $this->ratings_instance->get_user_rating($this->user_id, $this->course_id);
        
        // Test moderation actions
        wp_set_current_user($this->admin_id);
        
        // Approve rating
        $result = $this->ratings_instance->moderate_rating($rating->id, 'approved');
        $this->assertTrue($result);
        
        $updated_rating = $this->ratings_instance->get_rating_by_id($rating->id);
        $this->assertEquals('approved', $updated_rating->status);
        
        // Reject rating
        $result = $this->ratings_instance->moderate_rating($rating->id, 'rejected');
        $this->assertTrue($result);
        
        $updated_rating = $this->ratings_instance->get_rating_by_id($rating->id);
        $this->assertEquals('rejected', $updated_rating->status);
        
        // Test that rejected ratings don't appear in public listings
        $public_ratings = $this->ratings_instance->get_course_ratings($this->course_id);
        $this->assertEmpty($public_ratings);
    }
    
    /**
     * Test rating statistics and analytics
     * Requirements: 11.2, 11.4
     */
    public function test_rating_statistics() {
        // Create ratings for multiple courses
        wp_set_current_user($this->admin_id);
        $course2_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Second Rating Course',
        ));
        
        // Add ratings to both courses
        for ($i = 1; $i <= 5; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->ratings_instance->submit_rating($user_id, $this->course_id, rand(3, 5), "Review {$i} for course 1");
            $this->ratings_instance->submit_rating($user_id, $course2_id, rand(1, 3), "Review {$i} for course 2");
        }
        
        // Get rating statistics
        $stats = $this->ratings_instance->get_rating_statistics();
        
        $this->assertArrayHasKey('total_ratings', $stats);
        $this->assertArrayHasKey('average_rating', $stats);
        $this->assertArrayHasKey('rating_distribution', $stats);
        $this->assertArrayHasKey('top_rated_courses', $stats);
        
        $this->assertEquals(10, $stats['total_ratings']);
        $this->assertGreaterThan(0, $stats['average_rating']);
        
        // Test course-specific statistics
        $course_stats = $this->ratings_instance->get_course_rating_stats($this->course_id);
        $this->assertArrayHasKey('average_rating', $course_stats);
        $this->assertArrayHasKey('total_ratings', $course_stats);
        $this->assertArrayHasKey('rating_distribution', $course_stats);
        
        $this->assertEquals(5, $course_stats['total_ratings']);
    }
    
    /**
     * Test rating performance
     * Requirements: 7.2
     */
    public function test_rating_performance() {
        // Test rating submission performance
        $start_time = microtime(true);
        
        for ($i = 1; $i <= 20; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->ratings_instance->submit_rating($user_id, $this->course_id, rand(1, 5), "Performance test rating {$i}");
        }
        
        $submission_time = microtime(true) - $start_time;
        $this->assertLessThan(3.0, $submission_time, 'Rating submissions should complete within 3 seconds');
        
        // Test average rating calculation performance
        $start_time = microtime(true);
        $average = $this->ratings_instance->get_average_rating($this->course_id);
        $calculation_time = microtime(true) - $start_time;
        
        $this->assertLessThan(0.5, $calculation_time, 'Average rating calculation should complete within 0.5 seconds');
        
        // Test rating retrieval performance
        $start_time = microtime(true);
        $ratings = $this->ratings_instance->get_course_ratings($this->course_id, 1, 10);
        $retrieval_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $retrieval_time, 'Rating retrieval should complete within 1 second');
    }
    
    /**
     * Test rating cache functionality
     * Requirements: 7.2
     */
    public function test_rating_cache() {
        // Add some ratings
        for ($i = 1; $i <= 5; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->ratings_instance->submit_rating($user_id, $this->course_id, rand(1, 5), "Cache test rating {$i}");
        }
        
        // First call should not be cached
        $start_time = microtime(true);
        $average1 = $this->ratings_instance->get_average_rating($this->course_id);
        $time1 = microtime(true) - $start_time;
        
        // Second call should be cached and faster
        $start_time = microtime(true);
        $average2 = $this->ratings_instance->get_average_rating($this->course_id);
        $time2 = microtime(true) - $start_time;
        
        $this->assertEquals($average1, $average2);
        $this->assertLessThan($time1, $time2, 'Cached call should be faster');
        
        // Test cache invalidation when new rating is added
        $user_id = QLCM_Test_Utilities::create_regular_user();
        $this->ratings_instance->submit_rating($user_id, $this->course_id, 5, 'Cache invalidation test');
        
        $average3 = $this->ratings_instance->get_average_rating($this->course_id);
        $this->assertNotEquals($average1, $average3, 'Cache should be invalidated after new rating');
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Course_Ratings::get_instance();
        $instance2 = QLCM_Course_Ratings::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}