<?php
/**
 * The front page template file
 *
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header(); 

// Check if user is logged in and redirect to dashboard
if (is_user_logged_in()) {
    $dashboard_page_id = get_option('quicklearn_dashboard_page_id');
    if ($dashboard_page_id) {
        wp_redirect(get_permalink($dashboard_page_id));
        exit;
    }
}
?>

<main id="primary" class="site-main front-page">
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-background">
            <div class="hero-overlay"></div>
        </div>
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title"><?php esc_html_e('Start Your Learning Journey Today', 'quicklearn'); ?></h1>
                <p class="hero-subtitle"><?php esc_html_e('Access world-class courses and transform your career with our comprehensive e-learning platform', 'quicklearn'); ?></p>
                <div class="hero-actions">
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn btn--primary btn--lg">
                        <?php esc_html_e('Get Started Free', 'quicklearn'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_post_type_archive_link('quick_course')); ?>" class="btn btn--secondary btn--lg">
                        <?php esc_html_e('Browse Courses', 'quicklearn'); ?>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number" data-count="<?php echo esc_attr(wp_count_posts('quick_course')->publish); ?>">0</span>
                        <span class="stat-label"><?php esc_html_e('Courses', 'quicklearn'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-count="<?php echo esc_attr(count_users()['total_users']); ?>">0</span>
                        <span class="stat-label"><?php esc_html_e('Students', 'quicklearn'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-count="100">0</span>
                        <span class="stat-label"><?php esc_html_e('% Satisfaction', 'quicklearn'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title"><?php esc_html_e('Why Choose QuickLearn?', 'quicklearn'); ?></h2>
            <p class="section-subtitle"><?php esc_html_e('Everything you need to succeed in your learning journey', 'quicklearn'); ?></p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Expert Instructors', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Learn from industry professionals with years of real-world experience', 'quicklearn'); ?></p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-video-alt3"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Interactive Learning', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Engage with video lessons, quizzes, and hands-on projects', 'quicklearn'); ?></p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Earn Certificates', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Get recognized certificates upon successful course completion', 'quicklearn'); ?></p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Community Support', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Join a vibrant community of learners and get help when needed', 'quicklearn'); ?></p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-smartphone"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Learn Anywhere', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Access courses on any device, anytime, anywhere', 'quicklearn'); ?></p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <h3 class="feature-title"><?php esc_html_e('Lifetime Access', 'quicklearn'); ?></h3>
                    <p class="feature-description"><?php esc_html_e('Once enrolled, access your courses forever with free updates', 'quicklearn'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="featured-courses-section">
        <div class="container">
            <h2 class="section-title"><?php esc_html_e('Popular Courses', 'quicklearn'); ?></h2>
            <p class="section-subtitle"><?php esc_html_e('Start learning with our most popular courses', 'quicklearn'); ?></p>
            
            <?php
            // Get featured courses
            $featured_courses = new WP_Query(array(
                'post_type' => 'quick_course',
                'posts_per_page' => 6,
                'meta_key' => '_quicklearn_featured',
                'meta_value' => 'yes',
                'post_status' => 'publish'
            ));
            
            // If no featured courses, get latest courses
            if (!$featured_courses->have_posts()) {
                $featured_courses = new WP_Query(array(
                    'post_type' => 'quick_course',
                    'posts_per_page' => 6,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));
            }
            ?>
            
            <?php if ($featured_courses->have_posts()) : ?>
                <div class="course-grid featured-grid">
                    <?php while ($featured_courses->have_posts()) : $featured_courses->the_post(); ?>
                        <?php get_template_part('template-parts/course', 'card'); ?>
                    <?php endwhile; ?>
                </div>
                
                <div class="section-cta">
                    <a href="<?php echo esc_url(get_post_type_archive_link('quick_course')); ?>" class="btn btn--primary">
                        <?php esc_html_e('View All Courses', 'quicklearn'); ?>
                    </a>
                </div>
            <?php else : ?>
                <p class="no-courses-message"><?php esc_html_e('No courses available yet. Check back soon!', 'quicklearn'); ?></p>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works-section">
        <div class="container">
            <h2 class="section-title"><?php esc_html_e('How It Works', 'quicklearn'); ?></h2>
            <p class="section-subtitle"><?php esc_html_e('Get started in three simple steps', 'quicklearn'); ?></p>
            
            <div class="steps-container">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <h3 class="step-title"><?php esc_html_e('Create Account', 'quicklearn'); ?></h3>
                    <p class="step-description"><?php esc_html_e('Sign up for free and set up your learning profile', 'quicklearn'); ?></p>
                </div>
                
                <div class="step-connector"></div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <h3 class="step-title"><?php esc_html_e('Choose Course', 'quicklearn'); ?></h3>
                    <p class="step-description"><?php esc_html_e('Browse our catalog and enroll in courses that interest you', 'quicklearn'); ?></p>
                </div>
                
                <div class="step-connector"></div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <h3 class="step-title"><?php esc_html_e('Start Learning', 'quicklearn'); ?></h3>
                    <p class="step-description"><?php esc_html_e('Access course materials and track your progress', 'quicklearn'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <h2 class="section-title"><?php esc_html_e('What Our Students Say', 'quicklearn'); ?></h2>
            
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <div class="testimonial-stars">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                        </div>
                        <p class="testimonial-text"><?php esc_html_e('QuickLearn has transformed my career. The courses are well-structured and the instructors are amazing!', 'quicklearn'); ?></p>
                        <div class="testimonial-author">
                            <strong><?php esc_html_e('Sarah Johnson', 'quicklearn'); ?></strong>
                            <span><?php esc_html_e('Web Developer', 'quicklearn'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <div class="testimonial-stars">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                        </div>
                        <p class="testimonial-text"><?php esc_html_e('The flexibility to learn at my own pace and the quality of content exceeded my expectations.', 'quicklearn'); ?></p>
                        <div class="testimonial-author">
                            <strong><?php esc_html_e('Michael Chen', 'quicklearn'); ?></strong>
                            <span><?php esc_html_e('Data Analyst', 'quicklearn'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        <div class="testimonial-stars">
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                            <span class="dashicons dashicons-star-filled"></span>
                        </div>
                        <p class="testimonial-text"><?php esc_html_e('I earned my certificate and landed my dream job. Thank you QuickLearn for this opportunity!', 'quicklearn'); ?></p>
                        <div class="testimonial-author">
                            <strong><?php esc_html_e('Emily Rodriguez', 'quicklearn'); ?></strong>
                            <span><?php esc_html_e('UX Designer', 'quicklearn'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title"><?php esc_html_e('Ready to Start Learning?', 'quicklearn'); ?></h2>
                <p class="cta-subtitle"><?php esc_html_e('Join thousands of students already learning on QuickLearn', 'quicklearn'); ?></p>
                <div class="cta-buttons">
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn btn--primary btn--lg">
                        <?php esc_html_e('Sign Up Now', 'quicklearn'); ?>
                    </a>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn btn--secondary btn--lg">
                        <?php esc_html_e('Login', 'quicklearn'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

</main><!-- #primary -->

<script>
// Animate numbers on scroll
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const animateNumbers = (entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                const count = parseInt(target.getAttribute('data-count'));
                const duration = 2000;
                const increment = count / (duration / 16);
                let current = 0;
                
                const updateNumber = () => {
                    current += increment;
                    if (current < count) {
                        target.textContent = Math.floor(current);
                        requestAnimationFrame(updateNumber);
                    } else {
                        target.textContent = count + (target.parentElement.textContent.includes('%') ? '%' : '');
                    }
                };
                
                updateNumber();
                observer.unobserve(target);
            }
        });
    };
    
    const observer = new IntersectionObserver(animateNumbers, { threshold: 0.5 });
    statNumbers.forEach(stat => observer.observe(stat));
});
</script>

<?php get_footer(); ?>