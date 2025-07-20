<?php
/**
 * Course Ratings and Reviews
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling course ratings and reviews
 */
class QLCM_Course_Ratings {
    
    /**
     * Instance of this class
     *
     * @var QLCM_Course_Ratings
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_Course_Ratings Instance of this class
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
        // Create database tables on plugin activation
        register_activation_hook(QLCM_PLUGIN_FILE, array($this, 'create_database_tables'));
        
        // Add rating fields to course
        add_action('quicklearn_after_course_content', array($this, 'add_rating_form'));
        
        // Handle rating submission
        add_action('wp_ajax_submit_course_rating', array($this, 'handle_rating_submission'));
        
        // Display course ratings
        add_action('quicklearn_before_course_content', array($this, 'display_course_rating'));
        
        // Add rating to course card
        add_action('quicklearn_course_card_meta', array($this, 'add_rating_to_course_card'));
        
        // Add rating meta box to course edit screen
        add_action('add_meta_boxes', array($this, 'add_ratings_meta_box'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add rating schema to structured data
        add_filter('qlcm_course_structured_data', array($this, 'add_rating_schema'));
    }
    
    /**
     * Create database tables for ratings and reviews
     */
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for course ratings
        $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
        
        $sql = "CREATE TABLE $ratings_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            rating int(1) NOT NULL,
            review_title varchar(255) DEFAULT NULL,
            review_content text DEFAULT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) DEFAULT 'approved' NOT NULL,
            PRIMARY KEY  (id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY rating (rating),
            KEY status (status),
            UNIQUE KEY user_course (user_id,course_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on course pages
        if (is_singular('quick_course') || is_post_type_archive('quick_course') || is_tax('course_category')) {
            wp_enqueue_style(
                'qlcm-ratings',
                QLCM_PLUGIN_URL . 'assets/css/ratings.css',
                array(),
                QLCM_VERSION
            );
            
            wp_enqueue_script(
                'qlcm-ratings',
                QLCM_PLUGIN_URL . 'assets/js/ratings.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_localize_script('qlcm-ratings', 'qlcm_ratings', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_rating_nonce'),
                'i18n' => array(
                    'submitting' => __('Submitting...', 'quicklearn-course-manager'),
                    'thank_you' => __('Thank you for your review!', 'quicklearn-course-manager'),
                    'error' => __('Error occurred. Please try again.', 'quicklearn-course-manager'),
                    'login_required' => __('Please log in to submit a review', 'quicklearn-course-manager'),
                )
            ));
        }
    }
    
    /**
     * Display course rating summary
     */
    public function display_course_rating() {
        if (!is_singular('quick_course')) {
            return;
        }
        
        $course_id = get_the_ID();
        $rating_data = $this->get_course_rating_data($course_id);
        
        if ($rating_data['count'] == 0) {
            return;
        }
        
        echo '<div class="qlcm-course-rating-summary">';
        
        // Average rating stars
        echo '<div class="qlcm-rating-stars">';
        $this->display_star_rating($rating_data['average']);
        echo '</div>';
        
        // Rating stats
        echo '<div class="qlcm-rating-stats">';
        echo '<span class="qlcm-rating-average">' . number_format_i18n($rating_data['average'], 1) . '</span>';
        echo '<span class="qlcm-rating-count">(' . sprintf(
            _n('%s review', '%s reviews', $rating_data['count'], 'quicklearn-course-manager'),
            number_format_i18n($rating_data['count'])
        ) . ')</span>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Add rating to course card
     */
    public function add_rating_to_course_card() {
        $course_id = get_the_ID();
        $rating_data = $this->get_course_rating_data($course_id);
        
        if ($rating_data['count'] > 0) {
            echo '<div class="qlcm-card-rating">';
            $this->display_star_rating($rating_data['average'], 'small');
            echo '<span class="qlcm-rating-text">' . number_format_i18n($rating_data['average'], 1) . ' (' . $rating_data['count'] . ')</span>';
            echo '</div>';
        }
    }
    
    /**
     * Display star rating
     *
     * @param float $rating Rating value (0-5)
     * @param string $size Size of stars ('small' or 'regular')
     */
    public function display_star_rating($rating, $size = 'regular') {
        $rating = max(0, min(5, $rating));
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $size_class = $size === 'small' ? ' qlcm-stars-small' : '';
        
        echo '<div class="qlcm-stars' . $size_class . '" aria-label="' . sprintf(__('Rated %s out of 5', 'quicklearn-course-manager'), number_format_i18n($rating, 1)) . '">';
        
        // Full stars
        for ($i = 0; $i < $full_stars; $i++) {
            echo '<span class="qlcm-star qlcm-star-full">★</span>';
        }
        
        // Half star
        if ($half_star) {
            echo '<span class="qlcm-star qlcm-star-half">★</span>';
        }
        
        // Empty stars
        for ($i = 0; $i < $empty_stars; $i++) {
            echo '<span class="qlcm-star qlcm-star-empty">☆</span>';
        }
        
        echo '</div>';
    }
    
    /**
     * Add rating form and display reviews
     */
    public function add_rating_form() {
        if (!is_singular('quick_course')) {
            return;
        }
        
        $course_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Check if user is enrolled in the course
        $enrollment = false;
        if (class_exists('QLCM_User_Enrollment')) {
            $enrollment_instance = QLCM_User_Enrollment::get_instance();
            $enrollment = $enrollment_instance->get_enrollment_status($user_id, $course_id);
        }
        
        // Get existing user rating
        $user_rating = $this->get_user_rating($user_id, $course_id);
        
        echo '<div class="qlcm-course-reviews">';
        echo '<h3>' . __('Course Reviews', 'quicklearn-course-manager') . '</h3>';
        
        // Display existing reviews
        $this->display_course_reviews($course_id);
        
        // Rating form
        echo '<div class="qlcm-rating-form-container">';
        
        if (!$user_id) {
            // Not logged in
            echo '<div class="qlcm-login-required">';
            echo '<p>' . __('Please log in to leave a review.', 'quicklearn-course-manager') . '</p>';
            echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="qlcm-button">';
            echo __('Log In', 'quicklearn-course-manager');
            echo '</a>';
            echo '</div>';
        } elseif (!$enrollment && !current_user_can('manage_options')) {
            // Not enrolled
            echo '<div class="qlcm-enrollment-required">';
            echo '<p>' . __('You need to enroll in this course to leave a review.', 'quicklearn-course-manager') . '</p>';
            echo '</div>';
        } else {
            // Can leave a review
            $form_title = $user_rating ? __('Update Your Review', 'quicklearn-course-manager') : __('Leave a Review', 'quicklearn-course-manager');
            
            echo '<h4>' . $form_title . '</h4>';
            echo '<form class="qlcm-rating-form" id="qlcm-rating-form">';
            
            // Rating stars
            echo '<div class="qlcm-rating-field">';
            echo '<label>' . __('Your Rating', 'quicklearn-course-manager') . '</label>';
            echo '<div class="qlcm-rating-input">';
            for ($i = 5; $i >= 1; $i--) {
                $checked = $user_rating && $user_rating->rating == $i ? 'checked' : '';
                echo '<input type="radio" name="rating" id="rating-' . $i . '" value="' . $i . '" ' . $checked . '>';
                echo '<label for="rating-' . $i . '" title="' . sprintf(__('%d stars', 'quicklearn-course-manager'), $i) . '">☆</label>';
            }
            echo '</div>';
            echo '</div>';
            
            // Review title
            echo '<div class="qlcm-review-field">';
            echo '<label for="review-title">' . __('Review Title (Optional)', 'quicklearn-course-manager') . '</label>';
            echo '<input type="text" id="review-title" name="review_title" maxlength="255" value="' . ($user_rating ? esc_attr($user_rating->review_title) : '') . '">';
            echo '</div>';
            
            // Review content
            echo '<div class="qlcm-review-field">';
            echo '<label for="review-content">' . __('Your Review (Optional)', 'quicklearn-course-manager') . '</label>';
            echo '<textarea id="review-content" name="review_content" rows="4" maxlength="1000">' . ($user_rating ? esc_textarea($user_rating->review_content) : '') . '</textarea>';
            echo '</div>';
            
            // Submit button
            echo '<div class="qlcm-submit-field">';
            echo '<button type="submit" class="qlcm-button qlcm-submit-rating">';
            echo $user_rating ? __('Update Review', 'quicklearn-course-manager') : __('Submit Review', 'quicklearn-course-manager');
            echo '</button>';
            echo '</div>';
            
            // Hidden fields
            echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
            echo '<input type="hidden" name="action" value="submit_course_rating">';
            echo '<input type="hidden" name="nonce" value="' . wp_create_nonce('qlcm_rating_nonce') . '">';
            
            echo '</form>';
        }
        
        echo '</div>'; // .qlcm-rating-form-container
        echo '</div>'; // .qlcm-course-reviews
    }
    
    /**
     * Display course reviews
     *
     * @param int $course_id Course ID
     */
    public function display_course_reviews($course_id) {
        $reviews = $this->get_course_reviews($course_id, 5); // Show latest 5 reviews
        
        if (empty($reviews)) {
            echo '<div class="qlcm-no-reviews">';
            echo '<p>' . __('No reviews yet. Be the first to review this course!', 'quicklearn-course-manager') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="qlcm-reviews-list">';
        
        foreach ($reviews as $review) {
            echo '<div class="qlcm-review-item">';
            
            // Review header
            echo '<div class="qlcm-review-header">';
            echo '<div class="qlcm-reviewer-info">';
            echo '<span class="qlcm-reviewer-name">' . esc_html($review->display_name) . '</span>';
            echo '<span class="qlcm-review-date">' . human_time_diff(strtotime($review->created_date), current_time('timestamp')) . ' ' . __('ago', 'quicklearn-course-manager') . '</span>';
            echo '</div>';
            
            // Rating stars
            echo '<div class="qlcm-review-rating">';
            $this->display_star_rating($review->rating, 'small');
            echo '</div>';
            echo '</div>'; // .qlcm-review-header
            
            // Review content
            if (!empty($review->review_title)) {
                echo '<h5 class="qlcm-review-title">' . esc_html($review->review_title) . '</h5>';
            }
            
            if (!empty($review->review_content)) {
                echo '<div class="qlcm-review-content">';
                echo '<p>' . esc_html($review->review_content) . '</p>';
                echo '</div>';
            }
            
            echo '</div>'; // .qlcm-review-item
        }
        
        echo '</div>'; // .qlcm-reviews-list
        
        // Show more reviews link if there are more
        $total_reviews = $this->get_course_rating_data($course_id)['count'];
        if ($total_reviews > 5) {
            echo '<div class="qlcm-more-reviews">';
            echo '<a href="#" class="qlcm-load-more-reviews" data-course-id="' . esc_attr($course_id) . '">';
            echo sprintf(__('Show %d more reviews', 'quicklearn-course-manager'), $total_reviews - 5);
            echo '</a>';
            echo '</div>';
        }
    }
    
    /**
     * Handle rating submission
     */
    public function handle_rating_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'qlcm_rating_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'quicklearn-course-manager')));
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to submit a review', 'quicklearn-course-manager')));
        }
        
        // Get and validate parameters
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
        $review_title = isset($_POST['review_title']) ? sanitize_text_field($_POST['review_title']) : '';
        $review_content = isset($_POST['review_content']) ? sanitize_textarea_field($_POST['review_content']) : '';
        
        // Validate course
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error(array('message' => __('Invalid course', 'quicklearn-course-manager')));
        }
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(array('message' => __('Please select a rating between 1 and 5 stars', 'quicklearn-course-manager')));
        }
        
        $user_id = get_current_user_id();
        
        // Check if user is enrolled (unless admin)
        if (!current_user_can('manage_options')) {
            $enrollment = false;
            if (class_exists('QLCM_User_Enrollment')) {
                $enrollment_instance = QLCM_User_Enrollment::get_instance();
                $enrollment = $enrollment_instance->get_enrollment_status($user_id, $course_id);
            }
            
            if (!$enrollment) {
                wp_send_json_error(array('message' => __('You must be enrolled in this course to leave a review', 'quicklearn-course-manager')));
            }
        }
        
        // Save or update rating
        $result = $this->save_course_rating($user_id, $course_id, $rating, $review_title, $review_content);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Thank you for your review!', 'quicklearn-course-manager'),
                'rating_data' => $this->get_course_rating_data($course_id)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save review. Please try again.', 'quicklearn-course-manager')));
        }
    }
    
    /**
     * Save course rating
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @param int $rating Rating (1-5)
     * @param string $review_title Review title
     * @param string $review_content Review content
     * @return bool Success
     */
    public function save_course_rating($user_id, $course_id, $rating, $review_title = '', $review_content = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_course_ratings';
        
        // Check if user already has a rating for this course
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        $data = array(
            'rating' => $rating,
            'review_title' => $review_title,
            'review_content' => $review_content,
            'status' => 'approved'
        );
        
        if ($existing) {
            // Update existing rating
            $data['updated_date'] = current_time('mysql');
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing),
                array('%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new rating
            $data['user_id'] = $user_id;
            $data['course_id'] = $course_id;
            $data['created_date'] = current_time('mysql');
            
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            // Trigger action for other plugins
            do_action('qlcm_rating_saved', $user_id, $course_id, $rating, $review_title, $review_content);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get course rating data
     *
     * @param int $course_id Course ID
     * @return array Rating data with average and count
     */
    public function get_course_rating_data($course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'qlcm_course_ratings';
        
        $results = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as average, COUNT(*) as count 
            FROM $table_name 
            WHERE course_id = %d AND status = 'approved'",
            $course_id
        ));
        
        return array(
            'average' => $results ? (float) $results->average : 0,
            'count' => $results ? (int) $results->count : 0
        );
    }
    
    /**
     * Get user's rating for a course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return object|null Rating object or null
     */
    public function get_user_rating($user_id, $course_id) {
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_course_ratings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
    }
    
    /**
     * Get course reviews
     *
     * @param int $course_id Course ID
     * @param int $limit Number of reviews to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of review objects
     */
    public function get_course_reviews($course_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        $ratings_table = $wpdb->prefix . 'qlcm_course_ratings';
        
        $query = $wpdb->prepare(
            "SELECT r.*, u.display_name 
            FROM $ratings_table r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.course_id = %d AND r.status = 'approved'
            ORDER BY r.created_date DESC
            LIMIT %d OFFSET %d",
            $course_id,
            $limit,
            $offset
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Add ratings meta box to course edit screen
     */
    public function add_ratings_meta_box() {
        add_meta_box(
            'qlcm_course_ratings',
            __('Course Ratings', 'quicklearn-course-manager'),
            array($this, 'render_ratings_meta_box'),
            'quick_course',
            'side',
            'default'
        );
    }
    
    /**
     * Render ratings meta box
     *
     * @param WP_Post $post Current post object
     */
    public function render_ratings_meta_box($post) {
        $course_id = $post->ID;
        $rating_data = $this->get_course_rating_data($course_id);
        
        echo '<div class="qlcm-rating-stats">';
        
        if ($rating_data['count'] > 0) {
            echo '<p><strong>' . __('Average Rating:', 'quicklearn-course-manager') . '</strong> ';
            $this->display_star_rating($rating_data['average'], 'small');
            echo ' ' . number_format_i18n($rating_data['average'], 1) . '/5</p>';
            
            echo '<p><strong>' . __('Total Reviews:', 'quicklearn-course-manager') . '</strong> ' . $rating_data['count'] . '</p>';
            
            echo '<p><a href="' . admin_url('edit.php?post_type=quick_course&page=course-ratings&course_id=' . $course_id) . '" class="button">';
            echo __('View All Reviews', 'quicklearn-course-manager');
            echo '</a></p>';
        } else {
            echo '<p>' . __('No ratings yet.', 'quicklearn-course-manager') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Add rating schema to structured data
     *
     * @param array $schema Existing schema data
     * @return array Modified schema data
     */
    public function add_rating_schema($schema) {
        if (!is_singular('quick_course')) {
            return $schema;
        }
        
        $course_id = get_the_ID();
        $rating_data = $this->get_course_rating_data($course_id);
        
        if ($rating_data['count'] > 0) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($rating_data['average'], 1),
                'reviewCount' => $rating_data['count'],
                'bestRating' => 5,
                'worstRating' => 1
            );
        }
        
        return $schema;
    }
}

// Initialize course ratings
QLCM_Course_Ratings::get_instance();