/**
 * Lazy Loading Implementation
 * Handles lazy loading of images, videos, and iframes
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        rootMargin: '50px 0px',
        threshold: 0.01,
        loadingClass: 'lazy-load',
        loadedClass: 'loaded',
        errorClass: 'error'
    };
    
    // Check for Intersection Observer support
    const supportsIntersectionObserver = 'IntersectionObserver' in window;
    
    /**
     * Initialize lazy loading
     */
    function initLazyLoading() {
        if (supportsIntersectionObserver) {
            initIntersectionObserver();
        } else {
            // Fallback for older browsers
            initScrollListener();
        }
        
        // Initialize AJAX pagination lazy loading
        initAjaxPagination();
    }
    
    /**
     * Initialize Intersection Observer for modern browsers
     */
    function initIntersectionObserver() {
        const imageObserver = new IntersectionObserver(handleIntersection, config);
        const videoObserver = new IntersectionObserver(handleVideoIntersection, config);
        const iframeObserver = new IntersectionObserver(handleIframeIntersection, config);
        
        // Observe all lazy load elements
        observeElements(imageObserver, 'img.' + config.loadingClass);
        observeElements(videoObserver, 'video.lazy-load-video');
        observeElements(iframeObserver, 'iframe.lazy-load-iframe');
        
        // Re-observe elements when new content is loaded via AJAX
        document.addEventListener('quicklearn:contentLoaded', function() {
            observeElements(imageObserver, 'img.' + config.loadingClass + ':not(.' + config.loadedClass + ')');
            observeElements(videoObserver, 'video.lazy-load-video:not(.loaded)');
            observeElements(iframeObserver, 'iframe.lazy-load-iframe:not(.loaded)');
        });
    }
    
    /**
     * Observe elements with given observer
     */
    function observeElements(observer, selector) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(element => observer.observe(element));
    }
    
    /**
     * Handle intersection for images
     */
    function handleIntersection(entries, observer) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                loadImage(img);
                observer.unobserve(img);
            }
        });
    }
    
    /**
     * Handle intersection for videos
     */
    function handleVideoIntersection(entries, observer) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const video = entry.target;
                loadVideo(video);
                observer.unobserve(video);
            }
        });
    }
    
    /**
     * Handle intersection for iframes
     */
    function handleIframeIntersection(entries, observer) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const iframe = entry.target;
                loadIframe(iframe);
                observer.unobserve(iframe);
            }
        });
    }
    
    /**
     * Load image with lazy loading
     */
    function loadImage(img) {
        const src = img.dataset.src;
        const srcset = img.dataset.srcset;
        
        if (!src) return;
        
        // Create a new image to preload
        const imageLoader = new Image();
        
        imageLoader.onload = function() {
            // Update the actual image
            img.src = src;
            if (srcset) {
                img.srcset = srcset;
            }
            
            // Add loaded class and remove loading class
            img.classList.add(config.loadedClass);
            img.classList.remove(config.loadingClass);
            
            // Remove data attributes
            delete img.dataset.src;
            delete img.dataset.srcset;
            
            // Trigger custom event
            img.dispatchEvent(new CustomEvent('quicklearn:imageLoaded', {
                bubbles: true,
                detail: { element: img }
            }));
        };
        
        imageLoader.onerror = function() {
            img.classList.add(config.errorClass);
            img.classList.remove(config.loadingClass);
        };
        
        // Start loading
        imageLoader.src = src;
        if (srcset) {
            imageLoader.srcset = srcset;
        }
    }
    
    /**
     * Load video with lazy loading
     */
    function loadVideo(video) {
        // Set preload to metadata to start loading
        video.preload = 'metadata';
        
        // Add loaded class
        video.classList.add('loaded');
        
        // Load video sources
        const sources = video.querySelectorAll('source[data-src]');
        sources.forEach(source => {
            source.src = source.dataset.src;
            delete source.dataset.src;
        });
        
        // Load video if it has src attribute
        if (video.dataset.src) {
            video.src = video.dataset.src;
            delete video.dataset.src;
        }
        
        video.load();
    }
    
    /**
     * Load iframe with lazy loading
     */
    function loadIframe(iframe) {
        const src = iframe.dataset.src;
        
        if (src) {
            iframe.src = src;
            iframe.classList.add('loaded');
            delete iframe.dataset.src;
        }
    }
    
    /**
     * Fallback scroll listener for older browsers
     */
    function initScrollListener() {
        let ticking = false;
        
        function handleScroll() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    checkElementsInViewport();
                    ticking = false;
                });
                ticking = true;
            }
        }
        
        function checkElementsInViewport() {
            const elements = document.querySelectorAll('.' + config.loadingClass + ':not(.' + config.loadedClass + ')');
            
            elements.forEach(element => {
                if (isElementInViewport(element)) {
                    if (element.tagName === 'IMG') {
                        loadImage(element);
                    } else if (element.tagName === 'VIDEO') {
                        loadVideo(element);
                    } else if (element.tagName === 'IFRAME') {
                        loadIframe(element);
                    }
                }
            });
        }
        
        function isElementInViewport(element) {
            const rect = element.getBoundingClientRect();
            const windowHeight = window.innerHeight || document.documentElement.clientHeight;
            const windowWidth = window.innerWidth || document.documentElement.clientWidth;
            
            return (
                rect.top >= -50 &&
                rect.left >= -50 &&
                rect.bottom <= windowHeight + 50 &&
                rect.right <= windowWidth + 50
            );
        }
        
        // Listen for scroll events
        window.addEventListener('scroll', handleScroll, { passive: true });
        window.addEventListener('resize', handleScroll, { passive: true });
        
        // Check initial viewport
        checkElementsInViewport();
    }
    
    /**
     * Initialize AJAX pagination for course lists
     */
    function initAjaxPagination() {
        const loadMoreButton = document.querySelector('.load-more-courses');
        const coursesContainer = document.querySelector('.courses-grid');
        
        if (!loadMoreButton || !coursesContainer) return;
        
        loadMoreButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const button = e.target;
            const currentPage = parseInt(button.dataset.page) || 1;
            const nextPage = currentPage + 1;
            const category = button.dataset.category || '';
            const postsPerPage = parseInt(button.dataset.postsPerPage) || 12;
            
            // Show loading state
            button.disabled = true;
            button.textContent = button.dataset.loadingText || 'Loading...';
            
            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'load_more_courses');
            formData.append('nonce', quicklearn_ajax.pagination_nonce);
            formData.append('page', nextPage);
            formData.append('category', category);
            formData.append('posts_per_page', postsPerPage);
            
            // Make AJAX request
            fetch(quicklearn_ajax.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Append new courses
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.data.html;
                    
                    // Add each course card with animation
                    const newCourses = tempDiv.querySelectorAll('.course-card');
                    newCourses.forEach((course, index) => {
                        setTimeout(() => {
                            course.style.opacity = '0';
                            course.style.transform = 'translateY(20px)';
                            coursesContainer.appendChild(course);
                            
                            // Animate in
                            requestAnimationFrame(() => {
                                course.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                course.style.opacity = '1';
                                course.style.transform = 'translateY(0)';
                            });
                        }, index * 100);
                    });
                    
                    // Update button state
                    if (data.data.has_more) {
                        button.dataset.page = nextPage;
                        button.disabled = false;
                        button.textContent = button.dataset.originalText || 'Load More Courses';
                    } else {
                        button.style.display = 'none';
                    }
                    
                    // Trigger content loaded event for lazy loading
                    document.dispatchEvent(new CustomEvent('quicklearn:contentLoaded'));
                    
                } else {
                    console.error('Failed to load more courses:', data.data.message);
                    button.disabled = false;
                    button.textContent = 'Error - Try Again';
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                button.disabled = false;
                button.textContent = 'Error - Try Again';
            });
        });
        
        // Store original button text
        if (loadMoreButton.dataset.originalText === undefined) {
            loadMoreButton.dataset.originalText = loadMoreButton.textContent;
        }
    }
    
    /**
     * Optimize images after loading
     */
    function optimizeLoadedImages() {
        document.addEventListener('quicklearn:imageLoaded', function(e) {
            const img = e.detail.element;
            
            // Add fade-in animation
            img.style.transition = 'opacity 0.3s ease';
            
            // Optimize image display
            if (img.naturalWidth > img.offsetWidth * 2) {
                // Image is much larger than display size, could be optimized
                console.log('Large image detected:', img.src);
            }
        });
    }
    
    /**
     * Handle WebP support
     */
    function handleWebPSupport() {
        // Check WebP support
        const webpSupported = (function() {
            const canvas = document.createElement('canvas');
            canvas.width = 1;
            canvas.height = 1;
            return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
        })();
        
        if (webpSupported) {
            document.documentElement.classList.add('webp-supported');
            
            // Replace image sources with WebP versions if available
            document.addEventListener('quicklearn:imageLoaded', function(e) {
                const img = e.detail.element;
                const webpSrc = img.src.replace(/\.(jpg|jpeg|png)$/i, '.webp');
                
                // Check if WebP version exists
                const webpTest = new Image();
                webpTest.onload = function() {
                    if (webpTest.width > 0) {
                        img.src = webpSrc;
                    }
                };
                webpTest.src = webpSrc;
            });
        }
    }
    
    /**
     * Performance monitoring
     */
    function initPerformanceMonitoring() {
        // Monitor lazy loading performance
        let lazyLoadCount = 0;
        let lazyLoadTime = 0;
        
        document.addEventListener('quicklearn:imageLoaded', function() {
            lazyLoadCount++;
            lazyLoadTime = performance.now();
        });
        
        // Log performance metrics
        window.addEventListener('load', function() {
            setTimeout(() => {
                if (window.console && console.log) {
                    console.log('Lazy loading stats:', {
                        imagesLoaded: lazyLoadCount,
                        lastLoadTime: lazyLoadTime,
                        totalLoadTime: performance.now()
                    });
                }
            }, 1000);
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyLoading);
    } else {
        initLazyLoading();
    }
    
    // Initialize additional optimizations
    optimizeLoadedImages();
    handleWebPSupport();
    
    // Initialize performance monitoring in development
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        initPerformanceMonitoring();
    }
    
})();