<?php
/**
 * Course Forums Functionality
 * 
 * Handles discussion forums for courses including threaded conversations,
 * moderation, and search capabilities.
 */

if (!defined('ABSPATH')) {
    exit;
}

class QLCM_Course_Forums {
    
    public function __construct() {
        add_action('init', array($this, 'create_forum_tables'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_forum_assets'));
        add_action('wp_ajax_submit_forum_post', array($this, 'handle_forum_post_submission'));
        add_action('wp_ajax_nopriv_submit_forum_post', array($this, 'handle_forum_post_submission'));
        add_action('wp_ajax_load_forum_posts', array($this, 'load_forum_posts'));
        add_action('wp_ajax_nopriv_load_forum_posts', array($this, 'load_forum_posts'));
        add_action('wp_ajax_moderate_forum_post', array($this, 'moderate_forum_post'));
        add_action('wp_ajax_search_forum_posts', array($this, 'search_forum_posts'));
        add_action('wp_ajax_nopriv_search_forum_posts', array($this, 'search_forum_posts'));
        add_action('wp_ajax_search_users', array($this, 'search_users'));
        
        add_shortcode('course_forum', array($this, 'display_course_forum'));
    }
    
    /**
     * Enqueue forum assets
     */
    public function enqueue_forum_assets() {
        // Only enqueue on pages that might have the forum shortcode
        if (is_singular('quick_course') || is_page()) {
            wp_enqueue_script(
                'qlcm-forums',
                QLCM_PLUGIN_URL . 'assets/js/forums.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-forums',
                QLCM_PLUGIN_URL . 'assets/css/forums.css',
                array(),
                QLCM_VERSION
            );
            
            // Localize script for AJAX
            wp_localize_script('qlcm-forums', 'qlcm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_forum_nonce')
            ));
        }
    }
    
    /**
     * Create database tables for forum functionality
     */
    public function create_forum_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Forum posts table
        $forum_posts_table = $wpdb->prefix . 'qlcm_forum_posts';
        $sql_posts = "CREATE TABLE IF NOT EXISTS $forum_posts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            title varchar(255) DEFAULT '',
            content longtext NOT NULL,
            status varchar(20) DEFAULT 'published',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_pinned tinyint(1) DEFAULT 0,
            reply_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY status (status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        // Forum moderators table
        $forum_moderators_table = $wpdb->prefix . 'qlcm_forum_moderators';
        $sql_moderators = "CREATE TABLE IF NOT EXISTS $forum_moderators_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY course_user (course_id, user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_posts);
        dbDelta($sql_moderators);
    }
    
    /**
     * Display course forum interface
     */
    public function display_course_forum($atts) {
        $atts = shortcode_atts(array(
            'course_id' => get_the_ID()
        ), $atts);
        
        $course_id = intval($atts['course_id']);
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            return '<p>Invalid course specified.</p>';
        }
        
        ob_start();
        ?>
        <div id="course-forum-<?php echo $course_id; ?>" class="qlcm-course-forum">
            <div class="forum-header">
                <h3>Course Discussion Forum</h3>
                <?php if (is_user_logged_in()): ?>
                    <button class="btn btn-primary" id="new-topic-btn">Start New Discussion</button>
                <?php else: ?>
                    <p><a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a> to participate in discussions.</p>
                <?php endif; ?>
            </div>
            
            <div class="forum-search">
                <input type="text" id="forum-search-input" placeholder="Search discussions..." />
                <button id="forum-search-btn">Search</button>
            </div>
            
            <div class="forum-filters">
                <select id="forum-sort">
                    <option value="recent">Most Recent</option>
                    <option value="popular">Most Replies</option>
                    <option value="pinned">Pinned First</option>
                </select>
            </div>
            
            <?php if (is_user_logged_in()): ?>
            <div id="new-topic-form" class="forum-form" style="display: none;">
                <h4>Start New Discussion</h4>
                <form id="forum-post-form">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>" />
                    <input type="hidden" name="parent_id" value="0" />
                    <div class="form-group">
                        <label for="topic-title">Discussion Title:</label>
                        <input type="text" id="topic-title" name="title" required maxlength="255" />
                    </div>
                    <div class="form-group">
                        <label for="topic-content">Your Message:</label>
                        <textarea id="topic-content" name="content" required rows="5"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Post Discussion</button>
                        <button type="button" class="btn btn-secondary" id="cancel-topic">Cancel</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <div id="forum-posts-container" class="forum-posts">
                <div class="loading-spinner" style="display: none;">Loading discussions...</div>
                <div id="forum-posts-list"></div>
            </div>
            
            <div id="forum-pagination" class="forum-pagination"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const forumManager = new QLCMForumManager(<?php echo $course_id; ?>);
            forumManager.init();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle forum post submission
     */
    public function handle_forum_post_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_forum_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to post.');
            return;
        }
        
        $course_id = intval($_POST['course_id']);
        $parent_id = intval($_POST['parent_id']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $user_id = get_current_user_id();
        
        // Validate inputs
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error('Invalid course specified.');
            return;
        }
        
        if (empty($content)) {
            wp_send_json_error('Content is required.');
            return;
        }
        
        if ($parent_id === 0 && empty($title)) {
            wp_send_json_error('Title is required for new topics.');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        // Insert forum post
        $result = $wpdb->insert(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id,
                'parent_id' => $parent_id,
                'title' => $title,
                'content' => $content,
                'status' => 'published'
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to save post.');
            return;
        }
        
        $post_id = $wpdb->insert_id;
        
        // Update reply count for parent post
        if ($parent_id > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET reply_count = reply_count + 1 WHERE id = %d",
                $parent_id
            ));
        }
        
        // Get the created post data
        $post_data = $this->get_forum_post($post_id);
        
        wp_send_json_success(array(
            'message' => 'Post created successfully.',
            'post' => $post_data
        ));
    }
    
    /**
     * Load forum posts for a course
     */
    public function load_forum_posts() {
        $course_id = intval($_GET['course_id']);
        $page = intval($_GET['page']) ?: 1;
        $sort = sanitize_text_field($_GET['sort']) ?: 'recent';
        $search = sanitize_text_field($_GET['search']) ?: '';
        $parent_id = intval($_GET['parent_id']) ?: 0;
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error('Invalid course specified.');
            return;
        }
        
        $posts = $this->get_forum_posts($course_id, $page, $sort, $search, $parent_id);
        $total_posts = $this->get_forum_posts_count($course_id, $search, $parent_id);
        
        wp_send_json_success(array(
            'posts' => $posts,
            'total' => $total_posts,
            'page' => $page,
            'per_page' => 10
        ));
    }
    
    /**
     * Get forum posts for a course
     */
    private function get_forum_posts($course_id, $page = 1, $sort = 'recent', $search = '', $parent_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause
        $where_conditions = array("fp.course_id = %d", "fp.parent_id = %d", "fp.status = 'published'");
        $where_values = array($course_id, $parent_id);
        
        if (!empty($search)) {
            $where_conditions[] = "(fp.title LIKE %s OR fp.content LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Build ORDER BY clause
        $order_by = 'ORDER BY ';
        switch ($sort) {
            case 'popular':
                $order_by .= 'fp.reply_count DESC, fp.created_date DESC';
                break;
            case 'pinned':
                $order_by .= 'fp.is_pinned DESC, fp.created_date DESC';
                break;
            default:
                $order_by .= 'fp.created_date DESC';
        }
        
        $sql = "SELECT fp.*, u.display_name, u.user_email 
                FROM $table_name fp 
                LEFT JOIN {$wpdb->users} u ON fp.user_id = u.ID 
                $where_clause 
                $order_by 
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $posts = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        
        // Get reply count for each post
        foreach ($posts as &$post) {
            $post->replies = $this->get_forum_post_replies($post->id, 3); // Get first 3 replies
            $post->avatar_url = get_avatar_url($post->user_email, array('size' => 40));
            $post->can_moderate = $this->can_moderate_forum($course_id, get_current_user_id());
        }
        
        return $posts;
    }
    
    /**
     * Get forum post replies
     */
    private function get_forum_post_replies($parent_id, $limit = 3) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        $sql = "SELECT fp.*, u.display_name, u.user_email 
                FROM $table_name fp 
                LEFT JOIN {$wpdb->users} u ON fp.user_id = u.ID 
                WHERE fp.parent_id = %d AND fp.status = 'published' 
                ORDER BY fp.created_date ASC 
                LIMIT %d";
        
        $replies = $wpdb->get_results($wpdb->prepare($sql, $parent_id, $limit));
        
        foreach ($replies as &$reply) {
            $reply->avatar_url = get_avatar_url($reply->user_email, array('size' => 32));
        }
        
        return $replies;
    }
    
    /**
     * Get forum posts count
     */
    private function get_forum_posts_count($course_id, $search = '', $parent_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        $where_conditions = array("course_id = %d", "parent_id = %d", "status = 'published'");
        $where_values = array($course_id, $parent_id);
        
        if (!empty($search)) {
            $where_conditions[] = "(title LIKE %s OR content LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $sql = "SELECT COUNT(*) FROM $table_name $where_clause";
        
        return $wpdb->get_var($wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get single forum post
     */
    private function get_forum_post($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        $sql = "SELECT fp.*, u.display_name, u.user_email 
                FROM $table_name fp 
                LEFT JOIN {$wpdb->users} u ON fp.user_id = u.ID 
                WHERE fp.id = %d";
        
        $post = $wpdb->get_row($wpdb->prepare($sql, $post_id));
        
        if ($post) {
            $post->avatar_url = get_avatar_url($post->user_email, array('size' => 40));
            $post->can_moderate = $this->can_moderate_forum($post->course_id, get_current_user_id());
        }
        
        return $post;
    }
    
    /**
     * Handle forum post moderation
     */
    public function moderate_forum_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_forum_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id']);
        $action = sanitize_text_field($_POST['action_type']);
        $course_id = intval($_POST['course_id']);
        
        // Check moderation permissions
        if (!$this->can_moderate_forum($course_id, get_current_user_id())) {
            wp_send_json_error('You do not have permission to moderate this forum.');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_posts';
        
        switch ($action) {
            case 'pin':
                $result = $wpdb->update(
                    $table_name,
                    array('is_pinned' => 1),
                    array('id' => $post_id),
                    array('%d'),
                    array('%d')
                );
                break;
            case 'unpin':
                $result = $wpdb->update(
                    $table_name,
                    array('is_pinned' => 0),
                    array('id' => $post_id),
                    array('%d'),
                    array('%d')
                );
                break;
            case 'hide':
                $result = $wpdb->update(
                    $table_name,
                    array('status' => 'hidden'),
                    array('id' => $post_id),
                    array('%s'),
                    array('%d')
                );
                break;
            case 'show':
                $result = $wpdb->update(
                    $table_name,
                    array('status' => 'published'),
                    array('id' => $post_id),
                    array('%s'),
                    array('%d')
                );
                break;
            default:
                wp_send_json_error('Invalid moderation action.');
                return;
        }
        
        if ($result !== false) {
            wp_send_json_success('Post moderated successfully.');
        } else {
            wp_send_json_error('Failed to moderate post.');
        }
    }
    
    /**
     * Search forum posts
     */
    public function search_forum_posts() {
        $course_id = intval($_GET['course_id']);
        $search_term = sanitize_text_field($_GET['search']);
        $page = intval($_GET['page']) ?: 1;
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error('Invalid course specified.');
            return;
        }
        
        $posts = $this->get_forum_posts($course_id, $page, 'recent', $search_term);
        $total_posts = $this->get_forum_posts_count($course_id, $search_term);
        
        wp_send_json_success(array(
            'posts' => $posts,
            'total' => $total_posts,
            'search_term' => $search_term
        ));
    }
    
    /**
     * Check if user can moderate forum
     */
    private function can_moderate_forum($course_id, $user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Administrators can always moderate
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user is assigned as moderator for this course
        global $wpdb;
        $moderators_table = $wpdb->prefix . 'qlcm_forum_moderators';
        
        $is_moderator = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $moderators_table WHERE course_id = %d AND user_id = %d",
            $course_id,
            $user_id
        ));
        
        return $is_moderator > 0;
    }
    
    /**
     * Add forum moderator
     */
    public function add_forum_moderator($course_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_moderators';
        
        return $wpdb->insert(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
    }
    
    /**
     * Remove forum moderator
     */
    public function remove_forum_moderator($course_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_forum_moderators';
        
        return $wpdb->delete(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id
            ),
            array('%d', '%d')
        );
    }
    
    /**
     * Search users for moderator assignment
     */
    public function search_users() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }
        
        $search_term = sanitize_text_field($_POST['search']);
        
        if (strlen($search_term) < 3) {
            wp_send_json_error('Search term must be at least 3 characters.');
            return;
        }
        
        $users = get_users(array(
            'search' => '*' . $search_term . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 10,
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        wp_send_json_success($users);
    }
}

// Initialize the forums class
new QLCM_Course_Forums();