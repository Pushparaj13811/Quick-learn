<?php
/**
 * Template for displaying my courses page
 * 
 * @package QuickLearn
 */

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <header class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
            <p class="page-description"><?php _e('Manage your enrolled courses and track your progress.', 'quicklearn'); ?></p>
        </header>

        <div class="my-courses-content">
            <?php
            $current_user = wp_get_current_user();
            $user_role = $current_user->roles[0] ?? '';
            
            // Display content based on user role
            if (in_array('qlcm_instructor', $current_user->roles)) {
                // Instructor view - show courses they teach
                ?>
                <div class="instructor-courses">
                    <h2><?php _e('Courses You Teach', 'quicklearn'); ?></h2>
                    
                    <div class="course-actions">
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=quick_course')); ?>" class="btn btn--primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Create New Course', 'quicklearn'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course')); ?>" class="btn btn--secondary">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Manage All Courses', 'quicklearn'); ?>
                        </a>
                    </div>
                    
                    <?php
                    $instructor_courses = new WP_Query(array(
                        'post_type' => 'quick_course',
                        'author' => $current_user->ID,
                        'posts_per_page' => -1,
                        'post_status' => array('publish', 'draft', 'pending')
                    ));
                    
                    if ($instructor_courses->have_posts()) :
                        ?>
                        <div class="courses-grid grid grid--courses">
                            <?php while ($instructor_courses->have_posts()) : $instructor_courses->the_post(); ?>
                                <article class="course-card card">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <div class="card__image-container">
                                            <?php the_post_thumbnail('medium', array('class' => 'card__image')); ?>
                                            <div class="card__badge card__badge--<?php echo get_post_status() === 'publish' ? 'success' : (get_post_status() === 'pending' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst(get_post_status()); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card__content">
                                        <h3 class="card__title">
                                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                        </h3>
                                        
                                        <div class="card__meta">
                                            <span class="card__meta-item">
                                                <span class="dashicons dashicons-admin-users"></span>
                                                <?php echo esc_html(get_post_meta(get_the_ID(), 'course_students', true) ?: '0'); ?> <?php _e('students', 'quicklearn'); ?>
                                            </span>
                                            <span class="card__meta-item">
                                                <span class="dashicons dashicons-calendar-alt"></span>
                                                <?php echo get_the_date(); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="card__actions">
                                            <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="btn btn--secondary btn--sm">
                                                <?php _e('Edit Course', 'quicklearn'); ?>
                                            </a>
                                            <a href="<?php the_permalink(); ?>" class="btn btn--primary btn--sm">
                                                <?php _e('View Course', 'quicklearn'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                        <?php
                        wp_reset_postdata();
                    else :
                        ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <span class="dashicons dashicons-welcome-add-page"></span>
                            </div>
                            <h3 class="empty-state__title"><?php _e('No courses yet', 'quicklearn'); ?></h3>
                            <p class="empty-state__message"><?php _e('Start sharing your knowledge by creating your first course.', 'quicklearn'); ?></p>
                            <div class="empty-state__actions">
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=quick_course')); ?>" class="btn btn--primary">
                                    <?php _e('Create Your First Course', 'quicklearn'); ?>
                                </a>
                            </div>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
                <?php
            } else {
                // Student view - show enrolled courses
                ?>
                <div class="student-courses">
                    <div class="course-tabs">
                        <button class="tab-button active" data-tab="enrolled"><?php _e('Enrolled Courses', 'quicklearn'); ?></button>
                        <button class="tab-button" data-tab="completed"><?php _e('Completed Courses', 'quicklearn'); ?></button>
                        <button class="tab-button" data-tab="wishlist"><?php _e('Wishlist', 'quicklearn'); ?></button>
                    </div>
                    
                    <div class="tab-content active" id="enrolled">
                        <h2><?php _e('Your Enrolled Courses', 'quicklearn'); ?></h2>
                        
                        <?php
                        // Get enrolled courses (this would typically come from a custom table or user meta)
                        $enrolled_courses = get_user_meta($current_user->ID, 'enrolled_courses', true) ?: array();
                        
                        if (!empty($enrolled_courses)) :
                            ?>
                            <div class="courses-grid grid grid--courses">
                                <?php foreach ($enrolled_courses as $course_id) :
                                    $course = get_post($course_id);
                                    if ($course && $course->post_status === 'publish') :
                                        setup_postdata($course);
                                        ?>
                                        <article class="course-card card student-course">
                                            <?php if (has_post_thumbnail($course_id)) : ?>
                                                <div class="card__image-container">
                                                    <?php echo get_the_post_thumbnail($course_id, 'medium', array('class' => 'card__image')); ?>
                                                    <div class="progress-overlay">
                                                        <div class="progress-circle progress-circle--sm">
                                                            <svg class="progress-circle__svg" viewBox="0 0 36 36">
                                                                <circle class="progress-circle__background" cx="18" cy="18" r="16"></circle>
                                                                <circle class="progress-circle__bar" cx="18" cy="18" r="16" 
                                                                        stroke-dasharray="75, 100" stroke-dashoffset="25"></circle>
                                                            </svg>
                                                            <div class="progress-circle__text">75%</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="card__content">
                                                <h3 class="card__title">
                                                    <a href="<?php echo get_permalink($course_id); ?>"><?php echo get_the_title($course_id); ?></a>
                                                </h3>
                                                
                                                <div class="course-progress">
                                                    <div class="progress">
                                                        <div class="progress__bar" style="width: 75%"></div>
                                                    </div>
                                                    <span class="progress-label">75% <?php _e('complete', 'quicklearn'); ?></span>
                                                </div>
                                                
                                                <div class="card__actions">
                                                    <a href="<?php echo get_permalink($course_id); ?>" class="btn btn--primary btn--sm">
                                                        <?php _e('Continue Learning', 'quicklearn'); ?>
                                                    </a>
                                                    <button class="btn btn--outline btn--sm" onclick="toggleBookmark(<?php echo $course_id; ?>)">
                                                        <span class="dashicons dashicons-heart"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </article>
                                        <?php
                                        wp_reset_postdata();
                                    endif;
                                endforeach; ?>
                            </div>
                            <?php
                        else :
                            ?>
                            <div class="empty-state">
                                <div class="empty-state__icon">
                                    <span class="dashicons dashicons-book"></span>
                                </div>
                                <h3 class="empty-state__title"><?php _e('No enrolled courses', 'quicklearn'); ?></h3>
                                <p class="empty-state__message"><?php _e('Start your learning journey by enrolling in a course.', 'quicklearn'); ?></p>
                                <div class="empty-state__actions">
                                    <a href="<?php echo esc_url(get_page_link(get_option('quicklearn_courses_page_id'))); ?>" class="btn btn--primary">
                                        <?php _e('Browse Courses', 'quicklearn'); ?>
                                    </a>
                                </div>
                            </div>
                            <?php
                        endif;
                        ?>
                    </div>
                    
                    <div class="tab-content" id="completed">
                        <h2><?php _e('Completed Courses', 'quicklearn'); ?></h2>
                        
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <span class="dashicons dashicons-awards"></span>
                            </div>
                            <h3 class="empty-state__title"><?php _e('No completed courses yet', 'quicklearn'); ?></h3>
                            <p class="empty-state__message"><?php _e('Complete your enrolled courses to earn certificates and see them here.', 'quicklearn'); ?></p>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="wishlist">
                        <h2><?php _e('Your Wishlist', 'quicklearn'); ?></h2>
                        
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <span class="dashicons dashicons-heart"></span>
                            </div>
                            <h3 class="empty-state__title"><?php _e('No courses in wishlist', 'quicklearn'); ?></h3>
                            <p class="empty-state__message"><?php _e('Save courses you\'re interested in to your wishlist for later.', 'quicklearn'); ?></p>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
</main>

<style>
.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--color-border-light);
}

.course-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.course-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--color-border);
}

.tab-button {
    padding: 1rem 2rem;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: var(--font-weight-medium);
    color: var(--color-text-muted);
    border-bottom: 2px solid transparent;
    transition: all var(--transition-base);
}

.tab-button.active,
.tab-button:hover {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.student-course {
    position: relative;
}

.progress-overlay {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(0, 0, 0, 0.8);
    border-radius: 50%;
    padding: 8px;
}

.course-progress {
    margin: 1rem 0;
}

.progress-label {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    margin-top: 0.5rem;
    display: block;
}

@media (max-width: 767px) {
    .course-actions {
        flex-direction: column;
    }
    
    .course-tabs {
        flex-direction: column;
        gap: 0;
    }
    
    .tab-button {
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 1px solid var(--color-border-light);
    }
    
    .tab-button.active {
        background: var(--color-primary-light);
    }
}
</style>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });
});

// Bookmark functionality
function toggleBookmark(courseId) {
    const formData = new FormData();
    formData.append('action', 'toggle_bookmark');
    formData.append('course_id', courseId);
    formData.append('nonce', '<?php echo wp_create_nonce('bookmark_nonce'); ?>');
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI to reflect bookmark status
            console.log('Bookmark toggled successfully');
        }
    })
    .catch(error => {
        console.error('Bookmark error:', error);
    });
}
</script>

<?php get_footer(); ?>