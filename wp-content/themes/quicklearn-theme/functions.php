<?php
/**
 * QuickLearn Theme Functions
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include performance optimization
require_once get_template_directory() . '/includes/performance-optimization.php';

/**
 * Theme setup function
 */
function quicklearn_theme_setup() {
    // Add theme support for post thumbnails
    add_theme_support('post-thumbnails');
    
    // Add theme support for HTML5 markup
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));
    
    // Add theme support for custom logo
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    
    // Add theme support for title tag
    add_theme_support('title-tag');
    
    // Add theme support for block editor styles
    add_theme_support('wp-block-styles');
    
    // Add theme support for editor color palette
    add_theme_support('editor-color-palette', array(
        array(
            'name'  => __('Primary Blue', 'quicklearn'),
            'slug'  => 'primary-blue',
            'color' => '#3498db',
        ),
        array(
            'name'  => __('Secondary Blue', 'quicklearn'),
            'slug'  => 'secondary-blue',
            'color' => '#2980b9',
        ),
        array(
            'name'  => __('Success Green', 'quicklearn'),
            'slug'  => 'success-green',
            'color' => '#28a745',
        ),
        array(
            'name'  => __('Warning Yellow', 'quicklearn'),
            'slug'  => 'warning-yellow',
            'color' => '#ffc107',
        ),
        array(
            'name'  => __('Dark Gray', 'quicklearn'),
            'slug'  => 'dark-gray',
            'color' => '#2c3e50',
        ),
        array(
            'name'  => __('Light Gray', 'quicklearn'),
            'slug'  => 'light-gray',
            'color' => '#6c757d',
        ),
    ));
    
    // Add theme support for editor font sizes
    add_theme_support('editor-font-sizes', array(
        array(
            'name' => __('Small', 'quicklearn'),
            'size' => 12,
            'slug' => 'small'
        ),
        array(
            'name' => __('Regular', 'quicklearn'),
            'size' => 16,
            'slug' => 'regular'
        ),
        array(
            'name' => __('Large', 'quicklearn'),
            'size' => 24,
            'slug' => 'large'
        ),
        array(
            'name' => __('Extra Large', 'quicklearn'),
            'size' => 32,
            'slug' => 'extra-large'
        ),
    ));
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'quicklearn'),
        'footer'  => __('Footer Menu', 'quicklearn'),
    ));
    
    // Set content width
    if (!isset($content_width)) {
        $content_width = 1200;
    }
}
add_action('after_setup_theme', 'quicklearn_theme_setup');

/**
 * Enqueue scripts and styles
 */
function quicklearn_enqueue_scripts() {
    $theme_version = wp_get_theme()->get('Version');
    
    // Enqueue design tokens first (foundation)
    wp_enqueue_style(
        'quicklearn-design-tokens',
        get_template_directory_uri() . '/css/design-tokens.css',
        array(),
        $theme_version
    );
    
    // Enqueue component stylesheets
    wp_enqueue_style(
        'quicklearn-buttons',
        get_template_directory_uri() . '/css/components/buttons.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    wp_enqueue_style(
        'quicklearn-cards',
        get_template_directory_uri() . '/css/components/cards.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    wp_enqueue_style(
        'quicklearn-forms',
        get_template_directory_uri() . '/css/components/forms.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    wp_enqueue_style(
        'quicklearn-grid',
        get_template_directory_uri() . '/css/components/grid.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    wp_enqueue_style(
        'quicklearn-progress',
        get_template_directory_uri() . '/css/components/progress.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    wp_enqueue_style(
        'quicklearn-modal',
        get_template_directory_uri() . '/css/components/modal.css',
        array('quicklearn-design-tokens'),
        $theme_version
    );
    
    // Enqueue main stylesheet
    wp_enqueue_style(
        'quicklearn-style',
        get_stylesheet_uri(),
        array(
            'quicklearn-design-tokens',
            'quicklearn-buttons',
            'quicklearn-cards',
            'quicklearn-forms',
            'quicklearn-grid',
            'quicklearn-progress',
            'quicklearn-modal'
        ),
        $theme_version
    );
    
    // Enqueue custom CSS
    wp_enqueue_style(
        'quicklearn-custom',
        get_template_directory_uri() . '/css/custom.css',
        array('quicklearn-style'),
        $theme_version
    );
    
    // Enqueue front page CSS (only on front page)
    if (is_front_page()) {
        wp_enqueue_style(
            'quicklearn-front-page',
            get_template_directory_uri() . '/css/front-page.css',
            array('quicklearn-custom'),
            $theme_version
        );
    }
    
    // Enqueue dashboard CSS (only on dashboard page)
    if (is_page('dashboard') || is_page_template('page-dashboard.php')) {
        wp_enqueue_style(
            'quicklearn-dashboard',
            get_template_directory_uri() . '/css/dashboard.css',
            array('quicklearn-custom'),
            $theme_version
        );
    }
    
    // Enqueue enhanced navigation CSS
    wp_enqueue_style(
        'quicklearn-navigation',
        get_template_directory_uri() . '/css/navigation.css',
        array('quicklearn-custom'),
        $theme_version
    );
    
    // Enqueue navigation script
    wp_enqueue_script(
        'quicklearn-navigation',
        get_template_directory_uri() . '/js/navigation.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
    
    // Enqueue lazy loading script
    wp_enqueue_script(
        'quicklearn-lazy-loading',
        get_template_directory_uri() . '/js/lazy-loading.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
    
    // Localize script for AJAX pagination
    wp_localize_script('quicklearn-lazy-loading', 'quicklearn_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'pagination_nonce' => wp_create_nonce('quicklearn_pagination_nonce'),
    ));
    
    // Enqueue course filter script (for courses page)
    if (is_page('courses') || is_page_template('page-courses.php') || is_post_type_archive('quick_course') || is_tax('course_category')) {
        wp_enqueue_script(
            'quicklearn-course-filter',
            get_template_directory_uri() . '/js/course-filter.js',
            array('jquery'),
            wp_get_theme()->get('Version'),
            true
        );
        
        // Localize script for AJAX (Requirement 5.2)
        wp_localize_script('quicklearn-course-filter', 'quicklearn_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('quicklearn_filter_nonce'),
            'security_error' => __('Security check failed. Please refresh the page and try again.', 'quicklearn'),
            'general_error' => __('An error occurred. Please try again.', 'quicklearn'),
            'loading_text' => __('Loading courses...', 'quicklearn'),
            'loading_slow_text' => __('This is taking longer than expected...', 'quicklearn'),
            'no_courses_text' => __('No courses found', 'quicklearn'),
            'filter_success_single' => __('Found 1 course', 'quicklearn'),
            'filter_success_multiple' => __('Found %d courses', 'quicklearn'),
            'filter_success_category' => __('in %s', 'quicklearn'),
            'timeout_error' => __('Request timed out. Please try again.', 'quicklearn'),
            'network_error' => __('Network error. Please check your connection and try again.', 'quicklearn'),
        ));
    }
}
add_action('wp_enqueue_scripts', 'quicklearn_enqueue_scripts');

/**
 * Add responsive navigation support
 */
function quicklearn_add_menu_toggle_script() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navigation = document.querySelector('.main-navigation');
        
        if (menuToggle && navigation) {
            menuToggle.addEventListener('click', function() {
                navigation.classList.toggle('active');
                this.setAttribute('aria-expanded', 
                    navigation.classList.contains('active') ? 'true' : 'false'
                );
            });
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'quicklearn_add_menu_toggle_script');

/**
 * Custom excerpt length
 */
function quicklearn_excerpt_length($length) {
    return 25;
}
add_filter('excerpt_length', 'quicklearn_excerpt_length');

/**
 * Custom excerpt more text
 */
function quicklearn_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'quicklearn_excerpt_more');

/**
 * Add custom body classes
 */
function quicklearn_body_classes($classes) {
    // Add class for pages
    if (is_page()) {
        $classes[] = 'page-' . get_post_field('post_name');
    }
    
    // Add class for courses page
    if (is_page('courses')) {
        $classes[] = 'courses-page';
    }
    
    return $classes;
}
add_filter('body_class', 'quicklearn_body_classes');

/**
 * Add responsive image sizes
 */
function quicklearn_add_image_sizes() {
    // Course thumbnail sizes for different screen sizes
    add_image_size('course-thumbnail-mobile', 400, 300, true);
    add_image_size('course-thumbnail-tablet', 600, 400, true);
    add_image_size('course-thumbnail-desktop', 800, 500, true);
    
    // Course featured image sizes
    add_image_size('course-featured-mobile', 600, 400, true);
    add_image_size('course-featured-desktop', 1200, 600, true);
}
add_action('after_setup_theme', 'quicklearn_add_image_sizes');

/**
 * Add theme support for additional features
 */
function quicklearn_add_theme_support() {
    // Add theme support for responsive embeds
    add_theme_support('responsive-embeds');
    
    // Add theme support for editor styles
    add_theme_support('editor-styles');
    
    // Add theme support for wide alignment
    add_theme_support('align-wide');
    
    // Add theme support for custom line height
    add_theme_support('custom-line-height');
    
    // Add theme support for custom units
    add_theme_support('custom-units');
}
add_action('after_setup_theme', 'quicklearn_add_theme_support');

/**
 * Add lazy loading to images
 */
function quicklearn_add_lazy_loading($attr, $attachment, $size) {
    // Add lazy loading to course thumbnails
    if (strpos($size, 'course-') === 0 || $size === 'medium' || $size === 'large') {
        $attr['loading'] = 'lazy';
        $attr['decoding'] = 'async';
    }
    
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'quicklearn_add_lazy_loading', 10, 3);

/**
 * Optimize image quality for web
 */
function quicklearn_optimize_image_quality($quality, $mime_type) {
    // Optimize JPEG quality for web
    if ($mime_type === 'image/jpeg') {
        return 85;
    }
    
    return $quality;
}
add_filter('wp_editor_set_quality', 'quicklearn_optimize_image_quality', 10, 2);

/**
 * Add responsive images support
 */
function quicklearn_responsive_images() {
    // Add responsive images support for course thumbnails
    add_filter('wp_calculate_image_srcset_meta', function($image_meta, $size_array, $image_src, $attachment_id) {
        // Ensure we have proper srcset for course images
        if (strpos($image_src, 'course') !== false) {
            return $image_meta;
        }
        return $image_meta;
    }, 10, 4);
}
add_action('init', 'quicklearn_responsive_images');

/**
 * Preload critical resources (Requirement 5.4 - Validate and escape all output data)
 */
function quicklearn_preload_resources() {
    // Preload critical CSS
    echo '<link rel="preload" href="' . esc_url(get_template_directory_uri() . '/css/custom.css') . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
    
    // Preload critical JavaScript
    if (is_page('courses')) {
        echo '<link rel="preload" href="' . esc_url(get_template_directory_uri() . '/js/course-filter.js') . '" as="script">';
    }
}
add_action('wp_head', 'quicklearn_preload_resources', 1);

/**
 * Security and validation functions for theme (Requirements 5.1, 5.2, 5.3, 5.4)
 */

/**
 * Sanitize course filter inputs (Requirement 5.1)
 * 
 * @param array $input_data Raw input data
 * @return array Sanitized input data
 */
function quicklearn_sanitize_filter_inputs($input_data) {
    $sanitized = array();
    
    if (isset($input_data['category'])) {
        $sanitized['category'] = sanitize_text_field($input_data['category']);
        $sanitized['category'] = sanitize_title($sanitized['category']);
        // Only allow valid slug characters
        $sanitized['category'] = preg_replace('/[^a-z0-9\-_]/', '', strtolower($sanitized['category']));
    }
    
    if (isset($input_data['posts_per_page'])) {
        $sanitized['posts_per_page'] = absint($input_data['posts_per_page']);
        // Enforce reasonable limits
        if ($sanitized['posts_per_page'] < 1 || $sanitized['posts_per_page'] > 50) {
            $sanitized['posts_per_page'] = 12;
        }
    }
    
    if (isset($input_data['paged'])) {
        $sanitized['paged'] = absint($input_data['paged']);
        if ($sanitized['paged'] < 1) {
            $sanitized['paged'] = 1;
        }
    }
    
    return $sanitized;
}

/**
 * Verify course filter nonce (Requirement 5.2)
 * 
 * @param string $nonce The nonce to verify
 * @return bool True if nonce is valid, false otherwise
 */
function quicklearn_verify_filter_nonce($nonce) {
    return wp_verify_nonce($nonce, 'quicklearn_filter_nonce');
}

/**
 * Check if user can view courses (Requirement 5.3)
 * 
 * @return bool True if user can view courses, false otherwise
 */
function quicklearn_user_can_view_courses() {
    // Courses are public, but we can add restrictions here if needed
    return true;
}

/**
 * Escape course data for output (Requirement 5.4)
 * 
 * @param mixed $data The data to escape
 * @param string $context The context for escaping (html, attr, url, js)
 * @return mixed Escaped data
 */
function quicklearn_escape_course_data($data, $context = 'html') {
    switch ($context) {
        case 'attr':
            return esc_attr($data);
        case 'url':
            return esc_url($data);
        case 'js':
            return esc_js($data);
        case 'textarea':
            return esc_textarea($data);
        case 'html':
        default:
            return esc_html($data);
    }
}

/**
 * Validate course category exists (Requirement 5.1)
 * 
 * @param string $category_slug The category slug to validate
 * @return bool True if category exists, false otherwise
 */
function quicklearn_validate_course_category($category_slug) {
    if (empty($category_slug)) {
        return true; // Empty means all categories
    }
    
    $term = get_term_by('slug', $category_slug, 'course_category');
    return ($term && !is_wp_error($term));
}

/**
 * Add security headers (Requirement 5.2)
 */
function quicklearn_add_security_headers() {
    // Only add headers on frontend
    if (!is_admin()) {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
add_action('send_headers', 'quicklearn_add_security_headers');

/**
 * Remove WordPress version from head (Security measure)
 */
function quicklearn_remove_wp_version() {
    return '';
}
add_filter('the_generator', 'quicklearn_remove_wp_version');

/**
 * Disable XML-RPC (Security measure)
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Remove RSD link (Security measure)
 */
remove_action('wp_head', 'rsd_link');

/**
 * Remove Windows Live Writer link (Security measure)
 */
remove_action('wp_head', 'wlwmanifest_link');

/**
 * ================================================
 * QuickLearn Course Manager Plugin Integration
 * ================================================
 */

/**
 * Register widget areas for course pages
 */
function quicklearn_register_widget_areas() {
    // Course sidebar widget area
    register_sidebar(array(
        'name'          => __('Course Sidebar', 'quicklearn'),
        'id'            => 'course-sidebar',
        'description'   => __('Widget area for course pages sidebar', 'quicklearn'),
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    // Course filters widget area
    register_sidebar(array(
        'name'          => __('Course Filters', 'quicklearn'),
        'id'            => 'course-filters',
        'description'   => __('Widget area for course filter tools', 'quicklearn'),
        'before_widget' => '<div class="filter-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="filter-widget-title">',
        'after_title'   => '</h4>',
    ));
    
    // Dashboard sidebar widget area
    register_sidebar(array(
        'name'          => __('Dashboard Sidebar', 'quicklearn'),
        'id'            => 'dashboard-sidebar',
        'description'   => __('Widget area for user dashboard sidebar', 'quicklearn'),
        'before_widget' => '<div class="dashboard-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="dashboard-widget-title">',
        'after_title'   => '</h3>',
    ));
    
    // Footer widget areas
    register_sidebar(array(
        'name'          => __('Footer Widget Area 1', 'quicklearn'),
        'id'            => 'footer-1',
        'description'   => __('First footer widget area', 'quicklearn'),
        'before_widget' => '<div class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget-title">',
        'after_title'   => '</h4>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer Widget Area 2', 'quicklearn'),
        'id'            => 'footer-2',
        'description'   => __('Second footer widget area', 'quicklearn'),
        'before_widget' => '<div class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget-title">',
        'after_title'   => '</h4>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer Widget Area 3', 'quicklearn'),
        'id'            => 'footer-3',
        'description'   => __('Third footer widget area', 'quicklearn'),
        'before_widget' => '<div class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget-title">',
        'after_title'   => '</h4>',
    ));
}
add_action('widgets_init', 'quicklearn_register_widget_areas');

/**
 * Add course-related body classes for better styling
 */
function quicklearn_course_body_classes($classes) {
    if (is_singular('quick_course')) {
        $classes[] = 'single-course-page';
    }
    
    if (is_tax('course_category')) {
        $classes[] = 'course-category-archive';
    }
    
    if (is_page('dashboard') || is_page_template('page-dashboard.php')) {
        $classes[] = 'user-dashboard-page';
    }
    
    // Check if user is enrolled in current course
    if (is_singular('quick_course') && function_exists('qlcm_is_user_enrolled')) {
        if (is_user_logged_in() && qlcm_is_user_enrolled(get_current_user_id(), get_the_ID())) {
            $classes[] = 'user-enrolled';
        }
    }
    
    return $classes;
}
add_filter('body_class', 'quicklearn_course_body_classes');

/**
 * Enqueue course-specific scripts and styles
 */
function quicklearn_course_scripts() {
    // Enqueue course module scripts on single course pages
    if (is_singular('quick_course')) {
        wp_enqueue_script(
            'quicklearn-course-modules',
            get_template_directory_uri() . '/js/course-modules.js',
            array('jquery'),
            wp_get_theme()->get('Version'),
            true
        );
        
        wp_localize_script('quicklearn-course-modules', 'quicklearn_course', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quicklearn_course_nonce'),
            'course_id' => get_the_ID(),
            'strings' => array(
                'loading' => __('Loading...', 'quicklearn'),
                'error' => __('An error occurred. Please try again.', 'quicklearn'),
                'completed' => __('Completed', 'quicklearn'),
                'in_progress' => __('In Progress', 'quicklearn'),
                'locked' => __('Locked', 'quicklearn'),
            ),
        ));
    }
    
    // Enqueue rating scripts
    if (is_singular('quick_course') && function_exists('qlcm_can_user_review_course')) {
        wp_enqueue_script(
            'quicklearn-ratings',
            get_template_directory_uri() . '/js/course-ratings.js',
            array('jquery'),
            wp_get_theme()->get('Version'),
            true
        );
        
        wp_localize_script('quicklearn-ratings', 'quicklearn_ratings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quicklearn_rating_nonce'),
            'course_id' => get_the_ID(),
            'strings' => array(
                'rate_course' => __('Rate this course', 'quicklearn'),
                'submit_review' => __('Submit Review', 'quicklearn'),
                'thank_you' => __('Thank you for your review!', 'quicklearn'),
            ),
        ));
    }
    
    // Enqueue dashboard scripts
    if (is_page('dashboard') || is_page_template('page-dashboard.php')) {
        wp_enqueue_script(
            'quicklearn-dashboard',
            get_template_directory_uri() . '/js/user-dashboard.js',
            array('jquery'),
            wp_get_theme()->get('Version'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'quicklearn_course_scripts');

/**
 * Add additional image sizes for course manager features
 */
function quicklearn_add_course_image_sizes() {
    // Certificate images
    add_image_size('certificate-thumbnail', 300, 200, true);
    add_image_size('certificate-full', 800, 600, true);
    
    // User avatars
    add_image_size('user-avatar-small', 48, 48, true);
    add_image_size('user-avatar-medium', 80, 80, true);
    add_image_size('user-avatar-large', 120, 120, true);
    
    // Course module images
    add_image_size('module-thumbnail', 200, 150, true);
    
    // Review/rating thumbnails
    add_image_size('review-avatar', 60, 60, true);
}
add_action('after_setup_theme', 'quicklearn_add_course_image_sizes');

/**
 * Add support for course post formats
 */
function quicklearn_add_course_post_formats() {
    add_theme_support('post-formats', array(
        'video',
        'audio',
        'gallery',
        'quote',
        'link'
    ));
}
add_action('after_setup_theme', 'quicklearn_add_course_post_formats');

/**
 * Custom template loader for course pages
 */
function quicklearn_course_template_loader($template) {
    if (is_singular('quick_course')) {
        $new_template = locate_template(array('single-quick_course.php'));
        if ('' != $new_template) {
            return $new_template;
        }
    }
    
    if (is_tax('course_category')) {
        $new_template = locate_template(array('taxonomy-course_category.php', 'archive-course.php'));
        if ('' != $new_template) {
            return $new_template;
        }
    }
    
    return $template;
}
add_filter('template_include', 'quicklearn_course_template_loader');

/**
 * Add course metadata to head for SEO
 */
function quicklearn_course_meta_tags() {
    if (is_singular('quick_course')) {
        global $post;
        
        // Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr(get_the_title()) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr(wp_trim_words(get_the_excerpt(), 20)) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '" />' . "\n";
        
        if (has_post_thumbnail()) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '" />' . "\n";
        }
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr(get_the_title()) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words(get_the_excerpt(), 20)) . '" />' . "\n";
        
        // Course-specific meta
        if (function_exists('qlcm_get_course_rating')) {
            $rating_data = qlcm_get_course_rating(get_the_ID());
            if ($rating_data && $rating_data['count'] > 0) {
                echo '<meta name="course:rating" content="' . esc_attr($rating_data['average']) . '" />' . "\n";
                echo '<meta name="course:review_count" content="' . esc_attr($rating_data['count']) . '" />' . "\n";
            }
        }
        
        if (function_exists('qlcm_get_course_enrollment_count')) {
            $enrollment_count = qlcm_get_course_enrollment_count(get_the_ID());
            echo '<meta name="course:enrollment_count" content="' . esc_attr($enrollment_count) . '" />' . "\n";
        }
    }
}
add_action('wp_head', 'quicklearn_course_meta_tags');

/**
 * Add structured data for courses
 */
function quicklearn_course_structured_data() {
    if (is_singular('quick_course')) {
        global $post;
        
        $structured_data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => get_the_title(),
            'description' => wp_trim_words(get_the_excerpt(), 20),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
        );
        
        // Add instructor info
        $author_id = get_the_author_meta('ID');
        $structured_data['instructor'] = array(
            '@type' => 'Person',
            'name' => get_the_author(),
            'url' => get_author_posts_url($author_id),
        );
        
        // Add thumbnail
        if (has_post_thumbnail()) {
            $structured_data['image'] = get_the_post_thumbnail_url($post->ID, 'large');
        }
        
        // Add rating if available
        if (function_exists('qlcm_get_course_rating')) {
            $rating_data = qlcm_get_course_rating(get_the_ID());
            if ($rating_data && $rating_data['count'] > 0) {
                $structured_data['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'ratingValue' => $rating_data['average'],
                    'reviewCount' => $rating_data['count'],
                    'bestRating' => 5,
                    'worstRating' => 1,
                );
            }
        }
        
        // Allow plugins to modify structured data
        $structured_data = apply_filters('quicklearn_course_structured_data', $structured_data, $post);
        
        echo '<script type="application/ld+json">' . json_encode($structured_data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
add_action('wp_head', 'quicklearn_course_structured_data');

/**
 * Add course card meta hook support
 */
function quicklearn_course_card_meta_hook($post_id) {
    // This allows plugins to add content to course cards
    do_action('quicklearn_course_card_meta', $post_id);
}

/**
 * Add custom CSS classes for course elements
 */
function quicklearn_course_css_classes($classes, $class, $post_id) {
    if (get_post_type($post_id) === 'quick_course') {
        $classes[] = 'course-item';
        
        // Add enrollment status class
        if (function_exists('qlcm_is_user_enrolled') && is_user_logged_in()) {
            if (qlcm_is_user_enrolled(get_current_user_id(), $post_id)) {
                $classes[] = 'user-enrolled';
            } else {
                $classes[] = 'user-not-enrolled';
            }
        }
        
        // Add completion status class
        if (function_exists('qlcm_get_user_course_progress') && is_user_logged_in()) {
            $progress = qlcm_get_user_course_progress(get_current_user_id(), $post_id);
            if ($progress !== false) {
                if ($progress >= 100) {
                    $classes[] = 'course-completed';
                } elseif ($progress > 0) {
                    $classes[] = 'course-in-progress';
                } else {
                    $classes[] = 'course-not-started';
                }
            }
        }
    }
    
    return $classes;
}
add_filter('post_class', 'quicklearn_course_css_classes', 10, 3);

/**
 * Add course-specific customizer options
 */
function quicklearn_course_customizer($wp_customize) {
    // Course Settings Section
    $wp_customize->add_section('quicklearn_course_settings', array(
        'title' => __('Course Settings', 'quicklearn'),
        'priority' => 30,
        'description' => __('Customize course display settings', 'quicklearn'),
    ));
    
    // Courses per page setting
    $wp_customize->add_setting('quicklearn_courses_per_page', array(
        'default' => 12,
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control('quicklearn_courses_per_page', array(
        'label' => __('Courses per page', 'quicklearn'),
        'section' => 'quicklearn_course_settings',
        'type' => 'number',
        'input_attrs' => array(
            'min' => 6,
            'max' => 24,
            'step' => 3,
        ),
    ));
    
    // Show course excerpts
    $wp_customize->add_setting('quicklearn_show_course_excerpts', array(
        'default' => true,
        'sanitize_callback' => 'wp_validate_boolean',
    ));
    
    $wp_customize->add_control('quicklearn_show_course_excerpts', array(
        'label' => __('Show course excerpts', 'quicklearn'),
        'section' => 'quicklearn_course_settings',
        'type' => 'checkbox',
    ));
    
    // Course card style
    $wp_customize->add_setting('quicklearn_course_card_style', array(
        'default' => 'standard',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('quicklearn_course_card_style', array(
        'label' => __('Course card style', 'quicklearn'),
        'section' => 'quicklearn_course_settings',
        'type' => 'select',
        'choices' => array(
            'standard' => __('Standard', 'quicklearn'),
            'compact' => __('Compact', 'quicklearn'),
            'detailed' => __('Detailed', 'quicklearn'),
        ),
    ));
}
add_action('customize_register', 'quicklearn_course_customizer');

/**
 * Get course display settings from customizer
 */
function quicklearn_get_courses_per_page() {
    return get_theme_mod('quicklearn_courses_per_page', 12);
}

function quicklearn_show_course_excerpts() {
    return get_theme_mod('quicklearn_show_course_excerpts', true);
}

function quicklearn_get_course_card_style() {
    return get_theme_mod('quicklearn_course_card_style', 'standard');
}

/**
 * Add course archive page support
 */
function quicklearn_course_archive_settings($query) {
    if (!is_admin() && $query->is_main_query()) {
        if (is_post_type_archive('quick_course') || is_tax('course_category')) {
            $query->set('posts_per_page', quicklearn_get_courses_per_page());
            $query->set('meta_key', '_course_order');
            $query->set('orderby', 'meta_value_num date');
            $query->set('order', 'ASC');
        }
    }
}
add_action('pre_get_posts', 'quicklearn_course_archive_settings');

/**
 * Add course shortcode support
 */
function quicklearn_course_shortcodes() {
    // Course grid shortcode
    add_shortcode('course_grid', 'quicklearn_course_grid_shortcode');
    
    // Featured courses shortcode
    add_shortcode('featured_courses', 'quicklearn_featured_courses_shortcode');
    
    // Course categories shortcode
    add_shortcode('course_categories', 'quicklearn_course_categories_shortcode');
}
add_action('init', 'quicklearn_course_shortcodes');

/**
 * Course grid shortcode function
 */
function quicklearn_course_grid_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 6,
        'category' => '',
        'columns' => 3,
        'show_excerpt' => 'true',
    ), $atts);
    
    $args = array(
        'post_type' => 'quick_course',
        'posts_per_page' => intval($atts['limit']),
        'post_status' => 'publish',
    );
    
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'course_category',
                'field' => 'slug',
                'terms' => $atts['category'],
            ),
        );
    }
    
    $courses = new WP_Query($args);
    
    if (!$courses->have_posts()) {
        return '<p>' . __('No courses found.', 'quicklearn') . '</p>';
    }
    
    ob_start();
    echo '<div class="course-grid-shortcode columns-' . esc_attr($atts['columns']) . '">';
    
    while ($courses->have_posts()) {
        $courses->the_post();
        get_template_part('template-parts/course', 'card');
    }
    
    echo '</div>';
    wp_reset_postdata();
    
    return ob_get_clean();
}

/**
 * Featured courses shortcode function
 */
function quicklearn_featured_courses_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 3,
    ), $atts);
    
    $args = array(
        'post_type' => 'quick_course',
        'posts_per_page' => intval($atts['limit']),
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_featured_course',
                'value' => '1',
                'compare' => '=',
            ),
        ),
    );
    
    $courses = new WP_Query($args);
    
    if (!$courses->have_posts()) {
        return '<p>' . __('No featured courses found.', 'quicklearn') . '</p>';
    }
    
    ob_start();
    echo '<div class="featured-courses-shortcode">';
    
    while ($courses->have_posts()) {
        $courses->the_post();
        get_template_part('template-parts/course', 'card');
    }
    
    echo '</div>';
    wp_reset_postdata();
    
    return ob_get_clean();
}

/**
 * Course categories shortcode function
 */
function quicklearn_course_categories_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_count' => 'true',
        'hierarchical' => 'true',
    ), $atts);
    
    $terms = get_terms(array(
        'taxonomy' => 'course_category',
        'hide_empty' => true,
        'hierarchical' => $atts['hierarchical'] === 'true',
    ));
    
    if (empty($terms) || is_wp_error($terms)) {
        return '<p>' . __('No course categories found.', 'quicklearn') . '</p>';
    }
    
    ob_start();
    echo '<div class="course-categories-shortcode">';
    echo '<ul class="course-category-list">';
    
    foreach ($terms as $term) {
        echo '<li>';
        echo '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
        if ($atts['show_count'] === 'true') {
            echo ' <span class="category-count">(' . $term->count . ')</span>';
        }
        echo '</li>';
    }
    
    echo '</ul>';
    echo '</div>';
    
    return ob_get_clean();
}

/**
 * Theme activation function - Creates necessary pages automatically
 */
function quicklearn_theme_activation() {
    // Create necessary pages for the theme
    $pages = array(
        array(
            'title' => 'Courses',
            'content' => '<!-- wp:shortcode -->[course_grid limit="12" columns="3"]<!-- /wp:shortcode -->',
            'template' => 'page-courses.php',
            'meta_key' => '_quicklearn_courses_page'
        ),
        array(
            'title' => 'Dashboard',
            'content' => '<!-- wp:paragraph --><p>Welcome to your learning dashboard!</p><!-- /wp:paragraph -->',
            'template' => 'page-dashboard.php',
            'meta_key' => '_quicklearn_dashboard_page'
        ),
        array(
            'title' => 'Verify Certificate',
            'content' => '<!-- wp:paragraph --><p>Enter your certificate code to verify its authenticity.</p><!-- /wp:paragraph -->',
            'template' => 'page-verify-certificate.php',
            'meta_key' => '_quicklearn_verify_page'
        ),
        array(
            'title' => 'Login',
            'content' => '<!-- wp:shortcode -->[wp_login_form]<!-- /wp:shortcode -->',
            'template' => '',
            'meta_key' => '_quicklearn_login_page'
        ),
        array(
            'title' => 'Register',
            'content' => '<!-- wp:shortcode -->[wp_registration_form]<!-- /wp:shortcode -->',
            'template' => '',
            'meta_key' => '_quicklearn_register_page'
        ),
        array(
            'title' => 'My Account',
            'content' => '<!-- wp:paragraph --><p>Manage your account settings and preferences.</p><!-- /wp:paragraph -->',
            'template' => '',
            'meta_key' => '_quicklearn_account_page'
        ),
        array(
            'title' => 'My Courses',
            'content' => '<!-- wp:shortcode -->[my_enrolled_courses]<!-- /wp:shortcode -->',
            'template' => '',
            'meta_key' => '_quicklearn_my_courses_page'
        )
    );
    
    foreach ($pages as $page_data) {
        // Check if page already exists
        $existing_page = get_option($page_data['meta_key']);
        if ($existing_page && get_post($existing_page)) {
            continue; // Page already exists
        }
        
        // Create the page
        $page_args = array(
            'post_title'    => $page_data['title'],
            'post_content'  => $page_data['content'],
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
            'comment_status' => 'closed'
        );
        
        // Insert the page
        $page_id = wp_insert_post($page_args);
        
        if (!is_wp_error($page_id)) {
            // Set page template if specified
            if (!empty($page_data['template'])) {
                update_post_meta($page_id, '_wp_page_template', $page_data['template']);
            }
            
            // Save the page ID for future reference
            update_option($page_data['meta_key'], $page_id);
        }
    }
    
    // Create default menu if it doesn't exist
    quicklearn_create_default_menu();
    
    // Set front page options
    update_option('show_on_front', 'page');
    $home_page = get_option('_quicklearn_courses_page');
    if ($home_page) {
        update_option('page_on_front', $home_page);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'quicklearn_theme_activation');

/**
 * Create default navigation menu
 */
function quicklearn_create_default_menu() {
    // Check if primary menu already exists
    $menu_name = 'Primary Menu';
    $menu_exists = wp_get_nav_menu_object($menu_name);
    
    if (!$menu_exists) {
        // Create the menu
        $menu_id = wp_create_nav_menu($menu_name);
        
        if (!is_wp_error($menu_id)) {
            // Add menu items
            $menu_items = array(
                array(
                    'title' => 'Home',
                    'url' => home_url('/'),
                    'position' => 1
                ),
                array(
                    'title' => 'Courses',
                    'page_option' => '_quicklearn_courses_page',
                    'position' => 2
                ),
                array(
                    'title' => 'Dashboard',
                    'page_option' => '_quicklearn_dashboard_page',
                    'position' => 3
                ),
                array(
                    'title' => 'My Account',
                    'page_option' => '_quicklearn_account_page',
                    'position' => 4
                ),
                array(
                    'title' => 'Login',
                    'page_option' => '_quicklearn_login_page',
                    'position' => 5
                ),
                array(
                    'title' => 'Register',
                    'page_option' => '_quicklearn_register_page',
                    'position' => 6
                )
            );
            
            foreach ($menu_items as $item) {
                $menu_item_data = array(
                    'menu-item-title' => $item['title'],
                    'menu-item-position' => $item['position'],
                    'menu-item-status' => 'publish'
                );
                
                if (isset($item['url'])) {
                    $menu_item_data['menu-item-url'] = $item['url'];
                    $menu_item_data['menu-item-type'] = 'custom';
                } elseif (isset($item['page_option'])) {
                    $page_id = get_option($item['page_option']);
                    if ($page_id) {
                        $menu_item_data['menu-item-object'] = 'page';
                        $menu_item_data['menu-item-object-id'] = $page_id;
                        $menu_item_data['menu-item-type'] = 'post_type';
                    }
                }
                
                wp_update_nav_menu_item($menu_id, 0, $menu_item_data);
            }
            
            // Assign menu to primary location
            $locations = get_theme_mod('nav_menu_locations');
            $locations['primary'] = $menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
    }
}

/**
 * Add custom login/register shortcodes
 */
function quicklearn_register_auth_shortcodes() {
    // Login form shortcode
    add_shortcode('wp_login_form', 'quicklearn_login_form_shortcode');
    
    // Registration form shortcode
    add_shortcode('wp_registration_form', 'quicklearn_registration_form_shortcode');
    
    // My enrolled courses shortcode
    add_shortcode('my_enrolled_courses', 'quicklearn_my_enrolled_courses_shortcode');
}
add_action('init', 'quicklearn_register_auth_shortcodes');

/**
 * Login form shortcode
 */
function quicklearn_login_form_shortcode($atts) {
    if (is_user_logged_in()) {
        return '<p>' . __('You are already logged in.', 'quicklearn') . ' <a href="' . esc_url(get_option('_quicklearn_dashboard_page') ? get_permalink(get_option('_quicklearn_dashboard_page')) : home_url('/dashboard')) . '">' . __('Go to Dashboard', 'quicklearn') . '</a></p>';
    }
    
    $atts = shortcode_atts(array(
        'redirect' => get_option('_quicklearn_dashboard_page') ? get_permalink(get_option('_quicklearn_dashboard_page')) : home_url('/dashboard'),
        'form_id' => 'quicklearn-login-form',
        'label_username' => __('Username or Email', 'quicklearn'),
        'label_password' => __('Password', 'quicklearn'),
        'label_remember' => __('Remember Me', 'quicklearn'),
        'label_log_in' => __('Log In', 'quicklearn'),
        'remember' => true
    ), $atts);
    
    return wp_login_form(array(
        'echo' => false,
        'redirect' => $atts['redirect'],
        'form_id' => $atts['form_id'],
        'label_username' => $atts['label_username'],
        'label_password' => $atts['label_password'],
        'label_remember' => $atts['label_remember'],
        'label_log_in' => $atts['label_log_in'],
        'id_username' => 'user_login',
        'id_password' => 'user_pass',
        'id_remember' => 'rememberme',
        'id_submit' => 'wp-submit',
        'remember' => $atts['remember'],
        'value_username' => '',
        'value_remember' => false
    ));
}

/**
 * Registration form shortcode
 */
function quicklearn_registration_form_shortcode($atts) {
    if (is_user_logged_in()) {
        return '<p>' . __('You are already registered and logged in.', 'quicklearn') . '</p>';
    }
    
    // Check if registration is enabled
    if (!get_option('users_can_register')) {
        return '<p>' . __('User registration is currently disabled.', 'quicklearn') . '</p>';
    }
    
    ob_start();
    ?>
    <form id="quicklearn-registration-form" class="quicklearn-form" action="<?php echo esc_url(site_url('wp-login.php?action=register', 'login_post')); ?>" method="post">
        <p class="quicklearn-form-row">
            <label for="user_login"><?php _e('Username', 'quicklearn'); ?> <span class="required">*</span></label>
            <input type="text" name="user_login" id="user_login" class="quicklearn-form-input" value="" required />
        </p>
        
        <p class="quicklearn-form-row">
            <label for="user_email"><?php _e('Email Address', 'quicklearn'); ?> <span class="required">*</span></label>
            <input type="email" name="user_email" id="user_email" class="quicklearn-form-input" value="" required />
        </p>
        
        <p class="quicklearn-form-row">
            <label for="first_name"><?php _e('First Name', 'quicklearn'); ?></label>
            <input type="text" name="first_name" id="first_name" class="quicklearn-form-input" value="" />
        </p>
        
        <p class="quicklearn-form-row">
            <label for="last_name"><?php _e('Last Name', 'quicklearn'); ?></label>
            <input type="text" name="last_name" id="last_name" class="quicklearn-form-input" value="" />
        </p>
        
        <?php do_action('register_form'); ?>
        
        <p class="quicklearn-form-row">
            <input type="submit" name="wp-submit" id="wp-submit" class="quicklearn-form-button" value="<?php esc_attr_e('Register', 'quicklearn'); ?>" />
        </p>
        
        <p class="quicklearn-form-info">
            <?php _e('A password will be sent to your email address.', 'quicklearn'); ?>
        </p>
        
        <p class="quicklearn-form-links">
            <?php _e('Already have an account?', 'quicklearn'); ?> 
            <a href="<?php echo esc_url(get_option('_quicklearn_login_page') ? get_permalink(get_option('_quicklearn_login_page')) : wp_login_url()); ?>">
                <?php _e('Log in', 'quicklearn'); ?>
            </a>
        </p>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * My enrolled courses shortcode
 */
function quicklearn_my_enrolled_courses_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>' . __('Please log in to view your enrolled courses.', 'quicklearn') . ' <a href="' . esc_url(get_option('_quicklearn_login_page') ? get_permalink(get_option('_quicklearn_login_page')) : wp_login_url()) . '">' . __('Log in', 'quicklearn') . '</a></p>';
    }
    
    $user_id = get_current_user_id();
    
    // Get enrolled courses using plugin function if available
    if (function_exists('qlcm_get_user_enrolled_courses')) {
        $enrolled_courses = qlcm_get_user_enrolled_courses($user_id);
        
        if (empty($enrolled_courses)) {
            return '<p>' . __('You have not enrolled in any courses yet.', 'quicklearn') . ' <a href="' . esc_url(get_option('_quicklearn_courses_page') ? get_permalink(get_option('_quicklearn_courses_page')) : home_url('/courses')) . '">' . __('Browse Courses', 'quicklearn') . '</a></p>';
        }
        
        ob_start();
        echo '<div class="my-enrolled-courses">';
        echo '<div class="course-grid">';
        
        foreach ($enrolled_courses as $course_id) {
            $post = get_post($course_id);
            if ($post && $post->post_status === 'publish') {
                setup_postdata($post);
                get_template_part('template-parts/course', 'card');
            }
        }
        
        echo '</div>';
        echo '</div>';
        wp_reset_postdata();
        
        return ob_get_clean();
    } else {
        return '<p>' . __('Course enrollment feature is not available.', 'quicklearn') . '</p>';
    }
}