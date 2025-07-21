<?php
/**
 * Student Dashboard Template Part
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get student statistics
$enrolled_courses = new WP_Query(array(
    'post_type' => 'quick_course',
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => '_qlcm_enrolled_users',
            'value' => serialize($user_id),
            'compare' => 'LIKE'
        )
    )
));

$completed_courses = 0;
$in_progress_courses = 0;
$total_progress = 0;

if ($enrolled_courses->have_posts()) {
    while ($enrolled_courses->have_posts()) {
        $enrolled_courses->the_post();
        $course_id = get_the_ID();
        $progress = get_user_meta($user_id, '_qlcm_course_progress_' . $course_id, true);
        $progress = floatval($progress);
        
        if ($progress >= 100) {
            $completed_courses++;
        } else {
            $in_progress_courses++;
        }
        
        $total_progress += $progress;
    }
    wp_reset_postdata();
}

$enrolled_count = $enrolled_courses->found_posts;
$average_progress = $enrolled_count > 0 ? round($total_progress / $enrolled_count, 1) : 0;
?>

<!-- Student Dashboard Statistics -->
<section class="dashboard-stats">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-book-alt"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($enrolled_count); ?></h3>
                <p class="stat-label"><?php esc_html_e('Enrolled Courses', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($completed_courses); ?></h3>
                <p class="stat-label"><?php esc_html_e('Completed', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($in_progress_courses); ?></h3>
                <p class="stat-label"><?php esc_html_e('In Progress', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($average_progress); ?>%</h3>
                <p class="stat-label"><?php esc_html_e('Average Progress', 'quicklearn'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Actions -->
<section class="dashboard-actions-section">
    <h2 class="section-title"><?php esc_html_e('Quick Actions', 'quicklearn'); ?></h2>
    <div class="action-cards">
        <a href="<?php echo esc_url(get_post_type_archive_link('quick_course')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-search"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Browse Courses', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Discover new courses to expand your skills', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(get_edit_user_link()); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Update Profile', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Keep your profile information up to date', 'quicklearn'); ?></p>
        </a>
        
        <?php
        $certificates_query = new WP_Query(array(
            'post_type' => 'qlcm_certificate',
            'posts_per_page' => 1,
            'author' => $user_id,
            'post_status' => 'publish'
        ));
        if ($certificates_query->have_posts()) :
        ?>
        <a href="#certificates" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('View Certificates', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Download and share your achievements', 'quicklearn'); ?></p>
        </a>
        <?php endif; wp_reset_postdata(); ?>
    </div>
</section>

<!-- Continue Learning Section -->
<section class="continue-learning-section">
    <h2 class="section-title"><?php esc_html_e('Continue Learning', 'quicklearn'); ?></h2>
    
    <?php
    // Get in-progress courses
    $in_progress_query = new WP_Query(array(
        'post_type' => 'quick_course',
        'posts_per_page' => 6,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_qlcm_enrolled_users',
                'value' => serialize($user_id),
                'compare' => 'LIKE'
            ),
            array(
                'key' => '_qlcm_course_progress_' . $user_id,
                'value' => 100,
                'compare' => '<',
                'type' => 'NUMERIC'
            )
        )
    ));
    
    if ($in_progress_query->have_posts()) :
    ?>
        <div class="course-grid">
            <?php while ($in_progress_query->have_posts()) : $in_progress_query->the_post(); ?>
                <?php
                $course_id = get_the_ID();
                $progress = get_user_meta($user_id, '_qlcm_course_progress_' . $course_id, true);
                $progress = floatval($progress);
                ?>
                <div class="course-card student-course">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="course-image">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                            <div class="progress-overlay">
                                <div class="progress-circle">
                                    <svg width="60" height="60" viewBox="0 0 60 60">
                                        <circle cx="30" cy="30" r="25" fill="none" stroke="#e9ecef" stroke-width="4"/>
                                        <circle cx="30" cy="30" r="25" fill="none" stroke="#28a745" stroke-width="4" 
                                                stroke-dasharray="<?php echo esc_attr(157.08 * $progress / 100); ?> 157.08" 
                                                stroke-dashoffset="0" transform="rotate(-90 30 30)"/>
                                    </svg>
                                    <span class="progress-text"><?php echo esc_html(round($progress)); ?>%</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="course-content">
                        <h3 class="course-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        
                        <div class="course-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                            </div>
                            <span class="progress-label"><?php echo esc_html(round($progress)); ?>% <?php esc_html_e('Complete', 'quicklearn'); ?></span>
                        </div>
                        
                        <div class="course-actions">
                            <a href="<?php the_permalink(); ?>" class="btn btn--primary">
                                <?php esc_html_e('Continue Learning', 'quicklearn'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <?php if ($in_progress_query->found_posts > 6) : ?>
            <div class="section-footer">
                <a href="<?php echo esc_url(add_query_arg('filter', 'in-progress', get_post_type_archive_link('quick_course'))); ?>" class="btn btn--secondary">
                    <?php esc_html_e('View All In-Progress Courses', 'quicklearn'); ?>
                </a>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-book-alt"></span>
            </div>
            <h3 class="empty-title"><?php esc_html_e('No courses in progress', 'quicklearn'); ?></h3>
            <p class="empty-description"><?php esc_html_e('Start learning by enrolling in a course!', 'quicklearn'); ?></p>
            <a href="<?php echo esc_url(get_post_type_archive_link('quick_course')); ?>" class="btn btn--primary">
                <?php esc_html_e('Browse Courses', 'quicklearn'); ?>
            </a>
        </div>
    <?php endif; wp_reset_postdata(); ?>
</section>

<!-- Recent Achievements -->
<?php
$recent_certificates = new WP_Query(array(
    'post_type' => 'qlcm_certificate',
    'posts_per_page' => 3,
    'author' => $user_id,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
));

if ($recent_certificates->have_posts()) :
?>
<section class="achievements-section" id="certificates">
    <h2 class="section-title"><?php esc_html_e('Recent Achievements', 'quicklearn'); ?></h2>
    
    <div class="certificates-grid">
        <?php while ($recent_certificates->have_posts()) : $recent_certificates->the_post(); ?>
            <div class="certificate-card">
                <div class="certificate-icon">
                    <span class="dashicons dashicons-awards"></span>
                </div>
                <div class="certificate-content">
                    <h3 class="certificate-title"><?php the_title(); ?></h3>
                    <p class="certificate-date"><?php echo get_the_date(); ?></p>
                    <div class="certificate-actions">
                        <a href="<?php the_permalink(); ?>" class="btn btn--sm btn--secondary">
                            <?php esc_html_e('View Certificate', 'quicklearn'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    
    <div class="section-footer">
        <a href="<?php echo esc_url(add_query_arg('post_type', 'qlcm_certificate', admin_url('edit.php'))); ?>" class="btn btn--secondary">
            <?php esc_html_e('View All Certificates', 'quicklearn'); ?>
        </a>
    </div>
</section>
<?php endif; wp_reset_postdata(); ?>

<!-- Recommended Courses -->
<section class="recommendations-section">
    <h2 class="section-title"><?php esc_html_e('Recommended for You', 'quicklearn'); ?></h2>
    
    <?php
    // Get courses user is not enrolled in
    $recommended_courses = new WP_Query(array(
        'post_type' => 'quick_course',
        'posts_per_page' => 3,
        'meta_query' => array(
            array(
                'key' => '_qlcm_enrolled_users',
                'value' => serialize($user_id),
                'compare' => 'NOT LIKE'
            )
        ),
        'orderby' => 'rand'
    ));
    
    if ($recommended_courses->have_posts()) :
    ?>
        <div class="course-grid">
            <?php while ($recommended_courses->have_posts()) : $recommended_courses->the_post(); ?>
                <?php get_template_part('template-parts/course', 'card'); ?>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-lightbulb"></span>
            </div>
            <h3 class="empty-title"><?php esc_html_e('Great job!', 'quicklearn'); ?></h3>
            <p class="empty-description"><?php esc_html_e('You\'ve enrolled in all available courses. Check back for new content!', 'quicklearn'); ?></p>
        </div>
    <?php endif; wp_reset_postdata(); ?>
</section>