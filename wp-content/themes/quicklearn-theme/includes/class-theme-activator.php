<?php
/**
 * QuickLearn Theme Activator
 * Handles automatic setup when theme is activated
 * 
 * @package QuickLearn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class QuickLearn_Theme_Activator {
    
    /**
     * Run the activator
     */
    public static function activate() {
        $activator = new self();
        $activator->run_activation();
    }
    
    /**
     * Main activation process
     */
    private function run_activation() {
        // Check if already activated
        if (get_option('quicklearn_theme_activated')) {
            return;
        }
        
        // Create essential pages
        $this->create_essential_pages();
        
        // Set up navigation menus
        $this->setup_navigation_menus();
        
        // Create sample content
        $this->create_sample_content();
        
        // Set up user roles and capabilities
        $this->setup_user_roles();
        
        // Configure theme options
        $this->setup_theme_options();
        
        // Create welcome content
        $this->create_welcome_content();
        
        // Create sample users
        $this->create_sample_users();
        
        // Mark theme as activated
        update_option('quicklearn_theme_activated', true);
        update_option('quicklearn_activation_date', current_time('mysql'));
        
        // Set flag to show welcome message
        set_transient('quicklearn_activation_notice', true, 60);
    }
    
    /**
     * Create essential pages
     */
    private function create_essential_pages() {
        $pages = array(
            'dashboard' => array(
                'title' => 'Dashboard',
                'content' => '[quicklearn_dashboard]',
                'template' => 'page-dashboard.php'
            ),
            'courses' => array(
                'title' => 'Courses',
                'content' => '[quicklearn_courses]',
                'template' => 'page-courses.php'
            ),
            'my-courses' => array(
                'title' => 'My Courses',
                'content' => '[quicklearn_my_courses]',
                'template' => 'page-my-courses.php'
            ),
            'profile' => array(
                'title' => 'My Profile',
                'content' => '[quicklearn_profile]',
                'template' => 'page-profile.php'
            ),
            'instructors' => array(
                'title' => 'Instructors',
                'content' => '[quicklearn_instructors]',
                'template' => 'page-instructors.php'
            ),
            'about' => array(
                'title' => 'About Us',
                'content' => $this->get_about_content(),
                'template' => 'page.php'
            ),
            'contact' => array(
                'title' => 'Contact Us',
                'content' => $this->get_contact_content(),
                'template' => 'page.php'
            ),
            'privacy-policy' => array(
                'title' => 'Privacy Policy',
                'content' => $this->get_privacy_content(),
                'template' => 'page.php'
            ),
            'terms-of-service' => array(
                'title' => 'Terms of Service',
                'content' => $this->get_terms_content(),
                'template' => 'page.php'
            ),
            'register' => array(
                'title' => 'Register',
                'content' => '',
                'template' => 'page-register.php'
            )
        );
        
        foreach ($pages as $slug => $page_data) {
            $existing_page = get_page_by_path($slug);
            
            if (!$existing_page) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug,
                    'meta_input' => array(
                        '_wp_page_template' => $page_data['template']
                    )
                ));
                
                // Store page ID for reference
                update_option("quicklearn_{$slug}_page_id", $page_id);
                
                // Set front page and dashboard
                if ($slug === 'dashboard') {
                    update_option('quicklearn_dashboard_page_id', $page_id);
                }
            }
        }
        
        // Set front page to show courses
        $courses_page = get_page_by_path('courses');
        if ($courses_page) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $courses_page->ID);
        }
    }
    
    /**
     * Set up navigation menus
     */
    private function setup_navigation_menus() {
        // Register menu locations
        register_nav_menus(array(
            'primary' => __('Primary Menu', 'quicklearn'),
            'footer' => __('Footer Menu', 'quicklearn'),
            'user' => __('User Menu', 'quicklearn')
        ));
        
        // Create primary menu
        $primary_menu_id = wp_create_nav_menu('Primary Navigation');
        
        if (!is_wp_error($primary_menu_id)) {
            // Add menu items
            $menu_items = array(
                array(
                    'title' => 'Home',
                    'url' => home_url('/'),
                    'menu_order' => 1
                ),
                array(
                    'title' => 'Courses',
                    'url' => get_page_link(get_option('quicklearn_courses_page_id')),
                    'menu_order' => 2
                ),
                array(
                    'title' => 'Instructors',
                    'url' => get_page_link(get_option('quicklearn_instructors_page_id')),
                    'menu_order' => 3
                ),
                array(
                    'title' => 'About',
                    'url' => get_page_link(get_option('quicklearn_about_page_id')),
                    'menu_order' => 4
                ),
                array(
                    'title' => 'Contact',
                    'url' => get_page_link(get_option('quicklearn_contact_page_id')),
                    'menu_order' => 5
                )
            );
            
            foreach ($menu_items as $item) {
                wp_update_nav_menu_item($primary_menu_id, 0, array(
                    'menu-item-title' => $item['title'],
                    'menu-item-url' => $item['url'],
                    'menu-item-status' => 'publish',
                    'menu-item-type' => 'custom',
                    'menu-item-position' => $item['menu_order']
                ));
            }
            
            // Assign menu to location
            $locations = get_theme_mod('nav_menu_locations');
            $locations['primary'] = $primary_menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
        
        // Create footer menu
        $footer_menu_id = wp_create_nav_menu('Footer Navigation');
        
        if (!is_wp_error($footer_menu_id)) {
            $footer_items = array(
                array(
                    'title' => 'Privacy Policy',
                    'url' => get_page_link(get_option('quicklearn_privacy-policy_page_id')),
                    'menu_order' => 1
                ),
                array(
                    'title' => 'Terms of Service',
                    'url' => get_page_link(get_option('quicklearn_terms-of-service_page_id')),
                    'menu_order' => 2
                ),
                array(
                    'title' => 'Contact',
                    'url' => get_page_link(get_option('quicklearn_contact_page_id')),
                    'menu_order' => 3
                )
            );
            
            foreach ($footer_items as $item) {
                wp_update_nav_menu_item($footer_menu_id, 0, array(
                    'menu-item-title' => $item['title'],
                    'menu-item-url' => $item['url'],
                    'menu-item-status' => 'publish',
                    'menu-item-type' => 'custom',
                    'menu-item-position' => $item['menu_order']
                ));
            }
            
            // Assign footer menu
            $locations = get_theme_mod('nav_menu_locations');
            $locations['footer'] = $footer_menu_id;
            set_theme_mod('nav_menu_locations', $locations);
        }
    }
    
    /**
     * Create sample content
     */
    private function create_sample_content() {
        // Create sample courses
        $sample_courses = array(
            array(
                'title' => 'Introduction to Web Development',
                'content' => $this->get_sample_course_content('web-development'),
                'featured_image' => 'web-dev.jpg',
                'instructor' => 'instructor_demo',
                'price' => '99.00',
                'duration' => '8 weeks',
                'level' => 'Beginner'
            ),
            array(
                'title' => 'Advanced JavaScript Programming',
                'content' => $this->get_sample_course_content('javascript'),
                'featured_image' => 'javascript.jpg',
                'instructor' => 'instructor_demo',
                'price' => '149.00',
                'duration' => '12 weeks',
                'level' => 'Advanced'
            ),
            array(
                'title' => 'UI/UX Design Fundamentals',
                'content' => $this->get_sample_course_content('ui-ux'),
                'featured_image' => 'ui-ux.jpg',
                'instructor' => 'instructor_demo',
                'price' => '79.00',
                'duration' => '6 weeks',
                'level' => 'Intermediate'
            ),
            array(
                'title' => 'Digital Marketing Mastery',
                'content' => $this->get_sample_course_content('marketing'),
                'featured_image' => 'marketing.jpg',
                'instructor' => 'instructor_demo',
                'price' => '129.00',
                'duration' => '10 weeks',
                'level' => 'Beginner'
            ),
            array(
                'title' => 'Data Science with Python',
                'content' => $this->get_sample_course_content('data-science'),
                'featured_image' => 'data-science.jpg',
                'instructor' => 'instructor_demo',
                'price' => '199.00',
                'duration' => '16 weeks',
                'level' => 'Advanced'
            )
        );
        
        foreach ($sample_courses as $course) {
            $course_id = wp_insert_post(array(
                'post_title' => $course['title'],
                'post_content' => $course['content'],
                'post_status' => 'publish',
                'post_type' => 'quick_course',
                'meta_input' => array(
                    'course_price' => $course['price'],
                    'course_duration' => $course['duration'],
                    'course_level' => $course['level'],
                    'course_instructor' => $course['instructor']
                )
            ));
            
            // Set course categories
            wp_set_object_terms($course_id, $course['level'], 'course_level');
        }
        
        // Create course categories
        $categories = array('Web Development', 'Programming', 'Design', 'Marketing', 'Data Science');
        foreach ($categories as $category) {
            if (!term_exists($category, 'course_category')) {
                wp_insert_term($category, 'course_category');
            }
        }
        
        // Create course levels
        $levels = array('Beginner', 'Intermediate', 'Advanced');
        foreach ($levels as $level) {
            if (!term_exists($level, 'course_level')) {
                wp_insert_term($level, 'course_level');
            }
        }
    }
    
    /**
     * Set up user roles and capabilities
     */
    private function setup_user_roles() {
        // Remove existing custom roles
        remove_role('qlcm_instructor');
        remove_role('qlcm_student');
        remove_role('qlcm_course_moderator');
        
        // Add Instructor role
        add_role('qlcm_instructor', 'Course Instructor', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'publish_posts' => true,
            'upload_files' => true,
            'qlcm_create_courses' => true,
            'qlcm_edit_courses' => true,
            'qlcm_delete_courses' => true,
            'qlcm_manage_students' => true,
            'qlcm_view_reports' => true
        ));
        
        // Add Student role
        add_role('qlcm_student', 'Student', array(
            'read' => true,
            'qlcm_enroll_courses' => true,
            'qlcm_view_courses' => true,
            'qlcm_submit_assignments' => true,
            'qlcm_participate_discussions' => true
        ));
        
        // Add Course Moderator role
        add_role('qlcm_course_moderator', 'Course Moderator', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
            'edit_others_posts' => true,
            'delete_others_posts' => true,
            'qlcm_moderate_courses' => true,
            'qlcm_manage_users' => true,
            'qlcm_view_all_reports' => true
        ));
        
        // Add capabilities to Administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'qlcm_create_courses',
                'qlcm_edit_courses',
                'qlcm_delete_courses',
                'qlcm_manage_students',
                'qlcm_view_reports',
                'qlcm_moderate_courses',
                'qlcm_manage_users',
                'qlcm_view_all_reports'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Set up theme options
     */
    private function setup_theme_options() {
        // Set default customizer options
        set_theme_mod('site_logo', get_template_directory_uri() . '/assets/images/logo.png');
        set_theme_mod('primary_color', '#3498db');
        set_theme_mod('secondary_color', '#2c3e50');
        set_theme_mod('accent_color', '#e74c3c');
        
        // Course settings
        update_option('quicklearn_courses_per_page', 12);
        update_option('quicklearn_enable_course_reviews', true);
        update_option('quicklearn_enable_course_ratings', true);
        update_option('quicklearn_currency_symbol', '$');
        update_option('quicklearn_currency_position', 'left');
        
        // Email settings
        update_option('quicklearn_enrollment_email', true);
        update_option('quicklearn_completion_email', true);
        update_option('quicklearn_instructor_notification', true);
        
        // General settings
        update_option('quicklearn_enable_certificates', true);
        update_option('quicklearn_enable_progress_tracking', true);
        update_option('quicklearn_enable_discussions', true);
        update_option('quicklearn_enable_assignments', true);
        
        // Site identity
        update_option('blogname', 'QuickLearn Academy');
        update_option('blogdescription', 'Master New Skills with Expert-Led Online Courses');
        
        // Enable user registration
        update_option('users_can_register', 1);
        update_option('default_role', 'qlcm_student');
        
        // Discussion settings
        update_option('default_comment_status', 'open');
        update_option('default_ping_status', 'open');
    }
    
    /**
     * Create welcome content
     */
    private function create_welcome_content() {
        // Create welcome post
        $welcome_post_id = wp_insert_post(array(
            'post_title' => 'Welcome to QuickLearn Academy!',
            'post_content' => $this->get_welcome_post_content(),
            'post_status' => 'publish',
            'post_type' => 'post',
            'meta_input' => array(
                'featured_post' => true
            )
        ));
        
        // Set as sticky post
        stick_post($welcome_post_id);
    }
    
    /**
     * Create sample users
     */
    private function create_sample_users() {
        // Create demo instructor
        $instructor_id = wp_create_user('instructor_demo', 'instructor123', 'instructor@quicklearn.demo');
        if (!is_wp_error($instructor_id)) {
            wp_update_user(array(
                'ID' => $instructor_id,
                'display_name' => 'Sarah Johnson',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'description' => 'Senior Full Stack Developer with 8+ years of experience in web development and online education.',
                'role' => 'qlcm_instructor'
            ));
            
            update_user_meta($instructor_id, 'instructor_title', 'Senior Full Stack Developer');
            update_user_meta($instructor_id, 'instructor_experience', '8+ years');
            update_user_meta($instructor_id, 'instructor_specialties', 'Web Development, JavaScript, React, Node.js');
        }
        
        // Create demo student
        $student_id = wp_create_user('student_demo', 'student123', 'student@quicklearn.demo');
        if (!is_wp_error($student_id)) {
            wp_update_user(array(
                'ID' => $student_id,
                'display_name' => 'John Smith',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'description' => 'Aspiring web developer eager to learn new technologies.',
                'role' => 'qlcm_student'
            ));
        }
    }
    
    /**
     * Get about page content
     */
    private function get_about_content() {
        return '
        <div class="about-content">
            <h2>About QuickLearn Academy</h2>
            <p>QuickLearn Academy is a premier online learning platform dedicated to helping individuals master new skills and advance their careers. Our expert-led courses cover a wide range of topics from web development to digital marketing.</p>
            
            <h3>Our Mission</h3>
            <p>To make quality education accessible to everyone, anywhere, at any time. We believe that learning should be engaging, practical, and immediately applicable to real-world scenarios.</p>
            
            <h3>Why Choose QuickLearn?</h3>
            <ul>
                <li>Expert instructors with industry experience</li>
                <li>Hands-on projects and practical assignments</li>
                <li>Flexible learning at your own pace</li>
                <li>Certificate of completion for all courses</li>
                <li>24/7 student support</li>
                <li>Money-back guarantee</li>
            </ul>
            
            <h3>Our Team</h3>
            <p>Our team consists of experienced educators, industry professionals, and passionate learners who are committed to providing the best online learning experience.</p>
        </div>';
    }
    
    /**
     * Get contact page content
     */
    private function get_contact_content() {
        return '
        <div class="contact-content">
            <h2>Contact Us</h2>
            <p>Have questions? We\'d love to hear from you. Get in touch with our team.</p>
            
            <div class="contact-info grid grid--2">
                <div class="contact-method">
                    <h3>Email</h3>
                    <p>For general inquiries:<br>
                    <a href="mailto:hmehtace@gmail.com">hmehtace@gmail.com</a></p>
                    
                    <p>For technical support:<br>
                    <a href="mailto:pushparajmehta002@gmail.com">pushparajmehta002@gmail.com</a></p>
                </div>
                
                <div class="contact-method">
                    <h3>Phone</h3>
                    <p>Student Support: (+91) 7635022185<br>
                    Available Monday-Friday, 9AM-6PM IST</p>
                    
                    <p>Business Inquiries: (+91) 7635022185</p>
                </div>
            </div>
            
            <h3>Frequently Asked Questions</h3>
            <p>Before contacting us, you might find your answer in our <a href="#">FAQ section</a>.</p>
            
            <h3>Social Media</h3>
            <p>Follow us on social media for updates and learning tips:</p>
            <ul>
                <li>Twitter: @Pushparaj1381_</li>
                <li>LinkedIn: pushparaj1381-</li>
                <li>YouTube: QuickLearn Academy</li>
            </ul>
        </div>';
    }
    
    /**
     * Get privacy policy content
     */
    private function get_privacy_content() {
        return '
        <div class="privacy-content">
            <h2>Privacy Policy</h2>
            <p><em>Last updated: ' . date('F j, Y') . '</em></p>
            
            <h3>Information We Collect</h3>
            <p>We collect information you provide directly to us, such as when you create an account, enroll in courses, or contact us for support.</p>
            
            <h3>How We Use Your Information</h3>
            <p>We use the information we collect to:</p>
            <ul>
                <li>Provide and maintain our services</li>
                <li>Process course enrollments and payments</li>
                <li>Send you course updates and notifications</li>
                <li>Improve our platform and services</li>
                <li>Comply with legal obligations</li>
            </ul>
            
            <h3>Information Sharing</h3>
            <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy.</p>
            
            <h3>Data Security</h3>
            <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
            
            <h3>Your Rights</h3>
            <p>You have the right to access, update, or delete your personal information. Contact us if you wish to exercise these rights.</p>
            
            <h3>Contact Us</h3>
            <p>If you have any questions about this Privacy Policy, please contact us at privacy@quicklearn.academy.</p>
        </div>';
    }
    
    /**
     * Get terms of service content
     */
    private function get_terms_content() {
        return '
        <div class="terms-content">
            <h2>Terms of Service</h2>
            <p><em>Last updated: ' . date('F j, Y') . '</em></p>
            
            <h3>Acceptance of Terms</h3>
            <p>By accessing and using QuickLearn Academy, you accept and agree to be bound by the terms and provision of this agreement.</p>
            
            <h3>Course Access and Enrollment</h3>
            <p>Upon enrollment, you gain access to course materials for the duration specified. Course access is personal and non-transferable.</p>
            
            <h3>Payment and Refunds</h3>
            <p>All payments are processed securely. We offer a 30-day money-back guarantee for all courses.</p>
            
            <h3>User Conduct</h3>
            <p>Users must:</p>
            <ul>
                <li>Provide accurate information</li>
                <li>Respect intellectual property rights</li>
                <li>Not share course materials publicly</li>
                <li>Maintain respectful communication</li>
            </ul>
            
            <h3>Intellectual Property</h3>
            <p>All course content, including videos, text, and materials, are the intellectual property of QuickLearn Academy and its instructors.</p>
            
            <h3>Limitation of Liability</h3>
            <p>QuickLearn Academy shall not be liable for any indirect, incidental, special, consequential, or punitive damages.</p>
            
            <h3>Changes to Terms</h3>
            <p>We reserve the right to update these terms at any time. Users will be notified of significant changes.</p>
        </div>';
    }
    
    /**
     * Get sample course content
     */
    private function get_sample_course_content($type) {
        $contents = array(
            'web-development' => '
                <h2>Course Overview</h2>
                <p>Learn the fundamentals of web development from scratch. This comprehensive course covers HTML, CSS, JavaScript, and modern web development practices.</p>
                
                <h3>What You\'ll Learn</h3>
                <ul>
                    <li>HTML5 structure and semantic markup</li>
                    <li>CSS3 styling and responsive design</li>
                    <li>JavaScript programming fundamentals</li>
                    <li>DOM manipulation and event handling</li>
                    <li>Version control with Git</li>
                    <li>Modern development tools and workflows</li>
                </ul>
                
                <h3>Course Modules</h3>
                <ol>
                    <li>Introduction to Web Development</li>
                    <li>HTML Fundamentals</li>
                    <li>CSS Styling and Layout</li>
                    <li>Responsive Web Design</li>
                    <li>JavaScript Basics</li>
                    <li>DOM Manipulation</li>
                    <li>Project: Build Your First Website</li>
                    <li>Version Control with Git</li>
                </ol>
                
                <h3>Prerequisites</h3>
                <p>No prior programming experience required. Basic computer skills and enthusiasm to learn are all you need!</p>',
            
            'javascript' => '
                <h2>Advanced JavaScript Programming</h2>
                <p>Master advanced JavaScript concepts and modern ES6+ features. Build complex applications with confidence.</p>
                
                <h3>Advanced Topics Covered</h3>
                <ul>
                    <li>ES6+ features and syntax</li>
                    <li>Asynchronous programming with Promises and async/await</li>
                    <li>Object-oriented programming in JavaScript</li>
                    <li>Functional programming concepts</li>
                    <li>Module systems and bundlers</li>
                    <li>Testing with Jest</li>
                    <li>Performance optimization</li>
                </ul>',
            
            'ui-ux' => '
                <h2>UI/UX Design Fundamentals</h2>
                <p>Learn the principles of user interface and user experience design. Create beautiful, functional designs that users love.</p>
                
                <h3>Design Skills You\'ll Master</h3>
                <ul>
                    <li>Design thinking methodology</li>
                    <li>User research and personas</li>
                    <li>Wireframing and prototyping</li>
                    <li>Visual design principles</li>
                    <li>Accessibility in design</li>
                    <li>Design tools (Figma, Sketch)</li>
                </ul>',
            
            'marketing' => '
                <h2>Digital Marketing Mastery</h2>
                <p>Comprehensive digital marketing course covering SEO, social media, content marketing, and paid advertising strategies.</p>
                
                <h3>Marketing Channels Covered</h3>
                <ul>
                    <li>Search Engine Optimization (SEO)</li>
                    <li>Pay-Per-Click (PPC) advertising</li>
                    <li>Social media marketing</li>
                    <li>Content marketing strategy</li>
                    <li>Email marketing campaigns</li>
                    <li>Analytics and measurement</li>
                </ul>',
            
            'data-science' => '
                <h2>Data Science with Python</h2>
                <p>Comprehensive introduction to data science using Python. Learn data analysis, visualization, and machine learning.</p>
                
                <h3>Technical Skills</h3>
                <ul>
                    <li>Python programming for data science</li>
                    <li>Data manipulation with Pandas</li>
                    <li>Data visualization with Matplotlib and Seaborn</li>
                    <li>Statistical analysis</li>
                    <li>Machine learning with Scikit-learn</li>
                    <li>Jupyter Notebooks</li>
                </ul>'
        );
        
        return isset($contents[$type]) ? $contents[$type] : $contents['web-development'];
    }
    
    /**
     * Get welcome post content
     */
    private function get_welcome_post_content() {
        return '
        <div class="welcome-post">
            <h2>🎉 Welcome to Your New Learning Journey!</h2>
            
            <p>Congratulations! You\'ve successfully activated QuickLearn Academy - your complete online learning management system. Everything is now set up and ready to go!</p>
            
            <h3>✅ What\'s Already Set Up For You</h3>
            <ul>
                <li><strong>5 Sample Courses</strong> - Explore our pre-loaded courses across different categories</li>
                <li><strong>Essential Pages</strong> - Dashboard, Courses, Profile, and more</li>
                <li><strong>User Roles</strong> - Student, Instructor, and Moderator roles with proper permissions</li>
                <li><strong>Navigation Menus</strong> - Professional navigation system</li>
                <li><strong>Demo Accounts</strong> - Try different user experiences</li>
            </ul>
            
            <h3>🚀 Quick Start Guide</h3>
            <ol>
                <li><strong>Explore the Dashboard</strong> - Visit your <a href="' . get_page_link(get_option('quicklearn_dashboard_page_id')) . '">Dashboard</a> to see the admin overview</li>
                <li><strong>Browse Courses</strong> - Check out the <a href="' . get_page_link(get_option('quicklearn_courses_page_id')) . '">sample courses</a> we\'ve created</li>
                <li><strong>Test User Roles</strong> - Login with demo accounts to see different user experiences:
                    <ul>
                        <li>Instructor: instructor_demo / instructor123</li>
                        <li>Student: student_demo / student123</li>
                    </ul>
                </li>
                <li><strong>Customize Your Site</strong> - Go to Appearance → Customize to personalize colors, logo, and settings</li>
                <li><strong>Add Your Content</strong> - Replace sample courses with your own content</li>
            </ol>
            
            <h3>📚 Sample Content Included</h3>
            <div class="course-preview grid grid--3">
                <div class="course-card">
                    <h4>Web Development</h4>
                    <p>Complete beginner-friendly course</p>
                </div>
                <div class="course-card">
                    <h4>JavaScript Programming</h4>
                    <p>Advanced programming concepts</p>
                </div>
                <div class="course-card">
                    <h4>UI/UX Design</h4>
                    <p>Design principles and tools</p>
                </div>
            </div>
            
            <h3>💡 Pro Tips</h3>
            <ul>
                <li>Customize your site colors and branding in the WordPress Customizer</li>
                <li>Set up payment processing for course sales</li>
                <li>Create instructor accounts for your team</li>
                <li>Configure email notifications for enrollments</li>
                <li>Add your own logo and course images</li>
            </ul>
            
            <h3>🎯 Next Steps</h3>
            <p>Your QuickLearn Academy is ready to use! Start by exploring the sample content, then begin adding your own courses and customizing the design to match your brand.</p>
            
            <div class="welcome-actions">
                <a href="' . get_page_link(get_option('quicklearn_dashboard_page_id')) . '" class="btn btn--primary">Go to Dashboard</a>
                <a href="' . get_page_link(get_option('quicklearn_courses_page_id')) . '" class="btn btn--secondary">Browse Courses</a>
                <a href="' . admin_url('customize.php') . '" class="btn btn--outline">Customize Site</a>
            </div>
        </div>
        
        <style>
        .welcome-post {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--color-background);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        
        .course-preview {
            margin: 2rem 0;
        }
        
        .course-card {
            background: var(--color-background-light);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            text-align: center;
        }
        
        .welcome-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .welcome-actions {
                flex-direction: column;
                align-items: center;
            }
        }
        </style>';
    }
}