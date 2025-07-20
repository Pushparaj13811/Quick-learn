/**
 * User Enrollment and Progress Tracking JavaScript
 * QuickLearn Course Manager Plugin
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initEnrollmentButtons();
        initProgressTracking();
    });
    
    /**
     * Initialize enrollment button functionality
     */
    function initEnrollmentButtons() {
        // Handle enrollment button clicks
        $(document).on('click', '.qlcm-enroll-button', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const courseId = $button.data('course-id');
            
            if (!courseId) {
                showMessage(__('Invalid course ID'), 'error');
                return;
            }
            
            enrollInCourse($button, courseId);
        });
        
        // Handle login required buttons
        $(document).on('click', '.qlcm-login-required', function(e) {
            // Allow default behavior (redirect to login)
            return true;
        });
    }
    
    /**
     * Initialize progress tracking functionality
     */
    function initProgressTracking() {
        // Track scroll progress for course content
        if ($('body').hasClass('single-quick_course')) {
            trackCourseProgress();
        }
        
        // Handle manual progress updates
        $(document).on('click', '.qlcm-mark-complete', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const courseId = $button.data('course-id');
            const moduleId = $button.data('module-id') || 'main-content';
            const progress = $button.data('progress') || 100;
            
            updateCourseProgress(courseId, moduleId, progress);
        });
    }
    
    /**
     * Enroll user in course via AJAX
     */
    function enrollInCourse($button, courseId) {
        const originalText = $button.text();
        
        // Show loading state
        $button.prop('disabled', true)
               .text(qlcm_enrollment.i18n.enrolling)
               .addClass('qlcm-loading');
        
        // Prepare enrollment data
        const enrollmentData = {
            action: 'enroll_in_course',
            nonce: qlcm_enrollment.nonce,
            course_id: courseId
        };
        
        // Submit enrollment request
        $.ajax({
            url: qlcm_enrollment.ajax_url,
            type: 'POST',
            data: enrollmentData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    
                    // Update button to show enrolled state
                    updateEnrollmentButton($button, 'enrolled', response.data.progress || 0);
                    
                    // Optionally reload page to show updated content
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    
                } else {
                    showMessage(response.data.message || qlcm_enrollment.i18n.error, 'error');
                    
                    // Check if redirect is needed (e.g., to login)
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 2000);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Enrollment error:', error);
                
                if (xhr.status === 403) {
                    showMessage(qlcm_enrollment.i18n.login_required, 'error');
                } else {
                    showMessage(qlcm_enrollment.i18n.error, 'error');
                }
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false)
                       .removeClass('qlcm-loading');
                
                if (!$button.hasClass('qlcm-enrolled')) {
                    $button.text(originalText);
                }
            }
        });
    }
    
    /**
     * Update enrollment button state
     */
    function updateEnrollmentButton($button, state, progress) {
        const $container = $button.closest('.qlcm-enrollment-container');
        
        switch (state) {
            case 'enrolled':
                $button.removeClass('qlcm-enroll-button')
                       .addClass('qlcm-continue-button')
                       .text(qlcm_enrollment.i18n.continue);
                
                // Add progress bar if not exists
                if (!$container.find('.qlcm-progress-bar').length) {
                    const progressHtml = `
                        <div class="qlcm-enrollment-status qlcm-in-progress">
                            <div class="qlcm-progress-bar">
                                <div class="qlcm-progress-fill" style="width:${progress}%"></div>
                            </div>
                            <span class="qlcm-progress-text">${progress}% ${__('Complete')}</span>
                        </div>
                    `;
                    $button.before(progressHtml);
                }
                break;
                
            case 'completed':
                $button.removeClass('qlcm-enroll-button qlcm-continue-button')
                       .addClass('qlcm-review-button')
                       .text(qlcm_enrollment.i18n.complete);
                
                // Update status to completed
                $container.find('.qlcm-enrollment-status')
                         .removeClass('qlcm-in-progress')
                         .addClass('qlcm-completed')
                         .html('<span class="dashicons dashicons-yes-alt"></span> ' + __('Course Completed'));
                break;
        }
    }
    
    /**
     * Track course progress based on scroll and time
     */
    function trackCourseProgress() {
        let progressTimer;
        let lastProgress = 0;
        const courseId = $('body').data('course-id') || $('.qlcm-enroll-button').data('course-id');
        
        if (!courseId) {
            return;
        }
        
        // Track scroll progress
        $(window).on('scroll', function() {
            clearTimeout(progressTimer);
            
            progressTimer = setTimeout(function() {
                const scrollTop = $(window).scrollTop();
                const docHeight = $(document).height() - $(window).height();
                const scrollPercent = Math.round((scrollTop / docHeight) * 100);
                
                // Only update if progress increased significantly
                if (scrollPercent > lastProgress + 10) {
                    lastProgress = scrollPercent;
                    updateCourseProgress(courseId, 'scroll-progress', Math.min(scrollPercent, 90));
                }
            }, 1000);
        });
        
        // Track time spent on page
        let timeSpent = 0;
        const timeTracker = setInterval(function() {
            timeSpent += 30; // 30 seconds
            
            // Update progress based on time (max 80% for time-based progress)
            const timeProgress = Math.min(Math.round(timeSpent / 300) * 10, 80); // 5 minutes = 80%
            
            if (timeProgress > lastProgress) {
                lastProgress = timeProgress;
                updateCourseProgress(courseId, 'time-progress', timeProgress);
            }
            
            // Stop tracking after 30 minutes
            if (timeSpent >= 1800) {
                clearInterval(timeTracker);
            }
        }, 30000);
        
        // Track when user reaches the end of content
        const $courseContent = $('.entry-content, .course-content, main');
        if ($courseContent.length) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.8) {
                        // User has viewed most of the content
                        updateCourseProgress(courseId, 'content-viewed', 100);
                    }
                });
            }, {
                threshold: 0.8
            });
            
            observer.observe($courseContent[0]);
        }
    }
    
    /**
     * Update course progress via AJAX
     */
    function updateCourseProgress(courseId, moduleId, progress) {
        // Don't update if progress is 0 or if we're not logged in
        if (progress <= 0 || !qlcm_enrollment.nonce) {
            return;
        }
        
        const progressData = {
            action: 'update_course_progress',
            nonce: qlcm_enrollment.nonce,
            course_id: courseId,
            module_id: moduleId,
            progress: progress
        };
        
        $.ajax({
            url: qlcm_enrollment.ajax_url,
            type: 'POST',
            data: progressData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update progress display
                    updateProgressDisplay(response.data.progress);
                    
                    // Check if course is completed
                    if (response.data.progress >= 100) {
                        const $button = $('.qlcm-continue-button');
                        if ($button.length) {
                            updateEnrollmentButton($button, 'completed', 100);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                // Silently fail for progress updates to avoid disrupting user experience
                console.log('Progress update failed:', error);
            }
        });
    }
    
    /**
     * Update progress display elements
     */
    function updateProgressDisplay(progress) {
        // Update progress bars
        $('.qlcm-progress-fill').css('width', progress + '%');
        $('.qlcm-progress-text').text(progress + '% ' + __('Complete'));
        
        // Update progress bar aria-label for accessibility
        $('.qlcm-progress-bar').attr('aria-label', progress + '% complete');
    }
    
    /**
     * Show success/error message
     */
    function showMessage(message, type) {
        // Remove existing messages
        $('.qlcm-message').remove();
        
        // Create new message
        const $message = $('<div class="qlcm-message qlcm-message-' + type + '">' + message + '</div>');
        
        // Insert message
        const $container = $('.qlcm-enrollment-container');
        if ($container.length) {
            $container.before($message);
        } else {
            $('main, .content, .entry-content').first().prepend($message);
        }
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }
    
    /**
     * Simple translation function (fallback)
     */
    function __(text) {
        return qlcm_enrollment.i18n[text] || text;
    }
    
    /**
     * Handle page visibility changes to pause/resume tracking
     */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, pause tracking
            $(window).off('scroll.progress');
        } else {
            // Page is visible, resume tracking
            if ($('body').hasClass('single-quick_course')) {
                trackCourseProgress();
            }
        }
    });
    
})(jQuery);