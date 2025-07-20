<?php
/**
 * AJAX Handlers for Course Filtering
 * 
 * Handles AJAX requests for filtering courses by category
 * 
 * @package QuickLearn_Course_Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handlers Class
 */
class QLCM_Ajax_Handlers {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register AJAX handlers for both logged-in and non-logged-in users
        add_action('wp_ajax_filter_courses', array($this, 'handle_course_filter'));
        add_action('wp_ajax_nopriv_filter_courses', array($this, 'handle_course_filter'));
    }
    
    /**
     * Handle course filtering AJAX request (Optimized for performance - Requirement 7.2)
     */
    public function handle_course_filter() {
        // Start performance timing
        $start_time = microtime(true);
        
        // Get security manager instance
        $security_manager = QLCM_Security_Manager::get_instance();
        
        // Enhanced rate limiting check (Requirement 5.2)
        if (!$security_manager->check_rate_limit('filter_courses', 30, 60)) {
            wp_send_json_error(array(
                'message' => __('Too many requests. Please wait a moment and try again.', 'quicklearn-course-manager')
            ));
            wp_die();
        }
        
        // Check for cached response to optimize performance (Requirement 7.2)
        $cache_key = $this->generate_cache_key($_POST);
        $cached_response = $this->get_cached_response($cache_key);
        
        if ($cached_response !== false) {
            // Add cache hit indicator for debugging
            $cached_response['cache_hit'] = true;
            $cached_response['response_time'] = microtime(true) - $start_time;
            wp_send_json_success($cached_response);
            wp_die();
        }
        
        // Enhanced nonce verification for security (Requirement 5.2)
        if (!isset($_POST['nonce']) || !$security_manager->verify_nonce($_POST['nonce'], 'quicklearn_filter_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page and try again.', 'quicklearn-course-manager')
            ));
            wp_die();
        }
        
        // Enhanced sanitization and validation using security manager (Requirement 5.1)
        $category_slug = isset($_POST['category']) ? $security_manager->sanitize_input($_POST['category'], 'slug') : '';
        $posts_per_page = isset($_POST['posts_per_page']) ? $security_manager->sanitize_input($_POST['posts_per_page'], 'int') : 12;
        $paged = isset($_POST['paged']) ? $security_manager->sanitize_input($_POST['paged'], 'int') : 1;
        
        // Additional validation with security manager
        $posts_per_page = max(1, min(50, $posts_per_page)); // Enforce limits
        $paged = max(1, min(1000, $paged)); // Prevent abuse
        
        // Additional input validation
        if ($category_slug !== '' && !$this->validate_category_exists($category_slug)) {
            wp_send_json_error(array(
                'message' => __('Invalid category specified.', 'quicklearn-course-manager')
            ));
            wp_die();
        }
        
        // Validate posts per page (prevent abuse)
        if ($posts_per_page > 50) {
            $posts_per_page = 50;
        }
        
        try {
            // Build query arguments
            $query_args = array(
                'post_type' => 'quick_course',
                'post_status' => 'publish',
                'posts_per_page' => $posts_per_page,
                'paged' => $paged,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(), // For future meta filtering
            );
            
            // Add taxonomy query if category is specified
            if (!empty($category_slug)) {
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'course_category',
                        'field'    => 'slug',
                        'terms'    => $category_slug,
                    ),
                );
            }
            
            // Execute query
            $courses_query = new WP_Query($query_args);
            
            // Generate HTML output
            $html_output = $this->generate_courses_html($courses_query);
            
            // Get category name for better user feedback (Requirement 7.1, 2.4)
            $category_name = '';
            if (!empty($category_slug)) {
                $term = get_term_by('slug', $category_slug, 'course_category');
                if ($term && !is_wp_error($term)) {
                    $category_name = $term->name;
                }
            }
            
            // Prepare response data with enhanced feedback information
            $response_data = array(
                'html' => $html_output,
                'found_posts' => $courses_query->found_posts,
                'max_num_pages' => $courses_query->max_num_pages,
                'current_page' => $paged,
                'category' => $category_slug,
                'category_name' => $category_name,
                'posts_per_page' => $posts_per_page,
                'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], // For performance monitoring
                'has_courses' => $courses_query->have_posts(),
            );
            
            // Clean up
            wp_reset_postdata();
            
            // Cache the response for future requests (Requirement 7.2)
            $this->cache_response($cache_key, $response_data);
            
            // Add performance timing
            $response_data['response_time'] = microtime(true) - $start_time;
            $response_data['cache_hit'] = false;
            
            // Send successful response
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            // Log error for debugging
            error_log('QuickLearn Course Filter Error: ' . $e->getMessage());
            
            // Send error response
            wp_send_json_error(array(
                'message' => __('An error occurred while filtering courses. Please try again.', 'quicklearn-course-manager')
            ));
        }
    }
    
    /**
     * Generate HTML output for courses
     * 
     * @param WP_Query $courses_query The courses query object
     * @return string Generated HTML
     */
    private function generate_courses_html($courses_query) {
        if (!$courses_query->have_posts()) {
            return $this->get_no_courses_html();
        }
        
        ob_start();
        
        while ($courses_query->have_posts()) {
            $courses_query->the_post();
            
            // Use the same template part as the main courses page
            if (locate_template('template-parts/course-card.php')) {
                get_template_part('template-parts/course', 'card');
            } else {
                // Fallback if template part doesn't exist
                $this->render_course_card_fallback();
            }
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get HTML for when no courses are found (Requirement 2.4, 7.1)
     * 
     * @return string No courses HTML
     */
    private function get_no_courses_html() {
        // Get current filter context for better messaging
        $category_slug = isset($_POST['category']) ? $this->sanitize_category_input($_POST['category']) : '';
        $category_name = '';
        
        if (!empty($category_slug)) {
            $term = get_term_by('slug', $category_slug, 'course_category');
            if ($term && !is_wp_error($term)) {
                $category_name = $term->name;
            }
        }
        
        ob_start();
        ?>
        <div class="no-courses-found enhanced-no-results" role="status" aria-live="polite">
            <div class="no-results-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            
            <div class="no-results-content">
                <?php if (!empty($category_name)) : ?>
                    <h3><?php printf(esc_html__('No courses found in "%s"', 'quicklearn-course-manager'), esc_html($category_name)); ?></h3>
                    <p><?php esc_html_e('We couldn\'t find any courses in this category. Here are some suggestions:', 'quicklearn-course-manager'); ?></p>
                    
                    <div class="no-results-suggestions">
                        <ul>
                            <li><?php esc_html_e('Try browsing all courses', 'quicklearn-course-manager'); ?></li>
                            <li><?php esc_html_e('Select a different category', 'quicklearn-course-manager'); ?></li>
                            <li><?php esc_html_e('Check back later for new courses', 'quicklearn-course-manager'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="no-results-actions">
                        <button type="button" class="reset-filter-btn primary-btn" onclick="document.getElementById('course-category-filter').value=''; document.getElementById('course-category-filter').dispatchEvent(new Event('change'));">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M3 12h18m-9-9l9 9-9 9"/>
                            </svg>
                            <?php esc_html_e('Show All Courses', 'quicklearn-course-manager'); ?>
                        </button>
                        
                        <button type="button" class="browse-categories-btn secondary-btn" onclick="document.getElementById('course-category-filter').focus();">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <?php esc_html_e('Browse Categories', 'quicklearn-course-manager'); ?>
                        </button>
                    </div>
                <?php else : ?>
                    <h3><?php esc_html_e('No courses available', 'quicklearn-course-manager'); ?></h3>
                    <p><?php esc_html_e('There are currently no courses available. Please check back later for new content.', 'quicklearn-course-manager'); ?></p>
                    
                    <div class="no-results-suggestions">
                        <p><strong><?php esc_html_e('What you can do:', 'quicklearn-course-manager'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('Contact us if you\'re looking for specific courses', 'quicklearn-course-manager'); ?></li>
                            <li><?php esc_html_e('Subscribe to our newsletter for updates', 'quicklearn-course-manager'); ?></li>
                            <li><?php esc_html_e('Explore other sections of our website', 'quicklearn-course-manager'); ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Fallback course card rendering if template part is missing
     */
    private function render_course_card_fallback() {
        $course_categories = get_the_terms(get_the_ID(), 'course_category');
        $categories_list = '';
        
        if ($course_categories && !is_wp_error($course_categories)) {
            $categories_names = wp_list_pluck($course_categories, 'name');
            $categories_list = implode(', ', $categories_names);
        }
        ?>
        <article class="course-card">
            <?php if (has_post_thumbnail()) : ?>
                <div class="course-thumbnail">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail('medium', array('alt' => get_the_title())); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="course-content">
                <h3 class="course-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                
                <?php if ($categories_list) : ?>
                    <div class="course-categories">
                        <span class="categories-label"><?php esc_html_e('Categories:', 'quicklearn-course-manager'); ?></span>
                        <span class="categories-list"><?php echo esc_html($categories_list); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (has_excerpt()) : ?>
                    <div class="course-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
                
                <div class="course-meta">
                    <span class="course-date"><?php echo esc_html(get_the_date()); ?></span>
                    <a href="<?php echo esc_url(get_permalink()); ?>" class="course-link">
                        <?php esc_html_e('Learn More', 'quicklearn-course-manager'); ?>
                    </a>
                </div>
            </div>
        </article>
        <?php
    }
    
    /**
     * Sanitize category input (Requirement 5.1)
     * 
     * @param mixed $input The category input to sanitize
     * @return string Sanitized category slug
     */
    private function sanitize_category_input($input) {
        if (empty($input)) {
            return '';
        }
        
        // Sanitize as text field and ensure it's a valid slug format
        $sanitized = sanitize_text_field($input);
        $sanitized = sanitize_title($sanitized);
        
        // Additional validation - only allow alphanumeric, hyphens, and underscores
        $sanitized = preg_replace('/[^a-z0-9\-_]/', '', strtolower($sanitized));
        
        return $sanitized;
    }
    
    /**
     * Sanitize posts per page input (Requirement 5.1)
     * 
     * @param mixed $input The posts per page input to sanitize
     * @return int Sanitized posts per page number
     */
    private function sanitize_posts_per_page($input) {
        $posts_per_page = absint($input);
        
        // Enforce reasonable limits to prevent abuse
        if ($posts_per_page < 1) {
            $posts_per_page = 12; // Default
        } elseif ($posts_per_page > 50) {
            $posts_per_page = 50; // Maximum allowed
        }
        
        return $posts_per_page;
    }
    
    /**
     * Sanitize page number input (Requirement 5.1)
     * 
     * @param mixed $input The page number input to sanitize
     * @return int Sanitized page number
     */
    private function sanitize_page_number($input) {
        $page = absint($input);
        
        // Ensure minimum page number is 1
        if ($page < 1) {
            $page = 1;
        }
        
        // Prevent extremely high page numbers that could cause performance issues
        if ($page > 1000) {
            $page = 1000;
        }
        
        return $page;
    }
    
    /**
     * Validate that a category exists (Requirement 5.1)
     * 
     * @param string $category_slug The category slug to validate
     * @return bool True if category exists, false otherwise
     */
    private function validate_category_exists($category_slug) {
        if (empty($category_slug)) {
            return true; // Empty is valid (means all categories)
        }
        
        $term = get_term_by('slug', $category_slug, 'course_category');
        return ($term && !is_wp_error($term));
    }
    
    /**
     * Sanitize and validate category slug (Legacy method - kept for compatibility)
     * 
     * @param string $category_slug The category slug to validate
     * @return string|false Validated slug or false if invalid
     */
    private function validate_category_slug($category_slug) {
        if (empty($category_slug)) {
            return '';
        }
        
        // Check if the category exists
        $term = get_term_by('slug', $category_slug, 'course_category');
        
        if (!$term || is_wp_error($term)) {
            return false;
        }
        
        return $category_slug;
    }
    
    /**
     * Generate cache key for AJAX request (Requirement 7.2)
     * 
     * @param array $post_data The POST data array
     * @return string Cache key
     */
    private function generate_cache_key($post_data) {
        $cache_data = array(
            'category' => isset($post_data['category']) ? $this->sanitize_category_input($post_data['category']) : '',
            'posts_per_page' => isset($post_data['posts_per_page']) ? $this->sanitize_posts_per_page($post_data['posts_per_page']) : 12,
            'paged' => isset($post_data['paged']) ? $this->sanitize_page_number($post_data['paged']) : 1,
        );
        
        return 'qlcm_filter_' . md5(serialize($cache_data));
    }
    
    /**
     * Get cached response (Requirement 7.2)
     * 
     * @param string $cache_key The cache key
     * @return array|false Cached response or false if not found
     */
    private function get_cached_response($cache_key) {
        // Use WordPress transients for caching (5 minutes cache)
        return get_transient($cache_key);
    }
    
    /**
     * Cache response for future requests (Requirement 7.2)
     * 
     * @param string $cache_key The cache key
     * @param array $response_data The response data to cache
     */
    private function cache_response($cache_key, $response_data) {
        // Cache for 5 minutes (300 seconds)
        set_transient($cache_key, $response_data, 300);
    }
}