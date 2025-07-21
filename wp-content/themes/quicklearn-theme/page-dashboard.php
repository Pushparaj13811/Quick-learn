<?php
/**
 * Template for displaying user dashboard
 * 
 * @package QuickLearn
 */

// Redirect non-logged-in users to login page
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header(); 

// Get current user info
$current_user = wp_get_current_user();
$user_roles = $current_user->roles;
$display_name = $current_user->display_name;
$user_email = $current_user->user_email;

// Determine dashboard type based on user role
$dashboard_type = 'student'; // default
if (in_array('administrator', $user_roles)) {
    $dashboard_type = 'admin';
} elseif (in_array('qlcm_instructor', $user_roles)) {
    $dashboard_type = 'instructor';
} elseif (in_array('qlcm_course_moderator', $user_roles)) {
    $dashboard_type = 'moderator';
}
?>

<div id="primary" class="content-area dashboard-<?php echo esc_attr($dashboard_type); ?>">
    <main id="main" class="site-main">

        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="container">
                <div class="dashboard-welcome">
                    <div class="user-avatar">
                        <?php echo get_avatar($current_user->ID, 80); ?>
                    </div>
                    <div class="user-info">
                        <h1 class="dashboard-title">
                            <?php 
                            printf(
                                esc_html__('Welcome back, %s!', 'quicklearn'),
                                esc_html($display_name)
                            ); 
                            ?>
                        </h1>
                        <p class="user-role">
                            <?php 
                            switch($dashboard_type) {
                                case 'admin':
                                    esc_html_e('Administrator', 'quicklearn');
                                    break;
                                case 'instructor':
                                    esc_html_e('Course Instructor', 'quicklearn');
                                    break;
                                case 'moderator':
                                    esc_html_e('Course Moderator', 'quicklearn');
                                    break;
                                default:
                                    esc_html_e('Student', 'quicklearn');
                            }
                            ?>
                        </p>
                    </div>
                    <div class="dashboard-actions">
                        <a href="<?php echo esc_url(get_edit_user_link()); ?>" class="btn btn--secondary">
                            <?php esc_html_e('Edit Profile', 'quicklearn'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="btn btn--outline">
                            <?php esc_html_e('Logout', 'quicklearn'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="container">
                
                <?php
                // Load role-specific dashboard content
                switch($dashboard_type) {
                    case 'admin':
                        get_template_part('template-parts/dashboard', 'admin');
                        break;
                    case 'instructor':
                        get_template_part('template-parts/dashboard', 'instructor');
                        break;
                    case 'moderator':
                        get_template_part('template-parts/dashboard', 'moderator');
                        break;
                    default:
                        get_template_part('template-parts/dashboard', 'student');
                }
                ?>
                
            </div>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>