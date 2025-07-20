<?php
/**
 * Performance Test Suite for QuickLearn Course Manager
 * Tests system performance under various load conditions
 */

class QLCM_Performance_Test_Suite {
    
    private $results = array();
    private $test_data = array();
    
    public function __construct() {
        $this->setup_test_environment();
    }
    
    /**
     * Setup test environment with sample data
     */
    private function setup_test_environment() {
        // Create test data for performance testing
        $this->test_data = QLCM_Test_Utilities::create_performance_test_data(100, 20);
    }
    
    /**
     * Run all performance tests
     */
    public function run_all_tests() {
        echo "QuickLearn Course Manager - Performance Test Suite\n";
        echo "================================================\n\n";
        
        $this->test_database_performance();
        $this->test_ajax_performance();
        $this->test_enrollment_performance();
        $this->test_rating_performance();
        $this->test_certificate_performance();
        $this->test_seo_performance();
        $this->test_multimedia_performance();
        $this->test_concurrent_user_performance();
        $this->test_memory_usage();
        $this->test_cache_performance();
        
        $this->generate_performance_report();
    }
    
    /**
     * Test database query performance
     */
    private function test_database_performance() {
        echo "1. Database Performance Tests\n";
        echo "============================\n";
        
        // Test course listing query
        $start_time = microtime(true);
        $query = new WP_Query(array(
            'post_type' => 'quick_course',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => array(
                array(
                    'key' => '_course_difficulty',
                    'value' => 'intermediate',
                    'compare' => '='
                )
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => 'web-development'
                )
            )
        ));
        $query_time = microtime(true) - $start_time;
        
        $this->record_result('Database - Complex Course Query', $query_time, 1.0);
        wp_reset_postdata();
        
        // Test enrollment queries
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        
        $start_time = microtime(true);
        $enrollments = $enrollment_instance->get_user_enrollments(1, 50);
        $enrollment_query_time = microtime(true) - $start_time;
        
        $this->record_result('Database - User Enrollments Query', $enrollment_query_time, 0.5);
        
        // Test rating aggregation
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        
        $start_time = microtime(true);
        $average_rating = $ratings_instance->get_average_rating($this->test_data['courses'][0]);
        $rating_query_time = microtime(true) - $start_time;
        
        $this->record_result('Database - Rating Aggregation', $rating_query_time, 0.3);
        
        // Test analytics queries
        $analytics_instance = QLCM_Analytics_Reporting::get_instance();
        
        $start_time = microtime(true);
        $stats = $analytics_instance->get_system_statistics();
        $analytics_time = microtime(true) - $start_time;
        
        $this->record_result('Database - Analytics Queries', $analytics_time, 2.0);
        
        echo "\n";
    }
    
    /**
     * Test AJAX request performance
     */
    private function test_ajax_performance() {
        echo "2. AJAX Performance Tests\n";
        echo "========================\n";
        
        $ajax_instance = QLCM_Ajax_Handlers::get_instance();
        
        // Test course filtering
        QLCM_Test_Utilities::mock_ajax_request('filter_courses', array(
            'category' => 'performance-category-1',
            'posts_per_page' => 20,
            'paged' => 1,
        ));
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $ajax_instance->handle_course_filter();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        ob_get_clean();
        
        $ajax_time = microtime(true) - $start_time;
        $this->record_result('AJAX - Course Filtering', $ajax_time, 2.0);
        
        // Test enrollment AJAX
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        
        QLCM_Test_Utilities::mock_ajax_request('enroll_course', array(
            'course_id' => $this->test_data['courses'][0],
        ));
        
        wp_set_current_user(1);
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $enrollment_instance->handle_enrollment_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        ob_get_clean();
        
        $enrollment_ajax_time = microtime(true) - $start_time;
        $this->record_result('AJAX - Course Enrollment', $enrollment_ajax_time, 1.0);
        
        // Test rating submission AJAX
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        
        QLCM_Test_Utilities::mock_ajax_request('submit_rating', array(
            'course_id' => $this->test_data['courses'][0],
            'rating' => 5,
            'review_text' => 'Performance test review',
        ));
        
        $start_time = microtime(true);
        
        ob_start();
        try {
            $ratings_instance->handle_rating_ajax();
        } catch (WPAjaxDieContinueException $e) {
            // Expected behavior
        }
        ob_get_clean();
        
        $rating_ajax_time = microtime(true) - $start_time;
        $this->record_result('AJAX - Rating Submission', $rating_ajax_time, 1.5);
        
        echo "\n";
    }
    
    /**
     * Test enrollment system performance
     */
    private function test_enrollment_performance() {
        echo "3. Enrollment System Performance\n";
        echo "===============================\n";
        
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        
        // Test batch enrollment
        $user_ids = array();
        for ($i = 1; $i <= 10; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        $start_time = microtime(true);
        
        foreach ($user_ids as $user_id) {
            $enrollment_instance->enroll_user($user_id, $this->test_data['courses'][0]);
        }
        
        $batch_enrollment_time = microtime(true) - $start_time;
        $this->record_result('Enrollment - Batch Processing (10 users)', $batch_enrollment_time, 3.0);
        
        // Test progress updates
        $start_time = microtime(true);
        
        foreach ($user_ids as $user_id) {
            $enrollment_instance->update_progress($user_id, $this->test_data['courses'][0], 'module_1', rand(25, 100));
        }
        
        $progress_update_time = microtime(true) - $start_time;
        $this->record_result('Enrollment - Progress Updates (10 users)', $progress_update_time, 2.0);
        
        // Test dashboard data retrieval
        $start_time = microtime(true);
        $dashboard_data = $enrollment_instance->get_user_dashboard_data($user_ids[0]);
        $dashboard_time = microtime(true) - $start_time;
        
        $this->record_result('Enrollment - Dashboard Data Retrieval', $dashboard_time, 1.0);
        
        echo "\n";
    }
    
    /**
     * Test rating system performance
     */
    private function test_rating_performance() {
        echo "4. Rating System Performance\n";
        echo "===========================\n";
        
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        
        // Test batch rating submission
        $user_ids = array();
        for ($i = 1; $i <= 20; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        $start_time = microtime(true);
        
        foreach ($user_ids as $user_id) {
            $ratings_instance->submit_rating($user_id, $this->test_data['courses'][0], rand(1, 5), "Performance test rating");
        }
        
        $batch_rating_time = microtime(true) - $start_time;
        $this->record_result('Rating - Batch Submission (20 ratings)', $batch_rating_time, 4.0);
        
        // Test average rating calculation with many ratings
        $start_time = microtime(true);
        $average = $ratings_instance->get_average_rating($this->test_data['courses'][0]);
        $avg_calculation_time = microtime(true) - $start_time;
        
        $this->record_result('Rating - Average Calculation (20 ratings)', $avg_calculation_time, 0.5);
        
        // Test rating retrieval with pagination
        $start_time = microtime(true);
        $ratings = $ratings_instance->get_course_ratings($this->test_data['courses'][0], 1, 10);
        $rating_retrieval_time = microtime(true) - $start_time;
        
        $this->record_result('Rating - Paginated Retrieval', $rating_retrieval_time, 1.0);
        
        echo "\n";
    }
    
    /**
     * Test certificate system performance
     */
    private function test_certificate_performance() {
        echo "5. Certificate System Performance\n";
        echo "================================\n";
        
        $certificate_instance = QLCM_Certificate_System::get_instance();
        
        // Test batch certificate generation
        $user_ids = array();
        for ($i = 1; $i <= 10; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        $start_time = microtime(true);
        
        $certificate_ids = array();
        foreach ($user_ids as $user_id) {
            $certificate_id = $certificate_instance->generate_certificate($user_id, $this->test_data['courses'][0]);
            $certificate_ids[] = $certificate_id;
        }
        
        $batch_generation_time = microtime(true) - $start_time;
        $this->record_result('Certificate - Batch Generation (10 certificates)', $batch_generation_time, 5.0);
        
        // Test PDF generation
        $start_time = microtime(true);
        $pdf_path = $certificate_instance->generate_pdf_certificate($certificate_ids[0]);
        $pdf_generation_time = microtime(true) - $start_time;
        
        $this->record_result('Certificate - PDF Generation', $pdf_generation_time, 3.0);
        
        // Test certificate verification
        $start_time = microtime(true);
        
        foreach ($certificate_ids as $certificate_id) {
            $verification = $certificate_instance->verify_certificate($certificate_id);
        }
        
        $verification_time = microtime(true) - $start_time;
        $this->record_result('Certificate - Batch Verification (10 certificates)', $verification_time, 1.0);
        
        echo "\n";
    }
    
    /**
     * Test SEO optimization performance
     */
    private function test_seo_performance() {
        echo "6. SEO Optimization Performance\n";
        echo "==============================\n";
        
        $seo_instance = QLCM_SEO_Optimization::get_instance();
        
        // Test structured data generation for multiple courses
        $start_time = microtime(true);
        
        $structured_data_batch = array();
        for ($i = 0; $i < 10; $i++) {
            $structured_data = $seo_instance->generate_course_structured_data($this->test_data['courses'][$i]);
            $structured_data_batch[] = $structured_data;
        }
        
        $structured_data_time = microtime(true) - $start_time;
        $this->record_result('SEO - Structured Data Generation (10 courses)', $structured_data_time, 2.0);
        
        // Test meta tags generation
        $start_time = microtime(true);
        
        for ($i = 0; $i < 10; $i++) {
            $meta_tags = $seo_instance->generate_all_meta_tags($this->test_data['courses'][$i]);
        }
        
        $meta_tags_time = microtime(true) - $start_time;
        $this->record_result('SEO - Meta Tags Generation (10 courses)', $meta_tags_time, 1.5);
        
        // Test sitemap generation
        $start_time = microtime(true);
        $sitemap_entries = $seo_instance->get_sitemap_entries();
        $sitemap_time = microtime(true) - $start_time;
        
        $this->record_result('SEO - Sitemap Generation', $sitemap_time, 3.0);
        
        echo "\n";
    }
    
    /**
     * Test multimedia content performance
     */
    private function test_multimedia_performance() {
        echo "7. Multimedia Content Performance\n";
        echo "================================\n";
        
        $multimedia_instance = QLCM_Multimedia_Content::get_instance();
        
        // Create test media files
        $upload_dir = wp_upload_dir();
        $media_files = array();
        
        for ($i = 1; $i <= 5; $i++) {
            $file_path = $upload_dir['basedir'] . "/perf-test-video-{$i}.mp4";
            file_put_contents($file_path, str_repeat("video content {$i} ", 1000));
            $media_files[] = $file_path;
        }
        
        // Test batch media processing
        $start_time = microtime(true);
        $attachment_ids = $multimedia_instance->batch_create_attachments($media_files);
        $batch_processing_time = microtime(true) - $start_time;
        
        $this->record_result('Multimedia - Batch Processing (5 files)', $batch_processing_time, 5.0);
        
        // Test video player generation
        $start_time = microtime(true);
        
        foreach ($attachment_ids as $attachment_id) {
            $player = $multimedia_instance->generate_video_player($attachment_id);
        }
        
        $player_generation_time = microtime(true) - $start_time;
        $this->record_result('Multimedia - Player Generation (5 players)', $player_generation_time, 2.0);
        
        // Test media optimization
        $start_time = microtime(true);
        
        foreach ($attachment_ids as $attachment_id) {
            $optimized = $multimedia_instance->optimize_image($attachment_id);
        }
        
        $optimization_time = microtime(true) - $start_time;
        $this->record_result('Multimedia - Optimization (5 files)', $optimization_time, 3.0);
        
        // Cleanup
        foreach ($media_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        echo "\n";
    }
    
    /**
     * Test concurrent user performance simulation
     */
    private function test_concurrent_user_performance() {
        echo "8. Concurrent User Performance\n";
        echo "=============================\n";
        
        // Simulate multiple users performing actions simultaneously
        $start_time = microtime(true);
        
        // Create 20 users
        $user_ids = array();
        for ($i = 1; $i <= 20; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        $enrollment_instance = QLCM_User_Enrollment::get_instance();
        $ratings_instance = QLCM_Course_Ratings::get_instance();
        
        // Simulate concurrent enrollments and ratings
        foreach ($user_ids as $index => $user_id) {
            $course_id = $this->test_data['courses'][$index % count($this->test_data['courses'])];
            
            // Enroll user
            $enrollment_instance->enroll_user($user_id, $course_id);
            
            // Update progress
            $enrollment_instance->update_progress($user_id, $course_id, 'module_1', rand(25, 100));
            
            // Submit rating
            $ratings_instance->submit_rating($user_id, $course_id, rand(1, 5), "Concurrent test rating");
        }
        
        $concurrent_time = microtime(true) - $start_time;
        $this->record_result('Concurrent - 20 Users (Enroll + Progress + Rate)', $concurrent_time, 10.0);
        
        echo "\n";
    }
    
    /**
     * Test memory usage
     */
    private function test_memory_usage() {
        echo "9. Memory Usage Tests\n";
        echo "====================\n";
        
        $initial_memory = memory_get_usage();
        $initial_peak = memory_get_peak_usage();
        
        // Perform memory-intensive operations
        $query = new WP_Query(array(
            'post_type' => 'quick_course',
            'posts_per_page' => 100,
            'meta_query' => array(
                array(
                    'key' => '_course_level',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $courses = $query->posts;
        
        // Process each course
        foreach ($courses as $course) {
            $meta = get_post_meta($course->ID);
            $terms = wp_get_post_terms($course->ID, 'course_category');
        }
        
        wp_reset_postdata();
        
        $final_memory = memory_get_usage();
        $final_peak = memory_get_peak_usage();
        
        $memory_used = $final_memory - $initial_memory;
        $peak_increase = $final_peak - $initial_peak;
        
        echo "  Memory Usage: " . $this->format_bytes($memory_used) . "\n";
        echo "  Peak Memory Increase: " . $this->format_bytes($peak_increase) . "\n";
        
        // Check if memory usage is reasonable (less than 50MB for this operation)
        $memory_limit = 50 * 1024 * 1024; // 50MB
        if ($memory_used < $memory_limit) {
            echo "  ✓ Memory usage within acceptable limits\n";
        } else {
            echo "  ✗ Memory usage exceeds acceptable limits\n";
        }
        
        echo "\n";
    }
    
    /**
     * Test cache performance
     */
    private function test_cache_performance() {
        echo "10. Cache Performance Tests\n";
        echo "==========================\n";
        
        $course_id = $this->test_data['courses'][0];
        
        // Test without cache
        wp_cache_flush();
        
        $start_time = microtime(true);
        $query1 = new WP_Query(array(
            'post_type' => 'quick_course',
            'p' => $course_id
        ));
        $time_without_cache = microtime(true) - $start_time;
        
        wp_reset_postdata();
        
        // Test with cache (second identical query)
        $start_time = microtime(true);
        $query2 = new WP_Query(array(
            'post_type' => 'quick_course',
            'p' => $course_id
        ));
        $time_with_cache = microtime(true) - $start_time;
        
        wp_reset_postdata();
        
        $cache_improvement = (($time_without_cache - $time_with_cache) / $time_without_cache) * 100;
        
        echo "  Query without cache: " . round($time_without_cache * 1000, 2) . "ms\n";
        echo "  Query with cache: " . round($time_with_cache * 1000, 2) . "ms\n";
        echo "  Cache improvement: " . round($cache_improvement, 1) . "%\n";
        
        if ($cache_improvement > 10) {
            echo "  ✓ Cache providing significant performance improvement\n";
        } else {
            echo "  ⚠ Cache improvement minimal or not working\n";
        }
        
        echo "\n";
    }
    
    /**
     * Record performance test result
     */
    private function record_result($test_name, $actual_time, $target_time) {
        $status = $actual_time <= $target_time ? 'PASS' : 'FAIL';
        $formatted_time = round($actual_time * 1000, 2);
        $target_formatted = round($target_time * 1000, 2);
        
        echo "  {$test_name}: {$formatted_time}ms (Target: {$target_formatted}ms) - {$status}\n";
        
        $this->results[] = array(
            'test' => $test_name,
            'actual_time' => $actual_time,
            'target_time' => $target_time,
            'status' => $status,
            'formatted_time' => $formatted_time,
            'target_formatted' => $target_formatted
        );
    }
    
    /**
     * Format bytes for display
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Generate performance report
     */
    private function generate_performance_report() {
        echo "Performance Test Summary\n";
        echo "=======================\n";
        
        $total_tests = count($this->results);
        $passed_tests = 0;
        $failed_tests = 0;
        
        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed_tests++;
            } else {
                $failed_tests++;
            }
        }
        
        $success_rate = ($passed_tests / $total_tests) * 100;
        
        echo "Total Performance Tests: {$total_tests}\n";
        echo "Passed: {$passed_tests}\n";
        echo "Failed: {$failed_tests}\n";
        echo "Success Rate: " . round($success_rate, 1) . "%\n\n";
        
        if ($failed_tests > 0) {
            echo "Failed Tests:\n";
            echo "============\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  ✗ {$result['test']}: {$result['formatted_time']}ms (Target: {$result['target_formatted']}ms)\n";
                }
            }
            echo "\n";
        }
        
        // Generate detailed performance report
        $this->generate_detailed_performance_report();
        
        echo "Performance testing complete!\n";
    }
    
    /**
     * Generate detailed performance report
     */
    private function generate_detailed_performance_report() {
        $report_file = dirname(__FILE__) . '/performance-report.html';
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>QuickLearn Course Manager - Performance Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #0073aa; color: white; padding: 20px; border-radius: 5px; }
        .summary { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .test-result.pass { border-left-color: #46b450; background: #f0f8f0; }
        .test-result.fail { border-left-color: #dc3232; background: #fdf0f0; }
        .performance-chart { margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .pass { color: #46b450; font-weight: bold; }
        .fail { color: #dc3232; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QuickLearn Course Manager - Performance Report</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
    </div>';
        
        $total_tests = count($this->results);
        $passed_tests = array_filter($this->results, function($r) { return $r['status'] === 'PASS'; });
        $failed_tests = array_filter($this->results, function($r) { return $r['status'] === 'FAIL'; });
        
        $html .= '
    <div class="summary">
        <h2>Performance Summary</h2>
        <p><strong>Total Tests:</strong> ' . $total_tests . '</p>
        <p><strong>Passed:</strong> <span class="pass">' . count($passed_tests) . '</span></p>
        <p><strong>Failed:</strong> <span class="fail">' . count($failed_tests) . '</span></p>
        <p><strong>Success Rate:</strong> ' . round((count($passed_tests) / $total_tests) * 100, 1) . '%</p>
    </div>
    
    <div class="details">
        <h2>Detailed Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Actual Time (ms)</th>
                    <th>Target Time (ms)</th>
                    <th>Status</th>
                    <th>Performance Ratio</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($this->results as $result) {
            $ratio = round(($result['actual_time'] / $result['target_time']) * 100, 1);
            $status_class = strtolower($result['status']);
            
            $html .= '<tr>
                <td>' . $result['test'] . '</td>
                <td>' . $result['formatted_time'] . '</td>
                <td>' . $result['target_formatted'] . '</td>
                <td class="' . $status_class . '">' . $result['status'] . '</td>
                <td>' . $ratio . '%</td>
            </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
    </div>
</body>
</html>';
        
        file_put_contents($report_file, $html);
        echo "Detailed performance report generated: {$report_file}\n";
    }
}

// Run performance tests if this file is executed directly
if (basename($_SERVER['PHP_SELF']) === 'performance-test-suite.php') {
    $performance_suite = new QLCM_Performance_Test_Suite();
    $performance_suite->run_all_tests();
}