/**
 * Multimedia Frontend JavaScript for QuickLearn Course Manager
 * 
 * Handles frontend multimedia functionality, accessibility, and lesson progression
 */

(function($) {
    'use strict';
    
    let progressTracking = {};
    let currentLesson = null;
    let lessonStartTime = null;
    
    $(document).ready(function() {
        initMultimediaFrontend();
        initLessonProgression();
    });
    
    /**
     * Initialize multimedia frontend functionality
     */
    function initMultimediaFrontend() {
        // Initialize video players
        initVideoPlayers();
        
        // Initialize audio players
        initAudioPlayers();
        
        // Handle responsive video resizing
        handleResponsiveVideos();
        
        // Add accessibility improvements
        addAccessibilityFeatures();
        
        // Handle loading states
        handleLoadingStates();
    }
    
    /**
     * Initialize lesson progression tracking
     */
    function initLessonProgression() {
        // Handle lesson clicks
        $('.qlcm-lesson').on('click', function(e) {
            e.preventDefault();
            const lessonId = $(this).data('lesson-id');
            const courseId = $(this).closest('.qlcm-course-modules').data('course-id') || qlcm_modules_frontend.course_id;
            const moduleId = $(this).closest('.qlcm-module').data('module-id');
            
            if (lessonId && courseId && moduleId) {
                startLesson(lessonId, courseId, moduleId, $(this));
            }
        });
        
        // Handle module toggle
        $('.qlcm-module-header').on('click', function(e) {
            if (!$(e.target).hasClass('qlcm-drag-handle') && !$(e.target).closest('.qlcm-module-actions').length) {
                $(this).siblings('.qlcm-lessons-list').slideToggle(300);
                $(this).find('.qlcm-module-toggle').toggleClass('expanded');
            }
        });
        
        // Track time spent on page
        if (qlcm_modules_frontend.user_id > 0) {
            trackPageTime();
        }
        
        // Handle lesson completion buttons
        $(document).on('click', '.qlcm-mark-complete', function() {
            const lessonId = $(this).data('lesson-id');
            const courseId = $(this).data('course-id');
            const moduleId = $(this).data('module-id');
            
            markLessonComplete(lessonId, courseId, moduleId, $(this));
        });
        
        // Auto-save progress periodically
        setInterval(function() {
            if (currentLesson) {
                saveCurrentProgress();
            }
        }, 30000); // Save every 30 seconds
        
        // Save progress before page unload
        $(window).on('beforeunload', function() {
            if (currentLesson) {
                saveCurrentProgress();
            }
        });
    }
    
    /**
     * Initialize video players
     */
    function initVideoPlayers() {
        $('.qlcm-video-container video').each(function() {
            const video = this;
            
            // Add loading event handlers
            video.addEventListener('loadstart', function() {
                $(this).closest('.qlcm-video-container').addClass('loading');
            });
            
            video.addEventListener('canplay', function() {
                $(this).closest('.qlcm-video-container').removeClass('loading');
            });
            
            // Handle errors
            video.addEventListener('error', function() {
                handleVideoError($(this));
            });
            
            // Add keyboard navigation
            video.addEventListener('keydown', function(e) {
                handleVideoKeyboard(e, this);
            });
        });
        
        // Handle iframe videos (YouTube, Vimeo)
        $('.qlcm-video-responsive iframe').each(function() {
            // Add title attribute if missing
            if (!$(this).attr('title')) {
                $(this).attr('title', 'Video content');
            }
        });
    }
    
    /**
     * Initialize audio players
     */
    function initAudioPlayers() {
        $('.qlcm-audio-item audio').each(function() {
            const audio = this;
            
            // Add loading event handlers
            audio.addEventListener('loadstart', function() {
                $(this).closest('.qlcm-audio-item').addClass('loading');
            });
            
            audio.addEventListener('canplay', function() {
                $(this).closest('.qlcm-audio-item').removeClass('loading');
            });
            
            // Handle errors
            audio.addEventListener('error', function() {
                handleAudioError($(this));
            });
            
            // Add keyboard navigation
            audio.addEventListener('keydown', function(e) {
                handleAudioKeyboard(e, this);
            });
            
            // Update progress
            audio.addEventListener('timeupdate', function() {
                updateAudioProgress(this);
            });
        });
    }
    
    /**
     * Handle responsive video resizing
     */
    function handleResponsiveVideos() {
        $(window).on('resize', function() {
            $('.qlcm-video-responsive').each(function() {
                const container = $(this);
                const iframe = container.find('iframe');
                
                if (iframe.length) {
                    // Maintain aspect ratio
                    const aspectRatio = iframe.attr('height') / iframe.attr('width');
                    const newHeight = container.width() * aspectRatio;
                    container.css('padding-bottom', (aspectRatio * 100) + '%');
                }
            });
        });
    }
    
    /**
     * Add accessibility features
     */
    function addAccessibilityFeatures() {
        // Add ARIA labels to media elements
        $('.qlcm-video-container video, .qlcm-audio-item audio').each(function() {
            if (!$(this).attr('aria-label')) {
                const title = $(this).closest('.qlcm-video-content, .qlcm-audio-item').find('.qlcm-audio-title').text() || 'Media content';
                $(this).attr('aria-label', title);
            }
        });
        
        // Add skip links for screen readers
        $('.qlcm-multimedia-wrapper').prepend('<a href="#content" class="screen-reader-text">Skip multimedia content</a>');
        
        // Ensure proper focus management
        $('.qlcm-video-container, .qlcm-audio-item').attr('tabindex', '0');
    }
    
    /**
     * Handle loading states
     */
    function handleLoadingStates() {
        // Show loading indicators
        $('.qlcm-video-container, .qlcm-audio-item').each(function() {
            const media = $(this).find('video, audio')[0];
            
            if (media && media.readyState < 3) {
                $(this).addClass('loading');
            }
        });
    }
    
    /**
     * Handle video errors
     */
    function handleVideoError($video) {
        const container = $video.closest('.qlcm-video-container');
        container.removeClass('loading');
        
        const errorHtml = '<div class="qlcm-video-error">' +
            '<p>Sorry, this video could not be loaded. Please try refreshing the page or contact support if the problem persists.</p>' +
            '</div>';
        
        container.html(errorHtml);
    }
    
    /**
     * Handle audio errors
     */
    function handleAudioError($audio) {
        const container = $audio.closest('.qlcm-audio-item');
        container.removeClass('loading');
        
        const errorHtml = '<div class="qlcm-audio-error">' +
            '<p>Sorry, this audio could not be loaded. Please try refreshing the page or contact support if the problem persists.</p>' +
            '</div>';
        
        $audio.replaceWith(errorHtml);
    }
    
    /**
     * Handle video keyboard navigation
     */
    function handleVideoKeyboard(e, video) {
        switch(e.key) {
            case ' ':
            case 'k':
                e.preventDefault();
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
                break;
            case 'ArrowLeft':
                e.preventDefault();
                video.currentTime = Math.max(0, video.currentTime - 10);
                break;
            case 'ArrowRight':
                e.preventDefault();
                video.currentTime = Math.min(video.duration, video.currentTime + 10);
                break;
            case 'ArrowUp':
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                break;
            case 'ArrowDown':
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                break;
            case 'm':
                e.preventDefault();
                video.muted = !video.muted;
                break;
            case 'f':
                e.preventDefault();
                if (video.requestFullscreen) {
                    video.requestFullscreen();
                }
                break;
        }
    }
    
    /**
     * Handle audio keyboard navigation
     */
    function handleAudioKeyboard(e, audio) {
        switch(e.key) {
            case ' ':
            case 'k':
                e.preventDefault();
                if (audio.paused) {
                    audio.play();
                } else {
                    audio.pause();
                }
                break;
            case 'ArrowLeft':
                e.preventDefault();
                audio.currentTime = Math.max(0, audio.currentTime - 10);
                break;
            case 'ArrowRight':
                e.preventDefault();
                audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
                break;
            case 'ArrowUp':
                e.preventDefault();
                audio.volume = Math.min(1, audio.volume + 0.1);
                break;
            case 'ArrowDown':
                e.preventDefault();
                audio.volume = Math.max(0, audio.volume - 0.1);
                break;
            case 'm':
                e.preventDefault();
                audio.muted = !audio.muted;
                break;
        }
    }
    
    /**
     * Update audio progress (for custom progress indicators if needed)
     */
    function updateAudioProgress(audio) {
        // This can be extended to show custom progress indicators
        const progress = (audio.currentTime / audio.duration) * 100;
        
        // Trigger custom event for other scripts to listen to
        $(audio).trigger('qlcm:audio:progress', {
            currentTime: audio.currentTime,
            duration: audio.duration,
            progress: progress
        });
    }
    
    /**
     * Utility function to format time
     */
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
    }
    
    /**
     * Handle media playback analytics (if needed)
     */
    function trackMediaPlayback(mediaElement, action) {
        // This can be extended to track media engagement
        $(document).trigger('qlcm:media:' + action, {
            element: mediaElement,
            currentTime: mediaElement.currentTime,
            duration: mediaElement.duration
        });
    }
    
    /**
     * Start a lesson and track progress
     */
    function startLesson(lessonId, courseId, moduleId, lessonElement) {
        // Save previous lesson progress if any
        if (currentLesson) {
            saveCurrentProgress();
        }
        
        // Set current lesson
        currentLesson = {
            id: lessonId,
            courseId: courseId,
            moduleId: moduleId,
            element: lessonElement
        };
        
        lessonStartTime = Date.now();
        
        // Mark lesson as started
        updateLessonProgress(lessonId, courseId, moduleId, 'in_progress', 'started');
        
        // Update UI
        lessonElement.addClass('qlcm-lesson-active');
        lessonElement.siblings().removeClass('qlcm-lesson-active');
        
        // Show lesson content if available
        showLessonContent(lessonElement);
    }
    
    /**
     * Show lesson content
     */
    function showLessonContent(lessonElement) {
        const lessonContent = lessonElement.find('.qlcm-lesson-content');
        
        if (lessonContent.length) {
            lessonContent.slideDown(300);
        }
        
        // Scroll to lesson
        $('html, body').animate({
            scrollTop: lessonElement.offset().top - 100
        }, 500);
    }
    
    /**
     * Mark lesson as complete
     */
    function markLessonComplete(lessonId, courseId, moduleId, buttonElement) {
        buttonElement.prop('disabled', true).text(qlcm_modules_frontend.strings.loading);
        
        updateLessonProgress(lessonId, courseId, moduleId, 'completed', 'completed', function(response) {
            if (response.success) {
                buttonElement.text(qlcm_modules_frontend.strings.completed).addClass('completed');
                
                // Update lesson UI
                const lessonElement = $('.qlcm-lesson[data-lesson-id="' + lessonId + '"]');
                lessonElement.addClass('qlcm-lesson-completed');
                
                // Update module progress
                updateModuleProgressUI(moduleId);
                
                // Show completion message
                showCompletionMessage(lessonElement);
            } else {
                buttonElement.prop('disabled', false).text('Mark Complete');
                showErrorMessage(response.data || qlcm_modules_frontend.strings.error);
            }
        });
    }
    
    /**
     * Update lesson progress via AJAX
     */
    function updateLessonProgress(lessonId, courseId, moduleId, status, progressData, callback) {
        $.ajax({
            url: qlcm_modules_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_update_lesson_progress',
                nonce: qlcm_modules_frontend.nonce,
                lesson_id: lessonId,
                course_id: courseId,
                module_id: moduleId,
                status: status,
                progress_data: progressData
            },
            success: function(response) {
                if (callback) {
                    callback(response);
                }
            },
            error: function() {
                if (callback) {
                    callback({success: false, data: qlcm_modules_frontend.strings.error});
                }
            }
        });
    }
    
    /**
     * Save current lesson progress
     */
    function saveCurrentProgress() {
        if (!currentLesson) {
            return;
        }
        
        const timeSpent = Math.floor((Date.now() - lessonStartTime) / 1000);
        const progressData = {
            timeSpent: timeSpent,
            lastAccessed: new Date().toISOString()
        };
        
        updateLessonProgress(
            currentLesson.id,
            currentLesson.courseId,
            currentLesson.moduleId,
            'in_progress',
            JSON.stringify(progressData)
        );
    }
    
    /**
     * Track page time for analytics
     */
    function trackPageTime() {
        let pageStartTime = Date.now();
        
        // Track time spent on page
        setInterval(function() {
            const timeSpent = Math.floor((Date.now() - pageStartTime) / 1000);
            
            // Store in session storage for persistence
            sessionStorage.setItem('qlcm_page_time', timeSpent);
        }, 10000); // Update every 10 seconds
    }
    
    /**
     * Update module progress UI
     */
    function updateModuleProgressUI(moduleId) {
        const moduleElement = $('.qlcm-module[data-module-id="' + moduleId + '"]');
        const lessons = moduleElement.find('.qlcm-lesson');
        const completedLessons = moduleElement.find('.qlcm-lesson-completed');
        
        const progressPercentage = Math.round((completedLessons.length / lessons.length) * 100);
        
        // Update progress bar
        const progressBar = moduleElement.find('.qlcm-progress-fill');
        const progressText = moduleElement.find('.qlcm-progress-text');
        
        if (progressBar.length) {
            progressBar.css('width', progressPercentage + '%');
        }
        
        if (progressText.length) {
            progressText.text(progressPercentage + '% Complete');
        }
        
        // Mark module as completed if all lessons are done
        if (progressPercentage === 100) {
            moduleElement.addClass('qlcm-module-completed');
        }
    }
    
    /**
     * Show completion message
     */
    function showCompletionMessage(lessonElement) {
        const message = $('<div class="qlcm-completion-message">' + 
            '<span class="dashicons dashicons-yes-alt"></span> ' +
            'Lesson completed!' +
            '</div>');
        
        lessonElement.append(message);
        
        setTimeout(function() {
            message.fadeOut(function() {
                message.remove();
            });
        }, 3000);
    }
    
    /**
     * Show error message
     */
    function showErrorMessage(message) {
        const errorDiv = $('<div class="qlcm-error-message">' + message + '</div>');
        
        $('body').append(errorDiv);
        
        setTimeout(function() {
            errorDiv.fadeOut(function() {
                errorDiv.remove();
            });
        }, 5000);
    }
    
    /**
     * Initialize lesson content based on type
     */
    function initLessonContent() {
        $('.qlcm-lesson-content').each(function() {
            const lessonType = $(this).data('lesson-type');
            
            switch (lessonType) {
                case 'video':
                    initVideoLesson($(this));
                    break;
                case 'audio':
                    initAudioLesson($(this));
                    break;
                case 'quiz':
                    initQuizLesson($(this));
                    break;
                case 'mixed':
                    initMixedLesson($(this));
                    break;
            }
        });
    }
    
    /**
     * Initialize video lesson
     */
    function initVideoLesson(lessonElement) {
        const video = lessonElement.find('video')[0];
        
        if (video) {
            video.addEventListener('ended', function() {
                // Auto-mark as complete when video ends
                const lessonId = lessonElement.closest('.qlcm-lesson').data('lesson-id');
                const courseId = lessonElement.closest('.qlcm-course-modules').data('course-id');
                const moduleId = lessonElement.closest('.qlcm-module').data('module-id');
                
                if (lessonId && courseId && moduleId) {
                    markLessonComplete(lessonId, courseId, moduleId, lessonElement.find('.qlcm-mark-complete'));
                }
            });
        }
    }
    
    /**
     * Initialize audio lesson
     */
    function initAudioLesson(lessonElement) {
        const audio = lessonElement.find('audio')[0];
        
        if (audio) {
            audio.addEventListener('ended', function() {
                // Auto-mark as complete when audio ends
                const lessonId = lessonElement.closest('.qlcm-lesson').data('lesson-id');
                const courseId = lessonElement.closest('.qlcm-course-modules').data('course-id');
                const moduleId = lessonElement.closest('.qlcm-module').data('module-id');
                
                if (lessonId && courseId && moduleId) {
                    markLessonComplete(lessonId, courseId, moduleId, lessonElement.find('.qlcm-mark-complete'));
                }
            });
        }
    }
    
    /**
     * Initialize quiz lesson
     */
    function initQuizLesson(lessonElement) {
        const quizForm = lessonElement.find('.qlcm-quiz-form');
        
        if (quizForm.length) {
            quizForm.on('submit', function(e) {
                e.preventDefault();
                handleQuizSubmission($(this));
            });
        }
    }
    
    /**
     * Initialize mixed content lesson
     */
    function initMixedLesson(lessonElement) {
        // Initialize both video and audio if present
        initVideoLesson(lessonElement);
        initAudioLesson(lessonElement);
    }
    
    /**
     * Handle quiz submission
     */
    function handleQuizSubmission(quizForm) {
        const formData = quizForm.serialize();
        const lessonId = quizForm.closest('.qlcm-lesson').data('lesson-id');
        const courseId = quizForm.closest('.qlcm-course-modules').data('course-id');
        const moduleId = quizForm.closest('.qlcm-module').data('module-id');
        
        // Disable form during submission
        quizForm.find('input, button').prop('disabled', true);
        
        $.ajax({
            url: qlcm_modules_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_submit_quiz',
                nonce: qlcm_modules_frontend.nonce,
                lesson_id: lessonId,
                course_id: courseId,
                module_id: moduleId,
                quiz_data: formData
            },
            success: function(response) {
                if (response.success) {
                    showQuizResults(quizForm, response.data);
                    
                    // Mark lesson as complete if passed
                    if (response.data.passed) {
                        markLessonComplete(lessonId, courseId, moduleId, quizForm.find('.qlcm-mark-complete'));
                    }
                } else {
                    showErrorMessage(response.data || 'Quiz submission failed');
                }
            },
            error: function() {
                showErrorMessage('Quiz submission failed');
            },
            complete: function() {
                quizForm.find('input, button').prop('disabled', false);
            }
        });
    }
    
    /**
     * Show quiz results
     */
    function showQuizResults(quizForm, results) {
        const resultsHtml = '<div class="qlcm-quiz-results">' +
            '<h4>Quiz Results</h4>' +
            '<p>Score: ' + results.score + '/' + results.total + ' (' + results.percentage + '%)</p>' +
            (results.passed ? '<p class="success">Congratulations! You passed!</p>' : '<p class="error">Please try again to pass.</p>') +
            '</div>';
        
        quizForm.after(resultsHtml);
    }
    
    // Initialize lesson content when DOM is ready
    $(document).ready(function() {
        initLessonContent();
    });
    
})(jQuery);