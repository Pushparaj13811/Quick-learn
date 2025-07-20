<?php
/**
 * Frontend Performance Optimization
 *
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling frontend performance optimizations
 */
class QuickLearn_Performance_Optimization {
    
    /**
     * Instance of this class
     *
     * @var QuickLearn_Performance_Optimization
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QuickLearn_Performance_Optimization Instance of this class
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
        // Initialize performance optimizations
        add_action('init', array($this, 'init_performance_optimizations'));
        
        // Add lazy loading for images and videos
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading_attributes'), 10, 3);
        add_filter('the_content', array($this, 'add_lazy_loading_to_content'));
        
        // Optimize CSS and JavaScript loading
        add_action('wp_enqueue_scripts', array($this, 'optimize_asset_loading'), 999);
        add_filter('style_loader_tag', array($this, 'optimize_css_loading'), 10, 4);
        add_filter('script_loader_tag', array($this, 'optimize_js_loading'), 10, 3);
        
        // Add browser caching headers
        add_action('send_headers', array($this, 'add_caching_headers'));
        
        // Implement AJAX pagination for course lists
        add_action('wp_ajax_load_more_courses', array($this, 'handle_ajax_pagination'));
        add_action('wp_ajax_nopriv_load_more_courses', array($this, 'handle_ajax_pagination'));
        
        // Add resource hints
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
        
        // Optimize images
        add_filter('wp_generate_attachment_metadata', array($this, 'optimize_image_generation'), 10, 2);
        
        // Add critical CSS inlining
        add_action('wp_head', array($this, 'inline_critical_css'), 2);
        
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 3);
        
        // Add service worker for caching
        add_action('wp_footer', array($this, 'register_service_worker'));
        
        // Optimize database queries
        add_action('pre_get_posts', array($this, 'optimize_course_queries'));
    }
    
    /**
     * Initialize performance optimizations
     */
    public function init_performance_optimizations() {
        // Enable output buffering for HTML minification
        if (!is_admin()) {
            ob_start(array($this, 'minify_html_output'));
        }
        
        // Remove unnecessary WordPress features
        $this->remove_unnecessary_features();
        
        // Optimize WordPress queries
        $this->optimize_wordpress_queries();
    }
    
    /**
     * Add lazy loading attributes to images
     *
     * @param array $attr Image attributes
     * @param WP_Post $attachment Attachment post object
     * @param string $size Image size
     * @return array Modified attributes
     */
    public function add_lazy_loading_attributes($attr, $attachment, $size) {
        // Skip if it's the first image (above the fold)
        static $image_count = 0;
        $image_count++;
        
        // Don't lazy load the first 2 images (likely above the fold)
        if ($image_count <= 2) {
            return $attr;
        }
        
        // Add lazy loading attributes
        $attr['loading'] = 'lazy';
        $attr['decoding'] = 'async';
        
        // Add intersection observer data attributes for custom lazy loading
        $attr['data-src'] = $attr['src'];
        $attr['data-srcset'] = isset($attr['srcset']) ? $attr['srcset'] : '';
        $attr['src'] = $this->get_placeholder_image();
        
        // Remove srcset temporarily
        if (isset($attr['srcset'])) {
            unset($attr['srcset']);
        }
        
        // Add lazy loading class
        $attr['class'] = isset($attr['class']) ? $attr['class'] . ' lazy-load' : 'lazy-load';
        
        return $attr;
    }
    
    /**
     * Add lazy loading to content images and videos
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function add_lazy_loading_to_content($content) {
        // Skip if we're in admin or RSS feed
        if (is_admin() || is_feed()) {
            return $content;
        }
        
        // Lazy load images in content
        $content = preg_replace_callback(
            '/<img([^>]+)>/i',
            array($this, 'make_image_lazy'),
            $content
        );
        
        // Lazy load videos in content
        $content = preg_replace_callback(
            '/<video([^>]+)>/i',
            array($this, 'make_video_lazy'),
            $content
        );
        
        // Lazy load iframes (YouTube, Vimeo, etc.)
        $content = preg_replace_callback(
            '/<iframe([^>]+)>/i',
            array($this, 'make_iframe_lazy'),
            $content
        );
        
        return $content;
    }
    
    /**
     * Make image lazy loadable
     *
     * @param array $matches Regex matches
     * @return string Modified image tag
     */
    private function make_image_lazy($matches) {
        $img_tag = $matches[0];
        $attributes = $matches[1];
        
        // Skip if already has loading attribute or is above the fold
        if (strpos($attributes, 'loading=') !== false || strpos($attributes, 'data-src=') !== false) {
            return $img_tag;
        }
        
        // Extract src attribute
        if (preg_match('/src=["\']([^"\']+)["\']/', $attributes, $src_matches)) {
            $original_src = $src_matches[1];
            $placeholder_src = $this->get_placeholder_image();
            
            // Replace src with placeholder and add data-src
            $attributes = str_replace($src_matches[0], 'src="' . $placeholder_src . '" data-src="' . $original_src . '"', $attributes);
            
            // Add lazy loading attributes
            $attributes .= ' loading="lazy" decoding="async"';
            
            // Add lazy class
            if (preg_match('/class=["\']([^"\']*)["\']/', $attributes, $class_matches)) {
                $new_class = $class_matches[1] . ' lazy-load';
                $attributes = str_replace($class_matches[0], 'class="' . $new_class . '"', $attributes);
            } else {
                $attributes .= ' class="lazy-load"';
            }
            
            return '<img' . $attributes . '>';
        }
        
        return $img_tag;
    }
    
    /**
     * Make video lazy loadable
     *
     * @param array $matches Regex matches
     * @return string Modified video tag
     */
    private function make_video_lazy($matches) {
        $video_tag = $matches[0];
        $attributes = $matches[1];
        
        // Add lazy loading attributes
        if (strpos($attributes, 'preload=') === false) {
            $attributes .= ' preload="none"';
        }
        
        // Add lazy class
        if (preg_match('/class=["\']([^"\']*)["\']/', $attributes, $class_matches)) {
            $new_class = $class_matches[1] . ' lazy-load-video';
            $attributes = str_replace($class_matches[0], 'class="' . $new_class . '"', $attributes);
        } else {
            $attributes .= ' class="lazy-load-video"';
        }
        
        return '<video' . $attributes . '>';
    }
    
    /**
     * Make iframe lazy loadable
     *
     * @param array $matches Regex matches
     * @return string Modified iframe tag
     */
    private function make_iframe_lazy($matches) {
        $iframe_tag = $matches[0];
        $attributes = $matches[1];
        
        // Skip if already has loading attribute
        if (strpos($attributes, 'loading=') !== false || strpos($attributes, 'data-src=') !== false) {
            return $iframe_tag;
        }
        
        // Extract src attribute
        if (preg_match('/src=["\']([^"\']+)["\']/', $attributes, $src_matches)) {
            $original_src = $src_matches[1];
            
            // Replace src with data-src
            $attributes = str_replace($src_matches[0], 'data-src="' . $original_src . '"', $attributes);
            
            // Add lazy loading attributes
            $attributes .= ' loading="lazy"';
            
            // Add lazy class
            if (preg_match('/class=["\']([^"\']*)["\']/', $attributes, $class_matches)) {
                $new_class = $class_matches[1] . ' lazy-load-iframe';
                $attributes = str_replace($class_matches[0], 'class="' . $new_class . '"', $attributes);
            } else {
                $attributes .= ' class="lazy-load-iframe"';
            }
            
            return '<iframe' . $attributes . '></iframe>';
        }
        
        return $iframe_tag;
    }
    
    /**
     * Get placeholder image for lazy loading
     *
     * @return string Placeholder image URL
     */
    private function get_placeholder_image() {
        // Return a 1x1 transparent pixel
        return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E';
    }
    
    /**
     * Optimize asset loading
     */
    public function optimize_asset_loading() {
        // Remove unnecessary default WordPress scripts and styles
        if (!is_admin()) {
            // Remove block library CSS if not using blocks
            if (!current_theme_supports('wp-block-styles')) {
                wp_dequeue_style('wp-block-library');
                wp_dequeue_style('wp-block-library-theme');
                wp_dequeue_style('wc-block-style');
            }
            
            // Remove dashicons for non-admin users
            if (!is_user_logged_in()) {
                wp_dequeue_style('dashicons');
            }
            
            // Remove contact form 7 styles if not on contact page
            if (!is_page('contact') && !is_singular('contact')) {
                wp_dequeue_style('contact-form-7');
                wp_dequeue_script('contact-form-7');
            }
            
            // Conditionally load course-specific scripts
            if (!is_singular('quick_course') && !is_post_type_archive('quick_course') && !is_tax('course_category')) {
                wp_dequeue_script('quicklearn-course-filter');
                wp_dequeue_script('quicklearn-course-modules');
                wp_dequeue_script('quicklearn-ratings');
            }
            
            // Remove jQuery migrate if not needed
            if (!is_admin()) {
                wp_deregister_script('jquery-migrate');
            }
        }
    }
    
    /**
     * Optimize CSS loading
     *
     * @param string $tag CSS link tag
     * @param string $handle CSS handle
     * @param string $href CSS URL
     * @param string $media Media attribute
     * @return string Modified CSS tag
     */
    public function optimize_css_loading($tag, $handle, $href, $media) {
        // Critical CSS files to load normally
        $critical_css = array('quicklearn-style', 'quicklearn-critical');
        
        if (in_array($handle, $critical_css)) {
            return $tag;
        }
        
        // Non-critical CSS files to load asynchronously
        $non_critical_css = array('quicklearn-custom', 'dashicons', 'wp-block-library');
        
        if (in_array($handle, $non_critical_css)) {
            // Load CSS asynchronously
            $tag = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
            $tag = str_replace('rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag);
            
            // Add noscript fallback
            $noscript = '<noscript>' . str_replace('rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', 'rel="stylesheet"', $tag) . '</noscript>';
            $tag .= $noscript;
        }
        
        return $tag;
    }
    
    /**
     * Optimize JavaScript loading
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script URL
     * @return string Modified script tag
     */
    public function optimize_js_loading($tag, $handle, $src) {
        // Critical JavaScript files to load normally
        $critical_js = array('jquery-core', 'quicklearn-critical');
        
        if (in_array($handle, $critical_js)) {
            return $tag;
        }
        
        // Non-critical JavaScript files to defer
        $defer_js = array('quicklearn-navigation', 'quicklearn-course-filter', 'quicklearn-lazy-loading');
        
        if (in_array($handle, $defer_js)) {
            // Add defer attribute
            if (strpos($tag, 'defer') === false) {
                $tag = str_replace('<script ', '<script defer ', $tag);
            }
        }
        
        return $tag;
    }
    
    /**
     * Defer non-critical JavaScript
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script URL
     * @return string Modified script tag
     */
    public function defer_non_critical_js($tag, $handle, $src) {
        // Skip admin and login pages
        if (is_admin() || strpos($src, 'wp-login.php') !== false) {
            return $tag;
        }
        
        // Scripts that should not be deferred
        $no_defer = array('jquery-core', 'jquery-migrate', 'quicklearn-critical');
        
        if (in_array($handle, $no_defer)) {
            return $tag;
        }
        
        // Add defer attribute to non-critical scripts
        if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add browser caching headers
     */
    public function add_caching_headers() {
        if (!is_admin()) {
            // Set cache headers for static assets
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Cache CSS and JS files for 1 year
            if (preg_match('/\.(css|js)$/', $request_uri)) {
                header('Cache-Control: public, max-age=31536000, immutable');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            }
            
            // Cache images for 1 month
            if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/', $request_uri)) {
                header('Cache-Control: public, max-age=2592000');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
            }
            
            // Cache HTML pages for 1 hour
            if (is_singular() || is_archive() || is_home()) {
                header('Cache-Control: public, max-age=3600');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
            }
        }
    }
    
    /**
     * Handle AJAX pagination for course lists
     */
    public function handle_ajax_pagination() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quicklearn_pagination_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn')));
        }
        
        // Get parameters
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $posts_per_page = isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 12;
        
        // Validate parameters
        if ($page < 1) $page = 1;
        if ($posts_per_page < 1 || $posts_per_page > 50) $posts_per_page = 12;
        
        // Build query arguments
        $args = array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_course_featured',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby' => 'menu_order date',
            'order' => 'ASC',
        );
        
        // Add category filter if specified
        if (!empty($category) && $category !== 'all') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => $category,
                ),
            );
        }
        
        // Execute query
        $courses_query = new WP_Query($args);
        
        if ($courses_query->have_posts()) {
            ob_start();
            
            while ($courses_query->have_posts()) {
                $courses_query->the_post();
                get_template_part('template-parts/course-card');
            }
            
            $html = ob_get_clean();
            wp_reset_postdata();
            
            wp_send_json_success(array(
                'html' => $html,
                'has_more' => $page < $courses_query->max_num_pages,
                'current_page' => $page,
                'max_pages' => $courses_query->max_num_pages,
                'total_posts' => $courses_query->found_posts,
            ));
        } else {
            wp_send_json_success(array(
                'html' => '<div class="no-courses-found">' . __('No more courses found.', 'quicklearn') . '</div>',
                'has_more' => false,
                'current_page' => $page,
                'max_pages' => 0,
                'total_posts' => 0,
            ));
        }
    }
    
    /**
     * Add resource hints for better performance
     */
    public function add_resource_hints() {
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.youtube.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//vimeo.com">' . "\n";
        
        // Preconnect to critical external resources
        echo '<link rel="preconnect" href="//fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="//fonts.gstatic.com" crossorigin>' . "\n";
        
        // Preload critical resources
        $critical_css = get_template_directory_uri() . '/css/critical.css';
        if (file_exists(get_template_directory() . '/css/critical.css')) {
            echo '<link rel="preload" href="' . esc_url($critical_css) . '" as="style">' . "\n";
        }
        
        // Preload critical JavaScript
        $critical_js = get_template_directory_uri() . '/js/critical.js';
        if (file_exists(get_template_directory() . '/js/critical.js')) {
            echo '<link rel="preload" href="' . esc_url($critical_js) . '" as="script">' . "\n";
        }
        
        // Preload hero image on homepage
        if (is_front_page() && has_custom_header()) {
            $header_image = get_header_image();
            if ($header_image) {
                echo '<link rel="preload" href="' . esc_url($header_image) . '" as="image">' . "\n";
            }
        }
        
        // Preload first course image on course archive pages
        if (is_post_type_archive('quick_course') || is_tax('course_category')) {
            $first_course = get_posts(array(
                'post_type' => 'quick_course',
                'posts_per_page' => 1,
                'meta_key' => '_thumbnail_id',
            ));
            
            if (!empty($first_course) && has_post_thumbnail($first_course[0]->ID)) {
                $thumbnail_url = get_the_post_thumbnail_url($first_course[0]->ID, 'medium');
                echo '<link rel="preload" href="' . esc_url($thumbnail_url) . '" as="image">' . "\n";
            }
        }
    }
    
    /**
     * Optimize image generation
     *
     * @param array $metadata Image metadata
     * @param int $attachment_id Attachment ID
     * @return array Modified metadata
     */
    public function optimize_image_generation($metadata, $attachment_id) {
        // Generate WebP versions of images if supported
        if (function_exists('imagewebp')) {
            $this->generate_webp_images($metadata, $attachment_id);
        }
        
        return $metadata;
    }
    
    /**
     * Generate WebP versions of images
     *
     * @param array $metadata Image metadata
     * @param int $attachment_id Attachment ID
     */
    private function generate_webp_images($metadata, $attachment_id) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        // Generate WebP for main image
        $this->create_webp_image($file_path);
        
        // Generate WebP for all sizes
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                $size_path = dirname($file_path) . '/' . $size_data['file'];
                $this->create_webp_image($size_path);
            }
        }
    }
    
    /**
     * Create WebP version of an image
     *
     * @param string $image_path Path to original image
     */
    private function create_webp_image($image_path) {
        if (!file_exists($image_path)) {
            return;
        }
        
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);
        
        // Skip if WebP already exists
        if (file_exists($webp_path)) {
            return;
        }
        
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return;
        }
        
        $mime_type = $image_info['mime'];
        $image = null;
        
        // Create image resource based on type
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                // Preserve transparency
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            default:
                return;
        }
        
        if ($image) {
            // Create WebP with 85% quality
            imagewebp($image, $webp_path, 85);
            imagedestroy($image);
        }
    }
    
    /**
     * Inline critical CSS
     */
    public function inline_critical_css() {
        $critical_css_file = get_template_directory() . '/css/critical.css';
        
        if (file_exists($critical_css_file)) {
            $critical_css = file_get_contents($critical_css_file);
            if ($critical_css) {
                echo '<style id="critical-css">' . $this->minify_css($critical_css) . '</style>' . "\n";
            }
        }
    }
    
    /**
     * Register service worker for caching
     */
    public function register_service_worker() {
        if (!is_admin()) {
            ?>
            <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('<?php echo esc_url(get_template_directory_uri() . '/js/sw.js'); ?>')
                        .then(function(registration) {
                            console.log('SW registered: ', registration);
                        })
                        .catch(function(registrationError) {
                            console.log('SW registration failed: ', registrationError);
                        });
                });
            }
            </script>
            <?php
        }
    }
    
    /**
     * Optimize course queries
     *
     * @param WP_Query $query WordPress query object
     */
    public function optimize_course_queries($query) {
        if (!is_admin() && $query->is_main_query()) {
            if (is_post_type_archive('quick_course') || is_tax('course_category')) {
                // Optimize course archive queries
                $query->set('meta_query', array(
                    array(
                        'key' => '_course_status',
                        'value' => 'published',
                        'compare' => '=',
                    ),
                ));
                
                // Add proper ordering
                $query->set('meta_key', '_course_order');
                $query->set('orderby', 'meta_value_num date');
                $query->set('order', 'ASC');
                
                // Limit fields for better performance
                $query->set('fields', 'ids');
            }
        }
    }
    
    /**
     * Remove unnecessary WordPress features
     */
    private function remove_unnecessary_features() {
        // Remove emoji scripts and styles
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        
        // Remove unnecessary meta tags
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Remove REST API links if not needed
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        
        // Disable embeds if not needed
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Remove query strings from static resources
        add_filter('style_loader_src', array($this, 'remove_query_strings'), 10, 1);
        add_filter('script_loader_src', array($this, 'remove_query_strings'), 10, 1);
    }
    
    /**
     * Optimize WordPress queries
     */
    private function optimize_wordpress_queries() {
        // Reduce the number of revisions
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }
        
        // Increase memory limit for image processing
        if (!defined('WP_MEMORY_LIMIT')) {
            define('WP_MEMORY_LIMIT', '256M');
        }
    }
    
    /**
     * Remove query strings from static resources
     *
     * @param string $src Resource URL
     * @return string Modified URL
     */
    public function remove_query_strings($src) {
        if (strpos($src, '?ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
    
    /**
     * Minify HTML output
     *
     * @param string $buffer HTML buffer
     * @return string Minified HTML
     */
    public function minify_html_output($buffer) {
        // Skip minification in admin or if debugging
        if (is_admin() || (defined('WP_DEBUG') && WP_DEBUG)) {
            return $buffer;
        }
        
        // Simple HTML minification
        $buffer = preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $buffer);
        $buffer = preg_replace('/\s+/', ' ', $buffer);
        $buffer = preg_replace('/>\s+</', '><', $buffer);
        
        return trim($buffer);
    }
    
    /**
     * Minify CSS
     *
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
        $css = str_replace(array('; ', ' ;', ' {', '{ ', ' }', '} ', ': ', ' :', ', ', ' ,'), array(';', ';', '{', '{', '}', '}', ':', ':', ',', ','), $css);
        
        return trim($css);
    }
}

// Initialize performance optimization
QuickLearn_Performance_Optimization::get_instance();