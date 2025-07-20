<?php
/**
 * Course Q&A System
 * 
 * Handles question and answer functionality with voting system
 */

if (!defined('ABSPATH')) {
    exit;
}

class QLCM_Course_QA {
    
    public function __construct() {
        add_action('init', array($this, 'create_qa_tables'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_qa_assets'));
        add_action('wp_ajax_submit_question', array($this, 'handle_question_submission'));
        add_action('wp_ajax_nopriv_submit_question', array($this, 'handle_question_submission'));
        add_action('wp_ajax_submit_answer', array($this, 'handle_answer_submission'));
        add_action('wp_ajax_nopriv_submit_answer', array($this, 'handle_answer_submission'));
        add_action('wp_ajax_vote_answer', array($this, 'handle_answer_vote'));
        add_action('wp_ajax_nopriv_vote_answer', array($this, 'handle_answer_vote'));
        add_action('wp_ajax_load_qa_content', array($this, 'load_qa_content'));
        add_action('wp_ajax_nopriv_load_qa_content', array($this, 'load_qa_content'));
        add_action('wp_ajax_mark_answer_helpful', array($this, 'mark_answer_helpful'));
        
        add_shortcode('course_qa', array($this, 'display_course_qa'));
    }
    
    /**
     * Create database tables for Q&A functionality
     */
    public function create_qa_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Questions table
        $questions_table = $wpdb->prefix . 'qlcm_questions';
        $sql_questions = "CREATE TABLE IF NOT EXISTS $questions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            status varchar(20) DEFAULT 'published',
            is_answered tinyint(1) DEFAULT 0,
            answer_count int(11) DEFAULT 0,
            view_count int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY course_id (course_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        // Answers table
        $answers_table = $wpdb->prefix . 'qlcm_answers';
        $sql_answers = "CREATE TABLE IF NOT EXISTS $answers_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            question_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            status varchar(20) DEFAULT 'published',
            is_helpful tinyint(1) DEFAULT 0,
            vote_score int(11) DEFAULT 0,
            upvotes int(11) DEFAULT 0,
            downvotes int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY question_id (question_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY vote_score (vote_score)
        ) $charset_collate;";
        
        // Answer votes table
        $votes_table = $wpdb->prefix . 'qlcm_answer_votes';
        $sql_votes = "CREATE TABLE IF NOT EXISTS $votes_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            answer_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            vote_type varchar(10) NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_answer_vote (answer_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_questions);
        dbDelta($sql_answers);
        dbDelta($sql_votes);
    }
    
    /**
     * Enqueue Q&A assets
     */
    public function enqueue_qa_assets() {
        if (is_singular('quick_course') || is_page()) {
            wp_enqueue_script(
                'qlcm-qa',
                QLCM_PLUGIN_URL . 'assets/js/qa.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-qa',
                QLCM_PLUGIN_URL . 'assets/css/qa.css',
                array(),
                QLCM_VERSION
            );
            
            wp_localize_script('qlcm-qa', 'qlcm_qa_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_qa_nonce')
            ));
        }
    }
    
    /**
     * Display course Q&A interface
     */
    public function display_course_qa($atts) {
        $atts = shortcode_atts(array(
            'course_id' => get_the_ID()
        ), $atts);
        
        $course_id = intval($atts['course_id']);
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            return '<p>Invalid course specified.</p>';
        }
        
        ob_start();
        ?>
        <div id="course-qa-<?php echo $course_id; ?>" class="qlcm-course-qa">
            <div class="qa-header">
                <h3>Questions & Answers</h3>
                <?php if (is_user_logged_in()): ?>
                    <button class="btn btn-primary" id="ask-question-btn">Ask a Question</button>
                <?php else: ?>
                    <p><a href="<?php echo wp_login_url(get_permalink()); ?>">Login</a> to ask questions.</p>
                <?php endif; ?>
            </div>
            
            <div class="qa-filters">
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All Questions</button>
                    <button class="filter-btn" data-filter="unanswered">Unanswered</button>
                    <button class="filter-btn" data-filter="answered">Answered</button>
                </div>
                
                <div class="sort-options">
                    <select id="qa-sort">
                        <option value="recent">Most Recent</option>
                        <option value="popular">Most Popular</option>
                        <option value="unanswered">Unanswered First</option>
                    </select>
                </div>
            </div>
            
            <?php if (is_user_logged_in()): ?>
            <div id="ask-question-form" class="qa-form" style="display: none;">
                <h4>Ask a Question</h4>
                <form id="question-form">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>" />
                    <div class="form-group">
                        <label for="question-title">Question Title:</label>
                        <input type="text" id="question-title" name="title" required maxlength="255" 
                               placeholder="What would you like to know?" />
                    </div>
                    <div class="form-group">
                        <label for="question-content">Question Details:</label>
                        <textarea id="question-content" name="content" required rows="5" 
                                  placeholder="Provide more details about your question..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Post Question</button>
                        <button type="button" class="btn btn-secondary" id="cancel-question">Cancel</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <div id="qa-content-container" class="qa-content">
                <div class="loading-spinner" style="display: none;">Loading questions...</div>
                <div id="qa-content-list"></div>
            </div>
            
            <div id="qa-pagination" class="qa-pagination"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const qaManager = new QLCMQAManager(<?php echo $course_id; ?>);
            qaManager.init();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle question submission
     */
    public function handle_question_submission() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_qa_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to ask questions.');
            return;
        }
        
        $course_id = intval($_POST['course_id']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $user_id = get_current_user_id();
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error('Invalid course specified.');
            return;
        }
        
        if (empty($title) || empty($content)) {
            wp_send_json_error('Title and content are required.');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_questions';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'course_id' => $course_id,
                'user_id' => $user_id,
                'title' => $title,
                'content' => $content,
                'status' => 'published'
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to save question.');
            return;
        }
        
        $question_id = $wpdb->insert_id;
        $question_data = $this->get_question($question_id);
        
        wp_send_json_success(array(
            'message' => 'Question posted successfully.',
            'question' => $question_data
        ));
    }
    
    /**
     * Handle answer submission
     */
    public function handle_answer_submission() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_qa_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to answer questions.');
            return;
        }
        
        $question_id = intval($_POST['question_id']);
        $content = wp_kses_post($_POST['content']);
        $user_id = get_current_user_id();
        
        if (!$question_id || empty($content)) {
            wp_send_json_error('Question ID and content are required.');
            return;
        }
        
        global $wpdb;
        $answers_table = $wpdb->prefix . 'qlcm_answers';
        $questions_table = $wpdb->prefix . 'qlcm_questions';
        
        // Insert answer
        $result = $wpdb->insert(
            $answers_table,
            array(
                'question_id' => $question_id,
                'user_id' => $user_id,
                'content' => $content,
                'status' => 'published'
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to save answer.');
            return;
        }
        
        // Update question answer count
        $wpdb->query($wpdb->prepare(
            "UPDATE $questions_table SET answer_count = answer_count + 1, is_answered = 1 WHERE id = %d",
            $question_id
        ));
        
        $answer_id = $wpdb->insert_id;
        $answer_data = $this->get_answer($answer_id);
        
        wp_send_json_success(array(
            'message' => 'Answer posted successfully.',
            'answer' => $answer_data
        ));
    }
    
    /**
     * Handle answer voting
     */
    public function handle_answer_vote() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_qa_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to vote.');
            return;
        }
        
        $answer_id = intval($_POST['answer_id']);
        $vote_type = sanitize_text_field($_POST['vote_type']);
        $user_id = get_current_user_id();
        
        if (!$answer_id || !in_array($vote_type, array('upvote', 'downvote'))) {
            wp_send_json_error('Invalid vote data.');
            return;
        }
        
        global $wpdb;
        $votes_table = $wpdb->prefix . 'qlcm_answer_votes';
        $answers_table = $wpdb->prefix . 'qlcm_answers';
        
        // Check if user already voted
        $existing_vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $votes_table WHERE answer_id = %d AND user_id = %d",
            $answer_id,
            $user_id
        ));
        
        if ($existing_vote) {
            if ($existing_vote->vote_type === $vote_type) {
                // Remove vote if same type
                $wpdb->delete(
                    $votes_table,
                    array('answer_id' => $answer_id, 'user_id' => $user_id),
                    array('%d', '%d')
                );
                $vote_action = 'removed';
            } else {
                // Update vote type
                $wpdb->update(
                    $votes_table,
                    array('vote_type' => $vote_type),
                    array('answer_id' => $answer_id, 'user_id' => $user_id),
                    array('%s'),
                    array('%d', '%d')
                );
                $vote_action = 'updated';
            }
        } else {
            // Insert new vote
            $wpdb->insert(
                $votes_table,
                array(
                    'answer_id' => $answer_id,
                    'user_id' => $user_id,
                    'vote_type' => $vote_type
                ),
                array('%d', '%d', '%s')
            );
            $vote_action = 'added';
        }
        
        // Update answer vote counts
        $upvotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE answer_id = %d AND vote_type = 'upvote'",
            $answer_id
        ));
        
        $downvotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE answer_id = %d AND vote_type = 'downvote'",
            $answer_id
        ));
        
        $vote_score = $upvotes - $downvotes;
        
        $wpdb->update(
            $answers_table,
            array(
                'upvotes' => $upvotes,
                'downvotes' => $downvotes,
                'vote_score' => $vote_score
            ),
            array('id' => $answer_id),
            array('%d', '%d', '%d'),
            array('%d')
        );
        
        wp_send_json_success(array(
            'message' => 'Vote recorded successfully.',
            'vote_action' => $vote_action,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'vote_score' => $vote_score
        ));
    }
    
    /**
     * Mark answer as helpful
     */
    public function mark_answer_helpful() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_qa_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }
        
        $answer_id = intval($_POST['answer_id']);
        $is_helpful = intval($_POST['is_helpful']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_answers';
        
        $result = $wpdb->update(
            $table_name,
            array('is_helpful' => $is_helpful),
            array('id' => $answer_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Answer marked successfully.');
        } else {
            wp_send_json_error('Failed to update answer.');
        }
    }
    
    /**
     * Load Q&A content
     */
    public function load_qa_content() {
        $course_id = intval($_GET['course_id']);
        $page = intval($_GET['page']) ?: 1;
        $filter = sanitize_text_field($_GET['filter']) ?: 'all';
        $sort = sanitize_text_field($_GET['sort']) ?: 'recent';
        
        if (!$course_id || get_post_type($course_id) !== 'quick_course') {
            wp_send_json_error('Invalid course specified.');
            return;
        }
        
        $questions = $this->get_questions($course_id, $page, $filter, $sort);
        $total_questions = $this->get_questions_count($course_id, $filter);
        
        wp_send_json_success(array(
            'questions' => $questions,
            'total' => $total_questions,
            'page' => $page,
            'per_page' => 10
        ));
    }
    
    /**
     * Get questions for a course
     */
    private function get_questions($course_id, $page = 1, $filter = 'all', $sort = 'recent') {
        global $wpdb;
        $questions_table = $wpdb->prefix . 'qlcm_questions';
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause
        $where_conditions = array("q.course_id = %d", "q.status = 'published'");
        $where_values = array($course_id);
        
        switch ($filter) {
            case 'answered':
                $where_conditions[] = "q.is_answered = 1";
                break;
            case 'unanswered':
                $where_conditions[] = "q.is_answered = 0";
                break;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Build ORDER BY clause
        $order_by = 'ORDER BY ';
        switch ($sort) {
            case 'popular':
                $order_by .= 'q.view_count DESC, q.answer_count DESC, q.created_date DESC';
                break;
            case 'unanswered':
                $order_by .= 'q.is_answered ASC, q.created_date DESC';
                break;
            default:
                $order_by .= 'q.created_date DESC';
        }
        
        $sql = "SELECT q.*, u.display_name, u.user_email 
                FROM $questions_table q 
                LEFT JOIN {$wpdb->users} u ON q.user_id = u.ID 
                $where_clause 
                $order_by 
                LIMIT %d OFFSET %d";
        
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $questions = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        
        // Get answers for each question
        foreach ($questions as &$question) {
            $question->answers = $this->get_question_answers($question->id);
            $question->avatar_url = get_avatar_url($question->user_email, array('size' => 40));
            
            // Increment view count
            $wpdb->query($wpdb->prepare(
                "UPDATE $questions_table SET view_count = view_count + 1 WHERE id = %d",
                $question->id
            ));
        }
        
        return $questions;
    }
    
    /**
     * Get answers for a question
     */
    private function get_question_answers($question_id, $limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_answers';
        
        $sql = "SELECT a.*, u.display_name, u.user_email 
                FROM $table_name a 
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
                WHERE a.question_id = %d AND a.status = 'published' 
                ORDER BY a.is_helpful DESC, a.vote_score DESC, a.created_date ASC 
                LIMIT %d";
        
        $answers = $wpdb->get_results($wpdb->prepare($sql, $question_id, $limit));
        
        foreach ($answers as &$answer) {
            $answer->avatar_url = get_avatar_url($answer->user_email, array('size' => 32));
            $answer->user_vote = $this->get_user_vote($answer->id, get_current_user_id());
        }
        
        return $answers;
    }
    
    /**
     * Get user's vote for an answer
     */
    private function get_user_vote($answer_id, $user_id) {
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_answer_votes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM $table_name WHERE answer_id = %d AND user_id = %d",
            $answer_id,
            $user_id
        ));
    }
    
    /**
     * Get questions count
     */
    private function get_questions_count($course_id, $filter = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_questions';
        
        $where_conditions = array("course_id = %d", "status = 'published'");
        $where_values = array($course_id);
        
        switch ($filter) {
            case 'answered':
                $where_conditions[] = "is_answered = 1";
                break;
            case 'unanswered':
                $where_conditions[] = "is_answered = 0";
                break;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $sql = "SELECT COUNT(*) FROM $table_name $where_clause";
        
        return $wpdb->get_var($wpdb->prepare($sql, $where_values));
    }
    
    /**
     * Get single question
     */
    private function get_question($question_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_questions';
        
        $sql = "SELECT q.*, u.display_name, u.user_email 
                FROM $table_name q 
                LEFT JOIN {$wpdb->users} u ON q.user_id = u.ID 
                WHERE q.id = %d";
        
        $question = $wpdb->get_row($wpdb->prepare($sql, $question_id));
        
        if ($question) {
            $question->avatar_url = get_avatar_url($question->user_email, array('size' => 40));
        }
        
        return $question;
    }
    
    /**
     * Get single answer
     */
    private function get_answer($answer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_answers';
        
        $sql = "SELECT a.*, u.display_name, u.user_email 
                FROM $table_name a 
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
                WHERE a.id = %d";
        
        $answer = $wpdb->get_row($wpdb->prepare($sql, $answer_id));
        
        if ($answer) {
            $answer->avatar_url = get_avatar_url($answer->user_email, array('size' => 32));
            $answer->user_vote = $this->get_user_vote($answer->id, get_current_user_id());
        }
        
        return $answer;
    }
}

// Initialize the Q&A class
new QLCM_Course_QA();