<?php
/**
 * Moderator Dashboard Template Part
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get moderation statistics
global $wpdb;

// Get course counts by status
$course_stats = $wpdb->get_row("
    SELECT 
        COUNT(CASE WHEN post_status = 'publish' THEN 1 END) as published,
        COUNT(CASE WHEN post_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN post_status = 'draft' THEN 1 END) as drafts,
        COUNT(*) as total
    FROM {$wpdb->posts} 
    WHERE post_type = 'quick_course'
");

// Get user activity stats
$user_stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_month,
        COUNT(CASE WHEN user_registered >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_week
    FROM {$wpdb->users}
");

// Get pending content count
$pending_reviews = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'qlcm_review' 
    AND post_status = 'pending'
");

$reported_content = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}quicklearn_reports 
    WHERE status = 'pending'
");
?>

<!-- Moderator Dashboard Statistics -->
<section class="dashboard-stats moderator-stats">
    <div class="stats-grid">
        <div class="stat-card featured">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-tools"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($course_stats->pending ?: '0'); ?></h3>
                <p class="stat-label"><?php esc_html_e('Pending Courses', 'quicklearn'); ?></p>
                <div class="stat-trend">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course&post_status=pending')); ?>">
                        <?php esc_html_e('Review Now', 'quicklearn'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-book"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($course_stats->published ?: '0'); ?></h3>
                <p class="stat-label"><?php esc_html_e('Published Courses', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($user_stats->total_users ?: '0'); ?></h3>
                <p class="stat-label"><?php esc_html_e('Total Users', 'quicklearn'); ?></p>
                <div class="stat-meta">
                    <span class="meta-item"><?php echo esc_html($user_stats->new_users_week ?: '0'); ?> <?php esc_html_e('this week', 'quicklearn'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-flag"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html(($pending_reviews ?: 0) + ($reported_content ?: 0)); ?></h3>
                <p class="stat-label"><?php esc_html_e('Pending Reviews', 'quicklearn'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Moderation Actions -->
<section class="dashboard-actions-section moderator-actions">
    <h2 class="section-title"><?php esc_html_e('Moderation Tools', 'quicklearn'); ?></h2>
    <div class="action-cards">
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course&post_status=pending')); ?>" class="action-card primary">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-tools"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Review Courses', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Approve or reject pending course submissions', 'quicklearn'); ?></p>
            <?php if ($course_stats->pending > 0) : ?>
                <div class="action-badge"><?php echo esc_html($course_stats->pending); ?></div>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-reports')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-flag"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Content Reports', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Handle reported content and user complaints', 'quicklearn'); ?></p>
            <?php if ($reported_content > 0) : ?>
                <div class="action-badge"><?php echo esc_html($reported_content); ?></div>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('User Management', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Manage user accounts and permissions', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-analytics')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Platform Analytics', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('View platform usage and engagement metrics', 'quicklearn'); ?></p>
        </a>
    </div>
</section>

<!-- Pending Content Review -->
<section class="pending-content-section">
    <h2 class="section-title"><?php esc_html_e('Pending Content', 'quicklearn'); ?></h2>
    
    <?php
    // Get pending courses
    $pending_courses = new WP_Query(array(
        'post_type' => 'quick_course',
        'posts_per_page' => 5,
        'post_status' => 'pending',
        'orderby' => 'date',
        'order' => 'ASC'
    ));
    
    if ($pending_courses->have_posts()) :
    ?>
        <div class="pending-content-list">
            <h3 class="subsection-title"><?php esc_html_e('Courses Awaiting Review', 'quicklearn'); ?></h3>
            <div class="content-review-table">
                <div class="table-header">
                    <div class="col-content"><?php esc_html_e('Course', 'quicklearn'); ?></div>
                    <div class="col-author"><?php esc_html_e('Instructor', 'quicklearn'); ?></div>
                    <div class="col-date"><?php esc_html_e('Submitted', 'quicklearn'); ?></div>
                    <div class="col-actions"><?php esc_html_e('Actions', 'quicklearn'); ?></div>
                </div>
                
                <?php while ($pending_courses->have_posts()) : $pending_courses->the_post(); ?>
                    <div class="table-row">
                        <div class="col-content">
                            <div class="content-info">
                                <?php if (has_post_thumbnail()) : ?>
                                    <div class="content-thumb">
                                        <?php the_post_thumbnail('thumbnail'); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="content-details">
                                    <h4 class="content-title">
                                        <a href="<?php echo esc_url(get_edit_post_link()); ?>"><?php the_title(); ?></a>
                                    </h4>
                                    <p class="content-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 15)); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-author">
                            <div class="author-info">
                                <?php echo get_avatar(get_the_author_meta('ID'), 32); ?>
                                <span class="author-name"><?php the_author(); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-date">
                            <span class="submit-date"><?php echo esc_html(human_time_diff(get_the_time('U'))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                        </div>
                        
                        <div class="col-actions">
                            <div class="action-buttons">
                                <button class="btn btn--sm btn--success approve-course" data-course-id="<?php echo esc_attr(get_the_ID()); ?>">
                                    <?php esc_html_e('Approve', 'quicklearn'); ?>
                                </button>
                                <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="btn btn--sm btn--secondary">
                                    <?php esc_html_e('Review', 'quicklearn'); ?>
                                </a>
                                <button class="btn btn--sm btn--danger reject-course" data-course-id="<?php echo esc_attr(get_the_ID()); ?>">
                                    <?php esc_html_e('Reject', 'quicklearn'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="section-footer">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course&post_status=pending')); ?>" class="btn btn--secondary">
                    <?php esc_html_e('View All Pending Courses', 'quicklearn'); ?>
                </a>
            </div>
        </div>
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h3 class="empty-title"><?php esc_html_e('No pending courses', 'quicklearn'); ?></h3>
            <p class="empty-description"><?php esc_html_e('All courses have been reviewed. Great job!', 'quicklearn'); ?></p>
        </div>
    <?php endif; wp_reset_postdata(); ?>
</section>

<!-- Recent Platform Activity -->
<section class="platform-activity-section">
    <h2 class="section-title"><?php esc_html_e('Recent Platform Activity', 'quicklearn'); ?></h2>
    
    <div class="activity-grid">
        <!-- Recent User Activity -->
        <div class="activity-panel">
            <h3 class="panel-title"><?php esc_html_e('New User Registrations', 'quicklearn'); ?></h3>
            <?php
            $recent_users = get_users(array(
                'orderby' => 'registered',
                'order' => 'DESC',
                'number' => 5,
                'date_query' => array(
                    'after' => '7 days ago'
                )
            ));
            
            if ($recent_users) :
            ?>
                <div class="activity-list">
                    <?php foreach ($recent_users as $user) : ?>
                        <div class="activity-item">
                            <div class="activity-avatar">
                                <?php echo get_avatar($user->ID, 40); ?>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">
                                    <strong><?php echo esc_html($user->display_name); ?></strong>
                                    <?php esc_html_e('joined as', 'quicklearn'); ?>
                                    <span class="user-role"><?php echo esc_html(implode(', ', $user->roles)); ?></span>
                                </p>
                                <span class="activity-time"><?php echo esc_html(human_time_diff(strtotime($user->user_registered))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                            </div>
                            <div class="activity-actions">
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="btn btn--sm btn--outline">
                                    <?php esc_html_e('View', 'quicklearn'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="no-activity"><?php esc_html_e('No new registrations this week.', 'quicklearn'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Course Activity -->
        <div class="activity-panel">
            <h3 class="panel-title"><?php esc_html_e('Recent Course Updates', 'quicklearn'); ?></h3>
            <?php
            $recent_courses = new WP_Query(array(
                'post_type' => 'quick_course',
                'posts_per_page' => 5,
                'orderby' => 'modified',
                'order' => 'DESC',
                'post_status' => 'publish'
            ));
            
            if ($recent_courses->have_posts()) :
            ?>
                <div class="activity-list">
                    <?php while ($recent_courses->have_posts()) : $recent_courses->the_post(); ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <p class="activity-text">
                                    <strong><?php the_title(); ?></strong>
                                    <?php esc_html_e('was updated by', 'quicklearn'); ?>
                                    <strong><?php the_author(); ?></strong>
                                </p>
                                <span class="activity-time"><?php echo esc_html(human_time_diff(get_the_modified_time('U'))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                            </div>
                            <div class="activity-actions">
                                <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="btn btn--sm btn--outline">
                                    <?php esc_html_e('View', 'quicklearn'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="no-activity"><?php esc_html_e('No recent course updates.', 'quicklearn'); ?></p>
            <?php endif; wp_reset_postdata(); ?>
        </div>
    </div>
</section>

<!-- Moderation Guidelines -->
<section class="moderation-guidelines-section">
    <h2 class="section-title"><?php esc_html_e('Moderation Guidelines', 'quicklearn'); ?></h2>
    
    <div class="guidelines-grid">
        <div class="guideline-card">
            <div class="guideline-icon">
                <span class="dashicons dashicons-admin-tools"></span>
            </div>
            <h3 class="guideline-title"><?php esc_html_e('Course Review Criteria', 'quicklearn'); ?></h3>
            <ul class="guideline-list">
                <li><?php esc_html_e('Content is original and educational', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Clear learning objectives provided', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Appropriate length and structure', 'quicklearn'); ?></li>
                <li><?php esc_html_e('No copyright violations', 'quicklearn'); ?></li>
            </ul>
        </div>
        
        <div class="guideline-card">
            <div class="guideline-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h3 class="guideline-title"><?php esc_html_e('Community Standards', 'quicklearn'); ?></h3>
            <ul class="guideline-list">
                <li><?php esc_html_e('Respectful communication required', 'quicklearn'); ?></li>
                <li><?php esc_html_e('No spam or promotional content', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Constructive feedback encouraged', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Report inappropriate behavior', 'quicklearn'); ?></li>
            </ul>
        </div>
        
        <div class="guideline-card">
            <div class="guideline-icon">
                <span class="dashicons dashicons-shield"></span>
            </div>
            <h3 class="guideline-title"><?php esc_html_e('Safety & Security', 'quicklearn'); ?></h3>
            <ul class="guideline-list">
                <li><?php esc_html_e('Monitor for suspicious activity', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Protect user privacy and data', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Verify instructor credentials', 'quicklearn'); ?></li>
                <li><?php esc_html_e('Report security concerns promptly', 'quicklearn'); ?></li>
            </ul>
        </div>
    </div>
</section>

<script>
// Moderator Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Course approval/rejection handlers
    const approveButtons = document.querySelectorAll('.approve-course');
    const rejectButtons = document.querySelectorAll('.reject-course');
    
    approveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const courseId = this.getAttribute('data-course-id');
            if (confirm('<?php esc_html_e('Are you sure you want to approve this course?', 'quicklearn'); ?>')) {
                moderateContent(courseId, 'approve');
            }
        });
    });
    
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const courseId = this.getAttribute('data-course-id');
            const reason = prompt('<?php esc_html_e('Please provide a reason for rejection:', 'quicklearn'); ?>');
            if (reason) {
                moderateContent(courseId, 'reject', reason);
            }
        });
    });
    
    function moderateContent(courseId, action, reason = '') {
        const formData = new FormData();
        formData.append('action', 'qlcm_moderate_course');
        formData.append('course_id', courseId);
        formData.append('moderation_action', action);
        formData.append('reason', reason);
        formData.append('nonce', '<?php echo wp_create_nonce('qlcm_moderation_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('<?php esc_html_e('Error processing request. Please try again.', 'quicklearn'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php esc_html_e('Error processing request. Please try again.', 'quicklearn'); ?>');
        });
    }
});
</script>