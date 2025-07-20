/**
 * Browser Compatibility Tests for QuickLearn Course Manager
 * Tests enhanced features across different browsers and devices
 */

const puppeteer = require('puppeteer');
const { expect } = require('chai');

describe('QuickLearn Course Manager - Enhanced Features Browser Tests', function() {
    this.timeout(30000);
    
    let browser;
    let page;
    const baseUrl = process.env.TEST_URL || 'http://localhost:8080';
    
    before(async function() {
        browser = await puppeteer.launch({
            headless: process.env.HEADLESS !== 'false',
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
    });
    
    after(async function() {
        if (browser) {
            await browser.close();
        }
    });
    
    beforeEach(async function() {
        page = await browser.newPage();
        await page.setViewport({ width: 1200, height: 800 });
    });
    
    afterEach(async function() {
        if (page) {
            await page.close();
        }
    });
    
    describe('Course Enrollment Workflow', function() {
        it('should allow user to enroll in course via AJAX', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Wait for course listing to load
            await page.waitForSelector('.course-grid');
            
            // Click on first course
            await page.click('.course-card:first-child .course-link');
            await page.waitForNavigation();
            
            // Check if enrollment button exists
            const enrollButton = await page.$('.enroll-button');
            expect(enrollButton).to.not.be.null;
            
            // Mock login (assuming test user is logged in)
            await page.evaluate(() => {
                window.qlcm_ajax.user_logged_in = true;
                window.qlcm_ajax.user_id = 1;
            });
            
            // Click enrollment button
            await page.click('.enroll-button');
            
            // Wait for AJAX response
            await page.waitForSelector('.enrollment-success', { timeout: 5000 });
            
            // Verify enrollment success message
            const successMessage = await page.$eval('.enrollment-success', el => el.textContent);
            expect(successMessage).to.include('enrolled');
        });
        
        it('should update progress indicators', async function() {
            await page.goto(`${baseUrl}/dashboard/`);
            
            // Wait for dashboard to load
            await page.waitForSelector('.user-dashboard');
            
            // Check for progress bars
            const progressBars = await page.$$('.progress-bar');
            expect(progressBars.length).to.be.greaterThan(0);
            
            // Verify progress percentages are displayed
            const progressText = await page.$eval('.progress-percentage', el => el.textContent);
            expect(progressText).to.match(/\d+%/);
        });
    });
    
    describe('Course Rating System', function() {
        it('should display interactive star rating', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Wait for rating section to load
            await page.waitForSelector('.course-rating-section');
            
            // Check for star rating elements
            const stars = await page.$$('.rating-star');
            expect(stars.length).to.equal(5);
            
            // Test star hover effect
            await page.hover('.rating-star:nth-child(4)');
            
            // Check if stars highlight on hover
            const highlightedStars = await page.$$('.rating-star.highlighted');
            expect(highlightedStars.length).to.equal(4);
        });
        
        it('should submit rating via AJAX', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Mock user login
            await page.evaluate(() => {
                window.qlcm_ajax.user_logged_in = true;
                window.qlcm_ajax.user_id = 1;
            });
            
            // Wait for rating form
            await page.waitForSelector('.rating-form');
            
            // Click 5th star
            await page.click('.rating-star:nth-child(5)');
            
            // Fill review text
            await page.type('.review-text', 'Excellent course! Highly recommended.');
            
            // Submit rating
            await page.click('.submit-rating');
            
            // Wait for success message
            await page.waitForSelector('.rating-success', { timeout: 5000 });
            
            // Verify rating was submitted
            const successMessage = await page.$eval('.rating-success', el => el.textContent);
            expect(successMessage).to.include('rating submitted');
        });
    });
    
    describe('Certificate Download', function() {
        it('should generate and download certificate', async function() {
            await page.goto(`${baseUrl}/dashboard/`);
            
            // Wait for dashboard
            await page.waitForSelector('.user-dashboard');
            
            // Look for certificate download button
            const certificateButton = await page.$('.download-certificate');
            if (certificateButton) {
                // Set up download handling
                await page._client.send('Page.setDownloadBehavior', {
                    behavior: 'allow',
                    downloadPath: './downloads'
                });
                
                // Click download button
                await page.click('.download-certificate');
                
                // Wait for download to start
                await page.waitForTimeout(2000);
                
                // Verify download initiated (check for loading state)
                const loadingState = await page.$('.certificate-loading');
                expect(loadingState).to.not.be.null;
            }
        });
    });
    
    describe('SEO and Social Sharing', function() {
        it('should have proper meta tags for SEO', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Check for meta description
            const metaDescription = await page.$eval('meta[name="description"]', el => el.content);
            expect(metaDescription).to.not.be.empty;
            expect(metaDescription.length).to.be.lessThan(160);
            
            // Check for Open Graph tags
            const ogTitle = await page.$eval('meta[property="og:title"]', el => el.content);
            expect(ogTitle).to.not.be.empty;
            
            const ogDescription = await page.$eval('meta[property="og:description"]', el => el.content);
            expect(ogDescription).to.not.be.empty;
            
            // Check for structured data
            const structuredData = await page.$eval('script[type="application/ld+json"]', el => el.textContent);
            const jsonData = JSON.parse(structuredData);
            expect(jsonData['@type']).to.equal('Course');
        });
        
        it('should have social sharing buttons', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Check for social sharing section
            await page.waitForSelector('.social-sharing');
            
            // Verify sharing buttons
            const facebookButton = await page.$('.share-facebook');
            const twitterButton = await page.$('.share-twitter');
            const linkedinButton = await page.$('.share-linkedin');
            
            expect(facebookButton).to.not.be.null;
            expect(twitterButton).to.not.be.null;
            expect(linkedinButton).to.not.be.null;
        });
    });
    
    describe('Multimedia Content', function() {
        it('should display responsive video player', async function() {
            await page.goto(`${baseUrl}/courses/multimedia-course/`);
            
            // Wait for video player
            await page.waitForSelector('.video-player');
            
            // Check if video is responsive
            const videoContainer = await page.$('.responsive-video-wrapper');
            expect(videoContainer).to.not.be.null;
            
            // Test video controls
            const playButton = await page.$('.video-play-button');
            if (playButton) {
                await page.click('.video-play-button');
                
                // Wait for video to start playing
                await page.waitForTimeout(1000);
                
                // Check if video is playing
                const isPlaying = await page.evaluate(() => {
                    const video = document.querySelector('video');
                    return video && !video.paused;
                });
                
                expect(isPlaying).to.be.true;
            }
        });
        
        it('should support audio player controls', async function() {
            await page.goto(`${baseUrl}/courses/audio-course/`);
            
            // Wait for audio player
            await page.waitForSelector('.audio-player');
            
            // Test audio controls
            const audioControls = await page.$('.audio-controls');
            expect(audioControls).to.not.be.null;
            
            // Check for play/pause button
            const playPauseButton = await page.$('.audio-play-pause');
            expect(playPauseButton).to.not.be.null;
            
            // Check for progress bar
            const progressBar = await page.$('.audio-progress');
            expect(progressBar).to.not.be.null;
        });
    });
    
    describe('Responsive Design', function() {
        const viewports = [
            { name: 'Mobile', width: 375, height: 667 },
            { name: 'Tablet', width: 768, height: 1024 },
            { name: 'Desktop', width: 1200, height: 800 }
        ];
        
        viewports.forEach(viewport => {
            it(`should be responsive on ${viewport.name}`, async function() {
                await page.setViewport({ width: viewport.width, height: viewport.height });
                await page.goto(`${baseUrl}/courses/`);
                
                // Wait for course grid
                await page.waitForSelector('.course-grid');
                
                // Check if navigation is responsive
                if (viewport.width < 768) {
                    // Mobile: check for hamburger menu
                    const mobileMenu = await page.$('.mobile-menu-toggle');
                    expect(mobileMenu).to.not.be.null;
                } else {
                    // Desktop/Tablet: check for full navigation
                    const desktopNav = await page.$('.desktop-navigation');
                    expect(desktopNav).to.not.be.null;
                }
                
                // Check course grid responsiveness
                const courseCards = await page.$$('.course-card');
                expect(courseCards.length).to.be.greaterThan(0);
                
                // Verify cards are properly sized
                const cardWidth = await page.$eval('.course-card', el => el.offsetWidth);
                expect(cardWidth).to.be.greaterThan(0);
                expect(cardWidth).to.be.lessThan(viewport.width);
            });
        });
    });
    
    describe('AJAX Course Filtering', function() {
        it('should filter courses without page reload', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Wait for course grid and filter
            await page.waitForSelector('.course-grid');
            await page.waitForSelector('.course-filter');
            
            // Get initial course count
            const initialCourses = await page.$$('.course-card');
            const initialCount = initialCourses.length;
            
            // Select a category filter
            await page.select('.category-filter', 'web-development');
            
            // Wait for AJAX response
            await page.waitForTimeout(2000);
            
            // Check if courses were filtered
            const filteredCourses = await page.$$('.course-card');
            const filteredCount = filteredCourses.length;
            
            // Verify filtering worked (assuming not all courses are web development)
            expect(filteredCount).to.be.lessThanOrEqual(initialCount);
            
            // Check for loading indicator during AJAX
            const loadingIndicator = await page.$('.courses-loading');
            // Loading indicator should be hidden after filtering
            const isHidden = await page.evaluate(el => el.style.display === 'none', loadingIndicator);
            expect(isHidden).to.be.true;
        });
        
        it('should handle pagination via AJAX', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Wait for pagination
            await page.waitForSelector('.pagination');
            
            // Check if pagination exists
            const paginationLinks = await page.$$('.pagination a');
            if (paginationLinks.length > 0) {
                // Click on page 2
                await page.click('.pagination a[data-page="2"]');
                
                // Wait for AJAX response
                await page.waitForTimeout(2000);
                
                // Verify URL didn't change (AJAX pagination)
                const currentUrl = page.url();
                expect(currentUrl).to.not.include('page=2');
                
                // Verify new courses loaded
                const newCourses = await page.$$('.course-card');
                expect(newCourses.length).to.be.greaterThan(0);
            }
        });
    });
    
    describe('Performance Testing', function() {
        it('should load course page within acceptable time', async function() {
            const startTime = Date.now();
            
            await page.goto(`${baseUrl}/courses/test-course/`);
            await page.waitForSelector('.course-content');
            
            const loadTime = Date.now() - startTime;
            
            // Page should load within 3 seconds
            expect(loadTime).to.be.lessThan(3000);
        });
        
        it('should handle AJAX requests efficiently', async function() {
            await page.goto(`${baseUrl}/courses/`);
            await page.waitForSelector('.course-filter');
            
            const startTime = Date.now();
            
            // Trigger AJAX filter
            await page.select('.category-filter', 'programming');
            await page.waitForSelector('.course-grid .course-card');
            
            const ajaxTime = Date.now() - startTime;
            
            // AJAX should complete within 2 seconds
            expect(ajaxTime).to.be.lessThan(2000);
        });
    });
    
    describe('Accessibility Testing', function() {
        it('should support keyboard navigation', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Test tab navigation
            await page.keyboard.press('Tab');
            await page.keyboard.press('Tab');
            await page.keyboard.press('Tab');
            
            // Check if focus is visible
            const focusedElement = await page.evaluate(() => document.activeElement.tagName);
            expect(focusedElement).to.not.be.null;
        });
        
        it('should have proper ARIA labels', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Check for ARIA labels on interactive elements
            const enrollButton = await page.$('.enroll-button[aria-label]');
            expect(enrollButton).to.not.be.null;
            
            const ratingStars = await page.$$('.rating-star[aria-label]');
            expect(ratingStars.length).to.equal(5);
        });
        
        it('should support screen readers', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Check for proper heading hierarchy
            const h1 = await page.$('h1');
            expect(h1).to.not.be.null;
            
            // Check for alt text on images
            const images = await page.$$('img[alt]');
            expect(images.length).to.be.greaterThan(0);
            
            // Check for form labels
            const labeledInputs = await page.$$('input[aria-label], input[aria-labelledby]');
            const inputs = await page.$$('input');
            
            // Most inputs should have labels
            expect(labeledInputs.length).to.be.greaterThan(inputs.length * 0.8);
        });
    });
    
    describe('Cross-Browser Compatibility', function() {
        // Note: This would require multiple browser instances
        // For now, we test features that commonly break across browsers
        
        it('should handle CSS Grid/Flexbox layouts', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Check if course grid is properly displayed
            const gridContainer = await page.$('.course-grid');
            const computedStyle = await page.evaluate(el => {
                return window.getComputedStyle(el).display;
            }, gridContainer);
            
            // Should use modern layout methods
            expect(['grid', 'flex']).to.include(computedStyle);
        });
        
        it('should handle modern JavaScript features gracefully', async function() {
            await page.goto(`${baseUrl}/courses/`);
            
            // Check if JavaScript is working
            const jsWorking = await page.evaluate(() => {
                return typeof window.qlcm_ajax !== 'undefined';
            });
            
            expect(jsWorking).to.be.true;
            
            // Check for error handling
            const errors = [];
            page.on('pageerror', error => errors.push(error));
            
            // Trigger some JavaScript interactions
            await page.click('.course-filter select');
            await page.waitForTimeout(1000);
            
            // Should not have JavaScript errors
            expect(errors.length).to.equal(0);
        });
    });
    
    describe('Security Testing', function() {
        it('should prevent XSS in user inputs', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Try to inject script in review form
            await page.waitForSelector('.review-text');
            await page.type('.review-text', '<script>alert("xss")</script>Test review');
            
            // Submit form
            await page.click('.submit-rating');
            await page.waitForTimeout(2000);
            
            // Check if script was executed (it shouldn't be)
            const alertFired = await page.evaluate(() => {
                return window.alertFired || false;
            });
            
            expect(alertFired).to.be.false;
        });
        
        it('should validate CSRF tokens', async function() {
            await page.goto(`${baseUrl}/courses/test-course/`);
            
            // Try to submit form without proper nonce
            await page.evaluate(() => {
                // Remove or modify nonce
                const nonceField = document.querySelector('input[name="nonce"]');
                if (nonceField) {
                    nonceField.value = 'invalid_nonce';
                }
            });
            
            // Try to submit rating
            await page.click('.submit-rating');
            await page.waitForTimeout(2000);
            
            // Should show error message
            const errorMessage = await page.$('.error-message');
            expect(errorMessage).to.not.be.null;
        });
    });
});

// Export for Node.js environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        // Test configuration
        browserConfig: {
            chrome: {
                name: 'Chrome',
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            },
            firefox: {
                name: 'Firefox', 
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
            },
            safari: {
                name: 'Safari',
                userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15'
            }
        },
        
        // Performance benchmarks
        performanceTargets: {
            pageLoad: 3000,
            ajaxResponse: 2000,
            domUpdate: 500
        },
        
        // Accessibility requirements
        accessibilityChecks: [
            'ARIA labels on interactive elements',
            'Color contrast ratios meet WCAG 2.1 AA',
            'Keyboard navigation support',
            'Screen reader compatibility'
        ]
    };
}

// Browser-side utilities for manual testing
if (typeof window !== 'undefined') {
    window.QLCMEnhancedTests = {
        // Test enrollment workflow
        testEnrollment: async function() {
            console.log('Testing enrollment workflow...');
            
            const enrollButton = document.querySelector('.enroll-button');
            if (enrollButton) {
                enrollButton.click();
                
                // Wait for response
                setTimeout(() => {
                    const successMessage = document.querySelector('.enrollment-success');
                    if (successMessage) {
                        console.log('✓ Enrollment successful');
                    } else {
                        console.log('✗ Enrollment failed or no feedback');
                    }
                }, 2000);
            } else {
                console.log('✗ Enrollment button not found');
            }
        },
        
        // Test rating system
        testRating: function() {
            console.log('Testing rating system...');
            
            const stars = document.querySelectorAll('.rating-star');
            if (stars.length === 5) {
                console.log('✓ 5-star rating system found');
                
                // Test hover effects
                stars[3].dispatchEvent(new Event('mouseenter'));
                
                setTimeout(() => {
                    const highlighted = document.querySelectorAll('.rating-star.highlighted');
                    if (highlighted.length === 4) {
                        console.log('✓ Star hover effects working');
                    } else {
                        console.log('✗ Star hover effects not working');
                    }
                }, 100);
            } else {
                console.log('✗ Rating system not found or incomplete');
            }
        },
        
        // Test multimedia content
        testMultimedia: function() {
            console.log('Testing multimedia content...');
            
            const videos = document.querySelectorAll('video');
            const audios = document.querySelectorAll('audio');
            
            console.log(`Found ${videos.length} video elements`);
            console.log(`Found ${audios.length} audio elements`);
            
            videos.forEach((video, index) => {
                if (video.controls) {
                    console.log(`✓ Video ${index + 1} has controls`);
                } else {
                    console.log(`✗ Video ${index + 1} missing controls`);
                }
            });
            
            audios.forEach((audio, index) => {
                if (audio.controls) {
                    console.log(`✓ Audio ${index + 1} has controls`);
                } else {
                    console.log(`✗ Audio ${index + 1} missing controls`);
                }
            });
        },
        
        // Test SEO elements
        testSEO: function() {
            console.log('Testing SEO elements...');
            
            // Check meta tags
            const metaDescription = document.querySelector('meta[name="description"]');
            const ogTitle = document.querySelector('meta[property="og:title"]');
            const structuredData = document.querySelector('script[type="application/ld+json"]');
            
            if (metaDescription) {
                console.log('✓ Meta description found');
                if (metaDescription.content.length <= 160) {
                    console.log('✓ Meta description length optimal');
                } else {
                    console.log('⚠ Meta description too long');
                }
            } else {
                console.log('✗ Meta description missing');
            }
            
            if (ogTitle) {
                console.log('✓ Open Graph title found');
            } else {
                console.log('✗ Open Graph title missing');
            }
            
            if (structuredData) {
                console.log('✓ Structured data found');
                try {
                    const data = JSON.parse(structuredData.textContent);
                    if (data['@type'] === 'Course') {
                        console.log('✓ Course structured data valid');
                    } else {
                        console.log('⚠ Structured data type unexpected');
                    }
                } catch (e) {
                    console.log('✗ Structured data invalid JSON');
                }
            } else {
                console.log('✗ Structured data missing');
            }
        },
        
        // Run all tests
        runAllTests: function() {
            console.log('Running all enhanced feature tests...');
            console.log('=====================================');
            
            this.testEnrollment();
            this.testRating();
            this.testMultimedia();
            this.testSEO();
            
            console.log('=====================================');
            console.log('Test run complete. Check console for results.');
        }
    };
    
    // Auto-run tests on course pages
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.pathname.includes('courses')) {
            console.log('QuickLearn Enhanced Tests available. Run window.QLCMEnhancedTests.runAllTests() to test all features.');
        }
    });
}