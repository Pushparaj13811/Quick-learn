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
        
        // Localize script for AJAX
        wp_localize_script('quicklearn-course-filter', 'quicklearn_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('quicklearn_filter_nonce'),
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