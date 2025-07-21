/**
 * Navigation functionality for QuickLearn Theme
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileMenu();
        initUserDropdown();
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
     * Initialize user dropdown functionality
     */
    function initUserDropdown() {
        const userDropdown = document.querySelector('.user-dropdown');
        
        if (!userDropdown) {
            return;
        }

        const userToggle = userDropdown.querySelector('.user-toggle');
        const dropdownMenu = userDropdown.querySelector('.user-dropdown-menu');
        
        if (!userToggle || !dropdownMenu) {
            return;
        }

        // Toggle dropdown menu
        userToggle.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const isExpanded = userToggle.getAttribute('aria-expanded') === 'true';
            
            // Close any other open dropdowns first
            closeAllDropdowns();
            
            if (!isExpanded) {
                userDropdown.classList.add('active');
                userToggle.setAttribute('aria-expanded', 'true');
                
                // Focus first menu item for keyboard navigation
                const firstMenuItem = dropdownMenu.querySelector('a');
                if (firstMenuItem) {
                    setTimeout(() => firstMenuItem.focus(), 100);
                }
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!userDropdown.contains(event.target)) {
                closeDropdown(userDropdown, userToggle);
            }
        });

        // Handle keyboard navigation
        userDropdown.addEventListener('keydown', function(event) {
            const menuItems = dropdownMenu.querySelectorAll('a');
            const currentIndex = Array.from(menuItems).indexOf(document.activeElement);
            
            switch (event.key) {
                case 'Escape':
                    event.preventDefault();
                    closeDropdown(userDropdown, userToggle);
                    userToggle.focus();
                    break;
                    
                case 'ArrowDown':
                    event.preventDefault();
                    if (currentIndex < menuItems.length - 1) {
                        menuItems[currentIndex + 1].focus();
                    } else {
                        menuItems[0].focus();
                    }
                    break;
                    
                case 'ArrowUp':
                    event.preventDefault();
                    if (currentIndex > 0) {
                        menuItems[currentIndex - 1].focus();
                    } else {
                        menuItems[menuItems.length - 1].focus();
                    }
                    break;
                    
                case 'Tab':
                    // Allow normal tab behavior but close dropdown when tabbing out
                    if (!userDropdown.contains(event.target)) {
                        closeDropdown(userDropdown, userToggle);
                    }
                    break;
            }
        });

        // Close dropdown on window resize
        window.addEventListener('resize', debounce(function() {
            closeDropdown(userDropdown, userToggle);
        }, 250));
    }

    /**
     * Close a specific dropdown
     */
    function closeDropdown(dropdown, toggle) {
        dropdown.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
    }

    /**
     * Close all open dropdowns
     */
    function closeAllDropdowns() {
        const allDropdowns = document.querySelectorAll('.user-dropdown');
        allDropdowns.forEach(function(dropdown) {
            const toggle = dropdown.querySelector('.user-toggle');
            if (toggle) {
                closeDropdown(dropdown, toggle);
            }
        });
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