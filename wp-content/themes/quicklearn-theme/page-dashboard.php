<?php
/**
 * Template for displaying user dashboard
 * 
 * @package QuickLearn
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php while (have_posts()) : the_post(); ?>
            
            <article id="post-<?php the_ID(); ?>" <?php post_class('dashboard-page'); ?>>
                
                <header class="page-header">
                    <h1 class="page-title"><?php the_title(); ?></h1>
                    
                    <?php if (get_the_excerpt()) : ?>
                        <div class="page-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                </header>

                <div class="page-content">
                    <?php
                    the_content();
                    
                    // Display user dashboard shortcode
                    echo do_shortcode('[quicklearn_dashboard]');
                    
                    wp_link_pages(array(
                        'before' => '<div class="page-links">' . esc_html__('Pages:', 'quicklearn'),
                        'after'  => '</div>',
                    ));
                    ?>
                </div>

            </article>

        <?php endwhile; ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>