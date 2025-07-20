<?php
/**
 * Unit Tests for Certificate System
 */

class Test_Certificate_System extends WP_UnitTestCase {
    
    private $certificate_instance;
    private $admin_id;
    private $user_id;
    private $course_id;
    
    public function setUp() {
        parent::setUp();
        $this->certificate_instance = QLCM_Certificate_System::get_instance();
        
        // Create test users
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        $this->user_id = QLCM_Test_Utilities::create_regular_user();
        
        // Create test course
        wp_set_current_user($this->admin_id);
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Certificate Test Course',
            'post_content' => 'Course content for certificate testing.',
        ));
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        $this->cleanup_certificate_data();
        parent::tearDown();
    }
    
    private function cleanup_certificate_data() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}qlcm_certificates");
        
        // Clean up certificate files
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/certificates/';
        if (is_dir($cert_dir)) {
            $files = glob($cert_dir . '*.pdf');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Test certificate database table creation
     * Requirements: 12.1, 12.2
     */
    public function test_certificate_table_creation() {
        global $wpdb;
        
        // Check if certificate table exists
        $certificate_table = $wpdb->prefix . 'qlcm_certificates';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$certificate_table}'");
        $this->assertEquals($certificate_table, $table_exists);
        
        // Verify table structure
        $certificate_columns = $wpdb->get_results("DESCRIBE {$certificate_table}");
        $column_names = wp_list_pluck($certificate_columns, 'Field');
        
        $expected_columns = array('id', 'user_id', 'course_id', 'certificate_id', 'issue_date', 'certificate_data');
        foreach ($expected_columns as $column) {
            $this->assertContains($column, $column_names);
        }
    }
    
    /**
     * Test certificate generation
     * Requirements: 12.1, 12.2, 12.3
     */
    public function test_certificate_generation() {
        wp_set_current_user($this->user_id);
        
        // Generate certificate
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        $this->assertNotFalse($certificate_id);
        $this->assertNotEmpty($certificate_id);
        
        // Verify certificate in database
        $certificate = $this->certificate_instance->get_certificate($this->user_id, $this->course_id);
        $this->assertNotNull($certificate);
        $this->assertEquals($this->user_id, $certificate->user_id);
        $this->assertEquals($this->course_id, $certificate->course_id);
        $this->assertEquals($certificate_id, $certificate->certificate_id);
        $this->assertNotNull($certificate->issue_date);
        
        // Test duplicate certificate prevention
        $duplicate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        $this->assertEquals($certificate_id, $duplicate_id);
    }
    
    /**
     * Test certificate ID generation and uniqueness
     * Requirements: 12.2, 12.4
     */
    public function test_certificate_id_uniqueness() {
        $certificate_ids = array();
        
        // Generate multiple certificates
        for ($i = 1; $i <= 10; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $certificate_id = $this->certificate_instance->generate_certificate($user_id, $this->course_id);
            $certificate_ids[] = $certificate_id;
        }
        
        // Check that all IDs are unique
        $unique_ids = array_unique($certificate_ids);
        $this->assertEquals(count($certificate_ids), count($unique_ids));
        
        // Check ID format (should be alphanumeric and specific length)
        foreach ($certificate_ids as $id) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $id);
        }
    }
    
    /**
     * Test certificate data structure
     * Requirements: 12.1, 12.2
     */
    public function test_certificate_data_structure() {
        wp_set_current_user($this->user_id);
        
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        $certificate = $this->certificate_instance->get_certificate($this->user_id, $this->course_id);
        
        // Parse certificate data
        $certificate_data = json_decode($certificate->certificate_data, true);
        
        $this->assertArrayHasKey('user_name', $certificate_data);
        $this->assertArrayHasKey('course_title', $certificate_data);
        $this->assertArrayHasKey('completion_date', $certificate_data);
        $this->assertArrayHasKey('certificate_id', $certificate_data);
        $this->assertArrayHasKey('issue_date', $certificate_data);
        
        // Verify data accuracy
        $user = get_user_by('id', $this->user_id);
        $course = get_post($this->course_id);
        
        $this->assertEquals($user->display_name, $certificate_data['user_name']);
        $this->assertEquals($course->post_title, $certificate_data['course_title']);
        $this->assertEquals($certificate_id, $certificate_data['certificate_id']);
    }
    
    /**
     * Test PDF certificate generation
     * Requirements: 12.3
     */
    public function test_pdf_certificate_generation() {
        wp_set_current_user($this->user_id);
        
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        
        // Generate PDF
        $pdf_path = $this->certificate_instance->generate_pdf_certificate($certificate_id);
        $this->assertNotFalse($pdf_path);
        $this->assertFileExists($pdf_path);
        
        // Check file size (should be reasonable for a PDF)
        $file_size = filesize($pdf_path);
        $this->assertGreaterThan(1000, $file_size); // At least 1KB
        $this->assertLessThan(1000000, $file_size); // Less than 1MB
        
        // Check PDF header
        $file_content = file_get_contents($pdf_path, false, null, 0, 10);
        $this->assertStringStartsWith('%PDF-', $file_content);
    }
    
    /**
     * Test certificate verification
     * Requirements: 12.4
     */
    public function test_certificate_verification() {
        wp_set_current_user($this->user_id);
        
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        
        // Test valid certificate verification
        $verification = $this->certificate_instance->verify_certificate($certificate_id);
        $this->assertTrue($verification['valid']);
        $this->assertArrayHasKey('certificate_data', $verification);
        $this->assertArrayHasKey('user_name', $verification['certificate_data']);
        $this->assertArrayHasKey('course_title', $verification['certificate_data']);
        
        // Test invalid certificate verification
        $invalid_verification = $this->certificate_instance->verify_certificate('INVALID123');
        $this->assertFalse($invalid_verification['valid']);
        $this->assertArrayHasKey('error', $invalid_verification);
        
        // Test empty certificate ID
        $empty_verification = $this->certificate_instance->verify_certificate('');
        $this->assertFalse($empty_verification['valid']);
    }
    
    /**
     * Test certificate download functionality
     * Requirements: 12.3
     */
    public function test_certificate_download() {
        wp_set_current_user($this->user_id);
        
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        
        // Test download URL generation
        $download_url = $this->certificate_instance->get_download_url($certificate_id);
        $this->assertNotEmpty($download_url);
        $this->assertStringContains('certificate_id=' . $certificate_id, $download_url);
        
        // Test download permissions
        $can_download = $this->certificate_instance->can_user_download_certificate($this->user_id, $certificate_id);
        $this->assertTrue($can_download);
        
        // Test that other users cannot download
        $other_user_id = QLCM_Test_Utilities::create_regular_user();
        wp_set_current_user($other_user_id);
        $cannot_download = $this->certificate_instance->can_user_download_certificate($other_user_id, $certificate_id);
        $this->assertFalse($cannot_download);
        
        // Test admin can download any certificate
        wp_set_current_user($this->admin_id);
        $admin_can_download = $this->certificate_instance->can_user_download_certificate($this->admin_id, $certificate_id);
        $this->assertTrue($admin_can_download);
    }
    
    /**
     * Test certificate template customization
     * Requirements: 12.2
     */
    public function test_certificate_template() {
        // Test default template
        $default_template = $this->certificate_instance->get_certificate_template();
        $this->assertNotEmpty($default_template);
        $this->assertStringContains('{{user_name}}', $default_template);
        $this->assertStringContains('{{course_title}}', $default_template);
        $this->assertStringContains('{{completion_date}}', $default_template);
        $this->assertStringContains('{{certificate_id}}', $default_template);
        
        // Test template customization
        $custom_template = 'Custom certificate for {{user_name}} completing {{course_title}}';
        $result = $this->certificate_instance->update_certificate_template($custom_template);
        $this->assertTrue($result);
        
        $updated_template = $this->certificate_instance->get_certificate_template();
        $this->assertEquals($custom_template, $updated_template);
        
        // Test template variable replacement
        wp_set_current_user($this->user_id);
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        $certificate = $this->certificate_instance->get_certificate($this->user_id, $this->course_id);
        
        $rendered_template = $this->certificate_instance->render_certificate_template($certificate_id);
        $this->assertStringNotContains('{{user_name}}', $rendered_template);
        $this->assertStringNotContains('{{course_title}}', $rendered_template);
    }
    
    /**
     * Test certificate security
     * Requirements: 12.4
     */
    public function test_certificate_security() {
        wp_set_current_user($this->user_id);
        
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        
        // Test unauthorized access prevention
        wp_set_current_user(0); // Logout
        
        $unauthorized_download = $this->certificate_instance->can_user_download_certificate(0, $certificate_id);
        $this->assertFalse($unauthorized_download);
        
        // Test certificate ID tampering prevention
        $tampered_id = str_replace('A', 'B', $certificate_id);
        $verification = $this->certificate_instance->verify_certificate($tampered_id);
        $this->assertFalse($verification['valid']);
        
        // Test SQL injection prevention
        $malicious_id = "'; DROP TABLE certificates; --";
        $verification = $this->certificate_instance->verify_certificate($malicious_id);
        $this->assertFalse($verification['valid']);
        
        // Verify table still exists
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}qlcm_certificates'");
        $this->assertNotEmpty($table_exists);
    }
    
    /**
     * Test certificate statistics and reporting
     * Requirements: 11.2, 11.4
     */
    public function test_certificate_statistics() {
        // Generate certificates for multiple users
        $certificate_ids = array();
        for ($i = 1; $i <= 5; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $certificate_id = $this->certificate_instance->generate_certificate($user_id, $this->course_id);
            $certificate_ids[] = $certificate_id;
        }
        
        // Get certificate statistics
        $stats = $this->certificate_instance->get_certificate_statistics();
        
        $this->assertArrayHasKey('total_certificates', $stats);
        $this->assertArrayHasKey('certificates_this_month', $stats);
        $this->assertArrayHasKey('most_certified_courses', $stats);
        
        $this->assertEquals(5, $stats['total_certificates']);
        $this->assertEquals(5, $stats['certificates_this_month']);
        
        // Test course-specific certificate count
        $course_cert_count = $this->certificate_instance->get_course_certificate_count($this->course_id);
        $this->assertEquals(5, $course_cert_count);
    }
    
    /**
     * Test certificate performance
     * Requirements: 7.2
     */
    public function test_certificate_performance() {
        // Test certificate generation performance
        $start_time = microtime(true);
        
        for ($i = 1; $i <= 10; $i++) {
            $user_id = QLCM_Test_Utilities::create_regular_user();
            $this->certificate_instance->generate_certificate($user_id, $this->course_id);
        }
        
        $generation_time = microtime(true) - $start_time;
        $this->assertLessThan(5.0, $generation_time, 'Certificate generation should complete within 5 seconds');
        
        // Test PDF generation performance
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id);
        
        $start_time = microtime(true);
        $pdf_path = $this->certificate_instance->generate_pdf_certificate($certificate_id);
        $pdf_time = microtime(true) - $start_time;
        
        $this->assertLessThan(3.0, $pdf_time, 'PDF generation should complete within 3 seconds');
        
        // Test verification performance
        $start_time = microtime(true);
        $verification = $this->certificate_instance->verify_certificate($certificate_id);
        $verification_time = microtime(true) - $start_time;
        
        $this->assertLessThan(0.5, $verification_time, 'Certificate verification should complete within 0.5 seconds');
    }
    
    /**
     * Test certificate batch operations
     * Requirements: 12.1, 12.3
     */
    public function test_certificate_batch_operations() {
        // Create multiple users
        $user_ids = array();
        for ($i = 1; $i <= 5; $i++) {
            $user_ids[] = QLCM_Test_Utilities::create_regular_user();
        }
        
        // Test batch certificate generation
        $certificate_ids = $this->certificate_instance->generate_certificates_batch($user_ids, $this->course_id);
        $this->assertCount(5, $certificate_ids);
        
        foreach ($certificate_ids as $certificate_id) {
            $this->assertNotEmpty($certificate_id);
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $certificate_id);
        }
        
        // Test batch PDF generation
        $pdf_paths = $this->certificate_instance->generate_pdfs_batch($certificate_ids);
        $this->assertCount(5, $pdf_paths);
        
        foreach ($pdf_paths as $pdf_path) {
            $this->assertFileExists($pdf_path);
        }
    }
    
    /**
     * Test certificate expiration (if implemented)
     * Requirements: 12.4
     */
    public function test_certificate_expiration() {
        wp_set_current_user($this->user_id);
        
        // Generate certificate with expiration
        $certificate_id = $this->certificate_instance->generate_certificate($this->user_id, $this->course_id, array(
            'expires_in_days' => 365
        ));
        
        $certificate = $this->certificate_instance->get_certificate($this->user_id, $this->course_id);
        $certificate_data = json_decode($certificate->certificate_data, true);
        
        if (isset($certificate_data['expiration_date'])) {
            $this->assertNotEmpty($certificate_data['expiration_date']);
            
            // Test expiration check
            $is_expired = $this->certificate_instance->is_certificate_expired($certificate_id);
            $this->assertFalse($is_expired); // Should not be expired yet
            
            // Test verification of non-expired certificate
            $verification = $this->certificate_instance->verify_certificate($certificate_id);
            $this->assertTrue($verification['valid']);
        }
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Certificate_System::get_instance();
        $instance2 = QLCM_Certificate_System::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}