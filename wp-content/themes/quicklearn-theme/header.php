<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e('Skip to content', 'quicklearn'); ?></a>

<div id="page" class="site">
    <header id="masthead" class="site-header">
        <div class="header-container">
            <div class="site-branding">
                <?php
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                            <?php bloginfo('name'); ?>
                        </a>
                    </h1>
                    <?php
                    $description = get_bloginfo('description', 'display');
                    if ($description || is_customize_preview()) {
                        ?>
                        <p class="site-description"><?php echo $description; ?></p>
                        <?php
                    }
                }
                ?>
            </div><!-- .site-branding -->

            <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
                <span class="screen-reader-text"><?php esc_html_e('Primary Menu', 'quicklearn'); ?></span>
                ☰
            </button>

            <nav id="site-navigation" class="main-navigation">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'container'      => false,
                    'fallback_cb'    => 'quicklearn_fallback_menu',
                ));
                ?>
            </nav><!-- #site-navigation -->
            
            <!-- User Menu -->
            <div class="user-menu">
                <?php if (is_user_logged_in()) : ?>
                    <?php 
                    $current_user = wp_get_current_user();
                    $dashboard_page_id = get_option('quicklearn_dashboard_page_id');
                    $dashboard_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : admin_url();
                    ?>
                    <div class="user-dropdown">
                        <button class="user-toggle" aria-expanded="false">
                            <?php echo get_avatar($current_user->ID, 32); ?>
                            <span class="user-name"><?php echo esc_html($current_user->display_name); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="user-dropdown-menu">
                            <div class="user-info">
                                <div class="user-avatar-large">
                                    <?php echo get_avatar($current_user->ID, 48); ?>
                                </div>
                                <div class="user-details">
                                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                                    <span class="user-role">
                                        <?php 
                                        $user_roles = $current_user->roles;
                                        if (in_array('administrator', $user_roles)) {
                                            esc_html_e('Administrator', 'quicklearn');
                                        } elseif (in_array('qlcm_instructor', $user_roles)) {
                                            esc_html_e('Instructor', 'quicklearn');
                                        } elseif (in_array('qlcm_course_moderator', $user_roles)) {
                                            esc_html_e('Moderator', 'quicklearn');
                                        } else {
                                            esc_html_e('Student', 'quicklearn');
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="user-menu-links">
                                <a href="<?php echo esc_url($dashboard_url); ?>" class="user-menu-link">
                                    <span class="dashicons dashicons-dashboard"></span>
                                    <?php esc_html_e('Dashboard', 'quicklearn'); ?>
                                </a>
                                
                                <?php if (current_user_can('qlcm_create_courses')) : ?>
                                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=quick_course')); ?>" class="user-menu-link">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <?php esc_html_e('Create Course', 'quicklearn'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (current_user_can('manage_options')) : ?>
                                    <a href="<?php echo esc_url(admin_url()); ?>" class="user-menu-link">
                                        <span class="dashicons dashicons-admin-settings"></span>
                                        <?php esc_html_e('Admin Panel', 'quicklearn'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo esc_url(get_edit_user_link()); ?>" class="user-menu-link">
                                    <span class="dashicons dashicons-admin-users"></span>
                                    <?php esc_html_e('My Profile', 'quicklearn'); ?>
                                </a>
                                
                                <a href="<?php echo esc_url(get_post_type_archive_link('quick_course')); ?>" class="user-menu-link">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php esc_html_e('Browse Courses', 'quicklearn'); ?>
                                </a>
                                
                                <div class="user-menu-separator"></div>
                                
                                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="user-menu-link logout-link">
                                    <span class="dashicons dashicons-exit"></span>
                                    <?php esc_html_e('Logout', 'quicklearn'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="auth-links">
                        <a href="<?php echo esc_url(wp_login_url()); ?>" class="login-link">
                            <?php esc_html_e('Login', 'quicklearn'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="register-link btn btn--primary">
                            <?php esc_html_e('Sign Up', 'quicklearn'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div><!-- .user-menu -->
        </div><!-- .header-container -->
    </header><!-- #masthead -->

    <div id="content" class="site-content">

<?php
/**
 * Fallback menu when no menu is assigned
 */
function quicklearn_fallback_menu() {
    ?>
    <ul id="primary-menu" class="menu">
        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'quicklearn'); ?></a></li>
        <li><a href="<?php echo esc_url(home_url('/courses')); ?>"><?php esc_html_e('Courses', 'quicklearn'); ?></a></li>
        <li><a href="<?php echo esc_url(home_url('/about')); ?>"><?php esc_html_e('About', 'quicklearn'); ?></a></li>
        <li><a href="<?php echo esc_url(home_url('/contact')); ?>"><?php esc_html_e('Contact', 'quicklearn'); ?></a></li>
    </ul>
    <?php
}