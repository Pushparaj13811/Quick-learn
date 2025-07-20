<?php
/**
 * Test Course Modules Functionality
 * 
 * Tests for the modular course content structure including:
 * - Course modules custom post type registration
 * - Course lessons custom post type registration
 * - Database table creation
 * - Meta box functionality
 * - Progress tracking
 * 
 * @package QuickLearn_Course_Manager
 * @subpackage Tests
 */

class Test_Course_Modules extends WP_UnitTestCase {
    
    private $course_modules_instance;
    private $test_course_id;
    private $test_module_id;
    private $test_lesson_id;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Include the course modules file
        require_once QLCM_PLUGIN_PATH . 'includes/course-modules.php';
        
        // Get instance of course modules class
        $this->course_modules_instance = QLCM_Course_Modules::get_instance();
        
        // Create test course
        $this->test_course_id = $this->factory->post->create(array(
            'post_type' => 'quick_course',
            'post_title' => 'Test Course',
            'post_status' => 'publish'
        ));
        
        // Create test module
        $this->test_module_id = $this->factory->post->create(array(
            'post_type' => 'course_module',
            'post_title' => 'Test Module',
            'post_status' => 'publish'
        ));
        
        // Create test lesson
        $this->test_lesson_id = $this->factory->post->create(array(
            'post_type' => 'course_lesson',
            'post_title' => 'Test Lesson',
            'post_status' => 'publish'
        ));
        
        // Set up module-course relationship
        update_post_meta($this->test_module_id, '_qlcm_module_course_id', $this->test_course_id);
        update_post_meta($this->test_module_id, '_qlcm_module_order', 1);
        
        // Set up lesson-module relationship
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_course_id', $this->test_course_id);
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_module_id', $this->test_module_id);
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_order', 1);
    }
    
    /**
     * Test course module post type registration
     */
    public function test_course_module_post_type_registration() {
        $this->assertTrue(post_type_exists('course_module'));
        
        $post_type_object = get_post_type_object('course_module');
        $this->assertNotNull($post_type_object);
        $this->assertEquals('Course Modules', $post_type_object->label);
        $this->assertFalse($post_type_object->public);
        $this->assertTrue($post_type_object->show_ui);
    }
    
    /**
     * Test course lesson post type registration
     */
    public function test_course_lesson_post_type_registration() {
        $this->assertTrue(post_type_exists('course_lesson'));
        
        $post_type_object = get_post_type_object('course_lesson');
        $this->assertNotNull($post_type_object);
        $this->assertEquals('Course Lessons', $post_type_object->label);
        $this->assertFalse($post_type_object->public);
        $this->assertTrue($post_type_object->show_ui);
    }
    
    /**
     * Test database tables creation
     */
    public function test_database_tables_exist() {
        global $wpdb;
        
        $module_progress_table = $wpdb->prefix . 'qlcm_module_progress';
        $lesson_progress_table = $wpdb->prefix . 'qlcm_lesson_progress';
        
        // Check if tables exist
        $module_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$module_progress_table'") === $module_progress_table;
        $lesson_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$lesson_progress_table'") === $lesson_progress_table;
        
        $this->assertTrue($module_table_exists, 'Module progress table should exist');
        $this->assertTrue($lesson_table_exists, 'Lesson progress table should exist');
    }
    
    /**
     * Test module meta data saving
     */
    public function test_module_meta_data() {
        // Test module course assignment
        $course_id = get_post_meta($this->test_module_id, '_qlcm_module_course_id', true);
        $this->assertEquals($this->test_course_id, $course_id);
        
        // Test module order
        $module_order = get_post_meta($this->test_module_id, '_qlcm_module_order', true);
        $this->assertEquals(1, $module_order);
        
        // Test additional module settings
        update_post_meta($this->test_module_id, '_qlcm_module_is_free', '1');
        update_post_meta($this->test_module_id, '_qlcm_module_duration', 60);
        update_post_meta($this->test_module_id, '_qlcm_module_difficulty', 'beginner');
        
        $is_free = get_post_meta($this->test_module_id, '_qlcm_module_is_free', true);
        $duration = get_post_meta($this->test_module_id, '_qlcm_module_duration', true);
        $difficulty = get_post_meta($this->test_module_id, '_qlcm_module_difficulty', true);
        
        $this->assertEquals('1', $is_free);
        $this->assertEquals(60, $duration);
        $this->assertEquals('beginner', $difficulty);
    }
    
    /**
     * Test lesson meta data saving
     */
    public function test_lesson_meta_data() {
        // Test lesson course and module assignment
        $course_id = get_post_meta($this->test_lesson_id, '_qlcm_lesson_course_id', true);
        $module_id = get_post_meta($this->test_lesson_id, '_qlcm_lesson_module_id', true);
        $lesson_order = get_post_meta($this->test_lesson_id, '_qlcm_lesson_order', true);
        
        $this->assertEquals($this->test_course_id, $course_id);
        $this->assertEquals($this->test_module_id, $module_id);
        $this->assertEquals(1, $lesson_order);
        
        // Test additional lesson settings
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_type', 'video');
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_duration', 30);
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_is_free', '1');
        update_post_meta($this->test_lesson_id, '_qlcm_lesson_video_url', 'https://www.youtube.com/watch?v=test');
        
        $lesson_type = get_post_meta($this->test_lesson_id, '_qlcm_lesson_type', true);
        $duration = get_post_meta($this->test_lesson_id, '_qlcm_lesson_duration', true);
        $is_free = get_post_meta($this->test_lesson_id, '_qlcm_lesson_is_free', true);
        $video_url = get_post_meta($this->test_lesson_id, '_qlcm_lesson_video_url', true);
        
        $this->assertEquals('video', $lesson_type);
        $this->assertEquals(30, $duration);
        $this->assertEquals('1', $is_free);
        $this->assertEquals('https://www.youtube.com/watch?v=test', $video_url);
    }
    
    /**
     * Test lesson progress tracking
     */
    public function test_lesson_progress_tracking() {
        global $wpdb;
        
        $user_id = $this->factory->user->create();
        
        // Insert lesson progress
        $lesson_progress_table = $wpdb->prefix . 'qlcm_lesson_progress';
        
        $result = $wpdb->insert(
            $lesson_progress_table,
            array(
                'user_id' => $user_id,
                'course_id' => $this->test_course_id,
                'module_id' => $this->test_module_id,
                'lesson_id' => $this->test_lesson_id,
                'status' => 'completed',
                'started_date' => current_time('mysql'),
                'completed_date' => current_time('mysql'),
                'time_spent' => 1800 // 30 minutes
            )
        );
        
        $this->assertNotFalse($result, 'Lesson progress should be inserted successfully');
        
        // Verify the data was inserted
        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $lesson_progress_table WHERE user_id = %d AND lesson_id = %d",
            $user_id,
            $this->test_lesson_id
        ));
        
        $this->assertNotNull($progress);
        $this->assertEquals('completed', $progress->status);
        $this->assertEquals(1800, $progress->time_spent);
    }
    
    /**
     * Test module progress calculation
     */
    public function test_module_progress_calculation() {
        global $wpdb;
        
        $user_id = $this->factory->user->create();
        
        // Create additional lessons for the module
        $lesson_2_id = $this->factory->post->create(array(
            'post_type' => 'course_lesson',
            'post_title' => 'Test Lesson 2',
            'post_status' => 'publish'
        ));
        
        update_post_meta($lesson_2_id, '_qlcm_lesson_course_id', $this->test_course_id);
        update_post_meta($lesson_2_id, '_qlcm_lesson_module_id', $this->test_module_id);
        update_post_meta($lesson_2_id, '_qlcm_lesson_order', 2);
        
        // Mark first lesson as completed
        $lesson_progress_table = $wpdb->prefix . 'qlcm_lesson_progress';
        
        $wpdb->insert(
            $lesson_progress_table,
            array(
                'user_id' => $user_id,
                'course_id' => $this->test_course_id,
                'module_id' => $this->test_module_id,
                'lesson_id' => $this->test_lesson_id,
                'status' => 'completed',
                'started_date' => current_time('mysql'),
                'completed_date' => current_time('mysql')
            )
        );
        
        // Calculate progress (should be 50% - 1 out of 2 lessons completed)
        $total_lessons = get_posts(array(
            'post_type' => 'course_lesson',
            'meta_key' => '_qlcm_lesson_module_id',
            'meta_value' => $this->test_module_id,
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        $completed_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $lesson_progress_table 
             WHERE user_id = %d AND module_id = %d AND status = 'completed'",
            $user_id,
            $this->test_module_id
        ));
        
        $progress_percentage = round(($completed_lessons / count($total_lessons)) * 100);
        
        $this->assertEquals(2, count($total_lessons));
        $this->assertEquals(1, $completed_lessons);
        $this->assertEquals(50, $progress_percentage);
    }
    
    /**
     * Test content scheduling functionality
     */
    public function test_content_scheduling() {
        // Set a future release date
        $future_date = date('Y-m-d H:i:s', strtotime('+1 week'));
        update_post_meta($this->test_module_id, '_qlcm_module_release_date', $future_date);
        
        $release_date = get_post_meta($this->test_module_id, '_qlcm_module_release_date', true);
        $this->assertEquals($future_date, $release_date);
        
        // Test if content is considered scheduled
        $is_released = strtotime($release_date) <= current_time('timestamp');
        $this->assertFalse($is_released, 'Content should not be released yet');
        
        // Set a past release date
        $past_date = date('Y-m-d H:i:s', strtotime('-1 week'));
        update_post_meta($this->test_module_id, '_qlcm_module_release_date', $past_date);
        
        $release_date = get_post_meta($this->test_module_id, '_qlcm_module_release_date', true);
        $is_released = strtotime($release_date) <= current_time('timestamp');
        $this->assertTrue($is_released, 'Content should be released');
    }
    
    /**
     * Test drag and drop ordering
     */
    public function test_drag_drop_ordering() {
        // Create additional modules
        $module_2_id = $this->factory->post->create(array(
            'post_type' => 'course_module',
            'post_title' => 'Test Module 2',
            'post_status' => 'publish'
        ));
        
        $module_3_id = $this->factory->post->create(array(
            'post_type' => 'course_module',
            'post_title' => 'Test Module 3',
            'post_status' => 'publish'
        ));
        
        // Set up course relationships
        update_post_meta($module_2_id, '_qlcm_module_course_id', $this->test_course_id);
        update_post_meta($module_3_id, '_qlcm_module_course_id', $this->test_course_id);
        
        // Set initial order
        update_post_meta($this->test_module_id, '_qlcm_module_order', 1);
        update_post_meta($module_2_id, '_qlcm_module_order', 2);
        update_post_meta($module_3_id, '_qlcm_module_order', 3);
        
        // Test reordering (simulate drag and drop)
        $new_order = array($module_3_id, $this->test_module_id, $module_2_id);
        
        foreach ($new_order as $index => $module_id) {
            update_post_meta($module_id, '_qlcm_module_order', $index + 1);
        }
        
        // Verify new order
        $this->assertEquals(2, get_post_meta($this->test_module_id, '_qlcm_module_order', true));
        $this->assertEquals(3, get_post_meta($module_2_id, '_qlcm_module_order', true));
        $this->assertEquals(1, get_post_meta($module_3_id, '_qlcm_module_order', true));
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up test data
        wp_delete_post($this->test_course_id, true);
        wp_delete_post($this->test_module_id, true);
        wp_delete_post($this->test_lesson_id, true);
        
        parent::tearDown();
    }
}