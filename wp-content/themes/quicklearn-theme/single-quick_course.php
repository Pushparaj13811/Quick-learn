<?php
/**
 * Template for displaying single course
 * 
 * @package QuickLearn
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php while (have_posts()) : the_post(); ?>
            
            <article id="post-<?php the_ID(); ?>" <?php post_class('single-course'); ?>>
                
                <!-- Course Header -->
                <header class="course-header">
                    <div class="course-breadcrumb">
                        <nav aria-label="<?php esc_attr_e('Breadcrumb', 'quicklearn'); ?>">
                            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'quicklearn'); ?></a>
                            <span class="breadcrumb-separator"> / </span>
                            <a href="<?php echo esc_url(home_url('/courses')); ?>"><?php esc_html_e('Courses', 'quicklearn'); ?></a>
                            <span class="breadcrumb-separator"> / </span>
                            <span class="current-page"><?php the_title(); ?></span>
                        </nav>
                    </div>

                    <?php
                    $course_categories = get_the_terms(get_the_ID(), 'course_category');
                    if ($course_categories && !is_wp_error($course_categories)) :
                    ?>
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

                    <h1 class="course-title"><?php the_title(); ?></h1>

                    <?php
                    // Display course rating if available
                    if (function_exists('qlcm_get_course_rating')) :
                        $rating_data = qlcm_get_course_rating(get_the_ID());
                        if ($rating_data && $rating_data['count'] > 0) :
                    ?>
                        <div class="course-rating-summary">
                            <div class="rating-stars" aria-label="<?php echo esc_attr(sprintf(__('Rated %s out of 5', 'quicklearn'), $rating_data['average'])); ?>">
                                <?php echo qlcm_get_star_rating_html($rating_data['average']); ?>
                            </div>
                            <span class="rating-average"><?php echo esc_html(number_format($rating_data['average'], 1)); ?></span>
                            <span class="rating-count">(<?php echo esc_html(sprintf(_n('%s review', '%s reviews', $rating_data['count'], 'quicklearn'), $rating_data['count'])); ?>)</span>
                        </div>
                    <?php
                        endif;
                    endif;
                    ?>

                    <div class="course-meta">
                        <span class="course-date">
                            <?php esc_html_e('Published:', 'quicklearn'); ?>
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo esc_html(get_the_date()); ?>
                            </time>
                        </span>
                        
                        <?php if (get_the_modified_date() !== get_the_date()) : ?>
                            <span class="course-modified">
                                <?php esc_html_e('Updated:', 'quicklearn'); ?>
                                <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>">
                                    <?php echo esc_html(get_the_modified_date()); ?>
                                </time>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Course Featured Image -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="course-featured-image">
                        <?php 
                        // Use responsive featured image sizes
                        the_post_thumbnail('course-featured-desktop', array(
                            'alt' => the_title_attribute(array('echo' => false)),
                            'loading' => 'eager', // Featured images should load immediately
                            'decoding' => 'async',
                            'sizes' => '(max-width: 768px) 600px, 1200px',
                            'srcset' => wp_get_attachment_image_srcset(get_post_thumbnail_id(), 'course-featured-desktop')
                        )); ?>
                    </div>
                <?php endif; ?>

                <!-- Course Sidebar with Enrollment and Progress -->
                <div class="course-main-content">
                    <aside class="course-sidebar">
                        <?php
                        // Enrollment Section
                        if (function_exists('qlcm_render_enrollment_button')) :
                            echo '<div class="course-enrollment-box">';
                            qlcm_render_enrollment_button(get_the_ID());
                            
                            // Show progress if enrolled
                            if (is_user_logged_in() && function_exists('qlcm_get_user_course_progress')) :
                                $progress = qlcm_get_user_course_progress(get_current_user_id(), get_the_ID());
                                if ($progress !== false) :
                        ?>
                                    <div class="course-progress-info">
                                        <h4><?php esc_html_e('Your Progress', 'quicklearn'); ?></h4>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
                                        </div>
                                        <span class="progress-text"><?php echo esc_html($progress); ?>% <?php esc_html_e('Complete', 'quicklearn'); ?></span>
                                    </div>
                        <?php
                                endif;
                            endif;
                            echo '</div>';
                        endif;
                        
                        // Course Information Box
                        ?>
                        <div class="course-info-box">
                            <h3><?php esc_html_e('Course Information', 'quicklearn'); ?></h3>
                            <ul class="course-details">
                                <?php
                                // Display enrollment count
                                if (function_exists('qlcm_get_course_enrollment_count')) :
                                    $enrollment_count = qlcm_get_course_enrollment_count(get_the_ID());
                                ?>
                                    <li>
                                        <span class="detail-label"><?php esc_html_e('Enrolled Students:', 'quicklearn'); ?></span>
                                        <span class="detail-value"><?php echo esc_html($enrollment_count); ?></span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                // Display module count
                                if (function_exists('qlcm_get_course_modules')) :
                                    $modules = qlcm_get_course_modules(get_the_ID());
                                    $module_count = is_array($modules) ? count($modules) : 0;
                                ?>
                                    <li>
                                        <span class="detail-label"><?php esc_html_e('Modules:', 'quicklearn'); ?></span>
                                        <span class="detail-value"><?php echo esc_html($module_count); ?></span>
                                    </li>
                                <?php endif; ?>
                                
                                <li>
                                    <span class="detail-label"><?php esc_html_e('Last Updated:', 'quicklearn'); ?></span>
                                    <span class="detail-value"><?php echo esc_html(get_the_modified_date()); ?></span>
                                </li>
                                
                                <?php
                                // Certificate availability
                                if (function_exists('qlcm_course_has_certificate')) :
                                    if (qlcm_course_has_certificate(get_the_ID())) :
                                ?>
                                    <li>
                                        <span class="detail-label"><?php esc_html_e('Certificate:', 'quicklearn'); ?></span>
                                        <span class="detail-value"><?php esc_html_e('Available on completion', 'quicklearn'); ?></span>
                                    </li>
                                <?php
                                    endif;
                                endif;
                                ?>
                            </ul>
                        </div>
                        
                        <?php
                        // Course instructor info if available
                        $author_id = get_the_author_meta('ID');
                        ?>
                        <div class="course-instructor-box">
                            <h3><?php esc_html_e('Instructor', 'quicklearn'); ?></h3>
                            <div class="instructor-info">
                                <?php echo get_avatar($author_id, 80); ?>
                                <div class="instructor-details">
                                    <h4><?php the_author(); ?></h4>
                                    <?php if (get_the_author_meta('description')) : ?>
                                        <p><?php echo esc_html(get_the_author_meta('description')); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <div class="course-content-wrapper">
                        <?php do_action('quicklearn_before_course_content'); ?>
                
                        <!-- Course Content -->
                        <div class="course-content">
                            <?php
                            the_content();
                            
                            wp_link_pages(array(
                                'before' => '<div class="page-links">' . esc_html__('Pages:', 'quicklearn'),
                                'after'  => '</div>',
                            ));
                            ?>
                        </div>
                        
                        <?php
                        // Display course modules if available
                        if (function_exists('qlcm_render_course_modules')) :
                        ?>
                            <div class="course-modules-section">
                                <h2><?php esc_html_e('Course Curriculum', 'quicklearn'); ?></h2>
                                <?php qlcm_render_course_modules(get_the_ID()); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php do_action('quicklearn_after_course_content'); ?>
                        
                        <?php
                        // Course Reviews Section
                        if (function_exists('qlcm_render_course_reviews')) :
                        ?>
                            <section class="course-reviews-section">
                                <h2><?php esc_html_e('Student Reviews', 'quicklearn'); ?></h2>
                                <?php qlcm_render_course_reviews(get_the_ID()); ?>
                                
                                <?php
                                // Review form for enrolled users
                                if (is_user_logged_in() && function_exists('qlcm_can_user_review_course')) :
                                    if (qlcm_can_user_review_course(get_current_user_id(), get_the_ID())) :
                                        qlcm_render_review_form(get_the_ID());
                                    endif;
                                endif;
                                ?>
                            </section>
                        <?php endif; ?>

                        <!-- Course Discussion Forum -->
                        <section class="course-forum-section">
                            <?php echo do_shortcode('[course_forum course_id="' . get_the_ID() . '"]'); ?>
                        </section>

                        <!-- Course Q&A Section -->
                        <section class="course-qa-section">
                            <?php echo do_shortcode('[course_qa course_id="' . get_the_ID() . '"]'); ?>
                        </section>
                    </div><!-- .course-content-wrapper -->
                </div><!-- .course-main-content -->

                <!-- Course Footer -->
                <footer class="course-footer">
                    <?php if ($course_categories && !is_wp_error($course_categories)) : ?>
                        <div class="course-tags">
                            <h3><?php esc_html_e('Course Categories:', 'quicklearn'); ?></h3>
                            <div class="category-list">
                                <?php foreach ($course_categories as $category) : ?>
                                    <a href="<?php echo esc_url(get_term_link($category)); ?>" class="category-tag">
                                        <?php echo esc_html($category->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="course-navigation">
                        <div class="nav-links">
                            <?php
                            $prev_post = get_previous_post(true, '', 'course_category');
                            $next_post = get_next_post(true, '', 'course_category');
                            
                            if ($prev_post) :
                            ?>
                                <div class="nav-previous">
                                    <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" rel="prev">
                                        <span class="nav-subtitle"><?php esc_html_e('Previous Course:', 'quicklearn'); ?></span>
                                        <span class="nav-title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></span>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($next_post) : ?>
                                <div class="nav-next">
                                    <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" rel="next">
                                        <span class="nav-subtitle"><?php esc_html_e('Next Course:', 'quicklearn'); ?></span>
                                        <span class="nav-title"><?php echo esc_html(get_the_title($next_post->ID)); ?></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="back-to-courses">
                        <a href="<?php echo esc_url(home_url('/courses')); ?>" class="back-link">
                            ← <?php esc_html_e('Back to All Courses', 'quicklearn'); ?>
                        </a>
                    </div>
                </footer>

            </article>

            <?php
            // Related Courses Section
            if ($course_categories && !is_wp_error($course_categories)) :
                $category_ids = wp_list_pluck($course_categories, 'term_id');
                
                $related_courses = new WP_Query(array(
                    'post_type' => 'quick_course',
                    'post_status' => 'publish',
                    'posts_per_page' => 3,
                    'post__not_in' => array(get_the_ID()),
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'course_category',
                            'field'    => 'term_id',
                            'terms'    => $category_ids,
                        ),
                    ),
                    'orderby' => 'rand',
                ));

                if ($related_courses->have_posts()) :
            ?>
                    <section class="related-courses">
                        <h2><?php esc_html_e('Related Courses', 'quicklearn'); ?></h2>
                        <div class="related-courses-grid">
                            <?php
                            while ($related_courses->have_posts()) : $related_courses->the_post();
                                get_template_part('template-parts/course', 'card');
                            endwhile;
                            wp_reset_postdata();
                            ?>
                        </div>
                    </section>
            <?php
                endif;
            endif;
            ?>

        <?php endwhile; ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>