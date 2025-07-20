<?php
/**
 * Unit Tests for Multimedia Content Support
 */

class Test_Multimedia_Content extends WP_UnitTestCase {
    
    private $multimedia_instance;
    private $admin_id;
    private $course_id;
    
    public function setUp() {
        parent::setUp();
        $this->multimedia_instance = QLCM_Multimedia_Content::get_instance();
        
        // Create test user
        $this->admin_id = QLCM_Test_Utilities::create_admin_user();
        wp_set_current_user($this->admin_id);
        
        // Create test course
        $this->course_id = QLCM_Test_Utilities::create_test_course(array(
            'post_title' => 'Multimedia Test Course',
            'post_content' => 'Course content for multimedia testing.',
        ));
    }
    
    public function tearDown() {
        QLCM_Test_Utilities::cleanup_test_data();
        $this->cleanup_multimedia_data();
        parent::tearDown();
    }
    
    private function cleanup_multimedia_data() {
        // Clean up uploaded test files
        $upload_dir = wp_upload_dir();
        $test_files = glob($upload_dir['basedir'] . '/test-*');
        foreach ($test_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Test video embed support
     * Requirements: 13.1
     */
    public function test_video_embed_support() {
        // Test YouTube embed
        $youtube_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $youtube_embed = $this->multimedia_instance->generate_video_embed($youtube_url);
        
        $this->assertNotEmpty($youtube_embed);
        $this->assertStringContains('iframe', $youtube_embed);
        $this->assertStringContains('youtube.com/embed/', $youtube_embed);
        $this->assertStringContains('dQw4w9WgXcQ', $youtube_embed);
        
        // Test Vimeo embed
        $vimeo_url = 'https://vimeo.com/123456789';
        $vimeo_embed = $this->multimedia_instance->generate_video_embed($vimeo_url);
        
        $this->assertNotEmpty($vimeo_embed);
        $this->assertStringContains('iframe', $vimeo_embed);
        $this->assertStringContains('player.vimeo.com/video/', $vimeo_embed);
        $this->assertStringContains('123456789', $vimeo_embed);
        
        // Test invalid URL
        $invalid_embed = $this->multimedia_instance->generate_video_embed('https://example.com/invalid');
        $this->assertFalse($invalid_embed);
    }
    
    /**
     * Test video upload and processing
     * Requirements: 13.1
     */
    public function test_video_upload_processing() {
        // Create mock video file
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/test-video.mp4';
        file_put_contents($test_video_path, 'mock video content');
        
        // Test video attachment creation
        $attachment_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Test Video',
            'post_content' => 'Test video description',
        ));
        
        $this->assertNotFalse($attachment_id);
        $this->assertGreaterThan(0, $attachment_id);
        
        // Verify attachment
        $attachment = get_post($attachment_id);
        $this->assertEquals('attachment', $attachment->post_type);
        $this->assertEquals('Test Video', $attachment->post_title);
        $this->assertStringContains('video/', $attachment->post_mime_type);
        
        // Test video player generation
        $video_player = $this->multimedia_instance->generate_video_player($attachment_id);
        $this->assertNotEmpty($video_player);
        $this->assertStringContains('<video', $video_player);
        $this->assertStringContains('controls', $video_player);
        $this->assertStringContains('preload', $video_player);
    }
    
    /**
     * Test audio support
     * Requirements: 13.1
     */
    public function test_audio_support() {
        // Create mock audio file
        $upload_dir = wp_upload_dir();
        $test_audio_path = $upload_dir['basedir'] . '/test-audio.mp3';
        file_put_contents($test_audio_path, 'mock audio content');
        
        // Test audio attachment creation
        $attachment_id = $this->multimedia_instance->create_audio_attachment($test_audio_path, array(
            'post_title' => 'Test Audio',
            'post_content' => 'Test audio description',
        ));
        
        $this->assertNotFalse($attachment_id);
        $this->assertGreaterThan(0, $attachment_id);
        
        // Verify attachment
        $attachment = get_post($attachment_id);
        $this->assertEquals('attachment', $attachment->post_type);
        $this->assertEquals('Test Audio', $attachment->post_title);
        $this->assertStringContains('audio/', $attachment->post_mime_type);
        
        // Test audio player generation
        $audio_player = $this->multimedia_instance->generate_audio_player($attachment_id);
        $this->assertNotEmpty($audio_player);
        $this->assertStringContains('<audio', $audio_player);
        $this->assertStringContains('controls', $audio_player);
        $this->assertStringContains('preload', $audio_player);
    }
    
    /**
     * Test responsive media players
     * Requirements: 13.1
     */
    public function test_responsive_media_players() {
        // Test responsive video player
        $youtube_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $responsive_video = $this->multimedia_instance->generate_responsive_video_embed($youtube_url);
        
        $this->assertNotEmpty($responsive_video);
        $this->assertStringContains('responsive-video-wrapper', $responsive_video);
        $this->assertStringContains('iframe', $responsive_video);
        
        // Test responsive video with custom aspect ratio
        $responsive_video_16_9 = $this->multimedia_instance->generate_responsive_video_embed($youtube_url, '16:9');
        $this->assertStringContains('aspect-ratio-16-9', $responsive_video_16_9);
        
        $responsive_video_4_3 = $this->multimedia_instance->generate_responsive_video_embed($youtube_url, '4:3');
        $this->assertStringContains('aspect-ratio-4-3', $responsive_video_4_3);
    }
    
    /**
     * Test accessibility features
     * Requirements: 13.1
     */
    public function test_accessibility_features() {
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/test-video-accessibility.mp4';
        file_put_contents($test_video_path, 'mock video content');
        
        $attachment_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Accessibility Test Video',
        ));
        
        // Test video player with accessibility features
        $accessible_player = $this->multimedia_instance->generate_accessible_video_player($attachment_id, array(
            'captions' => true,
            'transcript' => 'This is a test video transcript.',
            'audio_description' => true,
        ));
        
        $this->assertNotEmpty($accessible_player);
        $this->assertStringContains('aria-label', $accessible_player);
        $this->assertStringContains('role="application"', $accessible_player);
        $this->assertStringContains('transcript', $accessible_player);
        
        // Test keyboard navigation support
        $this->assertStringContains('tabindex="0"', $accessible_player);
        $this->assertStringContains('keyboard-accessible', $accessible_player);
    }
    
    /**
     * Test media library integration
     * Requirements: 13.1
     */
    public function test_media_library_integration() {
        // Test media library filtering for course content
        $video_attachments = $this->multimedia_instance->get_course_media($this->course_id, 'video');
        $this->assertIsArray($video_attachments);
        
        $audio_attachments = $this->multimedia_instance->get_course_media($this->course_id, 'audio');
        $this->assertIsArray($audio_attachments);
        
        // Create and attach media to course
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/course-video.mp4';
        file_put_contents($test_video_path, 'course video content');
        
        $video_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Course Video',
            'post_parent' => $this->course_id,
        ));
        
        // Test that media is now associated with course
        $course_videos = $this->multimedia_instance->get_course_media($this->course_id, 'video');
        $this->assertNotEmpty($course_videos);
        
        $video_found = false;
        foreach ($course_videos as $video) {
            if ($video->ID == $video_id) {
                $video_found = true;
                break;
            }
        }
        $this->assertTrue($video_found);
    }
    
    /**
     * Test media optimization
     * Requirements: 7.1, 7.3
     */
    public function test_media_optimization() {
        $upload_dir = wp_upload_dir();
        $test_image_path = $upload_dir['basedir'] . '/test-large-image.jpg';
        
        // Create a mock large image
        file_put_contents($test_image_path, str_repeat('mock image data', 1000));
        
        $attachment_id = QLCM_Test_Utilities::create_test_attachment(array(
            'post_title' => 'Large Test Image',
        ));
        
        // Test image optimization
        $optimized = $this->multimedia_instance->optimize_image($attachment_id);
        $this->assertTrue($optimized);
        
        // Test lazy loading implementation
        $lazy_image = $this->multimedia_instance->generate_lazy_image($attachment_id, 'medium');
        $this->assertStringContains('loading="lazy"', $lazy_image);
        $this->assertStringContains('data-src', $lazy_image);
        
        // Test responsive images
        $responsive_image = $this->multimedia_instance->generate_responsive_image($attachment_id);
        $this->assertStringContains('srcset', $responsive_image);
        $this->assertStringContains('sizes', $responsive_image);
    }
    
    /**
     * Test video streaming and progressive loading
     * Requirements: 7.1, 7.4
     */
    public function test_video_streaming() {
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/streaming-test-video.mp4';
        file_put_contents($test_video_path, 'streaming video content');
        
        $video_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Streaming Test Video',
        ));
        
        // Test progressive loading
        $progressive_player = $this->multimedia_instance->generate_progressive_video_player($video_id);
        $this->assertStringContains('preload="metadata"', $progressive_player);
        $this->assertStringContains('data-progressive="true"', $progressive_player);
        
        // Test video quality selection
        $quality_player = $this->multimedia_instance->generate_quality_selector_player($video_id);
        $this->assertStringContains('quality-selector', $quality_player);
        $this->assertStringContains('data-qualities', $quality_player);
    }
    
    /**
     * Test multimedia content security
     * Requirements: 5.1, 5.2
     */
    public function test_multimedia_security() {
        // Test file type validation
        $invalid_file = $this->multimedia_instance->validate_media_file('test.exe');
        $this->assertFalse($invalid_file);
        
        $valid_video = $this->multimedia_instance->validate_media_file('test.mp4');
        $this->assertTrue($valid_video);
        
        $valid_audio = $this->multimedia_instance->validate_media_file('test.mp3');
        $this->assertTrue($valid_audio);
        
        // Test file size limits
        $oversized_file = $this->multimedia_instance->validate_file_size(100 * 1024 * 1024); // 100MB
        $this->assertFalse($oversized_file);
        
        $valid_size = $this->multimedia_instance->validate_file_size(10 * 1024 * 1024); // 10MB
        $this->assertTrue($valid_size);
        
        // Test URL sanitization for embeds
        $malicious_url = 'javascript:alert("xss")';
        $sanitized_embed = $this->multimedia_instance->generate_video_embed($malicious_url);
        $this->assertFalse($sanitized_embed);
        
        // Test XSS prevention in media metadata
        $malicious_title = '<script>alert("xss")</script>Malicious Video';
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/xss-test-video.mp4';
        file_put_contents($test_video_path, 'xss test video');
        
        $attachment_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => $malicious_title,
        ));
        
        $attachment = get_post($attachment_id);
        $this->assertNotContains('<script>', $attachment->post_title);
        $this->assertStringContains('Malicious Video', $attachment->post_title);
    }
    
    /**
     * Test multimedia performance
     * Requirements: 7.2, 7.4
     */
    public function test_multimedia_performance() {
        // Test batch media processing
        $upload_dir = wp_upload_dir();
        $media_files = array();
        
        for ($i = 1; $i <= 5; $i++) {
            $file_path = $upload_dir['basedir'] . "/performance-test-{$i}.mp4";
            file_put_contents($file_path, "performance test video {$i}");
            $media_files[] = $file_path;
        }
        
        $start_time = microtime(true);
        $attachment_ids = $this->multimedia_instance->batch_create_attachments($media_files);
        $batch_time = microtime(true) - $start_time;
        
        $this->assertCount(5, $attachment_ids);
        $this->assertLessThan(3.0, $batch_time, 'Batch media processing should complete within 3 seconds');
        
        // Test media player generation performance
        $start_time = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $player = $this->multimedia_instance->generate_video_player($attachment_ids[0]);
        }
        $player_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $player_time, 'Video player generation should be fast');
    }
    
    /**
     * Test multimedia caching
     * Requirements: 7.2
     */
    public function test_multimedia_caching() {
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/cache-test-video.mp4';
        file_put_contents($test_video_path, 'cache test video');
        
        $video_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Cache Test Video',
        ));
        
        // First call should not be cached
        $start_time = microtime(true);
        $player1 = $this->multimedia_instance->generate_video_player($video_id);
        $time1 = microtime(true) - $start_time;
        
        // Second call should be cached and faster
        $start_time = microtime(true);
        $player2 = $this->multimedia_instance->generate_video_player($video_id);
        $time2 = microtime(true) - $start_time;
        
        $this->assertEquals($player1, $player2);
        $this->assertLessThan($time1, $time2, 'Cached call should be faster');
        
        // Test cache invalidation when attachment is updated
        wp_update_post(array(
            'ID' => $video_id,
            'post_title' => 'Updated Cache Test Video'
        ));
        
        $player3 = $this->multimedia_instance->generate_video_player($video_id);
        $this->assertNotEquals($player1, $player3, 'Cache should be invalidated after update');
    }
    
    /**
     * Test multimedia analytics
     * Requirements: 11.1, 11.3
     */
    public function test_multimedia_analytics() {
        $upload_dir = wp_upload_dir();
        $test_video_path = $upload_dir['basedir'] . '/analytics-test-video.mp4';
        file_put_contents($test_video_path, 'analytics test video');
        
        $video_id = $this->multimedia_instance->create_video_attachment($test_video_path, array(
            'post_title' => 'Analytics Test Video',
            'post_parent' => $this->course_id,
        ));
        
        // Test video view tracking
        $this->multimedia_instance->track_video_view($video_id, $this->admin_id);
        $this->multimedia_instance->track_video_view($video_id, $this->admin_id);
        
        $view_count = $this->multimedia_instance->get_video_view_count($video_id);
        $this->assertEquals(2, $view_count);
        
        // Test video engagement tracking
        $this->multimedia_instance->track_video_engagement($video_id, $this->admin_id, array(
            'watch_time' => 120, // 2 minutes
            'completion_percentage' => 75,
        ));
        
        $engagement_data = $this->multimedia_instance->get_video_engagement($video_id);
        $this->assertArrayHasKey('average_watch_time', $engagement_data);
        $this->assertArrayHasKey('average_completion', $engagement_data);
        $this->assertEquals(120, $engagement_data['average_watch_time']);
        $this->assertEquals(75, $engagement_data['average_completion']);
        
        // Test multimedia statistics
        $stats = $this->multimedia_instance->get_multimedia_statistics();
        $this->assertArrayHasKey('total_videos', $stats);
        $this->assertArrayHasKey('total_audio', $stats);
        $this->assertArrayHasKey('total_views', $stats);
        $this->assertArrayHasKey('popular_content', $stats);
    }
    
    /**
     * Test multimedia content organization
     * Requirements: 13.2, 13.3
     */
    public function test_content_organization() {
        // Test playlist creation
        $playlist_id = $this->multimedia_instance->create_playlist(array(
            'name' => 'Test Playlist',
            'description' => 'Test playlist description',
            'course_id' => $this->course_id,
        ));
        
        $this->assertNotFalse($playlist_id);
        
        // Create test videos
        $upload_dir = wp_upload_dir();
        $video_ids = array();
        
        for ($i = 1; $i <= 3; $i++) {
            $video_path = $upload_dir['basedir'] . "/playlist-video-{$i}.mp4";
            file_put_contents($video_path, "playlist video {$i}");
            
            $video_id = $this->multimedia_instance->create_video_attachment($video_path, array(
                'post_title' => "Playlist Video {$i}",
                'post_parent' => $this->course_id,
            ));
            $video_ids[] = $video_id;
        }
        
        // Add videos to playlist
        $result = $this->multimedia_instance->add_videos_to_playlist($playlist_id, $video_ids);
        $this->assertTrue($result);
        
        // Test playlist retrieval
        $playlist_videos = $this->multimedia_instance->get_playlist_videos($playlist_id);
        $this->assertCount(3, $playlist_videos);
        
        // Test playlist player generation
        $playlist_player = $this->multimedia_instance->generate_playlist_player($playlist_id);
        $this->assertNotEmpty($playlist_player);
        $this->assertStringContains('playlist-player', $playlist_player);
        $this->assertStringContains('data-playlist-id', $playlist_player);
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = QLCM_Multimedia_Content::get_instance();
        $instance2 = QLCM_Multimedia_Content::get_instance();
        
        $this->assertSame($instance1, $instance2);
    }
}