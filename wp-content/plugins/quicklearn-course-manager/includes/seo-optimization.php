<?php
/**
 * SEO Optimization for QuickLearn Course Manager
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling SEO optimization
 */
class QLCM_SEO_Optimization {
    
    /**
     * Instance of this class
     *
     * @var QLCM_SEO_Optimization
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_SEO_Optimization Instance of this class
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
        // Add structured data to course pages
        add_action('wp_head', array($this, 'add_course_structured_data'));
        
        // Add Open Graph tags
        add_action('wp_head', array($this, 'add_open_graph_tags'));
        
        // Add Twitter Card tags
        add_action('wp_head', array($this, 'add_twitter_card_tags'));
        
        // Add meta description
        add_action('wp_head', array($this, 'add_meta_description'));
        
        // Add canonical URL
        add_action('wp_head', array($this, 'add_canonical_url'));
        
        // Register sitemap
        add_action('init', array($this, 'register_sitemap'));
        
        // Add breadcrumb structured data
        add_action('wp_head', array($this, 'add_breadcrumb_structured_data'));
    }
    
    /**
     * Add structured data for courses
     */
    public function add_course_structured_data() {
        if (!is_singular('quick_course')) {
            return;
        }
        
        global $post;
        $course = $post;
        
        // Get course data
        $title = get_the_title($course->ID);
        $description = get_the_excerpt($course->ID);
        if (empty($description)) {
            $description = wp_trim_words(get_the_content(), 25);
        }
        $url = get_permalink($course->ID);
        $date_published = get_the_date('c', $course->ID);
        $date_modified = get_the_modified_date('c', $course->ID);
        
        // Get featured image
        $image = '';
        if (has_post_thumbnail($course->ID)) {
            $image_id = get_post_thumbnail_id($course->ID);
            $image_data = wp_get_attachment_image_src($image_id, 'full');
            if ($image_data) {
                $image = $image_data[0];
            }
        }
        
        // Get categories
        $categories = get_the_terms($course->ID, 'course_category');
        $category_names = array();
        
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_names[] = $category->name;
            }
        }
        
        // Create structured data array
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'datePublished' => $date_published,
            'dateModified' => $date_modified,
            'provider' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => get_home_url()
            ),
            'educationalLevel' => 'Beginner',
            'courseMode' => 'online',
            'hasCourseInstance' => array(
                '@type' => 'CourseInstance',
                'courseMode' => 'online',
                'instructor' => array(
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name')
                )
            )
        );
        
        // Add image if available
        if (!empty($image)) {
            $schema['image'] = $image;
        }
        
        // Add categories as course subject
        if (!empty($category_names)) {
            $schema['about'] = implode(', ', $category_names);
            $schema['keywords'] = implode(', ', $category_names);
        }
        
        // Add rating data if available
        if (class_exists('QLCM_Course_Ratings')) {
            $ratings_instance = QLCM_Course_Ratings::get_instance();
            $rating_data = $ratings_instance->get_course_rating_data($course->ID);
            
            if ($rating_data['count'] > 0) {
                $schema['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'ratingValue' => number_format($rating_data['average'], 1),
                    'reviewCount' => $rating_data['count'],
                    'bestRating' => 5,
                    'worstRating' => 1
                );
            }
        }
        
        // Apply filter to allow modifications
        $schema = apply_filters('qlcm_course_structured_data', $schema);
        
        // Output structured data
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
    
    /**
     * Add breadcrumb structured data
     */
    public function add_breadcrumb_structured_data() {
        if (!is_singular('quick_course') && !is_post_type_archive('quick_course') && !is_tax('course_category')) {
            return;
        }
        
        $breadcrumbs = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array()
        );
        
        $position = 1;
        
        // Home
        $breadcrumbs['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Home', 'quicklearn-course-manager'),
            'item' => get_home_url()
        );
        
        // Courses
        $breadcrumbs['itemListElement'][] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Courses', 'quicklearn-course-manager'),
            'item' => get_post_type_archive_link('quick_course')
        );
        
        // Category (if applicable)
        if (is_tax('course_category')) {
            $term = get_queried_object();
            $breadcrumbs['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $term->name,
                'item' => get_term_link($term)
            );
        } elseif (is_singular('quick_course')) {
            $categories = get_the_terms(get_the_ID(), 'course_category');
            if ($categories && !is_wp_error($categories)) {
                $category = $categories[0]; // Use first category
                $breadcrumbs['itemListElement'][] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $category->name,
                    'item' => get_term_link($category)
                );
            }
            
            // Current course
            $breadcrumbs['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => get_the_title(),
                'item' => get_permalink()
            );
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
    
    /**
     * Add Open Graph tags
     */
    public function add_open_graph_tags() {
        if (!is_singular('quick_course') && !is_post_type_archive('quick_course') && !is_tax('course_category')) {
            return;
        }
        
        $title = '';
        $description = '';
        $url = '';
        $image = '';
        $type = 'website';
        
        if (is_singular('quick_course')) {
            $course = get_post();
            $title = get_the_title($course->ID);
            $description = get_the_excerpt($course->ID);
            if (empty($description)) {
                $description = wp_trim_words(get_the_content(), 25);
            }
            $url = get_permalink($course->ID);
            $type = 'article';
            
            // Get featured image
            if (has_post_thumbnail($course->ID)) {
                $image_id = get_post_thumbnail_id($course->ID);
                $image_data = wp_get_attachment_image_src($image_id, 'large');
                if ($image_data) {
                    $image = $image_data[0];
                }
            }
        } elseif (is_post_type_archive('quick_course')) {
            $title = __('Courses', 'quicklearn-course-manager') . ' - ' . get_bloginfo('name');
            $description = __('Browse our complete catalog of online courses. Filter by category to find the perfect course for your learning needs.', 'quicklearn-course-manager');
            $url = get_post_type_archive_link('quick_course');
        } elseif (is_tax('course_category')) {
            $term = get_queried_object();
            $title = $term->name . ' ' . __('Courses', 'quicklearn-course-manager') . ' - ' . get_bloginfo('name');
            $description = $term->description ? $term->description : sprintf(__('Browse %s courses in our online catalog.', 'quicklearn-course-manager'), $term->name);
            $url = get_term_link($term);
        }
        
        // Output Open Graph tags
        echo '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
        
        if (!empty($image)) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($title) . '" />' . "\n";
        }
        
        // Add article specific tags
        if ($type === 'article') {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '" />' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '" />' . "\n";
            
            $categories = get_the_terms(get_the_ID(), 'course_category');
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    echo '<meta property="article:section" content="' . esc_attr($category->name) . '" />' . "\n";
                }
            }
        }
    }
    
    /**
     * Add Twitter Card tags
     */
    public function add_twitter_card_tags() {
        if (!is_singular('quick_course') && !is_post_type_archive('quick_course') && !is_tax('course_category')) {
            return;
        }
        
        $title = '';
        $description = '';
        $image = '';
        $card_type = 'summary';
        
        if (is_singular('quick_course')) {
            $course = get_post();
            $title = get_the_title($course->ID);
            $description = get_the_excerpt($course->ID);
            if (empty($description)) {
                $description = wp_trim_words(get_the_content(), 25);
            }
            
            // Get featured image
            if (has_post_thumbnail($course->ID)) {
                $image_id = get_post_thumbnail_id($course->ID);
                $image_data = wp_get_attachment_image_src($image_id, 'large');
                if ($image_data) {
                    $image = $image_data[0];
                    $card_type = 'summary_large_image';
                }
            }
        } elseif (is_post_type_archive('quick_course')) {
            $title = __('Courses', 'quicklearn-course-manager') . ' - ' . get_bloginfo('name');
            $description = __('Browse our complete catalog of online courses. Filter by category to find the perfect course for your learning needs.', 'quicklearn-course-manager');
        } elseif (is_tax('course_category')) {
            $term = get_queried_object();
            $title = $term->name . ' ' . __('Courses', 'quicklearn-course-manager') . ' - ' . get_bloginfo('name');
            $description = $term->description ? $term->description : sprintf(__('Browse %s courses in our online catalog.', 'quicklearn-course-manager'), $term->name);
        }
        
        // Output Twitter Card tags
        echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if (!empty($image)) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr($title) . '" />' . "\n";
        }
        
        // Add site Twitter handle if available
        $twitter_handle = get_option('qlcm_twitter_handle');
        if ($twitter_handle) {
            echo '<meta name="twitter:site" content="@' . esc_attr($twitter_handle) . '" />' . "\n";
        }
    }
    
    /**
     * Add meta description
     */
    public function add_meta_description() {
        $description = '';
        
        if (is_singular('quick_course')) {
            $description = get_the_excerpt();
            if (empty($description)) {
                $description = wp_trim_words(get_the_content(), 25);
            }
        } elseif (is_post_type_archive('quick_course')) {
            $description = __('Browse our complete catalog of online courses. Filter by category to find the perfect course for your learning needs.', 'quicklearn-course-manager');
        } elseif (is_tax('course_category')) {
            $term = get_queried_object();
            $description = $term->description ? $term->description : sprintf(__('Browse %s courses in our online catalog.', 'quicklearn-course-manager'), $term->name);
        }
        
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }
    }
    
    /**
     * Add canonical URL
     */
    public function add_canonical_url() {
        $canonical_url = '';
        
        if (is_singular('quick_course')) {
            $canonical_url = get_permalink();
        } elseif (is_post_type_archive('quick_course')) {
            $canonical_url = get_post_type_archive_link('quick_course');
        } elseif (is_tax('course_category')) {
            $canonical_url = get_term_link(get_queried_object());
        }
        
        if (!empty($canonical_url)) {
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        }
    }
    
    /**
     * Register XML sitemap for courses
     */
    public function register_sitemap() {
        // Only register sitemap if WordPress 5.5+ (with core sitemaps)
        if (!function_exists('wp_sitemaps_get_server')) {
            return;
        }
        
        // Add course post type to sitemap
        add_filter('wp_sitemaps_post_types', function($post_types) {
            if (isset($post_types['quick_course'])) {
                // Already included
                return $post_types;
            }
            
            $post_type_object = get_post_type_object('quick_course');
            if ($post_type_object && $post_type_object->public) {
                $post_types['quick_course'] = $post_type_object;
            }
            
            return $post_types;
        });
        
        // Add course_category taxonomy to sitemap
        add_filter('wp_sitemaps_taxonomies', function($taxonomies) {
            if (isset($taxonomies['course_category'])) {
                // Already included
                return $taxonomies;
            }
            
            $taxonomy_object = get_taxonomy('course_category');
            if ($taxonomy_object && $taxonomy_object->public) {
                $taxonomies['course_category'] = $taxonomy_object;
            }
            
            return $taxonomies;
        });
        
        // Set sitemap priority and frequency for courses
        add_filter('wp_sitemaps_posts_entry', function($sitemap_entry, $post) {
            if ($post->post_type === 'quick_course') {
                $sitemap_entry['priority'] = 0.8;
                $sitemap_entry['changefreq'] = 'weekly';
            }
            return $sitemap_entry;
        }, 10, 2);
        
        // Set sitemap priority and frequency for course categories
        add_filter('wp_sitemaps_taxonomies_entry', function($sitemap_entry, $term, $taxonomy) {
            if ($taxonomy === 'course_category') {
                $sitemap_entry['priority'] = 0.6;
                $sitemap_entry['changefreq'] = 'monthly';
            }
            return $sitemap_entry;
        }, 10, 3);
    }
    
    /**
     * Add robots meta tag for better crawling
     */
    public function add_robots_meta() {
        if (is_singular('quick_course') || is_post_type_archive('quick_course') || is_tax('course_category')) {
            echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />' . "\n";
        }
    }
    
    /**
     * Add hreflang tags for multilingual support
     */
    public function add_hreflang_tags() {
        // This would be implemented if the site supports multiple languages
        // For now, we'll add the current language
        $locale = get_locale();
        $language = substr($locale, 0, 2);
        
        if (is_singular('quick_course') || is_post_type_archive('quick_course') || is_tax('course_category')) {
            $current_url = '';
            
            if (is_singular('quick_course')) {
                $current_url = get_permalink();
            } elseif (is_post_type_archive('quick_course')) {
                $current_url = get_post_type_archive_link('quick_course');
            } elseif (is_tax('course_category')) {
                $current_url = get_term_link(get_queried_object());
            }
            
            if (!empty($current_url)) {
                echo '<link rel="alternate" hreflang="' . esc_attr($language) . '" href="' . esc_url($current_url) . '" />' . "\n";
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($current_url) . '" />' . "\n";
            }
        }
    }
}

// Initialize SEO optimization
QLCM_SEO_Optimization::get_instance();