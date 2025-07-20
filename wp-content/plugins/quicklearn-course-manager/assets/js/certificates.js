/**
 * Certificate System JavaScript
 * QuickLearn Course Manager Plugin
 */

(function($) {
    'use strict';
    
    // Certificate system object
    var QLCMCertificates = {
        
        /**
         * Initialize certificate functionality
         */
        init: function() {
            this.bindEvents();
            this.initVerificationForm();
            this.initDownloadTracking();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Certificate download buttons
            $(document).on('click', '.qlcm-download-button', this.handleCertificateDownload);
            
            // Certificate verification form
            $(document).on('submit', '.qlcm-certificate-verification form', this.handleVerificationSubmit);
            
            // Copy certificate ID to clipboard
            $(document).on('click', '.qlcm-certificate-id', this.copyCertificateId);
            
            // Print certificate
            $(document).on('click', '.qlcm-print-certificate', this.printCertificate);
        },
        
        /**
         * Initialize verification form
         */
        initVerificationForm: function() {
            var $form = $('.qlcm-certificate-verification form');
            
            if ($form.length) {
                // Auto-focus on verification code input
                $form.find('input[name="verification_code"]').focus();
                
                // Format verification code input (uppercase, remove spaces)
                $form.find('input[name="verification_code"]').on('input', function() {
                    var value = $(this).val().toUpperCase().replace(/\s/g, '');
                    $(this).val(value);
                });
                
                // Add loading state on submit
                $form.on('submit', function() {
                    var $submitBtn = $(this).find('button[type="submit"]');
                    var originalText = $submitBtn.text();
                    
                    $submitBtn.prop('disabled', true)
                             .html('<span class="qlcm-loading"></span> Verifying...');
                    
                    // Re-enable button after 10 seconds (fallback)
                    setTimeout(function() {
                        $submitBtn.prop('disabled', false).text(originalText);
                    }, 10000);
                });
            }
        },
        
        /**
         * Handle certificate download
         */
        handleCertificateDownload: function(e) {
            var $button = $(this);
            var originalText = $button.html();
            
            // Add loading state
            $button.html('<span class="qlcm-loading"></span> Preparing...')
                   .prop('disabled', true);
            
            // Track download
            QLCMCertificates.trackCertificateDownload($button.attr('href'));
            
            // Reset button after delay
            setTimeout(function() {
                $button.html(originalText).prop('disabled', false);
            }, 2000);
        },
        
        /**
         * Handle verification form submit
         */
        handleVerificationSubmit: function(e) {
            var $form = $(this);
            var verificationCode = $form.find('input[name="verification_code"]').val().trim();
            
            // Basic validation
            if (!verificationCode) {
                e.preventDefault();
                QLCMCertificates.showMessage('Please enter a verification code.', 'error');
                return false;
            }
            
            if (verificationCode.length < 10) {
                e.preventDefault();
                QLCMCertificates.showMessage('Please enter a valid verification code.', 'error');
                return false;
            }
        },
        
        /**
         * Copy certificate ID to clipboard
         */
        copyCertificateId: function(e) {
            e.preventDefault();
            
            var $element = $(this);
            var certificateId = $element.text().replace('Certificate ID: ', '').trim();
            
            // Create temporary input element
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(certificateId).select();
            
            try {
                document.execCommand('copy');
                QLCMCertificates.showMessage('Certificate ID copied to clipboard!', 'success');
                
                // Visual feedback
                $element.addClass('qlcm-copied');
                setTimeout(function() {
                    $element.removeClass('qlcm-copied');
                }, 2000);
                
            } catch (err) {
                QLCMCertificates.showMessage('Failed to copy certificate ID.', 'error');
            }
            
            $temp.remove();
        },
        
        /**
         * Print certificate
         */
        printCertificate: function(e) {
            e.preventDefault();
            
            var certificateUrl = $(this).data('certificate-url');
            
            if (certificateUrl) {
                // Open certificate in new window and print
                var printWindow = window.open(certificateUrl, '_blank');
                
                printWindow.onload = function() {
                    setTimeout(function() {
                        printWindow.print();
                    }, 500);
                };
            }
        },
        
        /**
         * Track certificate download
         */
        trackCertificateDownload: function(downloadUrl) {
            // Send analytics event if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'certificate_download', {
                    'event_category': 'engagement',
                    'event_label': downloadUrl
                });
            }
            
            // Send to WordPress analytics if available
            if (typeof wp !== 'undefined' && wp.ajax) {
                wp.ajax.post('track_certificate_download', {
                    url: downloadUrl,
                    timestamp: Date.now()
                });
            }
        },
        
        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="qlcm-certificate-' + type + '">' + message + '</div>');
            
            // Find appropriate container
            var $container = $('.qlcm-certificate-verification, .qlcm-user-certificates, .qlcm-certificate-verification-result').first();
            
            if ($container.length) {
                $container.prepend($message);
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $message.remove();
                    });
                }, 5000);
                
                // Scroll to message
                $('html, body').animate({
                    scrollTop: $message.offset().top - 20
                }, 300);
            } else {
                // Fallback to alert
                alert(message);
            }
        },
        
        /**
         * Format certificate data for display
         */
        formatCertificateData: function(certificate) {
            return {
                id: certificate.certificate_id,
                course: certificate.course_title,
                student: certificate.user_name,
                issueDate: this.formatDate(certificate.issue_date),
                downloadUrl: '/certificate/download/' + certificate.certificate_id,
                verifyUrl: '/certificate/verify/' + certificate.verification_code
            };
        },
        
        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        },
        
        /**
         * Validate certificate ID format
         */
        isValidCertificateId: function(certificateId) {
            // Expected format: QLCM-timestamp-random
            var pattern = /^QLCM-\d{10}-[A-Z0-9]{6}$/;
            return pattern.test(certificateId);
        },
        
        /**
         * Generate certificate preview
         */
        generateCertificatePreview: function(certificateData, templateData) {
            var preview = '<div class="qlcm-certificate-preview">';
            preview += '<div class="certificate-header">' + (templateData.header_text || 'Certificate of Completion') + '</div>';
            preview += '<div class="certificate-body">';
            preview += '<p>' + (templateData.body_text || 'This is to certify that') + '</p>';
            preview += '<h3 class="student-name">' + certificateData.user_name + '</h3>';
            preview += '<p>' + (templateData.course_text || 'has successfully completed the course') + '</p>';
            preview += '<h4 class="course-name">"' + certificateData.course_title + '"</h4>';
            preview += '<p class="completion-date">' + this.formatDate(certificateData.completion_date) + '</p>';
            preview += '</div>';
            preview += '</div>';
            
            return preview;
        }
    };
    
    // Certificate animation effects
    var QLCMCertificateEffects = {
        
        /**
         * Initialize certificate animations
         */
        init: function() {
            this.animateCertificateCards();
            this.initScrollAnimations();
        },
        
        /**
         * Animate certificate cards on load
         */
        animateCertificateCards: function() {
            var $cards = $('.qlcm-certificate-card');
            
            $cards.each(function(index) {
                var $card = $(this);
                
                // Stagger animation
                setTimeout(function() {
                    $card.addClass('qlcm-animate-in');
                }, index * 100);
            });
        },
        
        /**
         * Initialize scroll-based animations
         */
        initScrollAnimations: function() {
            if (typeof IntersectionObserver !== 'undefined') {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            $(entry.target).addClass('qlcm-in-view');
                        }
                    });
                }, {
                    threshold: 0.1
                });
                
                $('.qlcm-certificate-card, .qlcm-verification-success, .qlcm-verification-failed').each(function() {
                    observer.observe(this);
                });
            }
        }
    };
    
    // Certificate utilities
    var QLCMCertificateUtils = {
        
        /**
         * Generate QR code for certificate verification
         */
        generateQRCode: function(verificationUrl, containerId) {
            if (typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById(containerId), {
                    text: verificationUrl,
                    width: 128,
                    height: 128,
                    colorDark: '#2c3e50',
                    colorLight: '#ffffff'
                });
            }
        },
        
        /**
         * Share certificate on social media
         */
        shareCertificate: function(platform, certificateData) {
            var shareText = 'I just earned a certificate in "' + certificateData.course + '" from QuickLearn Academy!';
            var shareUrl = window.location.origin + '/certificate/verify/' + certificateData.verification_code;
            
            var urls = {
                twitter: 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(shareUrl),
                facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl),
                linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl)
            };
            
            if (urls[platform]) {
                window.open(urls[platform], '_blank', 'width=600,height=400');
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        QLCMCertificates.init();
        QLCMCertificateEffects.init();
    });
    
    // Make objects available globally
    window.QLCMCertificates = QLCMCertificates;
    window.QLCMCertificateEffects = QLCMCertificateEffects;
    window.QLCMCertificateUtils = QLCMCertificateUtils;
    
    // Wrap the Enhanced certificate management functionality in its own IIFE to avoid global scope pollution.
    (function($) {
        'use strict';
        // Enhanced certificate management functionality
        var QLCMCertificateManagement = {
            
            /**
             * Initialize certificate management features
             */
            init: function() {
                this.bindShareButtons();
                this.initCopyToClipboard();
                this.initShareDropdowns();
            },
            
            /**
             * Bind share button events
             */
            bindShareButtons: function() {
                $(document).on('click', '.qlcm-share-button', function(e) {
                    e.preventDefault();
                    
                    var $button = $(this);
                    var certificateId = $button.data('certificate-id');
                    var courseTitle = $button.data('course-title');
                    
                    QLCMCertificateManagement.showShareOptions(certificateId, courseTitle, $button);
                });
            },
            
            /**
             * Initialize copy to clipboard functionality
             */
            initCopyToClipboard: function() {
                $(document).on('click', '.qlcm-certificate-id', function(e) {
                    e.preventDefault();
                    
                    var $element = $(this);
                    var certificateId = $element.text().trim();
                    
                    QLCMCertificates.copyCertificateId.call(this, e);
                });
            },
            
            /**
             * Initialize share dropdowns
             */
            initShareDropdowns: function() {
                // Close dropdowns when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.qlcm-share-dropdown').length) {
                        $('.qlcm-share-dropdown').removeClass('active');
                    }
                });
            },
            
            /**
             * Show share options
             */
            showShareOptions: function(certificateId, courseTitle, $button) {
                var verificationUrl = window.location.origin + '/certificate/verify/' + certificateId;
                
                // Create share menu if it doesn't exist
                var $dropdown = $button.closest('.qlcm-certificate-actions').find('.qlcm-share-dropdown');
                
                if (!$dropdown.length) {
                    $dropdown = $('<div class="qlcm-share-dropdown"></div>');
                    $button.wrap($dropdown);
                    $dropdown = $button.parent();
                }
                
                var $menu = $dropdown.find('.qlcm-share-menu');
                
                if (!$menu.length) {
                    $menu = $('<div class="qlcm-share-menu"></div>');
                    
                    // Add share options
                    var shareOptions = [
                        {
                            platform: 'twitter',
                            label: 'Twitter',
                            icon: 'twitter'
                        },
                        {
                            platform: 'facebook',
                            label: 'Facebook',
                            icon: 'facebook'
                        },
                        {
                            platform: 'linkedin',
                            label: 'LinkedIn',
                            icon: 'linkedin'
                        },
                        {
                            platform: 'copy',
                            label: 'Copy Link',
                            icon: 'admin-links'
                        }
                    ];
                    
                    shareOptions.forEach(function(option) {
                        var $link = $('<a href="#" data-platform="' + option.platform + '">');
                        $link.html('<span class="dashicons dashicons-' + option.icon + '"></span>' + option.label);
                        $link.on('click', function(e) {
                            e.preventDefault();
                            QLCMCertificateManagement.handleShare(option.platform, {
                                certificateId: certificateId,
                                courseTitle: courseTitle,
                                verificationUrl: verificationUrl
                            });
                            $dropdown.removeClass('active');
                        });
                        $menu.append($link);
                    });
                    
                    $dropdown.append($menu);
                }
                
                // Toggle dropdown
                $dropdown.toggleClass('active');
            },
            
            /**
             * Handle share action
             */
            handleShare: function(platform, data) {
                switch (platform) {
                    case 'twitter':
                        QLCMCertificateUtils.shareCertificate('twitter', data);
                        break;
                    case 'facebook':
                        QLCMCertificateUtils.shareCertificate('facebook', data);
                        break;
                    case 'linkedin':
                        QLCMCertificateUtils.shareCertificate('linkedin', data);
                        break;
                    case 'copy':
                        this.copyVerificationLink(data.verificationUrl);
                        break;
                }
            },
            
            /**
             * Copy verification link to clipboard
             */
            copyVerificationLink: function(url) {
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                
                try {
                    document.execCommand('copy');
                    QLCMCertificates.showMessage('Verification link copied to clipboard!', 'success');
                } catch (err) {
                    QLCMCertificates.showMessage('Failed to copy link.', 'error');
                }
                
                $temp.remove();
            }
        };
        // Enhanced certificate utilities
        $.extend(QLCMCertificateUtils, {
            
            /**
             * Enhanced social media sharing
             */
            shareCertificate: function(platform, certificateData) {
                var shareText = 'I just earned a certificate in "' + certificateData.courseTitle + '" from QuickLearn Academy! 🎓';
                var shareUrl = certificateData.verificationUrl;
                var hashtags = 'elearning,certificate,achievement';
                
                var urls = {
                    twitter: 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + 
                             '&url=' + encodeURIComponent(shareUrl) + 
                             '&hashtags=' + encodeURIComponent(hashtags),
                    facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl) + 
                             '&quote=' + encodeURIComponent(shareText),
                    linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl) + 
                             '&summary=' + encodeURIComponent(shareText)
                };
                
                if (urls[platform]) {
                    var popup = window.open(
                        urls[platform], 
                        'share-' + platform, 
                        'width=600,height=400,scrollbars=yes,resizable=yes'
                    );
                    
                    // Track sharing event
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'certificate_share', {
                            'event_category': 'engagement',
                            'event_label': platform,
                            'custom_parameter_1': certificateData.certificateId
                        });
                    }
                }
            },
            
            /**
             * Generate certificate badge HTML
             */
            generateCertificateBadge: function(certificateData) {
                var badge = '<div class="qlcm-certificate-badge-widget">';
                badge += '<div class="qlcm-badge-icon"><span class="dashicons dashicons-awards"></span></div>';
                badge += '<div class="qlcm-badge-content">';
                badge += '<h4>Certificate Earned</h4>';
                badge += '<p>' + certificateData.courseTitle + '</p>';
                badge += '<small>Issued: ' + QLCMCertificates.formatDate(certificateData.issueDate) + '</small>';
                badge += '</div>';
                badge += '<div class="qlcm-badge-actions">';
                badge += '<a href="' + certificateData.downloadUrl + '" class="qlcm-badge-download" target="_blank">Download</a>';
                badge += '<a href="' + certificateData.verifyUrl + '" class="qlcm-badge-verify" target="_blank">Verify</a>';
                badge += '</div>';
                badge += '</div>';
                
                return badge;
            },
            
            /**
             * Validate certificate format
             */
            validateCertificateFormat: function(certificateId) {
                // Enhanced validation for certificate ID format
                var patterns = [
                    /^QLCM-\d{10}-[A-Z0-9]{6}$/, // Standard format
                    /^[A-Z]{2,4}-\d{8,12}-[A-Z0-9]{4,8}$/ // Alternative format
                ];
                
                return patterns.some(function(pattern) {
                    return pattern.test(certificateId);
                });
            }
        });
        // Initialize enhanced features when document is ready
        $(document).ready(function() {
            QLCMCertificateManagement.init();
        });
        // Make certificate management available globally
        window.QLCMCertificateManagement = QLCMCertificateManagement;
    })(jQuery);
    
})(jQuery);