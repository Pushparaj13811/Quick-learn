<?php
/**
 * Template part for displaying course cards
 * 
 * @package QuickLearn
 */

// Ensure we're in the loop
if (!in_the_loop()) {
    return;
}

$course_id = get_the_ID();
$course_categories = get_the_terms($course_id, 'course_category');
$course_style = quicklearn_get_course_card_style();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('course-card course-card-' . esc_attr($course_style)); ?>>
    <div class="course-card-inner">
        
        <!-- Course Thumbnail -->
        <div class="course-thumbnail">
            <?php if (has_post_thumbnail()) : ?>
                <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('View course: %s', 'quicklearn'), get_the_title())); ?>">
                    <?php
                    the_post_thumbnail('course-thumbnail-desktop', array(
                        'alt' => the_title_attribute(array('echo' => false)),
                        'loading' => 'lazy',
                        'decoding' => 'async',
                    ));
                    ?>
                </a>
            <?php else : ?>
                <div class="course-thumbnail no-image">
                    <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('View course: %s', 'quicklearn'), get_the_title())); ?>">
                        <div class="placeholder-content">
                            <span class="placeholder-icon">📚</span>
                            <span class="placeholder-text"><?php esc_html_e('Course Image', 'quicklearn'); ?></span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Course Status Badges -->
            <div class="course-badges">
                <?php
                // Featured course badge
                if (get_post_meta($course_id, '_featured_course', true)) :
                ?>
                    <span class="course-badge featured-badge"><?php esc_html_e('Featured', 'quicklearn'); ?></span>
                <?php endif; ?>
                
                <?php
                // New course badge (courses published in last 30 days)
                $post_date = get_the_date('U');
                $thirty_days_ago = strtotime('-30 days');
                if ($post_date > $thirty_days_ago) :
                ?>
                    <span class="course-badge new-badge"><?php esc_html_e('New', 'quicklearn'); ?></span>
                <?php endif; ?>
                
                <?php
                // Certificate available badge
                if (function_exists('qlcm_course_has_certificate') && qlcm_course_has_certificate($course_id)) :
                ?>
                    <span class="course-badge certificate-badge" title="<?php esc_attr_e('Certificate available on completion', 'quicklearn'); ?>">
                        🏆
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Course Progress (for enrolled users) -->
            <?php if (is_user_logged_in() && function_exists('qlcm_get_user_course_progress')) :
                $progress = qlcm_get_user_course_progress(get_current_user_id(), $course_id);
                if ($progress !== false) :
            ?>
                <div class="course-progress-overlay">
                    <div class="progress-bar-mini">
                        <div class="progress-fill-mini" style="width: <?php echo esc_attr($progress); ?>%;"></div>
                    </div>
                    <span class="progress-text-mini"><?php echo esc_html($progress); ?>%</span>
                </div>
            <?php endif; endif; ?>
        </div>

        <!-- Course Content -->
        <div class="course-content">
            <header class="course-header">
                <!-- Course Categories -->
                <?php if ($course_categories && !is_wp_error($course_categories)) : ?>
                    <div class="course-categories">
                        <?php foreach ($course_categories as $index => $category) : ?>
                            <a href="<?php echo esc_url(get_term_link($category)); ?>" class="course-category">
                                <?php echo esc_html($category->name); ?>
                            </a>
                            <?php if ($index < count($course_categories) - 1) : ?>
                                <span class="category-separator">, </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Course Title -->
                <h3 class="course-title">
                    <a href="<?php the_permalink(); ?>" rel="bookmark">
                        <?php the_title(); ?>
                    </a>
                </h3>

                <!-- Course Rating -->
                <?php if (function_exists('qlcm_get_course_rating')) :
                    $rating_data = qlcm_get_course_rating($course_id);
                    if ($rating_data && $rating_data['count'] > 0) :
                ?>
                    <div class="course-rating">
                        <div class="rating-stars" aria-label="<?php echo esc_attr(sprintf(__('Rated %s out of 5', 'quicklearn'), $rating_data['average'])); ?>">
                            <?php
                            // Display star rating
                            $full_stars = floor($rating_data['average']);
                            $half_star = ($rating_data['average'] - $full_stars) >= 0.5;
                            $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                            
                            for ($i = 0; $i < $full_stars; $i++) {
                                echo '<span class="star full">★</span>';
                            }
                            if ($half_star) {
                                echo '<span class="star half">☆</span>';
                            }
                            for ($i = 0; $i < $empty_stars; $i++) {
                                echo '<span class="star empty">☆</span>';
                            }
                            ?>
                        </div>
                        <span class="rating-count">(<?php echo esc_html($rating_data['count']); ?>)</span>
                    </div>
                <?php endif; endif; ?>
            </header>

            <!-- Course Excerpt -->
            <?php if (quicklearn_show_course_excerpts() || $course_style === 'detailed') : ?>
                <div class="course-excerpt">
                    <?php
                    if (has_excerpt()) {
                        the_excerpt();
                    } else {
                        echo '<p>' . esc_html(wp_trim_words(get_the_content(), 20, '...')) . '</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Course Meta Information -->
            <div class="course-meta-info">
                <?php if ($course_style === 'detailed') : ?>
                    <!-- Instructor -->
                    <div class="meta-item instructor-meta">
                        <span class="meta-icon">👨‍🏫</span>
                        <span class="meta-label"><?php esc_html_e('Instructor:', 'quicklearn'); ?></span>
                        <span class="meta-value"><?php the_author(); ?></span>
                    </div>
                    
                    <!-- Module Count -->
                    <?php if (function_exists('qlcm_get_course_modules')) :
                        $modules = qlcm_get_course_modules($course_id);
                        $module_count = is_array($modules) ? count($modules) : 0;
                        if ($module_count > 0) :
                    ?>
                        <div class="meta-item modules-meta">
                            <span class="meta-icon">📚</span>
                            <span class="meta-label"><?php esc_html_e('Modules:', 'quicklearn'); ?></span>
                            <span class="meta-value"><?php echo esc_html($module_count); ?></span>
                        </div>
                    <?php endif; endif; ?>
                    
                    <!-- Enrollment Count -->
                    <?php if (function_exists('qlcm_get_course_enrollment_count')) :
                        $enrollment_count = qlcm_get_course_enrollment_count($course_id);
                        if ($enrollment_count > 0) :
                    ?>
                        <div class="meta-item enrollment-meta">
                            <span class="meta-icon">👥</span>
                            <span class="meta-label"><?php esc_html_e('Students:', 'quicklearn'); ?></span>
                            <span class="meta-value"><?php echo esc_html(number_format_i18n($enrollment_count)); ?></span>
                        </div>
                    <?php endif; endif; ?>
                <?php endif; ?>
                
                <!-- Last Updated -->
                <div class="meta-item date-meta">
                    <span class="meta-icon">📅</span>
                    <span class="meta-label"><?php esc_html_e('Updated:', 'quicklearn'); ?></span>
                    <span class="meta-value">
                        <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>">
                            <?php echo esc_html(get_the_modified_date()); ?>
                        </time>
                    </span>
                </div>
            </div>

            <!-- Course Footer -->
            <footer class="course-footer">
                <div class="course-actions">
                    <!-- Enrollment Button or Course Link -->
                    <?php if (function_exists('qlcm_is_user_enrolled') && is_user_logged_in()) :
                        if (qlcm_is_user_enrolled(get_current_user_id(), $course_id)) :
                    ?>
                            <a href="<?php the_permalink(); ?>" class="course-link enrolled">
                                <?php esc_html_e('Continue Learning', 'quicklearn'); ?>
                            </a>
                    <?php else : ?>
                            <a href="<?php the_permalink(); ?>" class="course-link enroll">
                                <?php esc_html_e('Enroll Now', 'quicklearn'); ?>
                            </a>
                    <?php endif; else : ?>
                        <a href="<?php the_permalink(); ?>" class="course-link">
                            <?php esc_html_e('View Course', 'quicklearn'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Wishlist Button (if plugin provides this functionality) -->
                    <?php if (function_exists('qlcm_render_wishlist_button')) : ?>
                        <div class="wishlist-action">
                            <?php qlcm_render_wishlist_button($course_id); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Course Card Meta Hook for Plugin Extensions -->
                <?php do_action('quicklearn_course_card_meta', $course_id); ?>
            </footer>
        </div>
    </div>
</article>

<style>
/* Course Card Styles */
.course-badges {
    position: absolute;
    top: 1rem;
    left: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 2;
}

.course-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.featured-badge {
    background: #ff6b35;
    color: #fff;
}

.new-badge {
    background: #28a745;
    color: #fff;
}

.certificate-badge {
    background: rgba(255, 255, 255, 0.9);
    color: #333;
    font-size: 1rem;
    padding: 0.25rem 0.5rem;
}

.course-progress-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 0.5rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar-mini {
    flex: 1;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: #28a745;
    transition: width 0.3s ease;
}

.progress-text-mini {
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 30px;
}

.placeholder-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
    background: #f8f9fa;
}

.placeholder-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.placeholder-text {
    font-size: 0.9rem;
    opacity: 0.7;
}

.course-rating {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
}

.rating-stars {
    display: flex;
    gap: 0.1rem;
}

.star {
    color: #ffc107;
    font-size: 1rem;
}

.star.empty {
    color: #e9ecef;
}

.star.half {
    background: linear-gradient(90deg, #ffc107 50%, #e9ecef 50%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.rating-count {
    font-size: 0.85rem;
    color: #6c757d;
}

.course-meta-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 1rem 0;
    font-size: 0.85rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.meta-icon {
    font-size: 1rem;
    min-width: 1.2rem;
    text-align: center;
}

.meta-label {
    color: #6c757d;
    font-weight: 500;
}

.meta-value {
    color: #2c3e50;
    font-weight: 600;
}

.course-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.course-link.enrolled {
    background: #28a745;
}

.course-link.enrolled:hover {
    background: #218838;
}

.course-link.enroll {
    background: #007cba;
}

.course-link.enroll:hover {
    background: #005a87;
}

.wishlist-action {
    display: flex;
    align-items: center;
}

/* Compact card style */
.course-card-compact .course-excerpt {
    display: none;
}

.course-card-compact .course-meta-info {
    display: none;
}

.course-card-compact .course-content {
    padding: 1rem;
}

/* Detailed card style */
.course-card-detailed .course-content {
    padding: 2rem;
}

.course-card-detailed .course-meta-info {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
    margin-top: 1rem;
}

/* List view adjustments */
.courses-grid.list-view .course-card {
    display: flex;
    max-width: none;
    margin-bottom: 1.5rem;
}

.courses-grid.list-view .course-thumbnail {
    width: 300px;
    min-width: 300px;
    aspect-ratio: 16/10;
}

.courses-grid.list-view .course-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.courses-grid.list-view .course-meta-info {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 1rem;
}

.courses-grid.list-view .meta-item {
    flex: 0 0 auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .course-badges {
        top: 0.5rem;
        left: 0.5rem;
    }
    
    .course-badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
    }
    
    .meta-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .meta-icon {
        align-self: flex-start;
    }
    
    .courses-grid.list-view .course-card {
        flex-direction: column;
    }
    
    .courses-grid.list-view .course-thumbnail {
        width: 100%;
    }
    
    .courses-grid.list-view .course-meta-info {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .course-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .course-link {
        width: 100%;
        text-align: center;
    }
}
</style>