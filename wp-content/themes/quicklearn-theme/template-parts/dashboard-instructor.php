<?php
/**
 * Instructor Dashboard Template Part
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get instructor statistics
$instructor_courses = new WP_Query(array(
    'post_type' => 'quick_course',
    'posts_per_page' => -1,
    'author' => $user_id,
    'post_status' => array('publish', 'draft', 'pending')
));

$published_courses = 0;
$draft_courses = 0;
$total_enrollments = 0;
$total_revenue = 0;

if ($instructor_courses->have_posts()) {
    while ($instructor_courses->have_posts()) {
        $instructor_courses->the_post();
        $course_id = get_the_ID();
        $status = get_post_status();
        
        if ($status === 'publish') {
            $published_courses++;
            
            // Count enrollments for this course
            $enrollments = get_post_meta($course_id, '_qlcm_enrollment_count', true);
            $total_enrollments += intval($enrollments);
            
            // Calculate revenue (if applicable)
            $price = get_post_meta($course_id, '_qlcm_course_price', true);
            if ($price) {
                $total_revenue += floatval($price) * intval($enrollments);
            }
        } elseif ($status === 'draft') {
            $draft_courses++;
        }
    }
    wp_reset_postdata();
}

$total_courses = $instructor_courses->found_posts;
?>

<!-- Instructor Dashboard Statistics -->
<section class="dashboard-stats instructor-stats">
    <div class="stats-grid">
        <div class="stat-card featured">
            <div class="stat-icon">
                <span class="dashicons dashicons-book"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($published_courses); ?></h3>
                <p class="stat-label"><?php esc_html_e('Published Courses', 'quicklearn'); ?></p>
                <div class="stat-trend">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course')); ?>">
                        <?php esc_html_e('Manage Courses', 'quicklearn'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($total_enrollments); ?></h3>
                <p class="stat-label"><?php esc_html_e('Total Students', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($draft_courses); ?></h3>
                <p class="stat-label"><?php esc_html_e('Drafts', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number">
                    <?php echo $total_enrollments > 0 ? esc_html(round($total_enrollments / max($published_courses, 1), 1)) : '0'; ?>
                </h3>
                <p class="stat-label"><?php esc_html_e('Avg. Students/Course', 'quicklearn'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Actions -->
<section class="dashboard-actions-section instructor-actions">
    <h2 class="section-title"><?php esc_html_e('Quick Actions', 'quicklearn'); ?></h2>
    <div class="action-cards">
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=quick_course')); ?>" class="action-card primary">
            <div class="action-icon">
                <span class="dashicons dashicons-plus-alt"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Create New Course', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Start building your next course', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course&post_status=draft')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Continue Drafts', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Finish your unpublished courses', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-analytics')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('View Analytics', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Track your course performance', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-students')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Manage Students', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('View and support your students', 'quicklearn'); ?></p>
        </a>
    </div>
</section>

<!-- Course Performance -->
<section class="course-performance-section">
    <h2 class="section-title"><?php esc_html_e('Course Performance', 'quicklearn'); ?></h2>
    
    <?php
    // Get top performing courses
    $performance_courses = new WP_Query(array(
        'post_type' => 'quick_course',
        'posts_per_page' => 5,
        'author' => $user_id,
        'post_status' => 'publish',
        'meta_key' => '_qlcm_enrollment_count',
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    ));
    
    if ($performance_courses->have_posts()) :
    ?>
        <div class="performance-table">
            <div class="table-header">
                <div class="col-course"><?php esc_html_e('Course', 'quicklearn'); ?></div>
                <div class="col-students"><?php esc_html_e('Students', 'quicklearn'); ?></div>
                <div class="col-rating"><?php esc_html_e('Rating', 'quicklearn'); ?></div>
                <div class="col-status"><?php esc_html_e('Status', 'quicklearn'); ?></div>
                <div class="col-actions"><?php esc_html_e('Actions', 'quicklearn'); ?></div>
            </div>
            
            <?php while ($performance_courses->have_posts()) : $performance_courses->the_post(); ?>
                <?php
                $course_id = get_the_ID();
                $enrollments = get_post_meta($course_id, '_qlcm_enrollment_count', true);
                $rating = get_post_meta($course_id, '_qlcm_average_rating', true);
                $reviews_count = get_post_meta($course_id, '_qlcm_reviews_count', true);
                ?>
                <div class="table-row">
                    <div class="col-course">
                        <div class="course-info">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="course-thumb">
                                    <?php the_post_thumbnail('thumbnail'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="course-details">
                                <h4 class="course-name"><?php the_title(); ?></h4>
                                <span class="course-date"><?php echo get_the_date(); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-students">
                        <span class="student-count"><?php echo esc_html($enrollments ?: '0'); ?></span>
                    </div>
                    
                    <div class="col-rating">
                        <?php if ($rating && $reviews_count) : ?>
                            <div class="rating-display">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <span class="star <?php echo $i <= $rating ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo esc_html(number_format($rating, 1)); ?> (<?php echo esc_html($reviews_count); ?>)</span>
                            </div>
                        <?php else : ?>
                            <span class="no-rating"><?php esc_html_e('No reviews yet', 'quicklearn'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-status">
                        <span class="status-badge status-<?php echo esc_attr(get_post_status()); ?>">
                            <?php echo esc_html(ucfirst(get_post_status())); ?>
                        </span>
                    </div>
                    
                    <div class="col-actions">
                        <div class="action-buttons">
                            <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="btn btn--sm btn--secondary">
                                <?php esc_html_e('Edit', 'quicklearn'); ?>
                            </a>
                            <a href="<?php the_permalink(); ?>" class="btn btn--sm btn--outline">
                                <?php esc_html_e('View', 'quicklearn'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="section-footer">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course')); ?>" class="btn btn--secondary">
                <?php esc_html_e('View All Courses', 'quicklearn'); ?>
            </a>
        </div>
        
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-book"></span>
            </div>
            <h3 class="empty-title"><?php esc_html_e('No courses yet', 'quicklearn'); ?></h3>
            <p class="empty-description"><?php esc_html_e('Create your first course to start teaching!', 'quicklearn'); ?></p>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=quick_course')); ?>" class="btn btn--primary">
                <?php esc_html_e('Create Your First Course', 'quicklearn'); ?>
            </a>
        </div>
    <?php endif; wp_reset_postdata(); ?>
</section>

<!-- Recent Student Activity -->
<section class="student-activity-section">
    <h2 class="section-title"><?php esc_html_e('Recent Student Activity', 'quicklearn'); ?></h2>
    
    <?php
    // Get recent enrollments in instructor's courses
    global $wpdb;
    $recent_enrollments = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, p.post_title as course_title, u.display_name as student_name
        FROM {$wpdb->prefix}quicklearn_enrollments e
        JOIN {$wpdb->posts} p ON e.course_id = p.ID
        JOIN {$wpdb->users} u ON e.user_id = u.ID
        WHERE p.post_author = %d
        ORDER BY e.enrollment_date DESC
        LIMIT 5
    ", $user_id));
    
    if ($recent_enrollments) :
    ?>
        <div class="activity-list">
            <?php foreach ($recent_enrollments as $enrollment) : ?>
                <div class="activity-item">
                    <div class="activity-avatar">
                        <?php echo get_avatar($enrollment->user_id, 40); ?>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text">
                            <strong><?php echo esc_html($enrollment->student_name); ?></strong>
                            <?php esc_html_e('enrolled in', 'quicklearn'); ?>
                            <strong><?php echo esc_html($enrollment->course_title); ?></strong>
                        </p>
                        <span class="activity-time"><?php echo esc_html(human_time_diff(strtotime($enrollment->enrollment_date))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                    </div>
                    <div class="activity-actions">
                        <a href="<?php echo esc_url(get_permalink($enrollment->course_id)); ?>" class="btn btn--sm btn--outline">
                            <?php esc_html_e('View Course', 'quicklearn'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section-footer">
            <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-students')); ?>" class="btn btn--secondary">
                <?php esc_html_e('View All Activity', 'quicklearn'); ?>
            </a>
        </div>
        
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h3 class="empty-title"><?php esc_html_e('No recent activity', 'quicklearn'); ?></h3>
            <p class="empty-description"><?php esc_html_e('Student enrollments and activity will appear here.', 'quicklearn'); ?></p>
        </div>
    <?php endif; ?>
</section>

<!-- Tips for Instructors -->
<section class="instructor-tips-section">
    <h2 class="section-title"><?php esc_html_e('Tips for Success', 'quicklearn'); ?></h2>
    
    <div class="tips-grid">
        <div class="tip-card">
            <div class="tip-icon">
                <span class="dashicons dashicons-video-alt3"></span>
            </div>
            <h3 class="tip-title"><?php esc_html_e('Engage with Video', 'quicklearn'); ?></h3>
            <p class="tip-description"><?php esc_html_e('Students prefer video content. Add engaging videos to increase completion rates.', 'quicklearn'); ?></p>
        </div>
        
        <div class="tip-card">
            <div class="tip-icon">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <h3 class="tip-title"><?php esc_html_e('Respond to Questions', 'quicklearn'); ?></h3>
            <p class="tip-description"><?php esc_html_e('Quick responses to student questions improve satisfaction and ratings.', 'quicklearn'); ?></p>
        </div>
        
        <div class="tip-card">
            <div class="tip-icon">
                <span class="dashicons dashicons-update"></span>
            </div>
            <h3 class="tip-title"><?php esc_html_e('Keep Content Fresh', 'quicklearn'); ?></h3>
            <p class="tip-description"><?php esc_html_e('Regular updates keep your courses relevant and students engaged.', 'quicklearn'); ?></p>
        </div>
    </div>
</section>