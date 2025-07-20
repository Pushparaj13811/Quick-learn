# QuickLearn Course Manager - Test Suite

This directory contains a comprehensive test suite for the QuickLearn Course Manager plugin, covering all aspects of functionality, security, performance, and browser compatibility.

## Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap file
├── phpunit.xml               # PHPUnit configuration
├── run-tests.php             # Comprehensive test runner
├── browser-tests.js          # Browser compatibility tests
├── README.md                 # This documentation
├── includes/
│   └── test-utilities.php    # Test helper functions
├── unit/
│   ├── test-course-cpt.php   # Course post type tests
│   ├── test-course-taxonomy.php # Taxonomy tests
│   └── test-ajax-handlers.php   # AJAX functionality tests
└── integration/
    └── test-course-workflow.php # End-to-end workflow tests
```

## Requirements Coverage

### ✅ Requirement 1: Course Management (1.1, 1.2, 1.3, 1.4)
- **Unit Tests**: `test-course-cpt.php`
- **Coverage**: Custom post type registration, admin permissions, course CRUD operations
- **Test Methods**:
  - `test_course_post_type_registration()`
  - `test_admin_capability_restrictions()`
  - `test_course_creation_and_management()`
  - `test_specific_course_permissions()`

### ✅ Requirement 2: Course Display (2.1, 2.2, 2.3, 2.4)
- **Integration Tests**: `test-course-workflow.php`
- **Coverage**: Course listing, individual course display, no courses messaging
- **Test Methods**:
  - `test_visitor_course_browsing_workflow()`
  - `test_complete_user_experience_workflow()`

### ✅ Requirement 3: AJAX Filtering (3.1, 3.2, 3.3, 3.4)
- **Unit Tests**: `test-ajax-handlers.php`
- **Integration Tests**: `test-course-workflow.php`
- **Coverage**: Category filtering, pagination, all categories display
- **Test Methods**:
  - `test_successful_course_filtering()`
  - `test_all_categories_filtering()`
  - `test_pagination_functionality()`
  - `test_ajax_filtering_workflow()`

### ✅ Requirement 4: Theme Integration (4.1, 4.2, 4.3)
- **Browser Tests**: `browser-tests.js`
- **Coverage**: Responsive design, navigation, styling
- **Test Scenarios**: Desktop, tablet, mobile viewports

### ✅ Requirement 5: Security (5.1, 5.2, 5.3, 5.4)
- **Unit Tests**: All test files include security validation
- **Integration Tests**: `test_security_and_permissions_workflow()`
- **Coverage**: Input sanitization, nonce verification, capability checks, output escaping
- **Test Methods**:
  - `test_nonce_verification_failure()`
  - `test_input_sanitization()`
  - `test_security_functions()`
  - `test_admin_permissions()`

### ✅ Requirement 6: Category Management (6.1, 6.2, 6.3, 6.4)
- **Unit Tests**: `test-course-taxonomy.php`
- **Coverage**: Taxonomy registration, admin interface, course association, deletion handling
- **Test Methods**:
  - `test_taxonomy_registration()`
  - `test_category_creation_and_management()`
  - `test_course_category_association()`
  - `test_category_deletion_with_course_reassignment()`

### ✅ Requirement 7: Performance & UX (7.1, 7.2, 7.3, 7.4)
- **Unit Tests**: `test_performance_and_caching()`
- **Integration Tests**: `test_performance_workflow()`
- **Browser Tests**: Performance monitoring and responsive design
- **Coverage**: Loading states, response times, caching, mobile optimization

## Running Tests

### Prerequisites

1. **WordPress Environment**: Tests must be run within a WordPress installation
2. **PHPUnit**: Install PHPUnit for comprehensive unit testing
3. **Test Database**: Use a separate database for testing

### Installation

```bash
# Install PHPUnit (if not already installed)
composer require --dev phpunit/phpunit

# Set up WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Running All Tests

```bash
# Run comprehensive test suite
php tests/run-tests.php

# Run PHPUnit tests only
phpunit -c tests/phpunit.xml

# Run specific test file
phpunit tests/unit/test-course-cpt.php
```

### Browser Testing

```bash
# Install Node.js dependencies for browser testing
npm install puppeteer selenium-webdriver

# Run browser tests (requires test server)
node tests/browser-tests.js
```

## Test Categories

### 1. Unit Tests

**Purpose**: Test individual components in isolation

**Files**:
- `test-course-cpt.php`: Course post type functionality
- `test-course-taxonomy.php`: Category taxonomy functionality  
- `test-ajax-handlers.php`: AJAX request handling

**Coverage**:
- Custom post type registration and properties
- Taxonomy registration and associations
- AJAX filtering logic and security
- Input sanitization and validation
- Permission checks and capability restrictions

### 2. Integration Tests

**Purpose**: Test complete workflows and component interactions

**Files**:
- `test-course-workflow.php`: End-to-end user workflows

**Coverage**:
- Admin course management workflow
- Visitor course browsing experience
- AJAX filtering with real data
- Security and permissions across components
- Performance with realistic data sets

### 3. Browser Tests

**Purpose**: Test frontend functionality across different browsers and devices

**Files**:
- `browser-tests.js`: Cross-browser compatibility tests

**Coverage**:
- AJAX filtering in Chrome, Firefox, Safari, Edge
- Responsive design on desktop, tablet, mobile
- Keyboard navigation and accessibility
- Loading states and user feedback
- Performance benchmarks

## Test Data Management

### Test Utilities

The `QLCM_Test_Utilities` class provides helper methods for:

- Creating test courses and categories
- Setting up user accounts with different roles
- Mocking AJAX requests with proper nonces
- Cleaning up test data after each test
- Performance measurement utilities

### Data Cleanup

All tests automatically clean up their data using:
```php
public function tearDown() {
    QLCM_Test_Utilities::cleanup_test_data();
    parent::tearDown();
}
```

## Performance Benchmarks

### Target Performance Metrics

- **Page Load**: < 3 seconds for courses page
- **AJAX Response**: < 2 seconds for filtering
- **DOM Updates**: < 500ms for result display
- **Database Queries**: < 1 second for course retrieval

### Performance Tests

```php
// Example performance test
public function test_ajax_performance() {
    $start_time = microtime(true);
    
    // Execute AJAX filtering
    $this->ajax_instance->handle_course_filter();
    
    $execution_time = microtime(true) - $start_time;
    $this->assertLessThan(2.0, $execution_time);
}
```

## Security Testing

### Security Test Coverage

1. **Input Sanitization**: All user inputs are properly sanitized
2. **Output Escaping**: All output is properly escaped for context
3. **Nonce Verification**: AJAX requests include valid nonces
4. **Capability Checks**: Admin functions require proper permissions
5. **Rate Limiting**: AJAX endpoints have rate limiting protection

### Security Test Examples

```php
// Test XSS prevention
public function test_xss_prevention() {
    $malicious_input = '<script>alert("xss")</script>';
    $sanitized = QuickLearn_Course_Manager::escape_course_data($malicious_input);
    $this->assertNotContains('<script>', $sanitized);
}

// Test permission enforcement
public function test_permission_enforcement() {
    wp_set_current_user($this->regular_user_id);
    $this->assertFalse($this->cpt_instance->current_user_can_manage_courses());
}
```

## Accessibility Testing

### Accessibility Coverage

- **ARIA Labels**: Interactive elements have proper labels
- **Keyboard Navigation**: All functionality accessible via keyboard
- **Screen Reader**: Content properly announced to screen readers
- **Color Contrast**: Meets WCAG 2.1 AA standards
- **Focus Management**: Clear focus indicators and logical tab order

### Manual Accessibility Testing

1. Navigate using only keyboard (Tab, Enter, Arrow keys)
2. Test with screen reader (NVDA, JAWS, VoiceOver)
3. Verify color contrast ratios
4. Check focus indicators visibility
5. Test with high contrast mode

## Continuous Integration

### GitHub Actions Example

```yaml
name: Test Suite
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install WordPress Test Suite
        run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
      - name: Run PHPUnit Tests
        run: phpunit -c tests/phpunit.xml
      - name: Run Browser Tests
        run: node tests/browser-tests.js
```

## Troubleshooting

### Common Issues

1. **WordPress Not Found**: Ensure tests run within WordPress environment
2. **Database Errors**: Check test database configuration
3. **Permission Errors**: Verify file permissions for test files
4. **Memory Limits**: Increase PHP memory limit for large test suites

### Debug Mode

Enable debug mode in tests:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

### Adding New Tests

1. Follow existing naming conventions
2. Include proper documentation
3. Clean up test data in `tearDown()`
4. Test both success and failure scenarios
5. Include performance considerations

### Test Guidelines

- **Isolation**: Each test should be independent
- **Clarity**: Test names should describe what they test
- **Coverage**: Aim for high code coverage
- **Performance**: Tests should run quickly
- **Reliability**: Tests should be deterministic

## Reporting

### Test Reports

The test runner generates:
- Console output with real-time results
- HTML report (`test-report.html`)
- Performance metrics
- Coverage statistics (with PHPUnit)

### Example Report Output

```
QuickLearn Course Manager - Comprehensive Test Suite
==================================================

1. Testing Plugin Activation...
  ✓ Plugin main class exists
  ✓ Plugin instance created successfully

2. Testing Custom Post Type Registration...
  ✓ Course post type is registered
  ✓ Post type public: PASS
  ✓ Post type show_ui: PASS
  ✓ Post type supports_title: PASS

...

============================================================
TEST RESULTS SUMMARY
============================================================
Total Tests: 45
Passed: 43
Failed: 2
Success Rate: 95.56%
Execution Time: 2.847 seconds

STATUS: SOME TESTS FAILED
Please review the failed tests above and fix the issues.
============================================================
```

This comprehensive test suite ensures that the QuickLearn Course Manager plugin meets all requirements and maintains high quality standards across functionality, security, performance, and user experience.