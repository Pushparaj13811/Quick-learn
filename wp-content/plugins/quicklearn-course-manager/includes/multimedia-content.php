<?php
/**
 * Multimedia Content Support for QuickLearn Course Manager
 * 
 * Handles video and audio integration for courses including:
 * - YouTube and Vimeo embed support
 * - Direct video/audio uploads
 * - Media library integration
 * - Responsive media players with accessibility
 * 
 * @package QuickLearn_Course_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multimedia Content Handler Class
 */
class QLCM_Multimedia_Content {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
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
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add meta boxes for multimedia content
        add_action('add_meta_boxes', array($this, 'add_multimedia_meta_boxes'));
        
        // Save multimedia content
        add_action('save_post', array($this, 'save_multimedia_content'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Add multimedia content to course display
        add_filter('the_content', array($this, 'add_multimedia_to_content'));
        
        // Register shortcodes
        add_shortcode('qlcm_video', array($this, 'video_shortcode'));
        add_shortcode('qlcm_audio', array($this, 'audio_shortcode'));
        
        // AJAX handlers for media management
        add_action('wp_ajax_qlcm_get_media_info', array($this, 'ajax_get_media_info'));
        add_action('wp_ajax_qlcm_validate_video_url', array($this, 'ajax_validate_video_url'));
    }
    
    /**
     * Add multimedia meta boxes to course edit screen
     */
    public function add_multimedia_meta_boxes() {
        add_meta_box(
            'qlcm_multimedia_content',
            __('Multimedia Content', 'quicklearn-course-manager'),
            array($this, 'render_multimedia_meta_box'),
            'quick_course',
            'normal',
            'high'
        );
    }
    
    /**
     * Render multimedia content meta box
     */
    public function render_multimedia_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('qlcm_multimedia_nonce', 'qlcm_multimedia_nonce_field');
        
        // Get existing multimedia data
        $video_data = get_post_meta($post->ID, '_qlcm_video_content', true);
        $audio_data = get_post_meta($post->ID, '_qlcm_audio_content', true);
        
        // Default values
        $video_data = wp_parse_args($video_data, array(
            'type' => 'none',
            'youtube_url' => '',
            'vimeo_url' => '',
            'upload_id' => '',
            'autoplay' => false,
            'controls' => true,
            'loop' => false,
            'muted' => false
        ));
        
        $audio_data = wp_parse_args($audio_data, array(
            'files' => array(),
            'autoplay' => false,
            'controls' => true,
            'loop' => false,
            'preload' => 'metadata'
        ));
        ?>
        
        <div id="qlcm-multimedia-content">
            <!-- Video Content Section -->
            <div class="qlcm-multimedia-section">
                <h3><?php _e('Video Content', 'quicklearn-course-manager'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="qlcm_video_type"><?php _e('Video Type', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <select id="qlcm_video_type" name="qlcm_video_type">
                                <option value="none" <?php selected($video_data['type'], 'none'); ?>><?php _e('No Video', 'quicklearn-course-manager'); ?></option>
                                <option value="youtube" <?php selected($video_data['type'], 'youtube'); ?>><?php _e('YouTube', 'quicklearn-course-manager'); ?></option>
                                <option value="vimeo" <?php selected($video_data['type'], 'vimeo'); ?>><?php _e('Vimeo', 'quicklearn-course-manager'); ?></option>
                                <option value="upload" <?php selected($video_data['type'], 'upload'); ?>><?php _e('Direct Upload', 'quicklearn-course-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="qlcm_youtube_row" style="display: <?php echo $video_data['type'] === 'youtube' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="qlcm_youtube_url"><?php _e('YouTube URL', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="qlcm_youtube_url" name="qlcm_youtube_url" value="<?php echo esc_url($video_data['youtube_url']); ?>" class="regular-text" placeholder="https://www.youtube.com/watch?v=..." />
                            <button type="button" id="qlcm_validate_youtube" class="button"><?php _e('Validate', 'quicklearn-course-manager'); ?></button>
                            <div id="qlcm_youtube_preview"></div>
                        </td>
                    </tr>
                    
                    <tr id="qlcm_vimeo_row" style="display: <?php echo $video_data['type'] === 'vimeo' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="qlcm_vimeo_url"><?php _e('Vimeo URL', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="qlcm_vimeo_url" name="qlcm_vimeo_url" value="<?php echo esc_url($video_data['vimeo_url']); ?>" class="regular-text" placeholder="https://vimeo.com/..." />
                            <button type="button" id="qlcm_validate_vimeo" class="button"><?php _e('Validate', 'quicklearn-course-manager'); ?></button>
                            <div id="qlcm_vimeo_preview"></div>
                        </td>
                    </tr>
                    
                    <tr id="qlcm_video_upload_row" style="display: <?php echo $video_data['type'] === 'upload' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="qlcm_video_upload"><?php _e('Video File', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <input type="hidden" id="qlcm_video_upload_id" name="qlcm_video_upload_id" value="<?php echo esc_attr($video_data['upload_id']); ?>" />
                            <button type="button" id="qlcm_video_upload_button" class="button"><?php _e('Select Video', 'quicklearn-course-manager'); ?></button>
                            <button type="button" id="qlcm_video_remove_button" class="button" style="display: <?php echo $video_data['upload_id'] ? 'inline-block' : 'none'; ?>;"><?php _e('Remove', 'quicklearn-course-manager'); ?></button>
                            <div id="qlcm_video_upload_preview">
                                <?php if ($video_data['upload_id']): ?>
                                    <?php echo wp_get_attachment_image($video_data['upload_id'], 'medium'); ?>
                                    <p><?php echo esc_html(get_the_title($video_data['upload_id'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <tr id="qlcm_video_options_row" style="display: <?php echo $video_data['type'] !== 'none' ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><?php _e('Video Options', 'quicklearn-course-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="qlcm_video_autoplay" value="1" <?php checked($video_data['autoplay']); ?> />
                                    <?php _e('Autoplay', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="qlcm_video_controls" value="1" <?php checked($video_data['controls']); ?> />
                                    <?php _e('Show Controls', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="qlcm_video_loop" value="1" <?php checked($video_data['loop']); ?> />
                                    <?php _e('Loop', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="qlcm_video_muted" value="1" <?php checked($video_data['muted']); ?> />
                                    <?php _e('Muted', 'quicklearn-course-manager'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Audio Content Section -->
            <div class="qlcm-multimedia-section">
                <h3><?php _e('Audio Content', 'quicklearn-course-manager'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="qlcm_audio_files"><?php _e('Audio Files', 'quicklearn-course-manager'); ?></label>
                        </th>
                        <td>
                            <div id="qlcm_audio_files_container">
                                <?php if (!empty($audio_data['files'])): ?>
                                    <?php foreach ($audio_data['files'] as $index => $file_id): ?>
                                        <div class="qlcm-audio-file-item" data-index="<?php echo $index; ?>">
                                            <input type="hidden" name="qlcm_audio_files[]" value="<?php echo esc_attr($file_id); ?>" />
                                            <?php echo wp_get_attachment_link($file_id, 'thumbnail'); ?>
                                            <span><?php echo esc_html(get_the_title($file_id)); ?></span>
                                            <button type="button" class="button qlcm-remove-audio-file"><?php _e('Remove', 'quicklearn-course-manager'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="qlcm_add_audio_file" class="button"><?php _e('Add Audio File', 'quicklearn-course-manager'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Audio Options', 'quicklearn-course-manager'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="qlcm_audio_autoplay" value="1" <?php checked($audio_data['autoplay']); ?> />
                                    <?php _e('Autoplay', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="qlcm_audio_controls" value="1" <?php checked($audio_data['controls']); ?> />
                                    <?php _e('Show Controls', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="qlcm_audio_loop" value="1" <?php checked($audio_data['loop']); ?> />
                                    <?php _e('Loop', 'quicklearn-course-manager'); ?>
                                </label><br>
                                
                                <label for="qlcm_audio_preload"><?php _e('Preload:', 'quicklearn-course-manager'); ?></label>
                                <select name="qlcm_audio_preload" id="qlcm_audio_preload">
                                    <option value="none" <?php selected($audio_data['preload'], 'none'); ?>><?php _e('None', 'quicklearn-course-manager'); ?></option>
                                    <option value="metadata" <?php selected($audio_data['preload'], 'metadata'); ?>><?php _e('Metadata', 'quicklearn-course-manager'); ?></option>
                                    <option value="auto" <?php selected($audio_data['preload'], 'auto'); ?>><?php _e('Auto', 'quicklearn-course-manager'); ?></option>
                                </select>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Save multimedia content when course is saved
     */
    public function save_multimedia_content($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'quick_course') {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['qlcm_multimedia_nonce_field']) || 
            !wp_verify_nonce($_POST['qlcm_multimedia_nonce_field'], 'qlcm_multimedia_nonce')) {
            return;
        }
        
        // Save video data
        $video_data = array(
            'type' => sanitize_key($_POST['qlcm_video_type'] ?? 'none'),
            'youtube_url' => esc_url_raw($_POST['qlcm_youtube_url'] ?? ''),
            'vimeo_url' => esc_url_raw($_POST['qlcm_vimeo_url'] ?? ''),
            'upload_id' => absint($_POST['qlcm_video_upload_id'] ?? 0),
            'autoplay' => isset($_POST['qlcm_video_autoplay']),
            'controls' => isset($_POST['qlcm_video_controls']),
            'loop' => isset($_POST['qlcm_video_loop']),
            'muted' => isset($_POST['qlcm_video_muted'])
        );
        
        update_post_meta($post_id, '_qlcm_video_content', $video_data);
        
        // Save audio data
        $audio_files = array();
        if (isset($_POST['qlcm_audio_files']) && is_array($_POST['qlcm_audio_files'])) {
            $audio_files = array_map('absint', $_POST['qlcm_audio_files']);
        }
        
        $audio_data = array(
            'files' => $audio_files,
            'autoplay' => isset($_POST['qlcm_audio_autoplay']),
            'controls' => isset($_POST['qlcm_audio_controls']),
            'loop' => isset($_POST['qlcm_audio_loop']),
            'preload' => sanitize_key($_POST['qlcm_audio_preload'] ?? 'metadata')
        );
        
        update_post_meta($post_id, '_qlcm_audio_content', $audio_data);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            if ($post_type === 'quick_course') {
                wp_enqueue_media();
                wp_enqueue_script(
                    'qlcm-multimedia-admin',
                    QLCM_PLUGIN_URL . 'assets/js/multimedia-admin.js',
                    array('jquery', 'media-upload', 'media-views'),
                    QLCM_VERSION,
                    true
                );
                
                wp_localize_script('qlcm-multimedia-admin', 'qlcm_multimedia', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('qlcm_multimedia_ajax'),
                    'strings' => array(
                        'select_video' => __('Select Video', 'quicklearn-course-manager'),
                        'select_audio' => __('Select Audio', 'quicklearn-course-manager'),
                        'remove' => __('Remove', 'quicklearn-course-manager'),
                        'validating' => __('Validating...', 'quicklearn-course-manager'),
                        'valid_url' => __('Valid URL', 'quicklearn-course-manager'),
                        'invalid_url' => __('Invalid URL', 'quicklearn-course-manager')
                    )
                ));
                
                wp_enqueue_style(
                    'qlcm-multimedia-admin',
                    QLCM_PLUGIN_URL . 'assets/css/multimedia-admin.css',
                    array(),
                    QLCM_VERSION
                );
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_singular('quick_course') || is_post_type_archive('quick_course')) {
            wp_enqueue_script(
                'qlcm-multimedia-frontend',
                QLCM_PLUGIN_URL . 'assets/js/multimedia-frontend.js',
                array('jquery'),
                QLCM_VERSION,
                true
            );
            
            wp_enqueue_style(
                'qlcm-multimedia-frontend',
                QLCM_PLUGIN_URL . 'assets/css/multimedia-frontend.css',
                array(),
                QLCM_VERSION
            );
        }
    }    

    /**
     * Add multimedia content to course content
     */
    public function add_multimedia_to_content($content) {
        if (!is_singular('quick_course')) {
            return $content;
        }
        
        global $post;
        
        $video_content = $this->get_video_content($post->ID);
        $audio_content = $this->get_audio_content($post->ID);
        
        $multimedia_content = '';
        
        if (!empty($video_content)) {
            $multimedia_content .= '<div class="qlcm-video-content">' . $video_content . '</div>';
        }
        
        if (!empty($audio_content)) {
            $multimedia_content .= '<div class="qlcm-audio-content">' . $audio_content . '</div>';
        }
        
        // Add multimedia content before the main content
        if (!empty($multimedia_content)) {
            $content = '<div class="qlcm-multimedia-wrapper">' . $multimedia_content . '</div>' . $content;
        }
        
        return $content;
    }
    
    /**
     * Get video content HTML
     */
    public function get_video_content($post_id) {
        $video_data = get_post_meta($post_id, '_qlcm_video_content', true);
        
        if (empty($video_data) || $video_data['type'] === 'none') {
            return '';
        }
        
        $html = '';
        
        switch ($video_data['type']) {
            case 'youtube':
                $html = $this->get_youtube_embed($video_data);
                break;
            case 'vimeo':
                $html = $this->get_vimeo_embed($video_data);
                break;
            case 'upload':
                $html = $this->get_video_upload_html($video_data);
                break;
        }
        
        return $html;
    }
    
    /**
     * Get audio content HTML
     */
    public function get_audio_content($post_id) {
        $audio_data = get_post_meta($post_id, '_qlcm_audio_content', true);
        
        if (empty($audio_data) || empty($audio_data['files'])) {
            return '';
        }
        
        return $this->get_audio_player_html($audio_data);
    }
    
    /**
     * Generate YouTube embed HTML
     */
    private function get_youtube_embed($video_data) {
        $youtube_id = $this->extract_youtube_id($video_data['youtube_url']);
        
        if (!$youtube_id) {
            return '';
        }
        
        $params = array();
        
        if ($video_data['autoplay']) {
            $params[] = 'autoplay=1';
        }
        
        if (!$video_data['controls']) {
            $params[] = 'controls=0';
        }
        
        if ($video_data['loop']) {
            $params[] = 'loop=1&playlist=' . $youtube_id;
        }
        
        if ($video_data['muted']) {
            $params[] = 'mute=1';
        }
        
        $param_string = !empty($params) ? '?' . implode('&', $params) : '';
        
        $html = '<div class="qlcm-video-container qlcm-youtube-container">';
        $html .= '<div class="qlcm-video-responsive">';
        $html .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . $param_string . '" ';
        $html .= 'frameborder="0" allowfullscreen ';
        $html .= 'title="' . esc_attr(get_the_title()) . '" ';
        $html .= 'aria-label="' . esc_attr__('YouTube video player', 'quicklearn-course-manager') . '">';
        $html .= '</iframe>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Vimeo embed HTML
     */
    private function get_vimeo_embed($video_data) {
        $vimeo_id = $this->extract_vimeo_id($video_data['vimeo_url']);
        
        if (!$vimeo_id) {
            return '';
        }
        
        $params = array();
        
        if ($video_data['autoplay']) {
            $params[] = 'autoplay=1';
        }
        
        if ($video_data['loop']) {
            $params[] = 'loop=1';
        }
        
        if ($video_data['muted']) {
            $params[] = 'muted=1';
        }
        
        $param_string = !empty($params) ? '?' . implode('&', $params) : '';
        
        $html = '<div class="qlcm-video-container qlcm-vimeo-container">';
        $html .= '<div class="qlcm-video-responsive">';
        $html .= '<iframe src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . $param_string . '" ';
        $html .= 'frameborder="0" allowfullscreen ';
        $html .= 'title="' . esc_attr(get_the_title()) . '" ';
        $html .= 'aria-label="' . esc_attr__('Vimeo video player', 'quicklearn-course-manager') . '">';
        $html .= '</iframe>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate video upload HTML
     */
    private function get_video_upload_html($video_data) {
        if (!$video_data['upload_id']) {
            return '';
        }
        
        $video_url = wp_get_attachment_url($video_data['upload_id']);
        $video_mime = get_post_mime_type($video_data['upload_id']);
        
        if (!$video_url) {
            return '';
        }
        
        $attributes = array();
        
        if ($video_data['controls']) {
            $attributes[] = 'controls';
        }
        
        if ($video_data['autoplay']) {
            $attributes[] = 'autoplay';
        }
        
        if ($video_data['loop']) {
            $attributes[] = 'loop';
        }
        
        if ($video_data['muted']) {
            $attributes[] = 'muted';
        }
        
        $html = '<div class="qlcm-video-container qlcm-upload-container">';
        $html .= '<video ' . implode(' ', $attributes) . ' ';
        $html .= 'preload="metadata" ';
        $html .= 'aria-label="' . esc_attr__('Course video content', 'quicklearn-course-manager') . '">';
        $html .= '<source src="' . esc_url($video_url) . '" type="' . esc_attr($video_mime) . '">';
        $html .= '<p>' . __('Your browser does not support the video tag.', 'quicklearn-course-manager') . '</p>';
        $html .= '</video>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate audio player HTML
     */
    private function get_audio_player_html($audio_data) {
        if (empty($audio_data['files'])) {
            return '';
        }
        
        $attributes = array();
        
        if ($audio_data['controls']) {
            $attributes[] = 'controls';
        }
        
        if ($audio_data['autoplay']) {
            $attributes[] = 'autoplay';
        }
        
        if ($audio_data['loop']) {
            $attributes[] = 'loop';
        }
        
        $preload = in_array($audio_data['preload'], array('none', 'metadata', 'auto')) ? $audio_data['preload'] : 'metadata';
        
        $html = '<div class="qlcm-audio-container">';
        
        foreach ($audio_data['files'] as $file_id) {
            $audio_url = wp_get_attachment_url($file_id);
            $audio_mime = get_post_mime_type($file_id);
            $audio_title = get_the_title($file_id);
            
            if (!$audio_url) {
                continue;
            }
            
            $html .= '<div class="qlcm-audio-item">';
            $html .= '<h4 class="qlcm-audio-title">' . esc_html($audio_title) . '</h4>';
            $html .= '<audio ' . implode(' ', $attributes) . ' ';
            $html .= 'preload="' . esc_attr($preload) . '" ';
            $html .= 'aria-label="' . esc_attr(sprintf(__('Audio: %s', 'quicklearn-course-manager'), $audio_title)) . '">';
            $html .= '<source src="' . esc_url($audio_url) . '" type="' . esc_attr($audio_mime) . '">';
            $html .= '<p>' . __('Your browser does not support the audio tag.', 'quicklearn-course-manager') . '</p>';
            $html .= '</audio>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Video shortcode handler
     */
    public function video_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'type' => 'upload',
            'url' => '',
            'autoplay' => 'false',
            'controls' => 'true',
            'loop' => 'false',
            'muted' => 'false'
        ), $atts, 'qlcm_video');
        
        $video_data = array(
            'type' => sanitize_key($atts['type']),
            'youtube_url' => $atts['type'] === 'youtube' ? esc_url_raw($atts['url']) : '',
            'vimeo_url' => $atts['type'] === 'vimeo' ? esc_url_raw($atts['url']) : '',
            'upload_id' => $atts['type'] === 'upload' ? absint($atts['id']) : 0,
            'autoplay' => $atts['autoplay'] === 'true',
            'controls' => $atts['controls'] === 'true',
            'loop' => $atts['loop'] === 'true',
            'muted' => $atts['muted'] === 'true'
        );
        
        switch ($video_data['type']) {
            case 'youtube':
                return $this->get_youtube_embed($video_data);
            case 'vimeo':
                return $this->get_vimeo_embed($video_data);
            case 'upload':
                return $this->get_video_upload_html($video_data);
            default:
                return '';
        }
    }
    
    /**
     * Audio shortcode handler
     */
    public function audio_shortcode($atts) {
        $atts = shortcode_atts(array(
            'ids' => '',
            'autoplay' => 'false',
            'controls' => 'true',
            'loop' => 'false',
            'preload' => 'metadata'
        ), $atts, 'qlcm_audio');
        
        $file_ids = array_map('absint', explode(',', $atts['ids']));
        
        $audio_data = array(
            'files' => $file_ids,
            'autoplay' => $atts['autoplay'] === 'true',
            'controls' => $atts['controls'] === 'true',
            'loop' => $atts['loop'] === 'true',
            'preload' => sanitize_key($atts['preload'])
        );
        
        return $this->get_audio_player_html($audio_data);
    }
    
    /**
     * AJAX handler to get media info
     */
    public function ajax_get_media_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_multimedia_ajax')) {
            wp_die(__('Security check failed', 'quicklearn-course-manager'));
        }
        
        $media_id = absint($_POST['media_id']);
        
        if (!$media_id) {
            wp_send_json_error(__('Invalid media ID', 'quicklearn-course-manager'));
        }
        
        $attachment = get_post($media_id);
        
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(__('Media not found', 'quicklearn-course-manager'));
        }
        
        $response = array(
            'id' => $media_id,
            'title' => get_the_title($media_id),
            'url' => wp_get_attachment_url($media_id),
            'mime_type' => get_post_mime_type($media_id),
            'thumbnail' => wp_get_attachment_image($media_id, 'thumbnail')
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler to validate video URLs
     */
    public function ajax_validate_video_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'qlcm_multimedia_ajax')) {
            wp_die(__('Security check failed', 'quicklearn-course-manager'));
        }
        
        $url = esc_url_raw($_POST['url']);
        $type = sanitize_key($_POST['type']);
        
        if (!$url) {
            wp_send_json_error(__('Invalid URL', 'quicklearn-course-manager'));
        }
        
        $is_valid = false;
        $video_id = '';
        $thumbnail = '';
        $title = '';
        
        if ($type === 'youtube') {
            $video_id = $this->extract_youtube_id($url);
            if ($video_id) {
                $is_valid = true;
                $thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
                // Get video title from YouTube API if available
                $title = $this->get_youtube_title($video_id);
            }
        } elseif ($type === 'vimeo') {
            $video_id = $this->extract_vimeo_id($url);
            if ($video_id) {
                $is_valid = true;
                // Get Vimeo thumbnail and title
                $vimeo_data = $this->get_vimeo_data($video_id);
                if ($vimeo_data) {
                    $thumbnail = $vimeo_data['thumbnail'];
                    $title = $vimeo_data['title'];
                }
            }
        }
        
        if ($is_valid) {
            wp_send_json_success(array(
                'video_id' => $video_id,
                'thumbnail' => $thumbnail,
                'title' => $title
            ));
        } else {
            wp_send_json_error(__('Invalid video URL', 'quicklearn-course-manager'));
        }
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    private function extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : false;
    }
    
    /**
     * Extract Vimeo video ID from URL
     */
    private function extract_vimeo_id($url) {
        $pattern = '/(?:vimeo\.com\/)(?:.*\/)?(\d+)/';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : false;
    }
    
    /**
     * Get YouTube video title
     */
    private function get_youtube_title($video_id) {
        // This would require YouTube API key for full implementation
        // For now, return a placeholder
        return sprintf(__('YouTube Video %s', 'quicklearn-course-manager'), $video_id);
    }
    
    /**
     * Get Vimeo video data
     */
    private function get_vimeo_data($video_id) {
        $api_url = "https://vimeo.com/api/v2/video/{$video_id}.json";
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!empty($data[0])) {
            return array(
                'title' => $data[0]['title'],
                'thumbnail' => $data[0]['thumbnail_large']
            );
        }
        
        return false;
    }
}

// Initialize the multimedia content handler
QLCM_Multimedia_Content::get_instance();