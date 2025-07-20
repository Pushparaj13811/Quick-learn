<?php
/**
 * The main template file
 *
 * @package QuickLearn
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="content-area">
        <?php if (have_posts()) : ?>
            
            <?php if (is_home() && !is_front_page()) : ?>
                <header class="page-header">
                    <h1 class="page-title"><?php single_post_title(); ?></h1>
                </header>
            <?php endif; ?>

            <div class="posts-container">
                <?php while (have_posts()) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class('post-item'); ?>>
                        <header class="entry-header">
                            <?php
                            if (is_singular()) :
                                the_title('<h1 class="entry-title">', '</h1>');
                            else :
                                the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>');
                            endif;
                            ?>
                            
                            <?php if (!is_page()) : ?>
                                <div class="entry-meta">
                                    <span class="posted-on">
                                        <?php echo get_the_date(); ?>
                                    </span>
                                    <span class="byline">
                                        <?php esc_html_e('by', 'quicklearn'); ?> <?php the_author(); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </header><!-- .entry-header -->

                        <?php if (has_post_thumbnail() && !is_singular()) : ?>
                            <div class="post-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="entry-content">
                            <?php
                            if (is_singular()) {
                                the_content();
                            } else {
                                the_excerpt();
                            }
                            ?>
                        </div><!-- .entry-content -->

                        <?php if (!is_singular()) : ?>
                            <footer class="entry-footer">
                                <a href="<?php the_permalink(); ?>" class="read-more">
                                    <?php esc_html_e('Read More', 'quicklearn'); ?>
                                </a>
                            </footer>
                        <?php endif; ?>
                    </article><!-- #post-<?php the_ID(); ?> -->
                <?php endwhile; ?>
            </div><!-- .posts-container -->

            <?php
            // Pagination
            the_posts_pagination(array(
                'prev_text' => __('Previous', 'quicklearn'),
                'next_text' => __('Next', 'quicklearn'),
            ));
            ?>

        <?php else : ?>
            
            <section class="no-results not-found">
                <header class="page-header">
                    <h1 class="page-title"><?php esc_html_e('Nothing here', 'quicklearn'); ?></h1>
                </header><!-- .page-header -->

                <div class="page-content">
                    <?php if (is_home() && current_user_can('publish_posts')) : ?>
                        <p>
                            <?php
                            printf(
                                wp_kses(
                                    __('Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'quicklearn'),
                                    array(
                                        'a' => array(
                                            'href' => array(),
                                        ),
                                    )
                                ),
                                esc_url(admin_url('post-new.php'))
                            );
                            ?>
                        </p>
                    <?php elseif (is_search()) : ?>
                        <p><?php esc_html_e('Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'quicklearn'); ?></p>
                        <?php get_search_form(); ?>
                    <?php else : ?>
                        <p><?php esc_html_e('It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'quicklearn'); ?></p>
                        <?php get_search_form(); ?>
                    <?php endif; ?>
                </div><!-- .page-content -->
            </section><!-- .no-results -->

        <?php endif; ?>
    </div><!-- .content-area -->
</main><!-- #primary -->

<?php get_footer();