/**
 * Course Filter JavaScript
 * Handles AJAX filtering of courses by category
 * 
 * @package QuickLearn
 */

(function($) {
    'use strict';

    /**
     * Course Filter Object
     */
    const CourseFilter = {
        
        /**
         * Initialize the course filter
         */
        init: function() {
            this.bindEvents();
            this.setupLoadingIndicator();
            this.initImageOptimization();
            this.initResponsiveFeatures();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle category filter change
            $('#course-category-filter').on('change', this.handleFilterChange.bind(this));
        },

        /**
         * Setup loading indicator elements
         */
        setupLoadingIndicator: function() {
            // Create spinner CSS if not already present
            if (!$('#course-filter-spinner-css').length) {
                $('<style id="course-filter-spinner-css">')
                    .text(`
                        .loading-indicator {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            padding: 15px;
                            background: #f8f9fa;
                            border-radius: 4px;
                            margin: 15px 0;
                        }
                        .spinner {
                            width: 20px;
                            height: 20px;
                            border: 2px solid #e3e3e3;
                            border-top: 2px solid #007cba;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                        }
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        .courses-grid.loading {
                            opacity: 0.6;
                            pointer-events: none;
                        }
                    `)
                    .appendTo('head');
            }
        },

        /**
         * Handle filter dropdown change
         */
        handleFilterChange: function(event) {
            const selectedCategory = $(event.target).val();
            this.filterCourses(selectedCategory);
        },

        /**
         * Filter courses via AJAX
         */
        filterCourses: function(category) {
            // Record start time for performance monitoring (Requirement 7.2)
            const startTime = performance.now();
            
            // Show loading state
            this.showLoading();

            // Prepare AJAX data
            const ajaxData = {
                action: 'filter_courses',
                category: category,
                nonce: quicklearn_ajax.nonce
            };

            // Make AJAX request
            $.ajax({
                url: quicklearn_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    this.handleFilterSuccess(response);
                    // Monitor performance after successful response
                    if (response.success && response.data) {
                        this.monitorPerformance(startTime, response.data);
                    }
                },
                error: this.handleFilterError.bind(this),
                complete: this.hideLoading.bind(this)
            });
        },

        /**
         * Handle successful AJAX response (Requirement 5.4 - Validate and escape all output data)
         */
        handleFilterSuccess: function(response) {
            // Validate response structure (Requirement 5.1)
            if (!this.validateAjaxResponse(response)) {
                this.showError(quicklearn_ajax.general_error || 'Invalid response received.');
                return;
            }
            
            if (response.success && response.data) {
                // Sanitize HTML content before inserting (Requirement 5.4)
                const sanitizedHtml = this.sanitizeHtmlContent(response.data.html);
                
                // Apply smooth transition when updating content (Requirement 7.1)
                this.updateCoursesWithTransition(sanitizedHtml, response.data);
                
                // Update URL without page reload (optional)
                this.updateURL(response.data.category);
                
                // Trigger custom event for other scripts
                $(document).trigger('coursesFiltered', [response.data]);
                
            } else {
                // Handle error in response
                const errorMessage = response.data && response.data.message ? 
                    this.escapeHtml(response.data.message) : 
                    (quicklearn_ajax.general_error || 'Unknown error occurred');
                this.showError(errorMessage);
            }
        },

        /**
         * Update courses with smooth transition (Requirement 7.1)
         */
        updateCoursesWithTransition: function(html, responseData) {
            const $coursesGrid = $('#courses-grid');
            
            // Fade out current content
            $coursesGrid.fadeOut(300, () => {
                // Update content
                $coursesGrid.html(html);
                
                // Show success message if filtering was applied
                if (responseData.found_posts !== undefined) {
                    this.showFilterSuccess(responseData);
                }
                
                // Fade in new content with staggered animation
                $coursesGrid.fadeIn(300, () => {
                    // Apply staggered animation to course cards
                    this.animateCourseCards();
                    
                    // Re-initialize image optimization for new content
                    this.setupLazyLoading();
                });
            });
        },





        /**
         * Show loading state with enhanced feedback (Requirement 7.1)
         */
        showLoading: function() {
            // Show loading indicator with smooth transition
            $('.loading-indicator').fadeIn(200).addClass('show');
            
            // Add loading class to courses grid with smooth transition
            $('#courses-grid').addClass('loading');
            
            // Disable filter dropdown to prevent multiple requests
            $('#course-category-filter').prop('disabled', true);
            
            // Update loading text dynamically
            $('.loading-text').text(quicklearn_ajax.loading_text || 'Loading courses...');
            
            // Add loading state to filter container
            $('.course-filters').addClass('filtering');
            
            // Add progress indicator for slow loading
            this.addProgressIndicator();
            
            // Start loading timeout for user feedback (Requirement 7.2)
            this.loadingTimeout = setTimeout(() => {
                if ($('.loading-indicator').is(':visible')) {
                    $('.loading-text').text(quicklearn_ajax.loading_slow_text || 'This is taking longer than expected...');
                    this.showSlowLoadingFeedback();
                }
            }, 3000);
            
            // Add accessibility announcement
            this.announceToScreenReader(quicklearn_ajax.loading_text || 'Loading courses...');
        },



        /**
         * Show error message (Requirement 5.4 - Validate and escape all output data)
         */
        showError: function(message) {
            // Remove any existing error messages
            $('.course-filter-error').remove();
            
            // Escape the message for safe HTML insertion
            const escapedMessage = this.escapeHtml(message);
            
            // Create and show error message
            const errorHtml = `
                <div class="course-filter-error" style="
                    background: #f8d7da;
                    color: #721c24;
                    padding: 12px 16px;
                    border: 1px solid #f5c6cb;
                    border-radius: 4px;
                    margin: 15px 0;
                ">
                    <strong>Error:</strong> ${escapedMessage}
                    <button type="button" class="error-dismiss" style="
                        float: right;
                        background: none;
                        border: none;
                        font-size: 18px;
                        cursor: pointer;
                        color: #721c24;
                    ">&times;</button>
                </div>
            `;
            
            $('.course-filters').after(errorHtml);
            
            // Handle error dismiss
            $('.error-dismiss').on('click', function() {
                $(this).closest('.course-filter-error').fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Auto-hide error after 5 seconds
            setTimeout(function() {
                $('.course-filter-error').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Update URL without page reload (optional enhancement)
         */
        updateURL: function(category) {
            if (history.pushState) {
                const url = new URL(window.location);
                if (category) {
                    url.searchParams.set('category', category);
                } else {
                    url.searchParams.delete('category');
                }
                history.pushState(null, '', url);
            }
        },

        /**
         * Initialize image optimization features
         */
        initImageOptimization: function() {
            // Handle lazy loading for existing images
            this.setupLazyLoading();
            
            // Listen for new images after AJAX filtering
            $(document).on('coursesFiltered', this.setupLazyLoading.bind(this));
        },

        /**
         * Setup lazy loading for images
         */
        setupLazyLoading: function() {
            const images = $('.course-thumbnail img[loading="lazy"]');
            
            images.each(function() {
                const img = $(this);
                
                // Add loading class
                img.addClass('loading');
                
                // Handle image load event
                img.on('load', function() {
                    $(this).removeClass('loading').addClass('loaded');
                });
                
                // Handle image error
                img.on('error', function() {
                    $(this).removeClass('loading').addClass('error');
                    // Add fallback placeholder
                    $(this).closest('.course-thumbnail').addClass('no-image');
                });
                
                // If image is already cached and loaded
                if (this.complete && this.naturalHeight !== 0) {
                    img.removeClass('loading').addClass('loaded');
                }
            });
        },

        /**
         * Initialize responsive features
         */
        initResponsiveFeatures: function() {
            // Handle window resize for responsive adjustments
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    CourseFilter.handleResponsiveResize();
                }, 250);
            });
            
            // Initial responsive setup
            this.handleResponsiveResize();
        },

        /**
         * Handle responsive resize adjustments
         */
        handleResponsiveResize: function() {
            const windowWidth = $(window).width();
            
            // Adjust filter container layout based on screen size
            if (windowWidth <= 768) {
                $('.filter-container').addClass('mobile-layout');
            } else {
                $('.filter-container').removeClass('mobile-layout');
            }
            
            // Adjust course grid animations for mobile
            if (windowWidth <= 480) {
                $('.course-card').addClass('mobile-card');
            } else {
                $('.course-card').removeClass('mobile-card');
            }
        },

        /**
         * Validate AJAX response structure (Requirement 5.1)
         */
        validateAjaxResponse: function(response) {
            // Check if response is an object
            if (typeof response !== 'object' || response === null) {
                return false;
            }
            
            // Check if response has required properties
            if (!response.hasOwnProperty('success')) {
                return false;
            }
            
            // If success is true, check for data property
            if (response.success === true && !response.hasOwnProperty('data')) {
                return false;
            }
            
            // If success is false, should have data with message
            if (response.success === false && response.data && typeof response.data.message !== 'string') {
                return false;
            }
            
            return true;
        },

        /**
         * Sanitize HTML content (Requirement 5.4)
         */
        sanitizeHtmlContent: function(html) {
            if (typeof html !== 'string') {
                return '';
            }
            
            // Create a temporary div to parse HTML
            const tempDiv = $('<div>').html(html);
            
            // Remove any script tags for security
            tempDiv.find('script').remove();
            
            // Remove any potentially dangerous attributes
            tempDiv.find('*').each(function() {
                const element = $(this);
                const allowedAttrs = ['class', 'id', 'href', 'src', 'alt', 'title', 'data-categories', 'datetime', 'rel', 'aria-hidden', 'tabindex', 'loading', 'decoding', 'sizes', 'srcset'];
                
                // Get all attributes
                const attrs = this.attributes;
                const attrsToRemove = [];
                
                for (let i = 0; i < attrs.length; i++) {
                    const attrName = attrs[i].name.toLowerCase();
                    if (!allowedAttrs.includes(attrName) && !attrName.startsWith('data-')) {
                        attrsToRemove.push(attrName);
                    }
                }
                
                // Remove dangerous attributes
                attrsToRemove.forEach(function(attr) {
                    element.removeAttr(attr);
                });
            });
            
            return tempDiv.html();
        },

        /**
         * Escape HTML content (Requirement 5.4)
         */
        escapeHtml: function(text) {
            if (typeof text !== 'string') {
                return '';
            }
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        },

        /**
         * Add progress indicator for slow loading (Requirement 7.1, 7.2)
         */
        addProgressIndicator: function() {
            // Check if progress indicator already exists
            if ($('.loading-progress').length === 0) {
                const progressHtml = `
                    <div class="loading-progress">
                        <div class="loading-progress-bar"></div>
                    </div>
                `;
                $('.loading-indicator').append(progressHtml);
            }
        },

        /**
         * Show slow loading feedback (Requirement 7.1, 7.2)
         */
        showSlowLoadingFeedback: function() {
            // Add visual feedback for slow loading
            $('.loading-indicator').addClass('slow-loading');
            
            // Show additional help text
            if ($('.slow-loading-help').length === 0) {
                const helpHtml = `
                    <div class="slow-loading-help" style="
                        margin-top: 0.5rem;
                        font-size: 0.8rem;
                        color: #6c757d;
                        text-align: center;
                        font-style: italic;
                    ">
                        ${this.escapeHtml(quicklearn_ajax.network_error || 'Please check your connection...')}
                    </div>
                `;
                $('.loading-indicator').append(helpHtml);
            }
        },

        /**
         * Announce to screen reader for accessibility (Requirement 7.1)
         */
        announceToScreenReader: function(message) {
            // Create or update screen reader announcement
            let $announcement = $('#sr-announcement');
            if ($announcement.length === 0) {
                $announcement = $('<div id="sr-announcement" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
                $('body').append($announcement);
            }
            
            // Update the announcement
            $announcement.text(this.escapeHtml(message));
        },

        /**
         * Enhanced filter success message with better localization (Requirement 7.1, 2.4)
         */
        showFilterSuccess: function(responseData) {
            // Remove any existing success messages
            $('.filter-success-message').remove();
            
            const categoryName = responseData.category_name || 'selected category';
            const foundPosts = responseData.found_posts || 0;
            
            let message;
            if (foundPosts === 0) {
                message = `${quicklearn_ajax.no_courses_text || 'No courses found'}`;
                if (responseData.category) {
                    message += ` ${quicklearn_ajax.filter_success_category ? quicklearn_ajax.filter_success_category.replace('%s', categoryName) : 'in ' + categoryName}`;
                }
            } else if (foundPosts === 1) {
                message = quicklearn_ajax.filter_success_single || 'Found 1 course';
                if (responseData.category) {
                    message += ` ${quicklearn_ajax.filter_success_category ? quicklearn_ajax.filter_success_category.replace('%s', categoryName) : 'in ' + categoryName}`;
                }
            } else {
                message = quicklearn_ajax.filter_success_multiple ? 
                    quicklearn_ajax.filter_success_multiple.replace('%d', foundPosts) : 
                    `Found ${foundPosts} courses`;
                if (responseData.category) {
                    message += ` ${quicklearn_ajax.filter_success_category ? quicklearn_ajax.filter_success_category.replace('%s', categoryName) : 'in ' + categoryName}`;
                }
            }
            
            // Create success message with enhanced styling
            const successHtml = `
                <div class="filter-success-message" role="status" aria-live="polite">
                    ${this.escapeHtml(message)}
                </div>
            `;
            
            $('.course-filters').after(successHtml);
            
            // Announce to screen reader
            this.announceToScreenReader(message);
            
            // Auto-hide success message after 4 seconds
            setTimeout(() => {
                $('.filter-success-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        /**
         * Enhanced error handling with better user feedback (Requirement 7.1, 5.4)
         */
        handleFilterError: function(xhr, status, error) {
            let errorMessage = quicklearn_ajax.general_error || 'Failed to load courses. ';
            
            // Provide specific error messages based on error type
            if (status === 'timeout') {
                errorMessage = quicklearn_ajax.timeout_error || 'Request timed out. Please try again.';
            } else if (status === 'parsererror') {
                errorMessage = 'Invalid response from server. Please refresh the page and try again.';
            } else if (status === 'error') {
                if (xhr.status === 0) {
                    errorMessage = quicklearn_ajax.network_error || 'Network error. Please check your connection and try again.';
                } else if (xhr.status === 403) {
                    errorMessage = quicklearn_ajax.security_error || 'Access denied. Please refresh the page and try again.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else {
                    errorMessage = `Error ${xhr.status}: ${xhr.statusText || 'Unknown error occurred'}`;
                }
            }
            
            this.showError(errorMessage);
            
            // Log detailed error for debugging
            console.error('Course filter AJAX error:', {
                status: status,
                error: error,
                xhr: xhr,
                responseText: xhr.responseText
            });
            
            // Announce error to screen reader
            this.announceToScreenReader('Error: ' + errorMessage);
        },

        /**
         * Enhanced loading state cleanup (Requirement 7.1)
         */
        hideLoading: function() {
            // Clear loading timeout
            if (this.loadingTimeout) {
                clearTimeout(this.loadingTimeout);
                this.loadingTimeout = null;
            }
            
            // Remove slow loading indicators
            $('.loading-progress').remove();
            $('.slow-loading-help').remove();
            $('.loading-indicator').removeClass('slow-loading');
            
            // Hide loading indicator with smooth transition
            $('.loading-indicator').fadeOut(200).removeClass('show');
            
            // Remove loading class from courses grid
            $('#courses-grid').removeClass('loading');
            
            // Re-enable filter dropdown
            $('#course-category-filter').prop('disabled', false);
            
            // Remove loading state from filter container
            $('.course-filters').removeClass('filtering');
            
            // Reset loading text
            $('.loading-text').text(quicklearn_ajax.loading_text || 'Loading courses...');
            
            // Clear screen reader announcement after a delay
            setTimeout(() => {
                this.announceToScreenReader('');
            }, 1000);
        },

        /**
         * Enhanced course card animation with performance optimization (Requirement 7.1)
         */
        animateCourseCards: function() {
            const $courseCards = $('.course-card');
            
            // Reset animation classes
            $courseCards.removeClass('animate-in');
            
            // Use requestAnimationFrame for better performance
            const animateCard = (index) => {
                if (index < $courseCards.length) {
                    const $card = $($courseCards[index]);
                    $card.addClass('animate-in');
                    
                    // Schedule next animation
                    setTimeout(() => {
                        requestAnimationFrame(() => animateCard(index + 1));
                    }, 100);
                }
            };
            
            // Start animation sequence
            if ($courseCards.length > 0) {
                requestAnimationFrame(() => animateCard(0));
            }
        },

        /**
         * Performance monitoring for AJAX requests (Requirement 7.2)
         */
        monitorPerformance: function(startTime, responseData) {
            const endTime = performance.now();
            const duration = endTime - startTime;
            
            // Log performance metrics
            console.log('Course filter performance:', {
                duration: duration + 'ms',
                foundPosts: responseData.found_posts,
                cacheHit: responseData.cache_hit,
                responseTime: responseData.response_time
            });
            
            // Show performance warning if request is slow
            if (duration > 2000 && !responseData.cache_hit) {
                console.warn('Slow course filter request detected:', duration + 'ms');
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on courses page
        if ($('#course-category-filter').length) {
            CourseFilter.init();
        }
    });

    // Make CourseFilter available globally for debugging
    window.QuickLearnCourseFilter = CourseFilter;

})(jQuery);