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
    // Enqueue main stylesheet
    wp_enqueue_style(
        'quicklearn-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get('Version')
    );
    
    // Enqueue custom CSS
    wp_enqueue_style(
        'quicklearn-custom',
        get_template_directory_uri() . '/css/custom.css',
        array('quicklearn-style'),
        wp_get_theme()->get('Version')
    );
    
    // Enqueue navigation script
    wp_enqueue_script(
        'quicklearn-navigation',
        get_template_directory_uri() . '/js/navigation.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
    
    // Enqueue course filter script (for courses page)
    if (is_page('courses') || is_page_template('page-courses.php')) {
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