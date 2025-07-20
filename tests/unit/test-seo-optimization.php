<?php
/**
 * Unit Tests for SEO Optimization
 */

class Test_SEO_Optimization extends WP_UnitTestCase {
    
    private $seo_instance;
    private $admin_id;
    private $course_id;
    private $category_id;
    
    public function setUp() {
        parent::setUp();
        $this->seo_instance = QLCM_SEO_Optimization::get_instance();
        
        // Create test user
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($this->admin_id);
        
        // Create test course and category
        $this->category_id = QLCM_Test_Utilities::create_test_category(array(
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'Learn web development skills',
        ));
        
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Complete Web Development Course',
            'post_content' => 'Learn HTML, CSS, JavaScript, and more in this comprehensive web development course.',
            'post_excerpt' => 'Master web development from basics to advanced concepts.',
        ));
        
        wp_set_object_terms($this->course_id, $this->category_id, 'course_category');
        QLCM_Test_Utilities::set_featured_image($this->course_id);
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test structured data markup generation
     * Requirements: 10.1
     */
    public function test_structured_data_markup() {
        // Test course structured data
        $structured_data = $this->seo_instance->generate_course_structured_data($this->course_id);
        
        $this->assertNotEmpty($structured_data);
        $this->assertStringContains('"@type": "Course"', $structured_data);
        $this->assertStringContains('"name": "Complete Web Development Course"', $structured_data);
        $this->assertStringContains('"description":', $structured_data);
        $this->assertStringContains('"provider":', $structured_data);
        
        // Validate JSON structure
        $json_data = json_decode($structured_data, true);
        $this->assertNotNull($json_data);
        $this->assertEquals('Course', $json_data['@type']);
        $this->assertEquals('Complete Web Development Course', $json_data['name']);
        $this->assertArrayHasKey('description', $json_data);
        $this->assertArrayHasKey('provider', $json_data);
        $this->assertArrayHasKey('url', $json_data);
        
        // Test with ratings
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        $user_id = QLCM_Test_Utilities::create_regular_user();
        $ratings_instance->submit_rating($user_id, $this->course_id, 5, 'Great course!');
        
        $structured_data_with_rating = $this->seo_instance->generate_course_structured_data($this->course_id);
        $json_with_rating = json_decode($structured_data_with_rating, true);
        
        $this->assertArrayHasKey('aggregateRating', $json_with_rating);
        $this->assertEquals('AggregateRating', $json_with_rating['aggregateRating']['@type']);
        $this->assertEquals(5, $json_with_rating['aggregateRating']['ratingValue']);
        $this->assertEquals(1, $json_with_rating['aggregateRating']['reviewCount']);
    }
    
    /**
     * Test meta tags generation
     * Requirements: 10.2, 10.4
     */
    public function test_meta_tags_generation() {
        global $post;
        $post = get_post($this->course_id);
        
        // Test meta description
        $meta_description = $this->seo_instance->generate_meta_description($this->course_id);
        $this->assertNotEmpty($meta_description);
        $this->assertLessThanOrEqual(160, strlen($meta_description)); // SEO best practice
        $this->assertStringContains('web development', strtolower($meta_description));
        
        // Test Open Graph tags
        $og_tags = $this->seo_instance->generate_open_graph_tags($this->course_id);
        $this->assertArrayHasKey('og:title', $og_tags);
        $this->assertArrayHasKey('og:description', $og_tags);
        $this->assertArrayHasKey('og:type', $og_tags);
        $this->assertArrayHasKey('og:url', $og_tags);
        $this->assertArrayHasKey('og:image', $og_tags);
        
        $this->assertEquals('Complete Web Development Course', $og_tags['og:title']);
        $this->assertEquals('article', $og_tags['og:type']);
        $this->assertStringContains(get_permalink($this->course_id), $og_tags['og:url']);
        
        // Test Twitter Card tags
        $twitter_tags = $this->seo_instance->generate_twitter_card_tags($this->course_id);
        $this->assertArrayHasKey('twitter:card', $twitter_tags);
        $this->assertArrayHasKey('twitter:title', $twitter_tags);
        $this->assertArrayHasKey('twitter:description', $twitter_tags);
        $this->assertArrayHasKey('twitter:image', $twitter_tags);
        
        $this->assertEquals('summary_large_image', $twitter_tags['twitter:card']);
        $this->assertEquals('Complete Web Development Course', $twitter_tags['twitter:title']);
    }
    
    /**
     * Test canonical URL generation
     * Requirements: 10.2
     */
    public function test_canonical_url_generation() {
        global $post;
        $post = get_post($this->course_id);
        
        $canonical_url = $this->seo_instance->generate_canonical_url($this->course_id);
        $expected_url = get_permalink($this->course_id);
        
        $this->assertEquals($expected_url, $canonical_url);
        
        // Test with query parameters (should be stripped)
        $_GET['utm_source'] = 'test';
        $_GET['ref'] = 'social';
        
        $canonical_url_clean = $this->seo_instance->generate_canonical_url($this->course_id);
        $this->assertEquals($expected_url, $canonical_url_clean);
        $this->assertStringNotContains('utm_source', $canonical_url_clean);
        $this->assertStringNotContains('ref', $canonical_url_clean);
        
        // Clean up
        unset($_GET['utm_source'], $_GET['ref']);
    }
    
    /**
     * Test XML sitemap integration
     * Requirements: 10.3
     */
    public function test_xml_sitemap_integration() {
        // Test that courses are included in sitemap
        $sitemap_entries = $this->seo_instance->get_sitemap_entries();
        
        $this->assertNotEmpty($sitemap_entries);
        
        $course_found = false;
        foreach ($sitemap_entries as $entry) {
            if ($entry['id'] == $this->course_id && $entry['type'] == 'quick_course') {
                $course_found = true;
                $this->assertArrayHasKey('url', $entry);
                $this->assertArrayHasKey('lastmod', $entry);
                $this->assertArrayHasKey('priority', $entry);
                $this->assertArrayHasKey('changefreq', $entry);
                
                $this->assertEquals(get_permalink($this->course_id), $entry['url']);
                $this->assertGreaterThan(0, $entry['priority']);
                $this->assertLessThanOrEqual(1, $entry['priority']);
                break;
            }
        }
        
        $this->assertTrue($course_found, 'Course should be found in sitemap entries');
        
        // Test category sitemap entries
        $category_entries = $this->seo_instance->get_taxonomy_sitemap_entries('course_category');
        
        $this->assertNotEmpty($category_entries);
        
        $category_found = false;
        foreach ($category_entries as $entry) {
            if ($entry['id'] == $this->category_id) {
                $category_found = true;
                $this->assertArrayHasKey('url', $entry);
                $this->assertArrayHasKey('lastmod', $entry);
                break;
            }
        }
        
        $this->assertTrue($category_found, 'Category should be found in sitemap entries');
    }
    
    /**
     * Test breadcrumb structured data
     * Requirements: 10.1
     */
    public function test_breadcrumb_structured_data() {
        global $post;
        $post = get_post($this->course_id);
        
        $breadcrumb_data = $this->seo_instance->generate_breadcrumb_structured_data($this->course_id);
        
        $this->assertNotEmpty($breadcrumb_data);
        $this->assertStringContains('"@type": "BreadcrumbList"', $breadcrumb_data);
        
        $json_data = json_decode($breadcrumb_data, true);
        $this->assertNotNull($json_data);
        $this->assertEquals('BreadcrumbList', $json_data['@type']);
        $this->assertArrayHasKey('itemListElement', $json_data);
        $this->assertNotEmpty($json_data['itemListElement']);
        
        // Check breadcrumb items
        $items = $json_data['itemListElement'];
        $this->assertGreaterThanOrEqual(2, count($items)); // At least Home and Course
        
        // First item should be home
        $this->assertEquals(1, $items[0]['position']);
        $this->assertEquals('Home', $items[0]['name']);
        
        // Last item should be the course
        $last_item = end($items);
        $this->assertEquals('Complete Web Development Course', $last_item['name']);
    }
    
    /**
     * Test SEO title optimization
     * Requirements: 10.2
     */
    public function test_seo_title_optimization() {
        global $post;
        $post = get_post($this->course_id);
        
        // Test default title
        $seo_title = $this->seo_instance->generate_seo_title($this->course_id);
        $this->assertNotEmpty($seo_title);
        $this->assertStringContains('Complete Web Development Course', $seo_title);
        
        // Test title length optimization
        $this->assertLessThanOrEqual(60, strlen($seo_title)); // SEO best practice
        
        // Test with custom title
        update_post_meta($this->course_id, '_seo_title', 'Custom SEO Title for Web Dev Course');
        $custom_seo_title = $this->seo_instance->generate_seo_title($this->course_id);
        $this->assertEquals('Custom SEO Title for Web Dev Course', $custom_seo_title);
        
        // Test title with site name
        $title_with_site = $this->seo_instance->generate_seo_title($this->course_id, true);
        $this->assertStringContains(get_bloginfo('name'), $title_with_site);
    }
    
    /**
     * Test robots meta tag generation
     * Requirements: 10.2
     */
    public function test_robots_meta_generation() {
        global $post;
        $post = get_post($this->course_id);
        
        // Test default robots meta
        $robots_meta = $this->seo_instance->generate_robots_meta($this->course_id);
        $this->assertEquals('index, follow', $robots_meta);
        
        // Test with custom robots settings
        update_post_meta($this->course_id, '_seo_robots', 'noindex, nofollow');
        $custom_robots = $this->seo_instance->generate_robots_meta($this->course_id);
        $this->assertEquals('noindex, nofollow', $custom_robots);
        
        // Test draft post
        wp_update_post(array(
            'ID' => $this->course_id,
            'post_status' => 'draft'
        ));
        
        $draft_robots = $this->seo_instance->generate_robots_meta($this->course_id);
        $this->assertEquals('noindex, nofollow', $draft_robots);
    }
    
    /**
     * Test schema markup validation
     * Requirements: 10.1
     */
    public function test_schema_markup_validation() {
        $structured_data = $this->seo_instance->generate_course_structured_data($this->course_id);
        
        // Test JSON validity
        $json_data = json_decode($structured_data, true);
        $this->assertNotNull($json_data, 'Structured data should be valid JSON');
        
        // Test required schema.org properties for Course
        $required_properties = array('@context', '@type', 'name', 'description', 'provider');
        foreach ($required_properties as $property) {
            $this->assertArrayHasKey($property, $json_data, "Required property '{$property}' should be present");
        }
        
        // Test schema.org context
        $this->assertEquals('https://schema.org', $json_data['@context']);
        
        // Test provider structure
        $this->assertArrayHasKey('@type', $json_data['provider']);
        $this->assertEquals('Organization', $json_data['provider']['@type']);
        $this->assertArrayHasKey('name', $json_data['provider']);
    }
    
    /**
     * Test SEO performance optimization
     * Requirements: 7.1, 7.3
     */
    public function test_seo_performance() {
        global $post;
        $post = get_post($this->course_id);
        
        // Test structured data generation performance
        $start_time = microtime(true);
        $structured_data = $this->seo_instance->generate_course_structured_data($this->course_id);
        $structured_time = microtime(true) - $start_time;
        
        $this->assertLessThan(0.5, $structured_time, 'Structured data generation should complete within 0.5 seconds');
        
        // Test meta tags generation performance
        $start_time = microtime(true);
        $meta_tags = $this->seo_instance->generate_all_meta_tags($this->course_id);
        $meta_time = microtime(true) - $start_time;
        
        $this->assertLessThan(0.3, $meta_time, 'Meta tags generation should complete within 0.3 seconds');
        
        // Test sitemap generation performance
        $start_time = microtime(true);
        $sitemap_entries = $this->seo_instance->get_sitemap_entries();
        $sitemap_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $sitemap_time, 'Sitemap generation should complete within 1 second');
    }
    
    /**
     * Test SEO caching functionality
     * Requirements: 7.2
     */
    public function test_seo_caching() {
        global $post;
        $post = get_post($this->course_id);
        
        // First call should not be cached
        $start_time = microtime(true);
        $structured_data1 = $this->seo_instance->generate_course_structured_data($this->course_id);
        $time1 = microtime(true) - $start_time;
        
        // Second call should be cached and faster
        $start_time = microtime(true);
        $structured_data2 = $this->seo_instance->generate_course_structured_data($this->course_id);
        $time2 = microtime(true) - $start_time;
        
        $this->assertEquals($structured_data1, $structured_data2);
        $this->assertLessThan($time1, $time2, 'Cached call should be faster');
        
        // Test cache invalidation when course is updated
        wp_update_post(array(
            'ID' => $this->course_id,
            'post_title' => 'Updated Web Development Course'
        ));
        
        $structured_data3 = $this->seo_instance->generate_course_structured_data($this->course_id);
        $this->assertNotEquals($structured_data1, $structured_data3, 'Cache should be invalidated after course update');
        $this->assertStringContains('Updated Web Development Course', $structured_data3);
    }
    
    /**
     * Test multiple courses SEO data
     * Requirements: 10.1, 10.3
     */
    public function test_multiple_courses_seo() {
        // Create additional courses
        $course_ids = array();
        for ($i = 1; $i <= 5; $i++) {
            $course_id = QLCM_Test_Utilities::create_test_course(array(
                'post_title' => "SEO Test Course {$i}",
                'post_content' => "Content for SEO test course {$i}.",
                'post_excerpt' => "Excerpt for SEO course {$i}.",
            ));
            $course_ids[] = $course_id;
            wp_set_object_terms($course_id, $this->category_id, 'course_category');
        }
        
        // Test batch structured data generation
        $start_time = microtime(true);
        $all_structured_data = $this->seo_instance->generate_batch_structured_data($course_ids);
        $batch_time = microtime(true) - $start_time;
        
        $this->assertCount(5, $all_structured_data);
        $this->assertLessThan(2.0, $batch_time, 'Batch structured data generation should complete within 2 seconds');
        
        // Test sitemap with multiple courses
        $sitemap_entries = $this->seo_instance->get_sitemap_entries();
        $course_entries = array_filter($sitemap_entries, function($entry) {
            return $entry['type'] == 'quick_course';
        });
        
        $this->assertGreaterThanOrEqual(6, count($course_entries)); // Original course + 5 new courses
    }
    
    /**
     * Test SEO for course categories
     * Requirements: 10.1, 10.3
     */
    public function test_category_seo() {
        // Test category structured data
        $category_structured_data = $this->seo_instance->generate_category_structured_data($this->category_id);
        
        $this->assertNotEmpty($category_structured_data);
        $this->assertStringContains('"@type": "CollectionPage"', $category_structured_data);
        
        $json_data = json_decode($category_structured_data, true);
        $this->assertNotNull($json_data);
        $this->assertEquals('CollectionPage', $json_data['@type']);
        $this->assertEquals('Web Development', $json_data['name']);
        
        // Test category meta tags
        $category_meta = $this->seo_instance->generate_category_meta_tags($this->category_id);
        $this->assertArrayHasKey('description', $category_meta);
        $this->assertArrayHasKey('og:title', $category_meta);
        $this->assertArrayHasKey('og:description', $category_meta);
        
        $this->assertStringContains('Web Development', $category_meta['og:title']);
    }
    
    /**
     * Test SEO hooks and filters integration
     * Requirements: 10.2
     */
    public function test_seo_hooks_integration() {
        // Test that SEO hooks are properly registered
        $this->assertTrue(has_action('wp_head', array($this->seo_instance, 'output_meta_tags')));
        $this->assertTrue(has_filter('wp_title', array($this->seo_instance, 'filter_title')));
        $this->assertTrue(has_filter('document_title_parts', array($this->seo_instance, 'filter_document_title')));
        
        // Test meta tags output
        global $post;
        $post = get_post($this->course_id);
        
        ob_start();
        $this->seo_instance->output_meta_tags();
        $meta_output = ob_get_clean();
        
        $this->assertStringContains('<meta name="description"', $meta_output);
        $this->assertStringContains('<meta property="og:title"', $meta_output);
        $this->assertStringContains('<meta name="twitter:card"', $meta_output);
        $this->assertStringContains('<script type="application/ld+json"', $meta_output);
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_SEO_Optimization::get_instance();
        $instance2 = QLCM_SEO_Optimization::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}