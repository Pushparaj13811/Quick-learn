<?php
/**
 * User Profiles and Learning History
 * 
 * Handles user profile pages, learning history, and achievements
 */

if (!defined('ABSPATH')) {
    exit;
}

class QLCM_User_Profiles {
    
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_profile_pages'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_assets'));
        add_action('wp_ajax_update_user_profile', array($this, 'handle_profile_update'));
        add_action('wp_ajax_send_private_message', array($this, 'handle_private_message'));
        add_action('wp_ajax_load_messages', array($this, 'load_private_messages'));
        add_action('wp_ajax_mark_message_read', array($this, 'mark_message_read'));
        
        add_shortcode('user_profile', array($this, 'display_user_profile'));
        add_shortcode('user_messages', array($this, 'display_user_messages'));
        
        // Add profile link to user menu
        add_action('wp_nav_menu_items', array($this, 'add_profile_menu_item'), 10, 2);
    }
    
    /**
     * Add rewrite rules for profile pages
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?profile_user=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^messages/?$',
            'index.php?user_messages=1',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'profile_user';
            $vars[] = 'user_messages';
            return $vars;
        });
    }
    
    /**
     * Handle profile page requests
     */
    public function handle_profile_pages() {
        global $wp_query;
        
        $profile_user = get_query_var('profile_user');
        $user_messages = get_query_var('user_messages');
        
        if ($profile_user) {
            $this->display_profile_page($profile_user);
            exit;
        }
        
        if ($user_messages) {
            $this->display_messages_page();
            exit;
        }
    }
    
    /**
     * Enqueue profile assets
     */
    public function enqueue_profile_assets() {
        if (get_query_var('profile_user') || get_query_var('user_messages')) {
            wp_enqueue_script(
                'qlcm-profiles',
                QLCM_PLUGIN_URL . 'assets/js/profiles.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-profiles',
                QLCM_PLUGIN_URL . 'assets/css/profiles.css',
                array(),
                QLCM_VERSION
            );
            
            wp_localize_script('qlcm-profiles', 'qlcm_profiles_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('qlcm_profiles_nonce'),
                'current_user_id' => get_current_user_id()
            ));
        }
    }
    
    /**
     * Display profile page
     */
    public function display_profile_page($username) {
        $user = get_user_by('login', $username);
        
        if (!$user) {
            wp_die('User not found.');
        }
        
        get_header();
        
        echo '<div class="qlcm-user-profile-page">';
        echo do_shortcode('[user_profile user_id="' . $user->ID . '"]');
        echo '</div>';
        
        get_footer();
    }
    
    /**
     * Display messages page
     */
    public function display_messages_page() {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/messages')));
            exit;
        }
        
        get_header();
        
        echo '<div class="qlcm-user-messages-page">';
        echo do_shortcode('[user_messages]');
        echo '</div>';
        
        get_footer();
    }
    
    /**
     * Display user profile shortcode
     */
    public function display_user_profile($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id()
        ), $atts);
        
        $user_id = intval($atts['user_id']);
        $user = get_userdata($user_id);
        
        if (!$user) {
            return '<p>User not found.</p>';
        }
        
        $current_user_id = get_current_user_id();
        $is_own_profile = ($current_user_id === $user_id);
        
        // Get user learning statistics
        $learning_stats = $this->get_user_learning_stats($user_id);
        
        ob_start();
        ?>
        <div class="qlcm-user-profile" data-user-id="<?php echo $user_id; ?>">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo get_avatar($user->user_email, 120); ?>
                </div>
                
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo esc_html($user->display_name); ?></h1>
                    <p class="profile-username">@<?php echo esc_html($user->user_login); ?></p>
                    
                    <?php if ($user->description) : ?>
                        <p class="profile-bio"><?php echo esc_html($user->description); ?></p>
                    <?php endif; ?>
                    
                    <div class="profile-meta">
                        <span class="join-date">
                            Member since <?php echo esc_html(date('F Y', strtotime($user->user_registered))); ?>
                        </span>
                    </div>
                    
                    <?php if (!$is_own_profile && is_user_logged_in()) : ?>
                        <div class="profile-actions">
                            <button class="btn btn-primary send-message-btn" data-user-id="<?php echo $user_id; ?>">
                                Send Message
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Learning Statistics -->
            <div class="learning-stats">
                <h2>Learning Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['enrolled_courses']); ?></div>
                        <div class="stat-label">Enrolled Courses</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['completed_courses']); ?></div>
                        <div class="stat-label">Completed Courses</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['certificates_earned']); ?></div>
                        <div class="stat-label">Certificates Earned</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['forum_posts']); ?></div>
                        <div class="stat-label">Forum Posts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['questions_asked']); ?></div>
                        <div class="stat-label">Questions Asked</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo esc_html($learning_stats['answers_given']); ?></div>
                        <div class="stat-label">Answers Given</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <?php
                    $recent_activities = $this->get_user_recent_activities($user_id, 10);
                    if (empty($recent_activities)) :
                    ?>
                        <p class="no-activity">No recent activity.</p>
                    <?php else : ?>
                        <?php foreach ($recent_activities as $activity) : ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php echo $this->get_activity_icon($activity->type); ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo $activity->description; ?></div>
                                    <div class="activity-date"><?php echo $this->time_ago(strtotime($activity->created_date)); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enrolled Courses -->
            <?php if ($is_own_profile || !empty($learning_stats['public_courses'])) : ?>
                <div class="enrolled-courses">
                    <h2><?php echo $is_own_profile ? 'My Courses' : 'Public Courses'; ?></h2>
                    <div class="courses-grid">
                        <?php
                        $enrolled_courses = $this->get_user_enrolled_courses($user_id, $is_own_profile);
                        if (empty($enrolled_courses)) :
                        ?>
                            <p class="no-courses">No courses found.</p>
                        <?php else : ?>
                            <?php foreach ($enrolled_courses as $course) : ?>
                                <div class="course-card">
                                    <?php if (has_post_thumbnail($course->course_id)) : ?>
                                        <div class="course-thumbnail">
                                            <?php echo get_the_post_thumbnail($course->course_id, 'medium'); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="course-info">
                                        <h3 class="course-title">
                                            <a href="<?php echo esc_url(get_permalink($course->course_id)); ?>">
                                                <?php echo esc_html(get_the_title($course->course_id)); ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="course-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo esc_attr($course->progress_percentage); ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo esc_html($course->progress_percentage); ?>% complete</span>
                                        </div>
                                        
                                        <div class="course-status">
                                            <span class="status-<?php echo esc_attr($course->status); ?>">
                                                <?php echo esc_html(ucfirst($course->status)); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Achievements/Badges -->
            <div class="user-achievements">
                <h2>Achievements</h2>
                <div class="achievements-grid">
                    <?php
                    $achievements = $this->get_user_achievements($user_id);
                    if (empty($achievements)) :
                    ?>
                        <p class="no-achievements">No achievements yet.</p>
                    <?php else : ?>
                        <?php foreach ($achievements as $achievement) : ?>
                            <div class="achievement-badge">
                                <div class="badge-icon"><?php echo $achievement->icon; ?></div>
                                <div class="badge-name"><?php echo esc_html($achievement->name); ?></div>
                                <div class="badge-description"><?php echo esc_html($achievement->description); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Private Message Modal -->
        <?php if (!$is_own_profile && is_user_logged_in()) : ?>
            <div id="message-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Send Message to <?php echo esc_html($user->display_name); ?></h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="private-message-form">
                            <input type="hidden" name="recipient_id" value="<?php echo $user_id; ?>" />
                            <div class="form-group">
                                <label for="message-subject">Subject:</label>
                                <input type="text" id="message-subject" name="subject" required maxlength="255" />
                            </div>
                            <div class="form-group">
                                <label for="message-content">Message:</label>
                                <textarea id="message-content" name="content" required rows="5"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const profileManager = new QLCMProfileManager();
            profileManager.init();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Display user messages shortcode
     */
    public function display_user_messages($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . wp_login_url() . '">login</a> to view your messages.</p>';
        }
        
        $current_user_id = get_current_user_id();
        
        ob_start();
        ?>
        <div class="qlcm-user-messages">
            <div class="messages-header">
                <h1>My Messages</h1>
                <button class="btn btn-primary compose-message-btn">Compose Message</button>
            </div>
            
            <div class="messages-container">
                <div class="messages-sidebar">
                    <div class="message-folders">
                        <button class="folder-btn active" data-folder="inbox">
                            Inbox <span class="unread-count" id="inbox-count"></span>
                        </button>
                        <button class="folder-btn" data-folder="sent">Sent</button>
                        <button class="folder-btn" data-folder="archived">Archived</button>
                    </div>
                </div>
                
                <div class="messages-main">
                    <div class="messages-list" id="messages-list">
                        <div class="loading-spinner">Loading messages...</div>
                    </div>
                </div>
                
                <div class="message-detail" id="message-detail" style="display: none;">
                    <div class="message-header">
                        <button class="back-to-list">&larr; Back to Messages</button>
                        <div class="message-actions">
                            <button class="reply-btn">Reply</button>
                            <button class="archive-btn">Archive</button>
                            <button class="delete-btn">Delete</button>
                        </div>
                    </div>
                    <div class="message-content-area"></div>
                </div>
            </div>
            
            <!-- Compose Message Modal -->
            <div id="compose-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Compose Message</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="compose-message-form">
                            <div class="form-group">
                                <label for="recipient-search">To:</label>
                                <input type="text" id="recipient-search" placeholder="Search for user..." />
                                <input type="hidden" id="recipient-id" name="recipient_id" />
                                <div id="recipient-search-results"></div>
                            </div>
                            <div class="form-group">
                                <label for="compose-subject">Subject:</label>
                                <input type="text" id="compose-subject" name="subject" required maxlength="255" />
                            </div>
                            <div class="form-group">
                                <label for="compose-content">Message:</label>
                                <textarea id="compose-content" name="content" required rows="6"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            const messagesManager = new QLCMMessagesManager();
            messagesManager.init();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user learning statistics
     */
    private function get_user_learning_stats($user_id) {
        global $wpdb;
        
        $enrollments_table = $wpdb->prefix . 'qlcm_enrollments';
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        $forum_posts_table = $wpdb->prefix . 'qlcm_forum_posts';
        $questions_table = $wpdb->prefix . 'qlcm_questions';
        $answers_table = $wpdb->prefix . 'qlcm_answers';
        
        $stats = array();
        
        // Enrolled courses
        $stats['enrolled_courses'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $enrollments_table WHERE user_id = %d",
            $user_id
        )) ?: 0;
        
        // Completed courses
        $stats['completed_courses'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $enrollments_table WHERE user_id = %d AND status = 'completed'",
            $user_id
        )) ?: 0;
        
        // Certificates earned
        $stats['certificates_earned'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $certificates_table WHERE user_id = %d",
            $user_id
        )) ?: 0;
        
        // Forum posts
        $stats['forum_posts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $forum_posts_table WHERE user_id = %d AND status = 'published'",
            $user_id
        )) ?: 0;
        
        // Questions asked
        $stats['questions_asked'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $questions_table WHERE user_id = %d AND status = 'published'",
            $user_id
        )) ?: 0;
        
        // Answers given
        $stats['answers_given'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $answers_table WHERE user_id = %d AND status = 'published'",
            $user_id
        )) ?: 0;
        
        return $stats;
    }
    
    /**
     * Get user recent activities
     */
    private function get_user_recent_activities($user_id, $limit = 10) {
        global $wpdb;
        
        // This is a simplified version - in a real implementation, 
        // you'd have a dedicated activities table
        $activities = array();
        
        // Get recent enrollments
        $enrollments = $wpdb->get_results($wpdb->prepare("
            SELECT e.enrollment_date as created_date, p.post_title as course_title, 'enrollment' as type
            FROM {$wpdb->prefix}qlcm_enrollments e
            JOIN {$wpdb->posts} p ON e.course_id = p.ID
            WHERE e.user_id = %d
            ORDER BY e.enrollment_date DESC
            LIMIT %d
        ", $user_id, $limit));
        
        foreach ($enrollments as $enrollment) {
            $activities[] = (object) array(
                'type' => 'enrollment',
                'description' => 'Enrolled in course: ' . $enrollment->course_title,
                'created_date' => $enrollment->created_date
            );
        }
        
        // Get recent forum posts
        $forum_posts = $wpdb->get_results($wpdb->prepare("
            SELECT fp.created_date, fp.title, 'forum_post' as type
            FROM {$wpdb->prefix}qlcm_forum_posts fp
            WHERE fp.user_id = %d AND fp.status = 'published' AND fp.parent_id = 0
            ORDER BY fp.created_date DESC
            LIMIT %d
        ", $user_id, $limit));
        
        foreach ($forum_posts as $post) {
            $activities[] = (object) array(
                'type' => 'forum_post',
                'description' => 'Posted in forum: ' . $post->title,
                'created_date' => $post->created_date
            );
        }
        
        // Sort activities by date
        usort($activities, function($a, $b) {
            return strtotime($b->created_date) - strtotime($a->created_date);
        });
        
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Get user enrolled courses
     */
    private function get_user_enrolled_courses($user_id, $include_private = false) {
        global $wpdb;
        
        $sql = "SELECT e.*, p.post_title, p.post_status
                FROM {$wpdb->prefix}qlcm_enrollments e
                JOIN {$wpdb->posts} p ON e.course_id = p.ID
                WHERE e.user_id = %d AND p.post_status = 'publish'";
        
        if (!$include_private) {
            // Only show completed courses for public profiles
            $sql .= " AND e.status = 'completed'";
        }
        
        $sql .= " ORDER BY e.enrollment_date DESC LIMIT 6";
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * Get user achievements
     */
    private function get_user_achievements($user_id) {
        // This is a simplified version - you could expand this with a proper achievements system
        $achievements = array();
        $stats = $this->get_user_learning_stats($user_id);
        
        if ($stats['enrolled_courses'] >= 1) {
            $achievements[] = (object) array(
                'name' => 'First Steps',
                'description' => 'Enrolled in your first course',
                'icon' => '🎯'
            );
        }
        
        if ($stats['completed_courses'] >= 1) {
            $achievements[] = (object) array(
                'name' => 'Course Completer',
                'description' => 'Completed your first course',
                'icon' => '🏆'
            );
        }
        
        if ($stats['completed_courses'] >= 5) {
            $achievements[] = (object) array(
                'name' => 'Learning Enthusiast',
                'description' => 'Completed 5 courses',
                'icon' => '🌟'
            );
        }
        
        if ($stats['forum_posts'] >= 10) {
            $achievements[] = (object) array(
                'name' => 'Community Contributor',
                'description' => 'Made 10 forum posts',
                'icon' => '💬'
            );
        }
        
        if ($stats['answers_given'] >= 5) {
            $achievements[] = (object) array(
                'name' => 'Helpful Helper',
                'description' => 'Answered 5 questions',
                'icon' => '🤝'
            );
        }
        
        return $achievements;
    }
    
    /**
     * Get activity icon
     */
    private function get_activity_icon($type) {
        $icons = array(
            'enrollment' => '📚',
            'completion' => '✅',
            'forum_post' => '💬',
            'question' => '❓',
            'answer' => '💡',
            'certificate' => '🏆'
        );
        
        return $icons[$type] ?? '📌';
    }
    
    /**
     * Time ago helper
     */
    private function time_ago($timestamp) {
        $time_ago = time() - $timestamp;
        
        if ($time_ago < 60) {
            return 'just now';
        } elseif ($time_ago < 3600) {
            return floor($time_ago / 60) . ' minutes ago';
        } elseif ($time_ago < 86400) {
            return floor($time_ago / 3600) . ' hours ago';
        } elseif ($time_ago < 2592000) {
            return floor($time_ago / 86400) . ' days ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
    
    /**
     * Handle private message sending
     */
    public function handle_private_message() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_profiles_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to send messages.');
            return;
        }
        
        $recipient_id = intval($_POST['recipient_id']);
        $subject = sanitize_text_field($_POST['subject']);
        $content = wp_kses_post($_POST['content']);
        $sender_id = get_current_user_id();
        
        if (!$recipient_id || !get_userdata($recipient_id)) {
            wp_send_json_error('Invalid recipient.');
            return;
        }
        
        if (empty($subject) || empty($content)) {
            wp_send_json_error('Subject and message are required.');
            return;
        }
        
        // Create messages table if it doesn't exist
        $this->create_messages_table();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_private_messages';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'subject' => $subject,
                'content' => $content,
                'status' => 'unread'
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to send message.');
            return;
        }
        
        wp_send_json_success('Message sent successfully.');
    }
    
    /**
     * Load private messages
     */
    public function load_private_messages() {
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
            return;
        }
        
        $folder = sanitize_text_field($_GET['folder']) ?: 'inbox';
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_private_messages';
        
        $where_clause = '';
        switch ($folder) {
            case 'sent':
                $where_clause = "sender_id = $user_id";
                break;
            case 'archived':
                $where_clause = "recipient_id = $user_id AND status = 'archived'";
                break;
            default: // inbox
                $where_clause = "recipient_id = $user_id AND status != 'archived'";
        }
        
        $messages = $wpdb->get_results("
            SELECT m.*, 
                   s.display_name as sender_name, 
                   r.display_name as recipient_name
            FROM $table_name m
            LEFT JOIN {$wpdb->users} s ON m.sender_id = s.ID
            LEFT JOIN {$wpdb->users} r ON m.recipient_id = r.ID
            WHERE $where_clause
            ORDER BY m.created_date DESC
            LIMIT 50
        ");
        
        wp_send_json_success($messages);
    }
    
    /**
     * Mark message as read
     */
    public function mark_message_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_profiles_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
            return;
        }
        
        $message_id = intval($_POST['message_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'qlcm_private_messages';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'read'),
            array('id' => $message_id, 'recipient_id' => $user_id),
            array('%s'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Message marked as read.');
        } else {
            wp_send_json_error('Failed to update message.');
        }
    }
    
    /**
     * Create messages table
     */
    private function create_messages_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'qlcm_private_messages';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            recipient_id bigint(20) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            status varchar(20) DEFAULT 'unread',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sender_id (sender_id),
            KEY recipient_id (recipient_id),
            KEY status (status),
            KEY created_date (created_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Add profile menu item
     */
    public function add_profile_menu_item($items, $args) {
        if (is_user_logged_in() && $args->theme_location === 'primary') {
            $current_user = wp_get_current_user();
            $profile_link = home_url('/profile/' . $current_user->user_login);
            $messages_link = home_url('/messages');
            
            $items .= '<li class="menu-item"><a href="' . esc_url($profile_link) . '">My Profile</a></li>';
            $items .= '<li class="menu-item"><a href="' . esc_url($messages_link) . '">Messages</a></li>';
        }
        
        return $items;
    }
}

// Initialize the user profiles class
new QLCM_User_Profiles();