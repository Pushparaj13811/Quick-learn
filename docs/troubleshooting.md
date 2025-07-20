# Troubleshooting Guide - QuickLearn E-Learning Portal

This guide helps you diagnose and resolve common issues with the QuickLearn e-learning platform.

## Table of Contents

1. [Common Issues](#common-issues)
2. [Installation Problems](#installation-problems)
3. [Course Display Issues](#course-display-issues)
4. [Enrollment Problems](#enrollment-problems)
5. [Performance Issues](#performance-issues)
6. [Security Issues](#security-issues)
7. [Database Problems](#database-problems)
8. [Diagnostic Tools](#diagnostic-tools)

## Common Issues

### Courses Not Displaying

**Symptoms:**
- Course page shows no courses
- Course categories not appearing
- Individual course pages show 404 errors

**Possible Causes:**
1. Plugin not activated
2. Permalink structure issues
3. Theme template conflicts
4. Database table missing

**Solutions:**

1. **Check Plugin Activation**
   ```
   WordPress Admin → Plugins → Ensure QuickLearn Course Manager is active
   ```

2. **Flush Permalinks**
   ```
   WordPress Admin → Settings → Permalinks → Save Changes
   ```

3. **Check Database Tables**
   ```sql
   SHOW TABLES LIKE 'wp_qlcm_%';
   ```
   If tables are missing, deactivate and reactivate the plugin.

4. **Template Debugging**
   ```php
   // Add to wp-config.php for debugging
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

### AJAX Filtering Not Working

**Symptoms:**
- Category filter dropdown doesn't respond
- Page refreshes instead of filtering
- JavaScript errors in console

**Possible Causes:**
1. JavaScript conflicts
2. Missing AJAX URL
3. Nonce verification issues
4. jQuery not loaded

**Solutions:**

1. **Check JavaScript Console**
   - Open browser developer tools
   - Look for JavaScript errors
   - Common errors: "ajaxurl is not defined"

2. **Verify AJAX Configuration**
   ```php
   // In functions.php, ensure this is present:
   wp_localize_script('course-filter', 'quicklearn_ajax', array(
       'ajaxurl' => admin_url('admin-ajax.php'),
       'nonce' => wp_create_nonce('quicklearn_filter_nonce')
   ));
   ```

3. **Test with Default Theme**
   - Temporarily switch to a default WordPress theme
   - If filtering works, there's a theme conflict

4. **Plugin Conflict Test**
   - Deactivate all other plugins
   - Test if filtering works
   - Reactivate plugins one by one to identify conflicts

### User Enrollment Issues

**Symptoms:**
- Enrollment button doesn't work
- Users can't access enrolled courses
- Progress not tracking

**Possible Causes:**
1. User permission issues
2. Database connection problems
3. Session management issues
4. Cache conflicts

**Solutions:**

1. **Check User Capabilities**
   ```php
   // Verify user can enroll
   if (current_user_can('read')) {
       // User should be able to enroll
   }
   ```

2. **Clear Cache**
   - Clear all caching plugins
   - Clear browser cache
   - Clear object cache if using Redis/Memcached

3. **Database Verification**
   ```sql
   SELECT * FROM wp_qlcm_enrollments WHERE user_id = [USER_ID];
   ```

## Installation Problems

### Plugin Activation Errors

**Error:** "Plugin could not be activated because it triggered a fatal error"

**Solutions:**

1. **Check PHP Version**
   - Ensure PHP 7.4 or higher
   - Check error logs for specific PHP errors

2. **Memory Limit Issues**
   ```php
   // Add to wp-config.php
   ini_set('memory_limit', '256M');
   ```

3. **File Permissions**
   ```bash
   # Set correct permissions
   chmod 755 wp-content/plugins/quicklearn-course-manager/
   chmod 644 wp-content/plugins/quicklearn-course-manager/*.php
   ```

### Database Table Creation Failed

**Symptoms:**
- Plugin activates but features don't work
- Database errors in logs

**Solutions:**

1. **Manual Table Creation**
   ```sql
   -- Run these queries manually in phpMyAdmin or similar
   CREATE TABLE wp_qlcm_enrollments (
       id bigint(20) NOT NULL AUTO_INCREMENT,
       user_id bigint(20) NOT NULL,
       course_id bigint(20) NOT NULL,
       enrollment_date datetime DEFAULT CURRENT_TIMESTAMP,
       status varchar(20) DEFAULT 'active',
       completion_date datetime DEFAULT NULL,
       progress_percentage int(3) DEFAULT 0,
       PRIMARY KEY (id),
       UNIQUE KEY user_course (user_id, course_id)
   );
   ```

2. **Check Database Permissions**
   - Ensure WordPress database user has CREATE TABLE permissions
   - Contact hosting provider if needed

### Theme Installation Issues

**Error:** Theme doesn't display correctly

**Solutions:**

1. **Check Theme Files**
   ```
   wp-content/themes/quicklearn-theme/
   ├── style.css (required)
   ├── index.php (required)
   ├── functions.php
   └── other template files
   ```

2. **Verify Theme Header**
   ```php
   <?php
   /*
   Theme Name: QuickLearn Theme
   Description: E-learning theme for QuickLearn platform
   Version: 1.0
   */
   ```

## Course Display Issues

### Course Content Not Showing

**Symptoms:**
- Course pages are blank
- Content appears but formatting is broken
- Images not loading

**Solutions:**

1. **Template Hierarchy Check**
   ```
   single-quick_course.php (specific)
   single.php (fallback)
   index.php (final fallback)
   ```

2. **Content Filters**
   ```php
   // Check if content is being filtered
   remove_all_filters('the_content');
   ```

3. **Image Path Issues**
   ```php
   // Verify image URLs
   $image_url = wp_get_attachment_image_src(get_post_thumbnail_id(), 'full');
   echo $image_url[0]; // Should show full URL
   ```

### Category Filtering Problems

**Symptoms:**
- Categories not showing in dropdown
- Filter returns no results
- Wrong courses displayed

**Solutions:**

1. **Taxonomy Registration**
   ```php
   // Verify taxonomy is registered
   $taxonomies = get_taxonomies();
   if (in_array('course_category', $taxonomies)) {
       echo "Taxonomy registered correctly";
   }
   ```

2. **Term Assignment**
   ```php
   // Check if courses have categories assigned
   $terms = wp_get_post_terms($course_id, 'course_category');
   var_dump($terms);
   ```

3. **Query Issues**
   ```php
   // Debug the filter query
   $args = array(
       'post_type' => 'quick_course',
       'tax_query' => array(
           array(
               'taxonomy' => 'course_category',
               'field' => 'term_id',
               'terms' => $category_id
           )
       )
   );
   $query = new WP_Query($args);
   ```

## Enrollment Problems

### Enrollment Button Not Working

**Symptoms:**
- Button doesn't respond to clicks
- No enrollment recorded in database
- Error messages not showing

**Solutions:**

1. **JavaScript Debugging**
   ```javascript
   // Check if event handlers are attached
   jQuery(document).ready(function($) {
       $('.enroll-button').on('click', function(e) {
           console.log('Enrollment button clicked');
           // Rest of enrollment code
       });
   });
   ```

2. **AJAX Handler Verification**
   ```php
   // Ensure AJAX handlers are registered
   add_action('wp_ajax_enroll_course', 'handle_course_enrollment');
   add_action('wp_ajax_nopriv_enroll_course', 'handle_course_enrollment');
   ```

3. **Nonce Verification**
   ```php
   // Check nonce in AJAX handler
   if (!wp_verify_nonce($_POST['nonce'], 'quicklearn_enroll_nonce')) {
       wp_die('Security check failed');
   }
   ```

### Progress Tracking Issues

**Symptoms:**
- Progress not updating
- Incorrect progress percentages
- Progress resets unexpectedly

**Solutions:**

1. **Database Integrity**
   ```sql
   -- Check for orphaned progress records
   SELECT * FROM wp_qlcm_course_progress 
   WHERE enrollment_id NOT IN (
       SELECT id FROM wp_qlcm_enrollments
   );
   ```

2. **Progress Calculation**
   ```php
   // Verify progress calculation logic
   function calculate_course_progress($enrollment_id) {
       global $wpdb;
       $total_modules = $wpdb->get_var("SELECT COUNT(*) FROM course_modules WHERE course_id = %d", $course_id);
       $completed_modules = $wpdb->get_var("SELECT COUNT(*) FROM wp_qlcm_course_progress WHERE enrollment_id = %d", $enrollment_id);
       return ($completed_modules / $total_modules) * 100;
   }
   ```

## Performance Issues

### Slow Page Loading

**Symptoms:**
- Course pages load slowly
- AJAX requests timeout
- High server resource usage

**Solutions:**

1. **Query Optimization**
   ```php
   // Add indexes to custom tables
   ALTER TABLE wp_qlcm_enrollments ADD INDEX idx_user_id (user_id);
   ALTER TABLE wp_qlcm_enrollments ADD INDEX idx_course_id (course_id);
   ALTER TABLE wp_qlcm_course_ratings ADD INDEX idx_course_rating (course_id, rating);
   ```

2. **Caching Implementation**
   ```php
   // Cache course data
   $cache_key = 'course_data_' . $course_id;
   $course_data = wp_cache_get($cache_key);
   
   if (false === $course_data) {
       $course_data = get_course_data($course_id);
       wp_cache_set($cache_key, $course_data, '', 3600); // 1 hour
   }
   ```

3. **Image Optimization**
   ```php
   // Lazy load images
   add_filter('wp_get_attachment_image_attributes', function($attr) {
       $attr['loading'] = 'lazy';
       return $attr;
   });
   ```

### Database Performance

**Symptoms:**
- Slow queries
- Database timeouts
- High CPU usage

**Solutions:**

1. **Query Analysis**
   ```sql
   -- Enable slow query log
   SET GLOBAL slow_query_log = 'ON';
   SET GLOBAL long_query_time = 2;
   ```

2. **Index Optimization**
   ```sql
   -- Add missing indexes
   SHOW INDEX FROM wp_qlcm_enrollments;
   EXPLAIN SELECT * FROM wp_qlcm_enrollments WHERE user_id = 123;
   ```

3. **Database Cleanup**
   ```php
   // Clean up old data
   function cleanup_old_progress_data() {
       global $wpdb;
       $wpdb->query("
           DELETE FROM wp_qlcm_course_progress 
           WHERE enrollment_id NOT IN (
               SELECT id FROM wp_qlcm_enrollments
           )
       ");
   }
   ```

## Security Issues

### CSRF/Nonce Failures

**Symptoms:**
- Forms fail with security errors
- AJAX requests rejected
- "Security check failed" messages

**Solutions:**

1. **Nonce Generation**
   ```php
   // Ensure nonces are generated correctly
   $nonce = wp_create_nonce('quicklearn_action_nonce');
   ```

2. **Nonce Verification**
   ```php
   // Proper nonce verification
   if (!wp_verify_nonce($_POST['nonce'], 'quicklearn_action_nonce')) {
       wp_send_json_error('Security verification failed');
   }
   ```

3. **Cache Issues with Nonces**
   ```php
   // Exclude nonce-containing pages from cache
   if (!defined('DONOTCACHEPAGE')) {
       define('DONOTCACHEPAGE', true);
   }
   ```

### Permission Issues

**Symptoms:**
- Users can't access features they should
- Admin functions not working
- Unauthorized access errors

**Solutions:**

1. **Capability Checks**
   ```php
   // Verify user capabilities
   if (!current_user_can('manage_courses')) {
       wp_die('Insufficient permissions');
   }
   ```

2. **Role Assignment**
   ```php
   // Check user roles
   $user = wp_get_current_user();
   if (in_array('instructor', $user->roles)) {
       // User is instructor
   }
   ```

## Database Problems

### Connection Issues

**Symptoms:**
- "Error establishing database connection"
- Intermittent database errors
- Data not saving

**Solutions:**

1. **Connection Testing**
   ```php
   // Test database connection
   global $wpdb;
   if ($wpdb->last_error) {
       error_log('Database error: ' . $wpdb->last_error);
   }
   ```

2. **Connection Limits**
   ```php
   // Check connection limits
   $result = $wpdb->get_results("SHOW PROCESSLIST");
   echo "Active connections: " . count($result);
   ```

### Data Corruption

**Symptoms:**
- Missing enrollment records
- Incorrect progress data
- Duplicate entries

**Solutions:**

1. **Data Integrity Check**
   ```sql
   -- Check for orphaned records
   SELECT e.* FROM wp_qlcm_enrollments e
   LEFT JOIN wp_posts p ON e.course_id = p.ID
   WHERE p.ID IS NULL;
   
   SELECT e.* FROM wp_qlcm_enrollments e
   LEFT JOIN wp_users u ON e.user_id = u.ID
   WHERE u.ID IS NULL;
   ```

2. **Data Repair**
   ```sql
   -- Remove orphaned enrollments
   DELETE e FROM wp_qlcm_enrollments e
   LEFT JOIN wp_posts p ON e.course_id = p.ID
   WHERE p.ID IS NULL;
   ```

## Diagnostic Tools

### Debug Mode

**Enable WordPress Debug Mode:**
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Custom Debug Functions

```php
// Add to functions.php for debugging
function quicklearn_debug_log($message, $data = null) {
    if (WP_DEBUG) {
        $log_message = '[QuickLearn Debug] ' . $message;
        if ($data) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        error_log($log_message);
    }
}

// Usage
quicklearn_debug_log('Enrollment attempt', array(
    'user_id' => $user_id,
    'course_id' => $course_id
));
```

### Health Check Script

```php
// Create a health check page
function quicklearn_health_check() {
    $health = array();
    
    // Check plugin activation
    $health['plugin_active'] = is_plugin_active('quicklearn-course-manager/quicklearn-course-manager.php');
    
    // Check database tables
    global $wpdb;
    $tables = array('enrollments', 'course_progress', 'course_ratings', 'certificates');
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . 'qlcm_' . $table;
        $health['table_' . $table] = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }
    
    // Check theme
    $health['theme_active'] = get_template() === 'quicklearn-theme';
    
    // Check PHP version
    $health['php_version'] = version_compare(PHP_VERSION, '7.4', '>=');
    
    return $health;
}
```

### Log Analysis

**Common Log Locations:**
- WordPress: `/wp-content/debug.log`
- Server Error Log: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- PHP Error Log: Check `php.ini` for `error_log` setting

**Log Analysis Commands:**
```bash
# View recent errors
tail -f /wp-content/debug.log

# Search for specific errors
grep "QuickLearn" /wp-content/debug.log

# Count error occurrences
grep -c "Fatal error" /wp-content/debug.log
```

### Performance Profiling

```php
// Add performance monitoring
function quicklearn_start_timer() {
    return microtime(true);
}

function quicklearn_end_timer($start_time, $operation) {
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
    
    if ($execution_time > 1000) { // Log slow operations (>1 second)
        error_log("Slow operation: $operation took {$execution_time}ms");
    }
}

// Usage
$start = quicklearn_start_timer();
// ... your code here ...
quicklearn_end_timer($start, 'Course enrollment');
```

## Getting Additional Help

### Information to Collect

When seeking help, collect this information:

1. **System Information**
   - WordPress version
   - PHP version
   - MySQL version
   - Active theme and plugins
   - Server configuration

2. **Error Details**
   - Exact error messages
   - Steps to reproduce
   - Browser and version
   - Screenshots if applicable

3. **Log Files**
   - WordPress debug log
   - Server error logs
   - Browser console errors

### Support Channels

1. **Documentation Review**
   - Check all documentation files
   - Review FAQ section
   - Search for similar issues

2. **Community Support**
   - WordPress support forums
   - Plugin-specific forums
   - Developer communities

3. **Professional Support**
   - Contact system administrator
   - Hire WordPress developer
   - Consult hosting provider

### Temporary Workarounds

While waiting for permanent fixes:

1. **Disable Problematic Features**
   ```php
   // Temporarily disable AJAX filtering
   remove_action('wp_ajax_filter_courses', 'handle_course_filtering');
   remove_action('wp_ajax_nopriv_filter_courses', 'handle_course_filtering');
   ```

2. **Use Fallback Methods**
   ```php
   // Fallback to standard WordPress queries
   if (!function_exists('quicklearn_get_courses')) {
       function quicklearn_get_courses() {
           return get_posts(array('post_type' => 'quick_course'));
       }
   }
   ```

3. **Increase Resource Limits**
   ```php
   // Temporary resource increase
   ini_set('memory_limit', '512M');
   ini_set('max_execution_time', 300);
   ```

Remember to document any temporary changes and revert them once permanent solutions are implemented.