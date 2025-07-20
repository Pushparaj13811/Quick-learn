<?php
/**
 * Unit Tests for Course Taxonomy
 */

class Test_Course_Taxonomy extends WP_UnitTestCase {
    
    private $taxonomy_instance;
    
    public function setUp() {
        parent::setUp();
        $this->taxonomy_instance = QLCM_Course_Taxonomy::get_instance();
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test taxonomy registration
     * Requirements: 6.1, 6.2
     */
    public function test_taxonomy_registration() {
        // Check if taxonomy is registered
        $this->assertTrue(taxonomy_exists('course_category'));
        
        // Get taxonomy object
        $taxonomy = get_taxonomy('course_category');
        
        // Test basic properties
        $this->assertEquals('course_category', $taxonomy->name);
        $this->assertTrue($taxonomy->public);
        $this->assertTrue($taxonomy->show_ui);
        $this->assertTrue($taxonomy->show_admin_column);
        $this->assertTrue($taxonomy->hierarchical);
        
        // Test association with course post type
        $this->assertContains('quick_course', $taxonomy->object_type);
        
        // Test rewrite rules
        $this->assertEquals('course-category', $taxonomy->rewrite['slug']);
        $this->assertFalse($taxonomy->rewrite['with_front']);
        $this->assertTrue($taxonomy->rewrite['hierarchical']);
        
        // Test capabilities
        $this->assertEquals('manage_options', $taxonomy->cap->manage_terms);
        $this->assertEquals('manage_options', $taxonomy->cap->edit_terms);
        $this->assertEquals('manage_options', $taxonomy->cap->delete_terms);
        $this->assertEquals('edit_posts', $taxonomy->cap->assign_terms);
    }
    
    /**
     * Test category creation and management
     * Requirements: 6.3
     */
    public function test_category_creation_and_management() {
        // Set admin user
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create a test category
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Courses related to web development',
        ));
        
        $this->assertNotFalse($category_id);
        $this->assertGreaterThan(0, $category_id);
        
        // Test category retrieval
        $category = get_term($category_id, 'course_category');
        $this->assertEquals('Web Development', $category->name);
        $this->assertEquals('web-development', $category->slug);
        $this->assertEquals('Courses related to web development', $category->description);
        
        // Test category update
        $updated_data = array(
            'name' => 'Advanced Web Development',
            'description' => 'Advanced courses for web development',
        );
        
        $result = wp_update_term($category_id, 'course_category', $updated_data);
        $this->assertNotWPError($result);
        
        $updated_category = get_term($category_id, 'course_category');
        $this->assertEquals('Advanced Web Development', $updated_category->name);
        $this->assertEquals('Advanced courses for web development', $updated_category->description);
        
        // Test category deletion
        $deleted = wp_delete_term($category_id, 'course_category');
        $this->assertNotWPError($deleted);
        $this->assertTrue($deleted);
        
        $deleted_category = get_term($category_id, 'course_category');
        $this->assertWPError($deleted_category);
    }
    
    /**
     * Test category permissions
     * Requirements: 5.3, 6.3
     */
    public function test_category_permissions() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        $user_id = QLCM_Test_Utilities::create_regular_user();
        
        // Test admin permissions
        wp_set_current_user($admin_id);
        $this->assertTrue($this->taxonomy_instance->current_user_can_manage_categories());
        $this->assertTrue($this->taxonomy_instance->validate_category_permissions('create'));
        $this->assertTrue($this->taxonomy_instance->validate_category_permissions('edit'));
        $this->assertTrue($this->taxonomy_instance->validate_category_permissions('delete'));
        
        // Test regular user permissions
        wp_set_current_user($user_id);
        $this->assertFalse($this->taxonomy_instance->current_user_can_manage_categories());
        $this->assertFalse($this->taxonomy_instance->validate_category_permissions('create'));
        $this->assertFalse($this->taxonomy_instance->validate_category_permissions('edit'));
        $this->assertFalse($this->taxonomy_instance->validate_category_permissions('delete'));
        
        // Test guest permissions
        wp_set_current_user(0);
        $this->assertFalse($this->taxonomy_instance->current_user_can_manage_categories());
        $this->assertFalse($this->taxonomy_instance->validate_category_permissions('create'));
    }
    
    /**
     * Test course-category association
     * Requirements: 6.2
     */
    public function test_course_category_association() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create category and course
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Programming',
            'slug' => 'programming',
        ));
        
        $course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'JavaScript Basics',
        ));
        
        // Associate course with category
        $result = wp_set_object_terms($course_id, $category_id, 'course_category');
        $this->assertNotWPError($result);
        $this->assertNotEmpty($result);
        
        // Test association retrieval
        $course_categories = wp_get_object_terms($course_id, 'course_category');
        $this->assertNotWPError($course_categories);
        $this->assertCount(1, $course_categories);
        $this->assertEquals('Programming', $course_categories[0]->name);
        
        // Test courses by category retrieval
        $courses_query = $this->taxonomy_instance->get_courses_by_category($category_id);
        $this->assertEquals(1, $courses_query->found_posts);
        $this->assertTrue($courses_query->have_posts());
    }
    
    /**
     * Test category deletion with course reassignment
     * Requirements: 6.4
     */
    public function test_category_deletion_with_course_reassignment() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create categories and courses
        $category1_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Frontend',
            'slug' => 'frontend',
        ));
        
        $category2_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Backend',
            'slug' => 'backend',
        ));
        
        $course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Full Stack Development',
        ));
        
        // Associate course with both categories
        wp_set_object_terms($course_id, array($category1_id, $category2_id), 'course_category');
        
        // Verify associations
        $course_categories = wp_get_object_terms($course_id, 'course_category');
        $this->assertCount(2, $course_categories);
        
        // Delete one category
        wp_delete_term($category1_id, 'course_category');
        
        // Verify course still has the remaining category
        $remaining_categories = wp_get_object_terms($course_id, 'course_category');
        $this->assertCount(1, $remaining_categories);
        $this->assertEquals('Backend', $remaining_categories[0]->name);
    }
    
    /**
     * Test get all categories method
     */
    public function test_get_all_categories() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create multiple categories
        $categories = array(
            array('name' => 'Design', 'slug' => 'design'),
            array('name' => 'Development', 'slug' => 'development'),
            array('name' => 'Marketing', 'slug' => 'marketing'),
        );
        
        foreach ($categories as $category) {
            QLCM_Test_Utilities::create_test_category($category);
        }
        
        // Get all categories
        $all_categories = $this->taxonomy_instance->get_all_categories();
        
        $this->assertNotWPError($all_categories);
        $this->assertGreaterThanOrEqual(3, count($all_categories));
        
        // Check if our categories are included
        $category_names = wp_list_pluck($all_categories, 'name');
        $this->assertContains('Design', $category_names);
        $this->assertContains('Development', $category_names);
        $this->assertContains('Marketing', $category_names);
    }
    
    /**
     * Test data sanitization
     * Requirements: 5.1
     */
    public function test_data_sanitization() {
        $dirty_data = array(
            'name' => '<script>alert("xss")</script>Web Development',
            'slug' => 'WEB DEVELOPMENT!!!',
            'description' => '<p>This is a <strong>description</strong> with <script>alert("xss")</script> content.</p>',
            'parent' => '5.5',
        );
        
        $sanitized = $this->taxonomy_instance->sanitize_category_data($dirty_data);
        
        $this->assertEquals('Web Development', $sanitized['name']);
        $this->assertEquals('web-development', $sanitized['slug']);
        $this->assertEquals('This is a description with  content.', $sanitized['description']);
        $this->assertEquals(5, $sanitized['parent']);
    }
    
    /**
     * Test course count for category
     */
    public function test_course_count_for_category() {
        $admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($admin_id);
        
        // Create category
        $category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Testing',
            'slug' => 'testing',
        ));
        
        // Create multiple courses
        for ($i = 1; $i <= 3; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "Test Course {$i}",
            ));
            wp_set_object_terms($course_id, $category_id, 'course_category');
        }
        
        // Test course count using reflection to access private method
        $reflection = new ReflectionClass($this->taxonomy_instance);
        $method = $reflection->getMethod('get_course_count_for_category');
        $method->setAccessible(true);
        
        $count = $method->invoke($this->taxonomy_instance, $category_id);
        $this->assertEquals(3, $count);
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Course_Taxonomy::get_instance();
        $instance2 = QLCM_Course_Taxonomy::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}