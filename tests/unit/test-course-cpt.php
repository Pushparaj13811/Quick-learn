<?php
/**
 * Unit Tests for Course Custom Post Type
 */

class Test_Course_CPT extends WP_UnitTestCase {
    
    private $cpt_instance;
    
    public function setUp() {
        parent::setUp();
        $this->cpt_instance = QLCM_Course_CPT::get_instance();
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test custom post type registration
     * Requirements: 1.1, 1.2, 1.3, 1.4
     */
    public function test_course_post_type_registration() {
        // Check if post type is registered
        $this->assertTrue(post_type_exists('quick_course'));
        
        // Get post type object
        $post_type = get_post_type_object('quick_course');
        
        // Test basic properties
        $this->assertEquals('quick_course', $post_type->name);
        $this->assertTrue($post_type->public);
        $this->assertTrue($post_type->show_ui);
        $this->assertTrue($post_type->show_in_menu);
        $this->assertTrue($post_type->has_archive);
        
        // Test supported features
        $this->assertTrue(post_type_supports('quick_course', 'title'));
        $this->assertTrue(post_type_supports('quick_course', 'editor'));
        $this->assertTrue(post_type_supports('quick_course', 'thumbnail'));
        $this->assertTrue(post_type_supports('quick_course', 'excerpt'));
        
        // Test rewrite rules
        $this->assertEquals('courses', $post_type->rewrite['slug']);
        
        // Test menu icon
        $this->assertEquals('dashicons-book-alt', $post_type->menu_icon);
    }
    
    /**
     * Test admin capability restrictions
     * Requirements: 1.4, 5.3
     */
    public function test_admin_capability_restrictions() {
        $post_type = get_post_type_object('quick_course');
        
        // Test that only admins can create courses
        $this->assertEquals('manage_options', $post_type->cap->create_posts);
        
        // Create admin and regular users
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        $user_id = QLCM_Test_Utilities::create_regular_user();
        
        // Test admin permissions
        wp_set_current_user($admin_id);
        $this->assertTrue($this->cpt_instance->current_user_can_manage_courses());
        $this->assertTrue($this->cpt_instance->validate_course_permissions());
        
        // Test regular user permissions
        wp_set_current_user($user_id);
        $this->assertFalse($this->cpt_instance->current_user_can_manage_courses());
        $this->assertFalse($this->cpt_instance->validate_course_permissions());
        
        // Test guest permissions
        wp_set_current_user(0);
        $this->assertFalse($this->cpt_instance->current_user_can_manage_courses());
        $this->assertFalse($this->cpt_instance->validate_course_permissions());
    }
    
    /**
     * Test course creation and management
     * Requirements: 1.1, 1.2, 1.3
     */
    public function test_course_creation_and_management() {
        // Set admin user
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create a test course
        $course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'PHP Fundamentals',
            'post_content' => 'Learn the basics of PHP programming.',
            'post_excerpt' => 'A comprehensive PHP course.',
        ));
        
        $this->assertNotFalse($course_id);
        $this->assertGreaterThan(0, $course_id);
        
        // Test course retrieval
        $course = get_post($course_id);
        $this->assertEquals('quick_course', $course->post_type);
        $this->assertEquals('PHP Fundamentals', $course->post_title);
        $this->assertEquals('Learn the basics of PHP programming.', $course->post_content);
        
        // Test course update
        $updated_data = array(
            'ID' => $course_id,
            'post_title' => 'Advanced PHP Programming',
            'post_content' => 'Master advanced PHP concepts.',
        );
        
        $result = wp_update_post($updated_data);
        $this->assertNotWPError($result);
        
        $updated_course = get_post($course_id);
        $this->assertEquals('Advanced PHP Programming', $updated_course->post_title);
        $this->assertEquals('Master advanced PHP concepts.', $updated_course->post_content);
        
        // Test course deletion
        $deleted = wp_delete_post($course_id, true);
        $this->assertNotFalse($deleted);
        
        $deleted_course = get_post($course_id);
        $this->assertNull($deleted_course);
    }
    
    /**
     * Test course permissions for specific courses
     * Requirements: 5.3
     */
    public function test_specific_course_permissions() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        $user_id = QLCM_Test_Utilities::create_regular_user();
        
        wp_set_current_user($admin_id);
        $course_id = QLCM_Test_Utilities::create_test_course();
        
        // Test admin permissions for specific course
        $this->assertTrue($this->cpt_instance->validate_course_permissions($course_id));
        
        // Test regular user permissions for specific course
        wp_set_current_user($user_id);
        $this->assertFalse($this->cpt_instance->validate_course_permissions($course_id));
        
        // Test with invalid course ID
        wp_set_current_user($admin_id);
        $this->assertFalse($this->cpt_instance->validate_course_permissions(99999));
    }
    
    /**
     * Test post type messages
     */
    public function test_post_type_messages() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        $course_id = QLCM_Test_Utilities::create_test_course();
        
        // Mock global post
        global $post;
        $post = get_post($course_id);
        
        // Test updated messages
        $messages = array();
        $updated_messages = $this->cpt_instance->updated_messages($messages);
        
        $this->assertArrayHasKey('quick_course', $updated_messages);
        $this->assertContains('Course updated.', $updated_messages['quick_course'][1]);
        $this->assertContains('Course published.', $updated_messages['quick_course'][6]);
        $this->assertContains('Course saved.', $updated_messages['quick_course'][7]);
    }
    
    /**
     * Test bulk updated messages
     */
    public function test_bulk_updated_messages() {
        $bulk_counts = array(
            'updated' => 3,
            'locked' => 1,
            'deleted' => 2,
            'trashed' => 1,
            'untrashed' => 1,
        );
        
        $bulk_messages = array();
        $updated_messages = $this->cpt_instance->bulk_updated_messages($bulk_messages, $bulk_counts);
        
        $this->assertArrayHasKey('quick_course', $updated_messages);
        $this->assertContains('3 courses updated.', $updated_messages['quick_course']['updated']);
        $this->assertContains('2 courses permanently deleted.', $updated_messages['quick_course']['deleted']);
        $this->assertContains('1 course moved to the Trash.', $updated_messages['quick_course']['trashed']);
    }
    
    /**
     * Test get post type method
     */
    public function test_get_post_type() {
        $this->assertEquals('quick_course', QLCM_Course_CPT::get_post_type());
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Course_CPT::get_instance();
        $instance2 = QLCM_Course_CPT::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}