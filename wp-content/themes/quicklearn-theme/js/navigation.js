/**
 * Navigation functionality for QuickLearn Theme
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileMenu();
        initSmoothScrolling();
        initAccessibility();
    });

    /**
     * Initialize mobile menu functionality
     */
    function initMobileMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navigation = document.querySelector('.main-navigation');
        
        if (!menuToggle || !navigation) {
            return;
        }

        // Toggle mobile menu
        menuToggle.addEventListener('click', function() {
            const isExpanded = navigation.classList.contains('active');
            
            navigation.classList.toggle('active');
            
            // Update ARIA attributes
            menuToggle.setAttribute('aria-expanded', !isExpanded);
            
            // Update button text for screen readers
            const screenReaderText = menuToggle.querySelector('.screen-reader-text');
            if (screenReaderText) {
                screenReaderText.textContent = !isExpanded ? 'Close Menu' : 'Primary Menu';
            }
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!navigation.contains(event.target) && !menuToggle.contains(event.target)) {
                navigation.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
                
                const screenReaderText = menuToggle.querySelector('.screen-reader-text');
                if (screenReaderText) {
                    screenReaderText.textContent = 'Primary Menu';
                }
            }
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && navigation.classList.contains('active')) {
                navigation.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.focus();
                
                const screenReaderText = menuToggle.querySelector('.screen-reader-text');
                if (screenReaderText) {
                    screenReaderText.textContent = 'Primary Menu';
                }
            }
        });

        // Handle window resize with debounce
        const handleResize = debounce(function() {
            if (window.innerWidth > 768) {
                navigation.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
                
                const screenReaderText = menuToggle.querySelector('.screen-reader-text');
                if (screenReaderText) {
                    screenReaderText.textContent = 'Primary Menu';
                }
            }
        }, 250);
        
        window.addEventListener('resize', handleResize);
    }

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScrolling() {
        const anchorLinks = document.querySelectorAll('a[href^="#"]');
        
        anchorLinks.forEach(function(link) {
            link.addEventListener('click', function(event) {
                const href = this.getAttribute('href');
                
                // Skip if it's just a hash
                if (href === '#') {
                    return;
                }
                
                const target = document.querySelector(href);
                
                if (target) {
                    event.preventDefault();
                    
                    const headerHeight = document.querySelector('.site-header').offsetHeight;
                    const targetPosition = target.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Update focus for accessibility
                    target.focus();
                }
            });
        });
    }

    /**
     * Initialize accessibility features
     */
    function initAccessibility() {
        // Add focus indicators for keyboard navigation
        const focusableElements = document.querySelectorAll(
            'a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])'
        );
        
        focusableElements.forEach(function(element) {
            element.addEventListener('focus', function() {
                this.classList.add('keyboard-focus');
            });
            
            element.addEventListener('blur', function() {
                this.classList.remove('keyboard-focus');
            });
            
            element.addEventListener('mousedown', function() {
                this.classList.remove('keyboard-focus');
            });
        });

        // Skip link functionality
        const skipLink = document.querySelector('.skip-link');
        if (skipLink) {
            skipLink.addEventListener('click', function(event) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    event.preventDefault();
                    target.focus();
                    target.scrollIntoView();
                }
            });
        }
    }

    /**
     * Utility function to debounce events
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

})();