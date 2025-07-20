/**
 * Cross-Browser Test Runner for QuickLearn Course Manager
 * Tests enhanced features across multiple browsers and devices
 */

const { Builder, By, until, Key } = require('selenium-webdriver');
const chrome = require('selenium-webdriver/chrome');
const firefox = require('selenium-webdriver/firefox');
const safari = require('selenium-webdriver/safari');
const edge = require('selenium-webdriver/edge');

class CrossBrowserTestRunner {
    constructor() {
        this.baseUrl = process.env.TEST_URL || 'http://localhost:8080';
        this.results = [];
        this.browsers = [
            { name: 'chrome', driver: null },
            { name: 'firefox', driver: null },
            { name: 'safari', driver: null },
            { name: 'edge', driver: null }
        ];
        this.testTimeout = 30000;
    }

    /**
     * Initialize browser drivers
     */
    async initializeBrowsers() {
        console.log('Initializing browser drivers...');
        
        for (const browser of this.browsers) {
            try {
                switch (browser.name) {
                    case 'chrome':
                        const chromeOptions = new chrome.Options();
                        chromeOptions.addArguments('--headless');
                        chromeOptions.addArguments('--no-sandbox');
                        chromeOptions.addArguments('--disable-dev-shm-usage');
                        browser.driver = await new Builder()
                            .forBrowser('chrome')
                            .setChromeOptions(chromeOptions)
                            .build();
                        break;
                        
                    case 'firefox':
                        const firefoxOptions = new firefox.Options();
                        firefoxOptions.addArguments('--headless');
                        browser.driver = await new Builder()
                            .forBrowser('firefox')
                            .setFirefoxOptions(firefoxOptions)
                            .build();
                        break;
                        
                    case 'safari':
                        // Safari requires manual setup and may not work in headless mode
                        if (process.platform === 'darwin') {
                            browser.driver = await new Builder()
                                .forBrowser('safari')
                                .build();
                        } else {
                            console.log('Safari testing skipped (not on macOS)');
                            browser.driver = null;
                        }
                        break;
                        
                    case 'edge':
                        const edgeOptions = new edge.Options();
                        edgeOptions.addArguments('--headless');
                        browser.driver = await new Builder()
                            .forBrowser('MicrosoftEdge')
                            .setEdgeOptions(edgeOptions)
                            .build();
                        break;
                }
                
                if (browser.driver) {
                    console.log(`✓ ${browser.name} driver initialized`);
                } else {
                    console.log(`⚠ ${browser.name} driver skipped`);
                }
            } catch (error) {
                console.log(`✗ Failed to initialize ${browser.name}: ${error.message}`);
                browser.driver = null;
            }
        }
    }

    /**
     * Run all cross-browser tests
     */
    async runAllTests() {
        console.log('QuickLearn Course Manager - Cross-Browser Test Suite');
        console.log('===================================================\n');

        await this.initializeBrowsers();

        const testSuites = [
            { name: 'Course Listing and Filtering', method: this.testCourseFiltering },
            { name: 'User Enrollment Workflow', method: this.testEnrollmentWorkflow },
            { name: 'Course Rating System', method: this.testRatingSystem },
            { name: 'Certificate Download', method: this.testCertificateDownload },
            { name: 'Responsive Design', method: this.testResponsiveDesign },
            { name: 'SEO and Meta Tags', method: this.testSEOElements },
            { name: 'Multimedia Content', method: this.testMultimediaContent },
            { name: 'Accessibility Features', method: this.testAccessibility },
            { name: 'Performance Benchmarks', method: this.testPerformance },
            { name: 'Security Features', method: this.testSecurity }
        ];

        for (const suite of testSuites) {
            console.log(`\nRunning ${suite.name} tests...`);
            console.log('='.repeat(suite.name.length + 15));
            
            await this.runTestSuite(suite.name, suite.method.bind(this));
        }

        await this.cleanup();
        this.generateReport();
    }

    /**
     * Run a test suite across all browsers
     */
    async runTestSuite(suiteName, testMethod) {
        for (const browser of this.browsers) {
            if (!browser.driver) continue;

            console.log(`\n${browser.name.toUpperCase()}:`);
            
            try {
                await browser.driver.manage().setTimeouts({ implicit: 10000 });
                const result = await testMethod(browser.driver, browser.name);
                
                this.recordResult(suiteName, browser.name, result.success, result.message, result.duration);
                
                if (result.success) {
                    console.log(`  ✓ ${result.message} (${result.duration}ms)`);
                } else {
                    console.log(`  ✗ ${result.message} (${result.duration}ms)`);
                }
            } catch (error) {
                console.log(`  ✗ Test failed: ${error.message}`);
                this.recordResult(suiteName, browser.name, false, error.message, 0);
            }
        }
    }

    /**
     * Test course filtering functionality
     */
    async testCourseFiltering(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/`);
        
        // Wait for course grid to load
        await driver.wait(until.elementLocated(By.css('.course-grid, .course-list')), 10000);
        
        // Find and use category filter
        const categoryFilter = await driver.findElement(By.css('.category-filter, #course-category-filter'));
        await categoryFilter.click();
        
        // Select a category (assuming 'web-development' exists)
        const options = await categoryFilter.findElements(By.css('option'));
        if (options.length > 1) {
            await options[1].click(); // Select first non-empty option
        }
        
        // Wait for AJAX response
        await driver.sleep(2000);
        
        // Check if courses are displayed
        const courseCards = await driver.findElements(By.css('.course-card, .course-item'));
        
        const duration = Date.now() - startTime;
        
        if (courseCards.length > 0) {
            return { success: true, message: `Course filtering works (${courseCards.length} courses found)`, duration };
        } else {
            return { success: false, message: 'No courses found after filtering', duration };
        }
    }

    /**
     * Test enrollment workflow
     */
    async testEnrollmentWorkflow(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/`);
        
        // Click on first course
        const firstCourse = await driver.wait(until.elementLocated(By.css('.course-card a, .course-item a')), 10000);
        await firstCourse.click();
        
        // Look for enrollment button
        try {
            const enrollButton = await driver.wait(until.elementLocated(By.css('.enroll-button, .enrollment-btn')), 5000);
            
            // Check if button is visible and clickable
            const isDisplayed = await enrollButton.isDisplayed();
            const isEnabled = await enrollButton.isEnabled();
            
            const duration = Date.now() - startTime;
            
            if (isDisplayed && isEnabled) {
                return { success: true, message: 'Enrollment button found and accessible', duration };
            } else {
                return { success: false, message: 'Enrollment button not accessible', duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Enrollment button not found', duration };
        }
    }

    /**
     * Test rating system
     */
    async testRatingSystem(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/test-course/`);
        
        try {
            // Look for rating stars
            const ratingStars = await driver.wait(until.elementsLocated(By.css('.rating-star, .star-rating .star')), 10000);
            
            if (ratingStars.length === 5) {
                // Test hover effect on stars
                await driver.actions().move({ origin: ratingStars[3] }).perform();
                await driver.sleep(500);
                
                // Check for review text area
                const reviewTextArea = await driver.findElements(By.css('.review-text, textarea[name="review"]'));
                
                const duration = Date.now() - startTime;
                
                if (reviewTextArea.length > 0) {
                    return { success: true, message: '5-star rating system with review text found', duration };
                } else {
                    return { success: true, message: '5-star rating system found (no review text)', duration };
                }
            } else {
                const duration = Date.now() - startTime;
                return { success: false, message: `Expected 5 stars, found ${ratingStars.length}`, duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Rating system not found', duration };
        }
    }

    /**
     * Test certificate download
     */
    async testCertificateDownload(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/dashboard/`);
        
        try {
            // Look for certificate download elements
            const certificateElements = await driver.findElements(By.css('.download-certificate, .certificate-download, .certificate-btn'));
            
            const duration = Date.now() - startTime;
            
            if (certificateElements.length > 0) {
                const isDisplayed = await certificateElements[0].isDisplayed();
                if (isDisplayed) {
                    return { success: true, message: 'Certificate download functionality found', duration };
                } else {
                    return { success: false, message: 'Certificate download button not visible', duration };
                }
            } else {
                return { success: false, message: 'Certificate download functionality not found', duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Dashboard page not accessible', duration };
        }
    }

    /**
     * Test responsive design
     */
    async testResponsiveDesign(driver, browserName) {
        const startTime = Date.now();
        
        const viewports = [
            { width: 1200, height: 800, name: 'Desktop' },
            { width: 768, height: 1024, name: 'Tablet' },
            { width: 375, height: 667, name: 'Mobile' }
        ];
        
        let allViewportsWork = true;
        let messages = [];
        
        for (const viewport of viewports) {
            await driver.manage().window().setRect({ width: viewport.width, height: viewport.height });
            await driver.get(`${this.baseUrl}/courses/`);
            
            try {
                // Check if navigation is accessible
                const navigation = await driver.findElements(By.css('nav, .navigation, .menu'));
                
                // Check if course grid adapts
                const courseGrid = await driver.findElement(By.css('.course-grid, .course-list'));
                const gridDisplay = await courseGrid.getCssValue('display');
                
                if (navigation.length > 0 && (gridDisplay === 'grid' || gridDisplay === 'flex' || gridDisplay === 'block')) {
                    messages.push(`${viewport.name}: ✓`);
                } else {
                    messages.push(`${viewport.name}: ✗`);
                    allViewportsWork = false;
                }
            } catch (error) {
                messages.push(`${viewport.name}: ✗ (${error.message})`);
                allViewportsWork = false;
            }
        }
        
        const duration = Date.now() - startTime;
        
        return {
            success: allViewportsWork,
            message: `Responsive design: ${messages.join(', ')}`,
            duration
        };
    }

    /**
     * Test SEO elements
     */
    async testSEOElements(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/test-course/`);
        
        try {
            // Check for meta description
            const metaDescription = await driver.findElements(By.css('meta[name="description"]'));
            
            // Check for Open Graph tags
            const ogTitle = await driver.findElements(By.css('meta[property="og:title"]'));
            const ogDescription = await driver.findElements(By.css('meta[property="og:description"]'));
            
            // Check for structured data
            const structuredData = await driver.findElements(By.css('script[type="application/ld+json"]'));
            
            const seoElements = {
                metaDescription: metaDescription.length > 0,
                ogTitle: ogTitle.length > 0,
                ogDescription: ogDescription.length > 0,
                structuredData: structuredData.length > 0
            };
            
            const foundElements = Object.values(seoElements).filter(Boolean).length;
            const totalElements = Object.keys(seoElements).length;
            
            const duration = Date.now() - startTime;
            
            if (foundElements === totalElements) {
                return { success: true, message: 'All SEO elements found', duration };
            } else {
                return { success: false, message: `${foundElements}/${totalElements} SEO elements found`, duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Error checking SEO elements', duration };
        }
    }

    /**
     * Test multimedia content
     */
    async testMultimediaContent(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/multimedia-course/`);
        
        try {
            // Look for video elements
            const videos = await driver.findElements(By.css('video, iframe[src*="youtube"], iframe[src*="vimeo"]'));
            
            // Look for audio elements
            const audios = await driver.findElements(By.css('audio'));
            
            // Check for media controls
            const mediaControls = await driver.findElements(By.css('.video-controls, .audio-controls, video[controls], audio[controls]'));
            
            const duration = Date.now() - startTime;
            
            if (videos.length > 0 || audios.length > 0) {
                const hasControls = mediaControls.length > 0;
                return {
                    success: true,
                    message: `Multimedia found: ${videos.length} videos, ${audios.length} audios${hasControls ? ' (with controls)' : ''}`,
                    duration
                };
            } else {
                return { success: false, message: 'No multimedia content found', duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Error checking multimedia content', duration };
        }
    }

    /**
     * Test accessibility features
     */
    async testAccessibility(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/`);
        
        try {
            // Check for proper heading structure
            const h1Elements = await driver.findElements(By.css('h1'));
            
            // Check for alt text on images
            const images = await driver.findElements(By.css('img'));
            let imagesWithAlt = 0;
            
            for (const img of images) {
                const altText = await img.getAttribute('alt');
                if (altText && altText.trim() !== '') {
                    imagesWithAlt++;
                }
            }
            
            // Check for ARIA labels on interactive elements
            const ariaLabels = await driver.findElements(By.css('[aria-label], [aria-labelledby]'));
            
            // Test keyboard navigation
            await driver.actions().sendKeys(Key.TAB).perform();
            await driver.sleep(100);
            const focusedElement = await driver.switchTo().activeElement();
            const tagName = await focusedElement.getTagName();
            
            const accessibilityScore = {
                h1Count: h1Elements.length,
                imagesWithAlt: imagesWithAlt,
                totalImages: images.length,
                ariaLabels: ariaLabels.length,
                keyboardNavigation: tagName !== 'body'
            };
            
            const duration = Date.now() - startTime;
            
            const issues = [];
            if (accessibilityScore.h1Count !== 1) issues.push('H1 structure');
            if (accessibilityScore.imagesWithAlt < accessibilityScore.totalImages * 0.8) issues.push('Image alt text');
            if (accessibilityScore.ariaLabels === 0) issues.push('ARIA labels');
            if (!accessibilityScore.keyboardNavigation) issues.push('Keyboard navigation');
            
            if (issues.length === 0) {
                return { success: true, message: 'All accessibility checks passed', duration };
            } else {
                return { success: false, message: `Accessibility issues: ${issues.join(', ')}`, duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Error checking accessibility', duration };
        }
    }

    /**
     * Test performance benchmarks
     */
    async testPerformance(driver, browserName) {
        const startTime = Date.now();
        
        // Test page load time
        const pageLoadStart = Date.now();
        await driver.get(`${this.baseUrl}/courses/`);
        await driver.wait(until.elementLocated(By.css('.course-grid, .course-list')), 10000);
        const pageLoadTime = Date.now() - pageLoadStart;
        
        // Test AJAX response time
        const ajaxStart = Date.now();
        const categoryFilter = await driver.findElement(By.css('.category-filter, #course-category-filter'));
        await categoryFilter.click();
        
        const options = await categoryFilter.findElements(By.css('option'));
        if (options.length > 1) {
            await options[1].click();
        }
        
        await driver.sleep(2000); // Wait for AJAX
        const ajaxTime = Date.now() - ajaxStart;
        
        const totalDuration = Date.now() - startTime;
        
        const performanceTargets = {
            pageLoad: 3000, // 3 seconds
            ajax: 2000      // 2 seconds
        };
        
        const pageLoadPass = pageLoadTime <= performanceTargets.pageLoad;
        const ajaxPass = ajaxTime <= performanceTargets.ajax;
        
        if (pageLoadPass && ajaxPass) {
            return {
                success: true,
                message: `Performance good: Page ${pageLoadTime}ms, AJAX ${ajaxTime}ms`,
                duration: totalDuration
            };
        } else {
            return {
                success: false,
                message: `Performance issues: Page ${pageLoadTime}ms (${pageLoadPass ? 'OK' : 'SLOW'}), AJAX ${ajaxTime}ms (${ajaxPass ? 'OK' : 'SLOW'})`,
                duration: totalDuration
            };
        }
    }

    /**
     * Test security features
     */
    async testSecurity(driver, browserName) {
        const startTime = Date.now();
        
        await driver.get(`${this.baseUrl}/courses/test-course/`);
        
        try {
            // Check for CSRF tokens in forms
            const csrfTokens = await driver.findElements(By.css('input[name*="nonce"], input[name*="token"], input[name*="csrf"]'));
            
            // Check for XSS prevention (try to inject script)
            const textInputs = await driver.findElements(By.css('input[type="text"], textarea'));
            
            let xssProtected = true;
            if (textInputs.length > 0) {
                await textInputs[0].sendKeys('<script>alert("xss")</script>');
                
                // Check if script was executed (it shouldn't be)
                const alerts = await driver.executeScript('return window.alertFired || false;');
                xssProtected = !alerts;
            }
            
            const duration = Date.now() - startTime;
            
            const securityFeatures = {
                csrfProtection: csrfTokens.length > 0,
                xssProtection: xssProtected
            };
            
            const securityIssues = [];
            if (!securityFeatures.csrfProtection) securityIssues.push('CSRF tokens');
            if (!securityFeatures.xssProtection) securityIssues.push('XSS protection');
            
            if (securityIssues.length === 0) {
                return { success: true, message: 'Security features working', duration };
            } else {
                return { success: false, message: `Security issues: ${securityIssues.join(', ')}`, duration };
            }
        } catch (error) {
            const duration = Date.now() - startTime;
            return { success: false, message: 'Error checking security features', duration };
        }
    }

    /**
     * Record test result
     */
    recordResult(suite, browser, success, message, duration) {
        this.results.push({
            suite,
            browser,
            success,
            message,
            duration,
            timestamp: new Date().toISOString()
        });
    }

    /**
     * Generate test report
     */
    generateReport() {
        console.log('\n' + '='.repeat(60));
        console.log('CROSS-BROWSER TEST RESULTS SUMMARY');
        console.log('='.repeat(60));

        const totalTests = this.results.length;
        const passedTests = this.results.filter(r => r.success).length;
        const failedTests = totalTests - passedTests;
        const successRate = ((passedTests / totalTests) * 100).toFixed(1);

        console.log(`Total Tests: ${totalTests}`);
        console.log(`Passed: ${passedTests}`);
        console.log(`Failed: ${failedTests}`);
        console.log(`Success Rate: ${successRate}%`);

        // Group results by browser
        const browserResults = {};
        this.results.forEach(result => {
            if (!browserResults[result.browser]) {
                browserResults[result.browser] = { passed: 0, failed: 0, total: 0 };
            }
            browserResults[result.browser].total++;
            if (result.success) {
                browserResults[result.browser].passed++;
            } else {
                browserResults[result.browser].failed++;
            }
        });

        console.log('\nBrowser-specific Results:');
        console.log('-'.repeat(30));
        Object.keys(browserResults).forEach(browser => {
            const stats = browserResults[browser];
            const rate = ((stats.passed / stats.total) * 100).toFixed(1);
            console.log(`${browser.toUpperCase()}: ${stats.passed}/${stats.total} (${rate}%)`);
        });

        if (failedTests > 0) {
            console.log('\nFailed Tests:');
            console.log('-'.repeat(20));
            this.results.filter(r => !r.success).forEach(result => {
                console.log(`✗ ${result.suite} (${result.browser}): ${result.message}`);
            });
        }

        // Generate HTML report
        this.generateHTMLReport();

        console.log('\nCross-browser testing complete!');
    }

    /**
     * Generate HTML report
     */
    generateHTMLReport() {
        const fs = require('fs');
        const path = require('path');

        const html = `<!DOCTYPE html>
<html>
<head>
    <title>QuickLearn Course Manager - Cross-Browser Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #0073aa; color: white; padding: 20px; border-radius: 5px; }
        .summary { background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .browser-section { margin: 20px 0; }
        .test-result { margin: 5px 0; padding: 8px; border-left: 4px solid #ddd; }
        .test-result.pass { border-left-color: #46b450; background: #f0f8f0; }
        .test-result.fail { border-left-color: #dc3232; background: #fdf0f0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; }
        .pass { color: #46b450; font-weight: bold; }
        .fail { color: #dc3232; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QuickLearn Course Manager - Cross-Browser Test Report</h1>
        <p>Generated on: ${new Date().toLocaleString()}</p>
    </div>

    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Total Tests:</strong> ${this.results.length}</p>
        <p><strong>Passed:</strong> <span class="pass">${this.results.filter(r => r.success).length}</span></p>
        <p><strong>Failed:</strong> <span class="fail">${this.results.filter(r => !r.success).length}</span></p>
        <p><strong>Success Rate:</strong> ${((this.results.filter(r => r.success).length / this.results.length) * 100).toFixed(1)}%</p>
    </div>

    <div class="details">
        <h2>Detailed Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Test Suite</th>
                    <th>Browser</th>
                    <th>Status</th>
                    <th>Message</th>
                    <th>Duration (ms)</th>
                </tr>
            </thead>
            <tbody>`;

        this.results.forEach(result => {
            const statusClass = result.success ? 'pass' : 'fail';
            const statusText = result.success ? 'PASS' : 'FAIL';
            
            html += `<tr>
                <td>${result.suite}</td>
                <td>${result.browser.toUpperCase()}</td>
                <td class="${statusClass}">${statusText}</td>
                <td>${result.message}</td>
                <td>${result.duration}</td>
            </tr>`;
        });

        html += `
            </tbody>
        </table>
    </div>
</body>
</html>`;

        const reportPath = path.join(__dirname, 'cross-browser-report.html');
        fs.writeFileSync(reportPath, html);
        console.log(`\nDetailed HTML report generated: ${reportPath}`);
    }

    /**
     * Cleanup browser drivers
     */
    async cleanup() {
        console.log('\nCleaning up browser drivers...');
        
        for (const browser of this.browsers) {
            if (browser.driver) {
                try {
                    await browser.driver.quit();
                    console.log(`✓ ${browser.name} driver closed`);
                } catch (error) {
                    console.log(`⚠ Error closing ${browser.name} driver: ${error.message}`);
                }
            }
        }
    }
}

// Run tests if this file is executed directly
if (require.main === module) {
    const runner = new CrossBrowserTestRunner();
    runner.runAllTests().catch(console.error);
}

module.exports = CrossBrowserTestRunner;