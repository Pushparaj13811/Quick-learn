<?php
/**
 * Administrator Dashboard Template Part
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get platform statistics
$total_courses = wp_count_posts('quick_course');
$total_users = count_users();
$total_enrollments = 0;
$recent_activity = array();

// Get enrollment statistics
global $wpdb;
$enrollment_stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_enrollments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_enrollments,
        COUNT(CASE WHEN enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_enrollments
    FROM {$wpdb->prefix}quicklearn_enrollments
");

$total_enrollments = $enrollment_stats ? $enrollment_stats->total_enrollments : 0;
$completed_enrollments = $enrollment_stats ? $enrollment_stats->completed_enrollments : 0;
$monthly_enrollments = $enrollment_stats ? $enrollment_stats->monthly_enrollments : 0;

// Calculate completion rate
$completion_rate = $total_enrollments > 0 ? round(($completed_enrollments / $total_enrollments) * 100, 1) : 0;
?>

<!-- Admin Dashboard Statistics -->
<section class="dashboard-stats admin-stats">
    <div class="stats-grid-large">
        <div class="stat-card featured">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-site"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($total_courses->publish); ?></h3>
                <p class="stat-label"><?php esc_html_e('Published Courses', 'quicklearn'); ?></p>
                <div class="stat-meta">
                    <?php if ($total_courses->draft > 0) : ?>
                        <span class="meta-item"><?php echo esc_html($total_courses->draft); ?> <?php esc_html_e('drafts', 'quicklearn'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="stat-card featured">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($total_users['total_users']); ?></h3>
                <p class="stat-label"><?php esc_html_e('Total Users', 'quicklearn'); ?></p>
                <div class="stat-meta">
                    <span class="meta-item"><?php echo esc_html($monthly_enrollments); ?> <?php esc_html_e('new this month', 'quicklearn'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-book-alt"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($total_enrollments); ?></h3>
                <p class="stat-label"><?php esc_html_e('Total Enrollments', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number"><?php echo esc_html($completion_rate); ?>%</h3>
                <p class="stat-label"><?php esc_html_e('Completion Rate', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number">
                    <?php
                    $instructor_count = count(get_users(array('role' => 'qlcm_instructor')));
                    echo esc_html($instructor_count);
                    ?>
                </h3>
                <p class="stat-label"><?php esc_html_e('Instructors', 'quicklearn'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-shield"></span>
            </div>
            <div class="stat-content">
                <h3 class="stat-number">
                    <?php
                    // Get security events from last 24 hours
                    $security_events = $wpdb->get_var("
                        SELECT COUNT(*) 
                        FROM {$wpdb->prefix}quicklearn_security_log 
                        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ");
                    echo esc_html($security_events ?: '0');
                    ?>
                </h3>
                <p class="stat-label"><?php esc_html_e('Security Events (24h)', 'quicklearn'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Quick Management Actions -->
<section class="dashboard-actions-section admin-actions">
    <h2 class="section-title"><?php esc_html_e('Platform Management', 'quicklearn'); ?></h2>
    <div class="action-cards admin-grid">
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=quick_course')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-book"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Manage Courses', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Review, edit, and organize all courses', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('User Management', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Add, edit, and manage user accounts', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-analytics')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Analytics', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('View detailed platform analytics', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=qlcm-security')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-shield"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Security Dashboard', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Monitor security and threats', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=qlcm_certificate')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Certificates', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Manage course certificates', 'quicklearn'); ?></p>
        </a>
        
        <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" class="action-card">
            <div class="action-icon">
                <span class="dashicons dashicons-admin-settings"></span>
            </div>
            <h3 class="action-title"><?php esc_html_e('Settings', 'quicklearn'); ?></h3>
            <p class="action-description"><?php esc_html_e('Configure platform settings', 'quicklearn'); ?></p>
        </a>
    </div>
</section>

<!-- Recent Activity Overview -->
<section class="activity-overview-section">
    <h2 class="section-title"><?php esc_html_e('Recent Platform Activity', 'quicklearn'); ?></h2>
    
    <div class="activity-grid">
        <!-- Recent Courses -->
        <div class="activity-panel">
            <h3 class="panel-title"><?php esc_html_e('Recent Courses', 'quicklearn'); ?></h3>
            <?php
            $recent_courses = new WP_Query(array(
                'post_type' => 'quick_course',
                'posts_per_page' => 5,
                'post_status' => array('publish', 'pending'),
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($recent_courses->have_posts()) :
            ?>
                <div class="activity-list">
                    <?php while ($recent_courses->have_posts()) : $recent_courses->the_post(); ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <h4 class="activity-title">
                                    <a href="<?php echo esc_url(get_edit_post_link()); ?>"><?php the_title(); ?></a>
                                </h4>
                                <div class="activity-meta">
                                    <span class="author"><?php esc_html_e('by', 'quicklearn'); ?> <?php the_author(); ?></span>
                                    <span class="status status-<?php echo esc_attr(get_post_status()); ?>">
                                        <?php echo esc_html(ucfirst(get_post_status())); ?>
                                    </span>
                                    <span class="date"><?php echo esc_html(human_time_diff(get_the_time('U'))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="no-activity"><?php esc_html_e('No recent courses.', 'quicklearn'); ?></p>
            <?php endif; wp_reset_postdata(); ?>
        </div>
        
        <!-- Recent Users -->
        <div class="activity-panel">
            <h3 class="panel-title"><?php esc_html_e('New Users', 'quicklearn'); ?></h3>
            <?php
            $recent_users = get_users(array(
                'orderby' => 'registered',
                'order' => 'DESC',
                'number' => 5
            ));
            
            if ($recent_users) :
            ?>
                <div class="activity-list">
                    <?php foreach ($recent_users as $user) : ?>
                        <div class="activity-item">
                            <div class="activity-avatar">
                                <?php echo get_avatar($user->ID, 32); ?>
                            </div>
                            <div class="activity-content">
                                <h4 class="activity-title">
                                    <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </h4>
                                <div class="activity-meta">
                                    <span class="role"><?php echo esc_html(implode(', ', $user->roles)); ?></span>
                                    <span class="date"><?php echo esc_html(human_time_diff(strtotime($user->user_registered))); ?> <?php esc_html_e('ago', 'quicklearn'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="no-activity"><?php esc_html_e('No recent users.', 'quicklearn'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- System Status -->
<section class="system-status-section">
    <h2 class="section-title"><?php esc_html_e('System Status', 'quicklearn'); ?></h2>
    
    <div class="status-grid">
        <div class="status-card">
            <div class="status-header">
                <h3 class="status-title"><?php esc_html_e('WordPress', 'quicklearn'); ?></h3>
                <span class="status-indicator good"><?php esc_html_e('Good', 'quicklearn'); ?></span>
            </div>
            <div class="status-details">
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Version:', 'quicklearn'); ?></span>
                    <span class="detail-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Memory Limit:', 'quicklearn'); ?></span>
                    <span class="detail-value"><?php echo esc_html(ini_get('memory_limit')); ?></span>
                </div>
            </div>
        </div>
        
        <div class="status-card">
            <div class="status-header">
                <h3 class="status-title"><?php esc_html_e('Database', 'quicklearn'); ?></h3>
                <span class="status-indicator good"><?php esc_html_e('Good', 'quicklearn'); ?></span>
            </div>
            <div class="status-details">
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Tables:', 'quicklearn'); ?></span>
                    <span class="detail-value">
                        <?php
                        $table_count = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
                        echo esc_html($table_count);
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Size:', 'quicklearn'); ?></span>
                    <span class="detail-value">
                        <?php
                        $db_size = $wpdb->get_var("
                            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
                            FROM information_schema.tables 
                            WHERE table_schema = '" . DB_NAME . "'
                        ");
                        echo esc_html($db_size . ' MB');
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="status-card">
            <div class="status-header">
                <h3 class="status-title"><?php esc_html_e('Security', 'quicklearn'); ?></h3>
                <?php
                $security_status = 'good';
                $recent_threats = $wpdb->get_var("
                    SELECT COUNT(*) 
                    FROM {$wpdb->prefix}quicklearn_security_log 
                    WHERE severity IN ('high', 'critical') 
                    AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ");
                if ($recent_threats > 0) {
                    $security_status = 'warning';
                }
                ?>
                <span class="status-indicator <?php echo esc_attr($security_status); ?>">
                    <?php echo $security_status === 'good' ? esc_html__('Good', 'quicklearn') : esc_html__('Warning', 'quicklearn'); ?>
                </span>
            </div>
            <div class="status-details">
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Failed Logins (24h):', 'quicklearn'); ?></span>
                    <span class="detail-value">
                        <?php
                        $failed_logins = $wpdb->get_var("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}quicklearn_security_log 
                            WHERE event_type = 'failed_login' 
                            AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ");
                        echo esc_html($failed_logins ?: '0');
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Blocked IPs:', 'quicklearn'); ?></span>
                    <span class="detail-value">
                        <?php
                        $blocked_ips = $wpdb->get_var("
                            SELECT COUNT(DISTINCT ip_address) 
                            FROM {$wpdb->prefix}quicklearn_security_log 
                            WHERE event_type = 'ip_blocked'
                        ");
                        echo esc_html($blocked_ips ?: '0');
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="status-card">
            <div class="status-header">
                <h3 class="status-title"><?php esc_html_e('Performance', 'quicklearn'); ?></h3>
                <span class="status-indicator good"><?php esc_html_e('Good', 'quicklearn'); ?></span>
            </div>
            <div class="status-details">
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('PHP Version:', 'quicklearn'); ?></span>
                    <span class="detail-value"><?php echo esc_html(phpversion()); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php esc_html_e('Max Execution Time:', 'quicklearn'); ?></span>
                    <span class="detail-value"><?php echo esc_html(ini_get('max_execution_time') . 's'); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Reports -->
<section class="quick-reports-section">
    <h2 class="section-title"><?php esc_html_e('Quick Reports', 'quicklearn'); ?></h2>
    
    <div class="reports-grid">
        <div class="report-card">
            <h3 class="report-title"><?php esc_html_e('Top Performing Courses', 'quicklearn'); ?></h3>
            <?php
            $top_courses = new WP_Query(array(
                'post_type' => 'quick_course',
                'posts_per_page' => 3,
                'meta_key' => '_qlcm_enrollment_count',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            ));
            
            if ($top_courses->have_posts()) :
            ?>
                <div class="report-list">
                    <?php while ($top_courses->have_posts()) : $top_courses->the_post(); ?>
                        <div class="report-item">
                            <span class="item-title"><?php the_title(); ?></span>
                            <span class="item-value">
                                <?php echo esc_html(get_post_meta(get_the_ID(), '_qlcm_enrollment_count', true) ?: '0'); ?>
                                <?php esc_html_e('students', 'quicklearn'); ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <p class="no-data"><?php esc_html_e('No enrollment data available.', 'quicklearn'); ?></p>
            <?php endif; wp_reset_postdata(); ?>
        </div>
        
        <div class="report-card">
            <h3 class="report-title"><?php esc_html_e('Most Active Instructors', 'quicklearn'); ?></h3>
            <?php
            $active_instructors = get_users(array(
                'role' => 'qlcm_instructor',
                'orderby' => 'post_count',
                'order' => 'DESC',
                'number' => 3
            ));
            
            if ($active_instructors) :
            ?>
                <div class="report-list">
                    <?php foreach ($active_instructors as $instructor) : ?>
                        <div class="report-item">
                            <span class="item-title"><?php echo esc_html($instructor->display_name); ?></span>
                            <span class="item-value">
                                <?php
                                $course_count = count_user_posts($instructor->ID, 'quick_course');
                                echo esc_html($course_count);
                                ?> <?php esc_html_e('courses', 'quicklearn'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="no-data"><?php esc_html_e('No instructor data available.', 'quicklearn'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>