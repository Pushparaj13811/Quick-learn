<?php
/**
 * Template for displaying courses page
 * 
 * @package QuickLearn
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <?php while (have_posts()) : the_post(); ?>
            <div class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
                <?php if (get_the_content()) : ?>
                    <div class="page-description">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>

        <div class="courses-container">
            <!-- Course Filter Section -->
            <div class="course-filters">
                <div class="filter-container">
                    <label for="course-category-filter" class="filter-label">
                        <?php esc_html_e('Filter by Category:', 'quicklearn'); ?>
                    </label>
                    <select id="course-category-filter" class="course-filter-dropdown" aria-describedby="filter-description">
                        <option value=""><?php esc_html_e('All Categories', 'quicklearn'); ?></option>
                        <?php
                        $categories = get_terms(array(
                            'taxonomy' => 'course_category',
                            'hide_empty' => true,
                        ));
                        
                        // Check for selected category from URL parameter (Requirement 5.1)
                        $selected_category = '';
                        if (isset($_GET['category'])) {
                            $selected_category = sanitize_text_field($_GET['category']);
                            $selected_category = sanitize_title($selected_category);
                            // Additional validation - only allow valid slug format
                            $selected_category = preg_replace('/[^a-z0-9\-_]/', '', strtolower($selected_category));
                        }
                        
                        if (!is_wp_error($categories) && !empty($categories)) {
                            foreach ($categories as $category) {
                                printf(
                                    '<option value="%s"%s>%s (%d)</option>',
                                    esc_attr($category->slug),
                                    selected($selected_category, $category->slug, false),
                                    esc_html($category->name),
                                    $category->count
                                );
                            }
                        }
                        ?>
                    </select>
                    <small id="filter-description" class="filter-description">
                        <?php esc_html_e('Select a category to filter courses', 'quicklearn'); ?>
                    </small>
                </div>
                
                <div class="loading-indicator" style="display: none;" role="status" aria-live="polite">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="loading-text"><?php esc_html_e('Loading courses...', 'quicklearn'); ?></span>
                </div>
            </div>

            <!-- Courses Grid -->
            <div id="courses-grid" class="courses-grid">
                <?php
                // Query for courses
                $courses_query = new WP_Query(array(
                    'post_type' => 'quick_course',
                    'post_status' => 'publish',
                    'posts_per_page' => 12,
                    'orderby' => 'date',
                    'order' => 'DESC',
                ));

                if ($courses_query->have_posts()) :
                    while ($courses_query->have_posts()) : $courses_query->the_post();
                        get_template_part('template-parts/course', 'card');
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <div class="no-courses-found">
                        <h3><?php esc_html_e('No courses found', 'quicklearn'); ?></h3>
                        <p><?php esc_html_e('There are currently no courses available. Please check back later.', 'quicklearn'); ?></p>
                    </div>
                    <?php
                endif;
                ?>
            </div>

            <!-- Pagination -->
            <?php if ($courses_query->max_num_pages > 1) : ?>
                <div class="courses-pagination">
                    <?php
                    echo paginate_links(array(
                        'total' => $courses_query->max_num_pages,
                        'current' => max(1, get_query_var('paged')),
                        'format' => '?paged=%#%',
                        'show_all' => false,
                        'end_size' => 1,
                        'mid_size' => 2,
                        'prev_next' => true,
                        'prev_text' => __('&laquo; Previous', 'quicklearn'),
                        'next_text' => __('Next &raquo;', 'quicklearn'),
                        'type' => 'plain',
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>