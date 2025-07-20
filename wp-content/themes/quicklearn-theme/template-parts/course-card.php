<?php
/**
 * Template part for displaying course cards
 * 
 * @package QuickLearn
 */

$course_categories = get_the_terms(get_the_ID(), 'course_category');
$category_names = array();
$category_slugs = array();

if ($course_categories && !is_wp_error($course_categories)) {
    foreach ($course_categories as $category) {
        $category_names[] = $category->name;
        $category_slugs[] = $category->slug;
    }
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('course-card'); ?> data-categories="<?php echo esc_attr(implode(',', $category_slugs)); ?>">
    <div class="course-card-inner">
        
        <?php if (has_post_thumbnail()) : ?>
            <div class="course-thumbnail">
                <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
                    <?php 
                    // Use responsive image sizes
                    the_post_thumbnail('course-thumbnail-desktop', array(
                        'alt' => the_title_attribute(array('echo' => false)),
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'sizes' => '(max-width: 480px) 400px, (max-width: 768px) 600px, 800px',
                        'srcset' => wp_get_attachment_image_srcset(get_post_thumbnail_id(), 'course-thumbnail-desktop')
                    )); ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="course-content">
            <header class="course-header">
                <?php if (!empty($category_names)) : ?>
                    <div class="course-categories">
                        <?php foreach ($category_names as $index => $category_name) : ?>
                            <span class="course-category"><?php echo esc_html($category_name); ?></span>
                            <?php if ($index < count($category_names) - 1) : ?>
                                <span class="category-separator">, </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="course-title">
                    <a href="<?php the_permalink(); ?>" rel="bookmark">
                        <?php the_title(); ?>
                    </a>
                </h2>
            </header>

            <div class="course-excerpt">
                <?php
                if (has_excerpt()) {
                    the_excerpt();
                } else {
                    echo wp_trim_words(get_the_content(), 25, '...');
                }
                ?>
            </div>

            <footer class="course-footer">
                <div class="course-meta">
                    <span class="course-date">
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                            <?php echo esc_html(get_the_date()); ?>
                        </time>
                    </span>
                    
                    <?php do_action('quicklearn_course_card_meta'); ?>
                </div>
                
                <a href="<?php the_permalink(); ?>" class="course-link">
                    <?php esc_html_e('View Course', 'quicklearn'); ?>
                    <span class="screen-reader-text"><?php the_title(); ?></span>
                </a>
            </footer>
        </div>
    </div>
</article>