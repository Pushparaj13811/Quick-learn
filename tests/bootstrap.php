<?php
/**
 * PHPUnit Bootstrap File for QuickLearn Course Manager Tests
 */

// Define test environment
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');

// Define WordPress test constants
define('WP_PHP_BINARY', 'php');
define('WP_TESTS_CONFIG_FILE_PATH', dirname(__FILE__) . '/wp-tests-config.php');

// WordPress test suite path (adjust as needed)
$wp_tests_dir = getenv('WP_TESTS_DIR') ? getenv('WP_TESTS_DIR') : '/tmp/wordpress-tests-lib';

// Load WordPress test functions
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Load the plugin
    require dirname(dirname(__FILE__)) . '/wp-content/plugins/quicklearn-course-manager/quicklearn-course-manager.php';
    
    // Load theme functions
    require dirname(dirname(__FILE__)) . '/wp-content/themes/quicklearn-theme/functions.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $wp_tests_dir . '/includes/bootstrap.php';

// Load test utilities
require_once dirname(__FILE__) . '/includes/test-utilities.php';