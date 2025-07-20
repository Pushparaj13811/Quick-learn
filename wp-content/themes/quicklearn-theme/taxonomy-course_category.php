<?php
/**
 * Template for course category archives
 * 
 * @package QuickLearn
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <div class="courses-container">
            <!-- Category Header -->
            <header class="category-header">
                <div class="category-breadcrumb">
                    <nav aria-label="<?php esc_attr_e('Breadcrumb', 'quicklearn'); ?>">
                        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'quicklearn'); ?></a>
                        <span class="breadcrumb-separator"> / </span>
                        <a href="<?php echo esc_url(home_url('/courses')); ?>"><?php esc_html_e('Courses', 'quicklearn'); ?></a>
                        <span class="breadcrumb-separator"> / </span>
                        <span class="current-category"><?php single_cat_title(); ?></span>
                    </nav>
                </div>

                <h1 class="category-title">
                    <?php
                    printf(
                        esc_html__('Courses in %s', 'quicklearn'),
                        '<span class="category-name">' . single_cat_title('', false) . '</span>'
                    );
                    ?>
                </h1>

                <?php
                $term_description = term_description();
                if (!empty($term_description)) :
                ?>
                    <div class="category-description">
                        <?php echo wp_kses_post($term_description); ?>
                    </div>
                <?php endif; ?>

                <div class="category-meta">
                    <?php
                    $term = get_queried_object();
                    $course_count = $term->count;
                    ?>
                    <span class="course-count">
                        <?php
                        printf(
                            _n('%s course available', '%s courses available', $course_count, 'quicklearn'),
                            '<strong>' . number_format_i18n($course_count) . '</strong>'
                        );
                        ?>
                    </span>
                    
                    <?php if (function_exists('qlcm_get_category_stats')) :
                        $stats = qlcm_get_category_stats($term->term_id);
                        if ($stats) :
                    ?>
                        <span class="category-stats">
                            <span class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Total Enrollments:', 'quicklearn'); ?></span>
                                <span class="stat-value"><?php echo esc_html(number_format_i18n($stats['total_enrollments'])); ?></span>
                            </span>
                            
                            <?php if (isset($stats['avg_rating']) && $stats['avg_rating'] > 0) : ?>
                                <span class="stat-item">
                                    <span class="stat-label"><?php esc_html_e('Average Rating:', 'quicklearn'); ?></span>
                                    <span class="stat-value">
                                        <?php echo esc_html(number_format($stats['avg_rating'], 1)); ?> ⭐
                                    </span>
                                </span>
                            <?php endif; ?>
                        </span>
                    <?php endif; endif; ?>
                </div>
            </header>

            <!-- Course Filters -->
            <div class="course-filters">
                <div class="filter-container">
                    <label for="course-sort" class="filter-label">
                        <?php esc_html_e('Sort by:', 'quicklearn'); ?>
                    </label>
                    <select id="course-sort" class="course-sort-dropdown">
                        <option value="date"><?php esc_html_e('Newest First', 'quicklearn'); ?></option>
                        <option value="title"><?php esc_html_e('Title A-Z', 'quicklearn'); ?></option>
                        <option value="popularity"><?php esc_html_e('Most Popular', 'quicklearn'); ?></option>
                        <?php if (function_exists('qlcm_get_course_rating')) : ?>
                            <option value="rating"><?php esc_html_e('Highest Rated', 'quicklearn'); ?></option>
                        <?php endif; ?>
                    </select>
                    
                    <div class="view-toggle">
                        <button type="button" class="view-btn grid-view active" aria-label="<?php esc_attr_e('Grid view', 'quicklearn'); ?>">
                            <span class="view-icon">⊞</span>
                        </button>
                        <button type="button" class="view-btn list-view" aria-label="<?php esc_attr_e('List view', 'quicklearn'); ?>">
                            <span class="view-icon">☰</span>
                        </button>
                    </div>
                </div>
                
                <!-- Loading indicator -->
                <div class="loading-indicator" id="courses-loading" role="status" aria-live="polite">
                    <div class="spinner" aria-hidden="true"></div>
                    <span class="loading-text"><?php esc_html_e('Loading courses...', 'quicklearn'); ?></span>
                </div>
            </div>

            <!-- Course Grid -->
            <div class="courses-grid" id="courses-grid">
                <?php if (have_posts()) : ?>
                    <?php while (have_posts()) : the_post(); ?>
                        <?php get_template_part('template-parts/course', 'card'); ?>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="no-courses-found">
                        <div class="no-courses-icon">📚</div>
                        <h3><?php esc_html_e('No courses found', 'quicklearn'); ?></h3>
                        <p><?php esc_html_e('There are currently no courses in this category.', 'quicklearn'); ?></p>
                        
                        <div class="no-courses-actions">
                            <a href="<?php echo esc_url(home_url('/courses')); ?>" class="primary-btn">
                                <?php esc_html_e('Browse All Courses', 'quicklearn'); ?>
                            </a>
                            
                            <?php
                            // Get other categories with courses
                            $other_categories = get_terms(array(
                                'taxonomy' => 'course_category',
                                'hide_empty' => true,
                                'exclude' => array($term->term_id),
                                'number' => 3,
                            ));
                            
                            if (!empty($other_categories) && !is_wp_error($other_categories)) :
                            ?>
                                <div class="suggested-categories">
                                    <h4><?php esc_html_e('Try these categories:', 'quicklearn'); ?></h4>
                                    <div class="category-links">
                                        <?php foreach ($other_categories as $category) : ?>
                                            <a href="<?php echo esc_url(get_term_link($category)); ?>" class="category-link">
                                                <?php echo esc_html($category->name); ?>
                                                <span class="category-count">(<?php echo esc_html($category->count); ?>)</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (have_posts()) : ?>
                <div class="courses-pagination">
                    <?php
                    echo paginate_links(array(
                        'total' => $wp_query->max_num_pages,
                        'current' => max(1, get_query_var('paged')),
                        'format' => '?paged=%#%',
                        'show_all' => false,
                        'end_size' => 1,
                        'mid_size' => 2,
                        'prev_next' => true,
                        'prev_text' => '&laquo; ' . esc_html__('Previous', 'quicklearn'),
                        'next_text' => esc_html__('Next', 'quicklearn') . ' &raquo;',
                        'add_args' => false,
                        'add_fragment' => '',
                    ));
                    ?>
                </div>
            <?php endif; ?>

            <!-- Related Categories -->
            <?php
            // Get child categories if this is a parent category
            $child_categories = get_terms(array(
                'taxonomy' => 'course_category',
                'parent' => $term->term_id,
                'hide_empty' => true,
            ));
            
            // If no child categories, get sibling categories
            if (empty($child_categories) || is_wp_error($child_categories)) {
                $sibling_categories = get_terms(array(
                    'taxonomy' => 'course_category',
                    'parent' => $term->parent,
                    'exclude' => array($term->term_id),
                    'hide_empty' => true,
                    'number' => 6,
                ));
                $related_categories = $sibling_categories;
                $related_title = __('Related Categories', 'quicklearn');
            } else {
                $related_categories = $child_categories;
                $related_title = __('Subcategories', 'quicklearn');
            }
            
            if (!empty($related_categories) && !is_wp_error($related_categories)) :
            ?>
                <section class="related-categories">
                    <h2><?php echo esc_html($related_title); ?></h2>
                    <div class="categories-grid">
                        <?php foreach ($related_categories as $category) : ?>
                            <div class="category-card">
                                <a href="<?php echo esc_url(get_term_link($category)); ?>" class="category-link">
                                    <h3 class="category-name"><?php echo esc_html($category->name); ?></h3>
                                    <p class="category-count">
                                        <?php
                                        printf(
                                            _n('%s course', '%s courses', $category->count, 'quicklearn'),
                                            number_format_i18n($category->count)
                                        );
                                        ?>
                                    </p>
                                    <?php if (!empty($category->description)) : ?>
                                        <p class="category-excerpt">
                                            <?php echo esc_html(wp_trim_words($category->description, 15)); ?>
                                        </p>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sortDropdown = document.getElementById('course-sort');
    const coursesGrid = document.getElementById('courses-grid');
    const loadingIndicator = document.getElementById('courses-loading');
    const viewButtons = document.querySelectorAll('.view-btn');
    
    // Handle sorting
    if (sortDropdown) {
        sortDropdown.addEventListener('change', function() {
            const sortBy = this.value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('orderby', sortBy);
            currentUrl.searchParams.delete('paged'); // Reset to first page
            window.location.href = currentUrl.toString();
        });
        
        // Set current sort option
        const urlParams = new URLSearchParams(window.location.search);
        const currentSort = urlParams.get('orderby');
        if (currentSort) {
            sortDropdown.value = currentSort;
        }
    }
    
    // Handle view toggle
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            viewButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            if (this.classList.contains('grid-view')) {
                coursesGrid.classList.remove('list-view');
                coursesGrid.classList.add('grid-view');
            } else {
                coursesGrid.classList.remove('grid-view');
                coursesGrid.classList.add('list-view');
            }
            
            // Store preference
            localStorage.setItem('quicklearn_course_view', this.classList.contains('grid-view') ? 'grid' : 'list');
        });
    });
    
    // Restore view preference
    const savedView = localStorage.getItem('quicklearn_course_view');
    if (savedView === 'list') {
        document.querySelector('.list-view').click();
    }
});
</script>

<style>
.category-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #e9ecef;
}

.category-breadcrumb {
    margin-bottom: 1rem;
}

.category-breadcrumb nav {
    font-size: 0.9rem;
    color: #6c757d;
}

.category-breadcrumb a {
    color: #007cba;
    text-decoration: none;
}

.category-breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb-separator {
    margin: 0 0.5rem;
    color: #999;
}

.current-category {
    color: #2c3e50;
    font-weight: 500;
}

.category-title {
    font-size: 2.5rem;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.category-name {
    color: #007cba;
}

.category-description {
    max-width: 800px;
    margin: 0 auto 1.5rem;
    color: #6c757d;
    font-size: 1.1rem;
    line-height: 1.6;
}

.category-meta {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
    font-size: 0.9rem;
}

.course-count {
    color: #495057;
}

.category-stats {
    display: flex;
    gap: 1rem;
}

.stat-item {
    display: flex;
    gap: 0.25rem;
}

.stat-label {
    color: #6c757d;
}

.stat-value {
    color: #2c3e50;
    font-weight: 600;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

.view-btn {
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-btn:hover,
.view-btn.active {
    background: #007cba;
    color: #fff;
    border-color: #007cba;
}

.view-icon {
    font-size: 1.2rem;
    line-height: 1;
}

.courses-grid.list-view .course-card {
    display: flex;
    align-items: flex-start;
    max-width: none;
}

.courses-grid.list-view .course-thumbnail {
    width: 200px;
    min-width: 200px;
    aspect-ratio: 16/10;
}

.courses-grid.list-view .course-content {
    flex: 1;
    padding: 1.5rem;
}

.no-courses-found {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px dashed #dee2e6;
}

.no-courses-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-courses-found h3 {
    font-size: 1.5rem;
    color: #495057;
    margin-bottom: 1rem;
}

.no-courses-found p {
    color: #6c757d;
    margin-bottom: 2rem;
}

.no-courses-actions {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    align-items: center;
}

.suggested-categories h4 {
    color: #495057;
    margin-bottom: 1rem;
}

.category-links {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}

.category-link {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.category-link:hover {
    border-color: #007cba;
    color: #007cba;
    transform: translateY(-2px);
}

.category-count {
    font-size: 0.85rem;
    color: #6c757d;
}

.related-categories {
    margin-top: 4rem;
    padding-top: 3rem;
    border-top: 2px solid #e9ecef;
}

.related-categories h2 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 2rem;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.category-card {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.category-card .category-link {
    background: none;
    border: none;
    padding: 0;
    display: block;
    text-decoration: none;
    color: inherit;
}

.category-card .category-name {
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.category-card .category-count {
    color: #007cba;
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: block;
}

.category-card .category-excerpt {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
}

@media (max-width: 768px) {
    .category-title {
        font-size: 2rem;
    }
    
    .category-meta {
        flex-direction: column;
        gap: 1rem;
    }
    
    .category-stats {
        flex-direction: column;
    }
    
    .filter-container {
        flex-direction: column;
        gap: 1rem;
    }
    
    .view-toggle {
        margin-left: 0;
        justify-content: center;
    }
    
    .courses-grid.list-view .course-card {
        flex-direction: column;
    }
    
    .courses-grid.list-view .course-thumbnail {
        width: 100%;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-links {
        flex-direction: column;
    }
}
</style>

<?php get_footer(); ?>