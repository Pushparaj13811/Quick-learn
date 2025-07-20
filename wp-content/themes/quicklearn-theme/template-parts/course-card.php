<?php
/**
 * Template part for displaying course cards
 *
 * @package QuickLearn
 */

// Get course data
$course_id = get_the_ID();
$course_categories = get_the_terms($course_id, 'course_category');
$course_rating = function_exists('qlcm_get_course_rating') ? qlcm_get_course_rating($course_id) : null;
$enrollment_count = function_exists('qlcm_get_course_enrollment_count') ? qlcm_get_course_enrollment_count($course_id) : 0;
$user_enrolled = is_user_logged_in() && function_exists('qlcm_is_user_enrolled') ? qlcm_is_user_enrolled(get_current_user_id(), $course_id) : false;
$user_progress = $user_enrolled && function_exists('qlcm_get_user_course_progress') ? qlcm_get_user_course_progress(get_current_user_id(), $course_id) : 0;
?>

<article id="course-<?php echo esc_attr($course_id); ?>" <?php post_class('course-card'); ?>>
    <div class="course-card-inner">
        
        <?php if (has_post_thumbnail()): ?>
        <div class="course-image">
            <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('View course: %s', 'quicklearn'), get_the_title())); ?>">
                <?php 
                // Use responsive image with lazy loading
                the_post_thumbnail('course-thumbnail-desktop', array(
                    'class' => 'lazy-load',
                    'alt' => get_the_title(),
                    'loading' => 'lazy',
                    'decoding' => 'async'
                )); 
                ?>
            </a>
            
            <?php if ($user_enrolled): ?>
            <div class="course-progress-overlay">
                <div class="progress-circle" data-progress="<?php echo esc_attr($user_progress); ?>">
                    <span class="progress-text"><?php echo esc_html($user_progress); ?>%</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="course-content">
            <header class="course-header">
                <?php if ($course_categories && !is_wp_error($course_categories)): ?>
                <div class="course-categories">
                    <?php foreach ($course_categories as $category): ?>
                    <a href="<?php echo esc_url(get_term_link($category)); ?>" 
                       class="course-category-tag"
                       rel="tag">
                        <?php echo esc_html($category->name); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <h2 class="course-title">
                    <a href="<?php the_permalink(); ?>" rel="bookmark">
                        <?php the_title(); ?>
                    </a>
                </h2>
                
                <?php if ($course_rating && $course_rating['count'] > 0): ?>
                <div class="course-rating">
                    <div class="stars" aria-label="<?php echo esc_attr(sprintf(__('Rating: %s out of 5 stars', 'quicklearn'), number_format($course_rating['average'], 1))); ?>">
                        <?php
                        $full_stars = floor($course_rating['average']);
                        $half_star = ($course_rating['average'] - $full_stars) >= 0.5;
                        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                        
                        // Full stars
                        for ($i = 0; $i < $full_stars; $i++) {
                            echo '<span class="star star-full" aria-hidden="true">★</span>';
                        }
                        
                        // Half star
                        if ($half_star) {
                            echo '<span class="star star-half" aria-hidden="true">☆</span>';
                        }
                        
                        // Empty stars
                        for ($i = 0; $i < $empty_stars; $i++) {
                            echo '<span class="star star-empty" aria-hidden="true">☆</span>';
                        }
                        ?>
                    </div>
                    <span class="rating-text">
                        <?php echo esc_html(number_format($course_rating['average'], 1)); ?>
                        <span class="rating-count">(<?php echo esc_html($course_rating['count']); ?>)</span>
                    </span>
                </div>
                <?php endif; ?>
            </header>
            
            <?php if (quicklearn_show_course_excerpts()): ?>
            <div class="course-excerpt">
                <?php the_excerpt(); ?>
            </div>
            <?php endif; ?>
            
            <footer class="course-meta">
                <div class="course-stats">
                    <?php if ($enrollment_count > 0): ?>
                    <span class="enrollment-count">
                        <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                        <?php echo esc_html(sprintf(_n('%d student', '%d students', $enrollment_count, 'quicklearn'), $enrollment_count)); ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="course-date">
                        <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                            <?php echo esc_html(get_the_date()); ?>
                        </time>
                    </span>
                </div>
                
                <div class="course-actions">
                    <?php if ($user_enrolled): ?>
                        <?php if ($user_progress >= 100): ?>
                        <a href="<?php the_permalink(); ?>" class="course-action-btn completed">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <?php _e('Completed', 'quicklearn'); ?>
                        </a>
                        <?php else: ?>
                        <a href="<?php the_permalink(); ?>" class="course-action-btn continue">
                            <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                            <?php _e('Continue', 'quicklearn'); ?>
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="<?php the_permalink(); ?>" class="course-action-btn enroll">
                        <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                        <?php _e('View Course', 'quicklearn'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </footer>
            
            <?php
            // Allow plugins to add additional content
            do_action('quicklearn_course_card_meta', $course_id);
            ?>
        </div>
    </div>
</article>