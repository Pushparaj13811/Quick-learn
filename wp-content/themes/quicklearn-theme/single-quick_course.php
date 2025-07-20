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
                
                <?php do_action('quicklearn_after_course_content'); ?>

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