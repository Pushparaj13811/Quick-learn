<?php
/**
 * Certificate Generation and Management System
 *
 * @package QuickLearn_Course_Manager
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling certificate generation and management
 */
class QLCM_Certificate_System {
    
    /**
     * Instance of this class
     *
     * @var QLCM_Certificate_System
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return QLCM_Certificate_System Instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Create database tables on plugin activation
        register_activation_hook(QLCM_PLUGIN_FILE, array($this, 'create_certificate_tables'));
        
        // Hook into course completion to generate certificates
        add_action('qlcm_course_completed', array($this, 'generate_certificate_on_completion'), 10, 2);
        
        // Add certificate download endpoint
        add_action('init', array($this, 'add_certificate_endpoints'));
        add_action('template_redirect', array($this, 'handle_certificate_requests'));
        
        // Add certificate verification page
        add_action('init', array($this, 'add_certificate_verification_endpoint'));
        
        // Add certificate management to admin
        add_action('admin_menu', array($this, 'add_certificate_admin_menu'));
        
        // Add certificate section to user dashboard
        add_action('qlcm_user_dashboard_after_courses', array($this, 'add_certificates_to_dashboard'));
        
        // Add certificate management shortcode
        add_shortcode('quicklearn_certificates', array($this, 'certificate_management_shortcode'));
        
        // Enqueue certificate styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_certificate_scripts'));
        
        // Add certificate meta box to course edit screen
        add_action('add_meta_boxes', array($this, 'add_certificate_meta_box'));
        
        // Save certificate settings
        add_action('save_post', array($this, 'save_certificate_settings'));
    }
    
    /**
     * Create database tables for certificates
     */
    public function create_certificate_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for certificates
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        $sql = "CREATE TABLE $certificates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            certificate_id varchar(50) NOT NULL,
            issue_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            certificate_data longtext,
            template_id varchar(50) DEFAULT 'default',
            verification_code varchar(100) NOT NULL,
            download_count int(10) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY certificate_id (certificate_id),
            KEY verification_code (verification_code),
            UNIQUE KEY user_course_cert (user_id, course_id)
        ) $charset_collate;";
        
        // Table for certificate templates
        $templates_table = $wpdb->prefix . 'qlcm_certificate_templates';
        
        $sql .= "CREATE TABLE $templates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_name varchar(100) NOT NULL,
            template_slug varchar(50) NOT NULL,
            template_data longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY template_slug (template_slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert default certificate template
        $this->create_default_certificate_template();
        
        // Update database version
        update_option('qlcm_certificate_db_version', '1.0');
    }
    
    /**
     * Create default certificate template
     */
    private function create_default_certificate_template() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'qlcm_certificate_templates';
        
        // Check if default template already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $templates_table WHERE template_slug = %s",
            'default'
        ));
        
        if ($existing) {
            return;
        }
        
        $default_template = array(
            'background_color' => '#ffffff',
            'border_color' => '#2c3e50',
            'border_width' => 3,
            'header_text' => 'Certificate of Completion',
            'header_font_size' => 28,
            'header_color' => '#2c3e50',
            'body_text' => 'This is to certify that',
            'body_font_size' => 16,
            'body_color' => '#34495e',
            'name_font_size' => 24,
            'name_color' => '#e74c3c',
            'course_text' => 'has successfully completed the course',
            'course_font_size' => 16,
            'course_color' => '#34495e',
            'course_name_font_size' => 20,
            'course_name_color' => '#2c3e50',
            'date_text' => 'Date of Completion:',
            'date_font_size' => 14,
            'date_color' => '#7f8c8d',
            'signature_text' => 'QuickLearn Academy',
            'signature_font_size' => 16,
            'signature_color' => '#2c3e50',
            'logo_url' => '',
            'show_verification_code' => true,
            'verification_text' => 'Verification Code:',
            'verification_font_size' => 12,
            'verification_color' => '#95a5a6'
        );
        
        $wpdb->insert(
            $templates_table,
            array(
                'template_name' => 'Default Certificate',
                'template_slug' => 'default',
                'template_data' => wp_json_encode($default_template),
                'is_default' => 1,
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Generate certificate on course completion
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     */
    public function generate_certificate_on_completion($user_id, $course_id) {
        // Check if certificate already exists
        if ($this->get_user_certificate($user_id, $course_id)) {
            return;
        }
        
        // Generate unique certificate ID
        $certificate_id = $this->generate_certificate_id();
        
        // Generate verification code
        $verification_code = $this->generate_verification_code();
        
        // Get course certificate settings
        $template_id = get_post_meta($course_id, '_qlcm_certificate_template', true) ?: 'default';
        
        // Prepare certificate data
        $certificate_data = array(
            'user_name' => get_userdata($user_id)->display_name,
            'course_title' => get_the_title($course_id),
            'completion_date' => current_time('mysql'),
            'issue_date' => current_time('mysql'),
            'template_id' => $template_id
        );
        
        // Save certificate to database
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        $result = $wpdb->insert(
            $certificates_table,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'certificate_id' => $certificate_id,
                'certificate_data' => wp_json_encode($certificate_data),
                'template_id' => $template_id,
                'verification_code' => $verification_code,
                'status' => 'active'
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Trigger action for other plugins
            do_action('qlcm_certificate_generated', $user_id, $course_id, $certificate_id);
            
            // Send notification email (optional)
            $this->send_certificate_notification($user_id, $course_id, $certificate_id);
        }
    }
    
    /**
     * Generate unique certificate ID
     *
     * @return string Unique certificate ID
     */
    private function generate_certificate_id() {
        $prefix = 'QLCM';
        $timestamp = time();
        $random = wp_generate_password(6, false, false);
        
        return $prefix . '-' . $timestamp . '-' . strtoupper($random);
    }
    
    /**
     * Generate verification code
     *
     * @return string Verification code
     */
    private function generate_verification_code() {
        return wp_generate_password(32, false, false);
    }
    
    /**
     * Get user certificate for a course
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @return object|null Certificate object or null
     */
    public function get_user_certificate($user_id, $course_id) {
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $certificates_table 
            WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id,
            $course_id
        ));
    }
    
    /**
     * Get certificate by certificate ID
     *
     * @param string $certificate_id Certificate ID
     * @return object|null Certificate object or null
     */
    public function get_certificate_by_id($certificate_id) {
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $certificates_table 
            WHERE certificate_id = %s AND status = 'active'",
            $certificate_id
        ));
    }
    
    /**
     * Get certificate by verification code
     *
     * @param string $verification_code Verification code
     * @return object|null Certificate object or null
     */
    public function get_certificate_by_verification_code($verification_code) {
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $certificates_table 
            WHERE verification_code = %s AND status = 'active'",
            $verification_code
        ));
    }
    
    /**
     * Get user certificates
     *
     * @param int $user_id User ID
     * @return array Array of certificate objects
     */
    public function get_user_certificates($user_id) {
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title as course_title
            FROM $certificates_table c
            JOIN {$wpdb->posts} p ON c.course_id = p.ID
            WHERE c.user_id = %d AND c.status = 'active'
            ORDER BY c.issue_date DESC",
            $user_id
        ));
    }
    
    /**
     * Get certificate template
     *
     * @param string $template_id Template ID
     * @return array|null Template data or null
     */
    public function get_certificate_template($template_id = 'default') {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'qlcm_certificate_templates';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $templates_table 
            WHERE template_slug = %s AND status = 'active'",
            $template_id
        ));
        
        if ($template) {
            return json_decode($template->template_data, true);
        }
        
        return null;
    }
    
    /**
     * Add certificate endpoints
     */
    public function add_certificate_endpoints() {
        add_rewrite_rule(
            '^certificate/download/([^/]+)/?$',
            'index.php?certificate_action=download&certificate_id=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^certificate/verify/([^/]+)/?$',
            'index.php?certificate_action=verify&verification_code=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^certificate/verify/?$',
            'index.php?certificate_action=verify_form',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'certificate_action';
            $vars[] = 'certificate_id';
            $vars[] = 'verification_code';
            return $vars;
        });
    }
    
    /**
     * Add certificate verification endpoint
     */
    public function add_certificate_verification_endpoint() {
        // Flush rewrite rules if needed
        if (get_option('qlcm_certificate_endpoints_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('qlcm_certificate_endpoints_flushed', '1');
        }
    }
    
    /**
     * Handle certificate requests
     */
    public function handle_certificate_requests() {
        $action = get_query_var('certificate_action');
        
        if (!$action) {
            return;
        }
        
        switch ($action) {
            case 'download':
                $this->handle_certificate_download();
                break;
            case 'verify':
                $this->handle_certificate_verification();
                break;
            case 'verify_form':
                $this->show_certificate_verification_form();
                break;
        }
    }
    
    /**
     * Handle certificate download
     */
    private function handle_certificate_download() {
        $certificate_id = get_query_var('certificate_id');
        
        if (!$certificate_id) {
            wp_die(__('Invalid certificate ID', 'quicklearn-course-manager'));
        }
        
        // Get certificate
        $certificate = $this->get_certificate_by_id($certificate_id);
        
        if (!$certificate) {
            wp_die(__('Certificate not found', 'quicklearn-course-manager'));
        }
        
        // Check if user has permission to download
        if (!is_user_logged_in() || get_current_user_id() != $certificate->user_id) {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to download this certificate', 'quicklearn-course-manager'));
            }
        }
        
        // Generate and serve PDF
        $this->generate_certificate_pdf($certificate);
        exit;
    }
    
    /**
     * Handle certificate verification
     */
    private function handle_certificate_verification() {
        $verification_code = get_query_var('verification_code');
        
        if (!$verification_code) {
            wp_redirect(home_url('/certificate/verify/'));
            exit;
        }
        
        $certificate = $this->get_certificate_by_verification_code($verification_code);
        
        // Show verification result
        $this->show_certificate_verification_result($certificate, $verification_code);
    }
    
    /**
     * Show certificate verification form
     */
    private function show_certificate_verification_form() {
        get_header();
        
        echo '<div class="qlcm-certificate-verification">';
        echo '<div class="container">';
        echo '<h1>' . __('Certificate Verification', 'quicklearn-course-manager') . '</h1>';
        
        echo '<form method="get" action="' . home_url('/certificate/verify/') . '">';
        echo '<div class="form-group">';
        echo '<label for="verification_code">' . __('Enter Verification Code:', 'quicklearn-course-manager') . '</label>';
        echo '<input type="text" id="verification_code" name="verification_code" required>';
        echo '</div>';
        echo '<button type="submit" class="qlcm-button">' . __('Verify Certificate', 'quicklearn-course-manager') . '</button>';
        echo '</form>';
        
        echo '</div>';
        echo '</div>';
        
        get_footer();
        exit;
    }
    
    /**
     * Show certificate verification result
     *
     * @param object|null $certificate Certificate object or null
     * @param string $verification_code Verification code
     */
    private function show_certificate_verification_result($certificate, $verification_code) {
        get_header();
        
        echo '<div class="qlcm-certificate-verification-result">';
        echo '<div class="container">';
        echo '<h1>' . __('Certificate Verification Result', 'quicklearn-course-manager') . '</h1>';
        
        if ($certificate) {
            $certificate_data = json_decode($certificate->certificate_data, true);
            
            echo '<div class="qlcm-verification-success">';
            echo '<div class="qlcm-success-icon">✓</div>';
            echo '<h2>' . __('Certificate Verified', 'quicklearn-course-manager') . '</h2>';
            
            echo '<div class="qlcm-certificate-details">';
            echo '<p><strong>' . __('Certificate ID:', 'quicklearn-course-manager') . '</strong> ' . esc_html($certificate->certificate_id) . '</p>';
            echo '<p><strong>' . __('Student Name:', 'quicklearn-course-manager') . '</strong> ' . esc_html($certificate_data['user_name']) . '</p>';
            echo '<p><strong>' . __('Course:', 'quicklearn-course-manager') . '</strong> ' . esc_html($certificate_data['course_title']) . '</p>';
            echo '<p><strong>' . __('Issue Date:', 'quicklearn-course-manager') . '</strong> ' . date_i18n(get_option('date_format'), strtotime($certificate->issue_date)) . '</p>';
            echo '</div>';
            
            echo '</div>';
        } else {
            echo '<div class="qlcm-verification-failed">';
            echo '<div class="qlcm-error-icon">✗</div>';
            echo '<h2>' . __('Certificate Not Found', 'quicklearn-course-manager') . '</h2>';
            echo '<p>' . __('The verification code you entered is invalid or the certificate does not exist.', 'quicklearn-course-manager') . '</p>';
            echo '</div>';
        }
        
        echo '<p><a href="' . home_url('/certificate/verify/') . '" class="qlcm-button">' . __('Verify Another Certificate', 'quicklearn-course-manager') . '</a></p>';
        
        echo '</div>';
        echo '</div>';
        
        get_footer();
        exit;
    }
    
    /**
     * Generate certificate PDF
     *
     * @param object $certificate Certificate object
     */
    private function generate_certificate_pdf($certificate) {
        // Update download count
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        $wpdb->update(
            $certificates_table,
            array('download_count' => $certificate->download_count + 1),
            array('id' => $certificate->id),
            array('%d'),
            array('%d')
        );
        
        // Get certificate data and template
        $certificate_data = json_decode($certificate->certificate_data, true);
        $template = $this->get_certificate_template($certificate->template_id);
        
        // For now, we'll create a simple HTML-based certificate
        // In a production environment, you might want to use a PDF library like TCPDF or FPDF
        $this->generate_html_certificate($certificate, $certificate_data, $template);
    }
    
    /**
     * Generate HTML certificate (enhanced version with better styling)
     *
     * @param object $certificate Certificate object
     * @param array $certificate_data Certificate data
     * @param array $template Template data
     */
    private function generate_html_certificate($certificate, $certificate_data, $template) {
        // Set headers for HTML certificate download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="certificate-' . $certificate->certificate_id . '.html"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Certificate - <?php echo esc_html($certificate_data['course_title']); ?></title>
            <style>
                body {
                    font-family: 'Times New Roman', serif;
                    margin: 0;
                    padding: 40px;
                    background-color: <?php echo esc_attr($template['background_color'] ?? '#ffffff'); ?>;
                }
                .certificate {
                    max-width: 800px;
                    margin: 0 auto;
                    border: <?php echo esc_attr($template['border_width'] ?? 3); ?>px solid <?php echo esc_attr($template['border_color'] ?? '#2c3e50'); ?>;
                    padding: 60px;
                    text-align: center;
                    background: white;
                }
                .header {
                    font-size: <?php echo esc_attr($template['header_font_size'] ?? 28); ?>px;
                    color: <?php echo esc_attr($template['header_color'] ?? '#2c3e50'); ?>;
                    font-weight: bold;
                    margin-bottom: 30px;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .body-text {
                    font-size: <?php echo esc_attr($template['body_font_size'] ?? 16); ?>px;
                    color: <?php echo esc_attr($template['body_color'] ?? '#34495e'); ?>;
                    margin-bottom: 20px;
                }
                .student-name {
                    font-size: <?php echo esc_attr($template['name_font_size'] ?? 24); ?>px;
                    color: <?php echo esc_attr($template['name_color'] ?? '#e74c3c'); ?>;
                    font-weight: bold;
                    margin: 20px 0;
                    text-decoration: underline;
                }
                .course-text {
                    font-size: <?php echo esc_attr($template['course_font_size'] ?? 16); ?>px;
                    color: <?php echo esc_attr($template['course_color'] ?? '#34495e'); ?>;
                    margin-bottom: 10px;
                }
                .course-name {
                    font-size: <?php echo esc_attr($template['course_name_font_size'] ?? 20); ?>px;
                    color: <?php echo esc_attr($template['course_name_color'] ?? '#2c3e50'); ?>;
                    font-weight: bold;
                    margin: 20px 0;
                    font-style: italic;
                }
                .date-section {
                    margin: 40px 0;
                }
                .date-text {
                    font-size: <?php echo esc_attr($template['date_font_size'] ?? 14); ?>px;
                    color: <?php echo esc_attr($template['date_color'] ?? '#7f8c8d'); ?>;
                }
                .signature {
                    margin-top: 60px;
                    font-size: <?php echo esc_attr($template['signature_font_size'] ?? 16); ?>px;
                    color: <?php echo esc_attr($template['signature_color'] ?? '#2c3e50'); ?>;
                    font-weight: bold;
                }
                .verification {
                    margin-top: 40px;
                    font-size: <?php echo esc_attr($template['verification_font_size'] ?? 12); ?>px;
                    color: <?php echo esc_attr($template['verification_color'] ?? '#95a5a6'); ?>;
                    border-top: 1px solid #ecf0f1;
                    padding-top: 20px;
                }
                @media print {
                    body { padding: 0; }
                    .certificate { border: none; }
                }
            </style>
        </head>
        <body>
            <div class="certificate">
                <?php if (!empty($template['logo_url'])): ?>
                    <div class="logo">
                        <img src="<?php echo esc_url($template['logo_url']); ?>" alt="Logo" style="max-height: 80px;">
                    </div>
                <?php endif; ?>
                
                <div class="header">
                    <?php echo esc_html($template['header_text'] ?? 'Certificate of Completion'); ?>
                </div>
                
                <div class="body-text">
                    <?php echo esc_html($template['body_text'] ?? 'This is to certify that'); ?>
                </div>
                
                <div class="student-name">
                    <?php echo esc_html($certificate_data['user_name']); ?>
                </div>
                
                <div class="course-text">
                    <?php echo esc_html($template['course_text'] ?? 'has successfully completed the course'); ?>
                </div>
                
                <div class="course-name">
                    "<?php echo esc_html($certificate_data['course_title']); ?>"
                </div>
                
                <div class="date-section">
                    <div class="date-text">
                        <?php echo esc_html($template['date_text'] ?? 'Date of Completion:'); ?>
                        <?php echo date_i18n(get_option('date_format'), strtotime($certificate_data['completion_date'])); ?>
                    </div>
                </div>
                
                <div class="signature">
                    <?php echo esc_html($template['signature_text'] ?? 'QuickLearn Academy'); ?>
                </div>
                
                <?php if ($template['show_verification_code'] ?? true): ?>
                    <div class="verification">
                        <strong><?php echo esc_html($template['verification_text'] ?? 'Verification Code:'); ?></strong>
                        <?php echo esc_html($certificate->verification_code); ?><br>
                        <small>Verify at: <?php echo home_url('/certificate/verify/'); ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Send certificate notification email
     *
     * @param int $user_id User ID
     * @param int $course_id Course ID
     * @param string $certificate_id Certificate ID
     */
    private function send_certificate_notification($user_id, $course_id, $certificate_id) {
        $user = get_userdata($user_id);
        $course_title = get_the_title($course_id);
        
        $subject = sprintf(__('Certificate Available - %s', 'quicklearn-course-manager'), $course_title);
        
        $message = sprintf(
            __('Congratulations %s!

You have successfully completed the course "%s" and your certificate is now available for download.

Certificate ID: %s
Download Link: %s

Thank you for learning with us!

Best regards,
QuickLearn Academy', 'quicklearn-course-manager'),
            $user->display_name,
            $course_title,
            $certificate_id,
            home_url('/certificate/download/' . $certificate_id)
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Enqueue certificate scripts and styles
     */
    public function enqueue_certificate_scripts() {
        if (is_page() && (strpos(get_query_var('pagename'), 'certificate') !== false || 
                         has_shortcode(get_post()->post_content ?? '', 'quicklearn_dashboard'))) {
            
            wp_enqueue_style(
                'qlcm-certificates',
                QLCM_PLUGIN_URL . 'assets/css/certificates.css',
                array(),
                QLCM_VERSION
            );
            
            wp_enqueue_script(
                'qlcm-certificates',
                QLCM_PLUGIN_URL . 'assets/js/certificates.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
        }
    }
    
    /**
     * Add certificates to user dashboard
     */
    public function add_certificates_to_dashboard() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $certificates = $this->get_user_certificates($user_id);
        
        echo '<div class="qlcm-user-certificates">';
        echo '<h2>' . __('My Certificates', 'quicklearn-course-manager') . '</h2>';
        
        if (empty($certificates)) {
            echo '<div class="qlcm-no-certificates">';
            echo '<p>' . __('You have not earned any certificates yet.', 'quicklearn-course-manager') . '</p>';
            echo '<p>' . __('Complete courses to earn certificates!', 'quicklearn-course-manager') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="qlcm-certificates-grid">';
            
            foreach ($certificates as $certificate) {
                echo '<div class="qlcm-certificate-card">';
                
                echo '<div class="qlcm-certificate-icon">';
                echo '<span class="dashicons dashicons-awards"></span>';
                echo '</div>';
                
                echo '<div class="qlcm-certificate-info">';
                echo '<h3>' . esc_html($certificate->course_title) . '</h3>';
                echo '<p class="qlcm-certificate-id">' . __('Certificate ID:', 'quicklearn-course-manager') . ' ' . esc_html($certificate->certificate_id) . '</p>';
                echo '<p class="qlcm-certificate-date">' . __('Issued:', 'quicklearn-course-manager') . ' ' . date_i18n(get_option('date_format'), strtotime($certificate->issue_date)) . '</p>';
                echo '</div>';
                
                echo '<div class="qlcm-certificate-actions">';
                echo '<a href="' . home_url('/certificate/download/' . $certificate->certificate_id) . '" class="qlcm-button qlcm-download-button" target="_blank">';
                echo '<span class="dashicons dashicons-download"></span> ' . __('Download', 'quicklearn-course-manager');
                echo '</a>';
                echo '</div>';
                
                echo '</div>'; // .qlcm-certificate-card
            }
            
            echo '</div>'; // .qlcm-certificates-grid
        }
        
        echo '</div>'; // .qlcm-user-certificates
    }
    
    /**
     * Add certificate meta box to course edit screen
     */
    public function add_certificate_meta_box() {
        add_meta_box(
            'qlcm_certificate_settings',
            __('Certificate Settings', 'quicklearn-course-manager'),
            array($this, 'render_certificate_meta_box'),
            'quick_course',
            'side',
            'default'
        );
    }
    
    /**
     * Render certificate meta box
     *
     * @param WP_Post $post Current post object
     */
    public function render_certificate_meta_box($post) {
        wp_nonce_field('qlcm_certificate_settings', 'qlcm_certificate_nonce');
        
        $template_id = get_post_meta($post->ID, '_qlcm_certificate_template', true) ?: 'default';
        $enable_certificates = get_post_meta($post->ID, '_qlcm_enable_certificates', true) !== 'no';
        
        echo '<div class="qlcm-certificate-settings">';
        
        // Enable certificates checkbox
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="qlcm_enable_certificates" value="yes"' . checked($enable_certificates, true, false) . '>';
        echo ' ' . __('Enable certificates for this course', 'quicklearn-course-manager');
        echo '</label>';
        echo '</p>';
        
        // Template selection
        echo '<p>';
        echo '<label for="qlcm_certificate_template">' . __('Certificate Template:', 'quicklearn-course-manager') . '</label><br>';
        echo '<select name="qlcm_certificate_template" id="qlcm_certificate_template">';
        
        // Get available templates
        global $wpdb;
        $templates_table = $wpdb->prefix . 'qlcm_certificate_templates';
        $templates = $wpdb->get_results("SELECT * FROM $templates_table WHERE status = 'active' ORDER BY template_name");
        
        foreach ($templates as $template) {
            echo '<option value="' . esc_attr($template->template_slug) . '"' . selected($template_id, $template->template_slug, false) . '>';
            echo esc_html($template->template_name);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</p>';
        
        // Certificate statistics
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        $cert_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $certificates_table WHERE course_id = %d AND status = 'active'",
            $post->ID
        ));
        
        echo '<p><strong>' . __('Certificates Issued:', 'quicklearn-course-manager') . '</strong> ' . $cert_count . '</p>';
        
        echo '</div>';
    }
    
    /**
     * Save certificate settings
     *
     * @param int $post_id Post ID
     */
    public function save_certificate_settings($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'quick_course') {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['qlcm_certificate_nonce']) || 
            !wp_verify_nonce($_POST['qlcm_certificate_nonce'], 'qlcm_certificate_settings')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save enable certificates setting
        $enable_certificates = isset($_POST['qlcm_enable_certificates']) ? 'yes' : 'no';
        update_post_meta($post_id, '_qlcm_enable_certificates', $enable_certificates);
        
        // Save template setting
        if (isset($_POST['qlcm_certificate_template'])) {
            $template_id = sanitize_text_field($_POST['qlcm_certificate_template']);
            update_post_meta($post_id, '_qlcm_certificate_template', $template_id);
        }
    }
    
    /**
     * Add certificate admin menu
     */
    public function add_certificate_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Certificates', 'quicklearn-course-manager'),
            __('Certificates', 'quicklearn-course-manager'),
            'manage_options',
            'course-certificates',
            array($this, 'render_certificates_admin_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=quick_course',
            __('Certificate Templates', 'quicklearn-course-manager'),
            __('Certificate Templates', 'quicklearn-course-manager'),
            'manage_options',
            'certificate-templates',
            array($this, 'render_certificate_templates_page')
        );
    }
    
    /**
     * Render certificates admin page
     */
    public function render_certificates_admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Course Certificates', 'quicklearn-course-manager') . '</h1>';
        
        // Get certificates
        global $wpdb;
        $certificates_table = $wpdb->prefix . 'qlcm_certificates';
        
        $certificates = $wpdb->get_results(
            "SELECT c.*, u.display_name as user_name, p.post_title as course_title
            FROM $certificates_table c
            JOIN {$wpdb->users} u ON c.user_id = u.ID
            JOIN {$wpdb->posts} p ON c.course_id = p.ID
            WHERE c.status = 'active'
            ORDER BY c.issue_date DESC
            LIMIT 50"
        );
        
        if (empty($certificates)) {
            echo '<p>' . __('No certificates have been issued yet.', 'quicklearn-course-manager') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Certificate ID', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Student', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Course', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Issue Date', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Downloads', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Actions', 'quicklearn-course-manager') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($certificates as $certificate) {
                echo '<tr>';
                echo '<td>' . esc_html($certificate->certificate_id) . '</td>';
                echo '<td>' . esc_html($certificate->user_name) . '</td>';
                echo '<td>' . esc_html($certificate->course_title) . '</td>';
                echo '<td>' . date_i18n(get_option('date_format'), strtotime($certificate->issue_date)) . '</td>';
                echo '<td>' . esc_html($certificate->download_count) . '</td>';
                echo '<td>';
                echo '<a href="' . home_url('/certificate/download/' . $certificate->certificate_id) . '" class="button" target="_blank">' . __('Download', 'quicklearn-course-manager') . '</a> ';
                echo '<a href="' . home_url('/certificate/verify/' . $certificate->verification_code) . '" class="button" target="_blank">' . __('Verify', 'quicklearn-course-manager') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render certificate templates admin page
     */
    public function render_certificate_templates_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Certificate Templates', 'quicklearn-course-manager') . '</h1>';
        echo '<p>' . __('Manage certificate templates for your courses.', 'quicklearn-course-manager') . '</p>';
        
        // Get templates
        global $wpdb;
        $templates_table = $wpdb->prefix . 'qlcm_certificate_templates';
        
        $templates = $wpdb->get_results(
            "SELECT * FROM $templates_table WHERE status = 'active' ORDER BY template_name"
        );
        
        if (empty($templates)) {
            echo '<p>' . __('No certificate templates found.', 'quicklearn-course-manager') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Template Name', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Template Slug', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Default', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Created', 'quicklearn-course-manager') . '</th>';
            echo '<th>' . __('Actions', 'quicklearn-course-manager') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($templates as $template) {
                echo '<tr>';
                echo '<td>' . esc_html($template->template_name) . '</td>';
                echo '<td>' . esc_html($template->template_slug) . '</td>';
                echo '<td>' . ($template->is_default ? __('Yes', 'quicklearn-course-manager') : __('No', 'quicklearn-course-manager')) . '</td>';
                echo '<td>' . date_i18n(get_option('date_format'), strtotime($template->created_date)) . '</td>';
                echo '<td>';
                echo '<a href="#" class="button">' . __('Edit', 'quicklearn-course-manager') . '</a> ';
                if (!$template->is_default) {
                    echo '<a href="#" class="button button-secondary">' . __('Delete', 'quicklearn-course-manager') . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Certificate management shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function certificate_management_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_verification_form' => 'yes',
            'columns' => '2'
        ), $atts, 'quicklearn_certificates');
        if (!is_user_logged_in()) {
            return '<div class="qlcm-login-required">' . 
                   '<p>' . __('Please log in to view your certificates.', 'quicklearn-course-manager') . '</p>' .
                   '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="qlcm-button">' .
                   __('Log In', 'quicklearn-course-manager') .
                   '</a></div>';
        }
        $user_id = get_current_user_id();
        $certificates = $this->get_user_certificates($user_id);
        ob_start();
        echo '<div class="qlcm-certificate-management">';
        // Certificate statistics
        echo '<div class="qlcm-certificate-stats">';
        echo '<h2>' . __('Certificate Overview', 'quicklearn-course-manager') . '</h2>';
        echo '<div class="qlcm-stats-grid">';
        $total_certificates = count($certificates);
        $total_downloads = array_sum(array_column($certificates, 'download_count'));
        echo '<div class="qlcm-stat-card">';
        echo '<div class="qlcm-stat-number">' . $total_certificates . '</div>';
        echo '<div class="qlcm-stat-label">' . __('Certificates Earned', 'quicklearn-course-manager') . '</div>';
        echo '</div>';
        echo '<div class="qlcm-stat-card">';
        echo '<div class="qlcm-stat-number">' . $total_downloads . '</div>';
        echo '<div class="qlcm-stat-label">' . __('Total Downloads', 'quicklearn-course-manager') . '</div>';
        echo '</div>';
        echo '</div>'; // .qlcm-stats-grid
        echo '</div>'; // .qlcm-certificate-stats
        // Certificates list
        if (empty($certificates)) {
            echo '<div class="qlcm-no-certificates">';
            echo '<div class="qlcm-empty-state">';
            echo '<span class="dashicons dashicons-awards"></span>';
            echo '<h3>' . __('No Certificates Yet', 'quicklearn-course-manager') . '</h3>';
            echo '<p>' . __('Complete courses to earn your first certificate!', 'quicklearn-course-manager') . '</p>';
            echo '<a href="' . esc_url(get_post_type_archive_link('quick_course')) . '" class="qlcm-button">';
            echo __('Browse Courses', 'quicklearn-course-manager');
            echo '</a>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="qlcm-certificates-section">';
            echo '<h2>' . __('My Certificates', 'quicklearn-course-manager') . '</h2>';
            $columns_class = 'qlcm-certificates-grid-' . absint($atts['columns']);
            echo '<div class="qlcm-certificates-grid ' . $columns_class . '">';
            foreach ($certificates as $certificate) {
                $certificate_data = json_decode($certificate->certificate_data, true);
                echo '<div class="qlcm-certificate-card qlcm-detailed">';
                // Certificate header
                echo '<div class="qlcm-certificate-header">';
                echo '<div class="qlcm-certificate-icon">';
                echo '<span class="dashicons dashicons-awards"></span>';
                echo '</div>';
                echo '<div class="qlcm-certificate-badge">';
                echo '<span class="qlcm-badge-text">' . __('Certified', 'quicklearn-course-manager') . '</span>';
                echo '</div>';
                echo '</div>';
                // Certificate content
                echo '<div class="qlcm-certificate-content">';
                echo '<h3 class="qlcm-course-title">' . esc_html($certificate->course_title) . '</h3>';
                echo '<div class="qlcm-certificate-meta">';
                echo '<div class="qlcm-meta-item">';
                echo '<span class="qlcm-meta-label">' . __('Certificate ID:', 'quicklearn-course-manager') . '</span>';
                echo '<span class="qlcm-meta-value qlcm-certificate-id" title="' . __('Click to copy', 'quicklearn-course-manager') . '">' . esc_html($certificate->certificate_id) . '</span>';
                echo '</div>';
                echo '<div class="qlcm-meta-item">';
                echo '<span class="qlcm-meta-label">' . __('Issued:', 'quicklearn-course-manager') . '</span>';
                echo '<span class="qlcm-meta-value">' . date_i18n(get_option('date_format'), strtotime($certificate->issue_date)) . '</span>';
                echo '</div>';
                echo '<div class="qlcm-meta-item">';
                echo '<span class="qlcm-meta-label">' . __('Downloads:', 'quicklearn-course-manager') . '</span>';
                echo '<span class="qlcm-meta-value">' . esc_html($certificate->download_count) . '</span>';
                echo '</div>';
                echo '</div>'; // .qlcm-certificate-meta
                echo '</div>'; // .qlcm-certificate-content
                // Certificate actions
                echo '<div class="qlcm-certificate-actions">';
                echo '<a href="' . home_url('/certificate/download/' . $certificate->certificate_id) . '" class="qlcm-button qlcm-download-button" target="_blank">';
                echo '<span class="dashicons dashicons-download"></span> ' . __('Download', 'quicklearn-course-manager');
                echo '</a>';
                echo '<a href="' . home_url('/certificate/verify/' . $certificate->verification_code) . '" class="qlcm-button qlcm-verify-button" target="_blank">';
                echo '<span class="dashicons dashicons-yes-alt"></span> ' . __('Verify', 'quicklearn-course-manager');
                echo '</a>';
                echo '<button class="qlcm-button qlcm-share-button" data-certificate-id="' . esc_attr($certificate->certificate_id) . '" data-course-title="' . esc_attr($certificate->course_title) . '">';
                echo '<span class="dashicons dashicons-share"></span> ' . __('Share', 'quicklearn-course-manager');
                echo '</button>';
                echo '</div>'; // .qlcm-certificate-actions
                echo '</div>'; // .qlcm-certificate-card
            }
            echo '</div>'; // .qlcm-certificates-grid
            echo '</div>'; // .qlcm-certificates-section
        }
        // Certificate verification form (if enabled)
        if ($atts['show_verification_form'] === 'yes') {
            echo '<div class="qlcm-verification-section">';
            echo '<h2>' . __('Verify a Certificate', 'quicklearn-course-manager') . '</h2>';
            echo '<p>' . __('Enter a verification code to check if a certificate is authentic.', 'quicklearn-course-manager') . '</p>';
            echo '<form class="qlcm-verification-form" method="get" action="' . home_url('/certificate/verify/') . '">';
            echo '<div class="qlcm-form-group">';
            echo '<input type="text" name="verification_code" placeholder="' . __('Enter verification code...', 'quicklearn-course-manager') . '" required>';
            echo '<button type="submit" class="qlcm-button">' . __('Verify', 'quicklearn-course-manager') . '</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>'; // .qlcm-verification-section
        }
        echo '</div>'; // .qlcm-certificate-management
        return ob_get_clean();
   }
}

// Initialize certificate system
QLCM_Certificate_System::get_instance();