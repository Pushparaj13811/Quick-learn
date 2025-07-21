<?php
/**
 * Template for displaying courses page
 * 
 * @package QuickLearn
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <header class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
            <?php if (get_the_content()) : ?>
                <div class="page-description">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>
        </header>

        <div class="courses-page-content">
            <!-- Course Filters -->
            <div class="course-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="course-search"><?php _e('Search Courses:', 'quicklearn'); ?></label>
                        <input type="text" id="course-search" placeholder="<?php _e('Search courses...', 'quicklearn'); ?>" class="form-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="course-category"><?php _e('Category:', 'quicklearn'); ?></label>
                        <select id="course-category" class="form-select">
                            <option value=""><?php _e('All Categories', 'quicklearn'); ?></option>
                            <?php
                            $categories = get_terms(array(
                                'taxonomy' => 'course_category',
                                'hide_empty' => false,
                            ));
                            if (!is_wp_error($categories)) {
                                foreach ($categories as $category) {
                                    echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="course-level"><?php _e('Level:', 'quicklearn'); ?></label>
                        <select id="course-level" class="form-select">
                            <option value=""><?php _e('All Levels', 'quicklearn'); ?></option>
                            <option value="beginner"><?php _e('Beginner', 'quicklearn'); ?></option>
                            <option value="intermediate"><?php _e('Intermediate', 'quicklearn'); ?></option>
                            <option value="advanced"><?php _e('Advanced', 'quicklearn'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="course-sort"><?php _e('Sort by:', 'quicklearn'); ?></label>
                        <select id="course-sort" class="form-select">
                            <option value="date"><?php _e('Newest First', 'quicklearn'); ?></option>
                            <option value="title"><?php _e('Title', 'quicklearn'); ?></option>
                            <option value="price"><?php _e('Price', 'quicklearn'); ?></option>
                            <option value="rating"><?php _e('Rating', 'quicklearn'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <div id="courses-container" class="courses-container">
                <div class="courses-grid grid grid--courses">
                    <?php
                    $courses_query = new WP_Query(array(
                        'post_type' => 'quick_course',
                        'posts_per_page' => get_option('quicklearn_courses_per_page', 12),
                        'post_status' => 'publish'
                    ));

                    if ($courses_query->have_posts()) :
                        while ($courses_query->have_posts()) : $courses_query->the_post();
                            ?>
                            <article class="course-card card">
                                <?php if (has_post_thumbnail()) : ?>
                                    <div class="card__image-container">
                                        <?php the_post_thumbnail('medium', array('class' => 'card__image')); ?>
                                        <div class="card__badge card__badge--primary">
                                            <?php echo esc_html(get_post_meta(get_the_ID(), 'course_level', true) ?: 'Beginner'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card__content">
                                    <h3 class="card__title">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h3>
                                    
                                    <div class="card__meta">
                                        <span class="card__meta-item">
                                            <span class="dashicons dashicons-clock"></span>
                                            <?php echo esc_html(get_post_meta(get_the_ID(), 'course_duration', true) ?: '6 weeks'); ?>
                                        </span>
                                        <span class="card__meta-item">
                                            <span class="dashicons dashicons-admin-users"></span>
                                            <?php echo esc_html(get_post_meta(get_the_ID(), 'course_students', true) ?: '0'); ?> <?php _e('students', 'quicklearn'); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="card__description">
                                        <?php echo wp_trim_words(get_the_excerpt() ?: get_the_content(), 20); ?>
                                    </div>
                                    
                                    <div class="course-card__instructor">
                                        <?php
                                        $instructor_id = get_post_meta(get_the_ID(), 'course_instructor', true);
                                        if ($instructor_id) {
                                            $instructor = get_user_by('login', $instructor_id);
                                            if ($instructor) {
                                                echo get_avatar($instructor->ID, 32, '', '', array('class' => 'course-card__instructor-avatar'));
                                                echo '<span class="course-card__instructor-name">' . esc_html($instructor->display_name) . '</span>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    
                                    <div class="card__actions">
                                        <?php
                                        $price = get_post_meta(get_the_ID(), 'course_price', true);
                                        if ($price && $price > 0) {
                                            echo '<span class="course-card__price">' . get_option('quicklearn_currency_symbol', '$') . esc_html($price) . '</span>';
                                        } else {
                                            echo '<span class="course-card__price course-card__price--free">' . __('Free', 'quicklearn') . '</span>';
                                        }
                                        ?>
                                        <a href="<?php the_permalink(); ?>" class="btn btn--primary btn--sm">
                                            <?php _e('View Course', 'quicklearn'); ?>
                                        </a>
                                    </div>
                                </div>
                            </article>
                            <?php
                        endwhile;
                        wp_reset_postdata();
                    else :
                        ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                            </div>
                            <h3 class="empty-state__title"><?php _e('No courses found', 'quicklearn'); ?></h3>
                            <p class="empty-state__message"><?php _e('There are no courses available at the moment. Please check back later.', 'quicklearn'); ?></p>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>