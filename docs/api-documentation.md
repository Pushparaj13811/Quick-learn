# API Documentation - QuickLearn E-Learning Portal

This document provides comprehensive technical documentation for developers working with the QuickLearn e-learning platform APIs and integration points.

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [REST API Endpoints](#rest-api-endpoints)
4. [AJAX Endpoints](#ajax-endpoints)
5. [WordPress Hooks](#wordpress-hooks)
6. [Database Schema](#database-schema)
7. [Integration Examples](#integration-examples)

## Overview

The QuickLearn platform provides multiple API interfaces for integration and customization:

- **WordPress REST API** - Standard WordPress REST endpoints
- **Custom REST Endpoints** - QuickLearn-specific API endpoints
- **AJAX Handlers** - Frontend interaction endpoints
- **WordPress Hooks** - Action and filter hooks for customization

### Base URLs

```
REST API Base: /wp-json/wp/v2/
Custom API Base: /wp-json/quicklearn/v1/
AJAX Base: /wp-admin/admin-ajax.php
```

## Authentication

### WordPress Authentication

1. **Cookie Authentication**
   - Used for logged-in users
   - Automatic for admin panel requests
   - Requires nonce verification

2. **Application Passwords**
   - For external applications
   - Generated in user profile
   - HTTP Basic Authentication

3. **JWT Authentication** (Optional Plugin)
   - Token-based authentication
   - For mobile apps and SPAs
   - Requires additional plugin

### API Key Authentication

```php
// Custom API key validation
function quicklearn_validate_api_key($api_key) {
    $stored_key = get_option('quicklearn_api_key');
    return hash_equals($stored_key, $api_key);
}
```

## REST API Endpoints

### Course Endpoints

#### Get All Courses

```http
GET /wp-json/wp/v2/quick_course
```

**Parameters:**
- `per_page` (int) - Number of courses per page (default: 10)
- `page` (int) - Page number (default: 1)
- `course_category` (int) - Filter by category ID
- `search` (string) - Search in course title/content
- `orderby` (string) - Sort field (date, title, menu_order)
- `order` (string) - Sort direction (asc, desc)

**Response:**
```json
[
  {
    "id": 123,
    "date": "2024-01-15T10:30:00",
    "title": {
      "rendered": "Introduction to Web Development"
    },
    "content": {
      "rendered": "<p>Course content...</p>"
    },
    "excerpt": {
      "rendered": "<p>Course excerpt...</p>"
    },
    "featured_media": 456,
    "course_category": [1, 2],
    "meta": {
      "course_duration": "40 hours",
      "course_level": "beginner"
    }
  }
]
```

#### Get Single Course

```http
GET /wp-json/wp/v2/quick_course/{id}
```

**Response:**
```json
{
  "id": 123,
  "title": {
    "rendered": "Introduction to Web Development"
  },
  "content": {
    "rendered": "<p>Full course content...</p>"
  },
  "course_category": [1, 2],
  "enrollment_count": 150,
  "average_rating": 4.5,
  "modules": [
    {
      "id": "module-1",
      "title": "HTML Basics",
      "lessons": [
        {
          "id": "lesson-1-1",
          "title": "Introduction to HTML",
          "content": "...",
          "duration": "30 minutes"
        }
      ]
    }
  ]
}
```

#### Create Course (Admin Only)

```http
POST /wp-json/wp/v2/quick_course
```

**Headers:**
```
Content-Type: application/json
Authorization: Basic {credentials}
```

**Body:**
```json
{
  "title": "New Course Title",
  "content": "Course content here",
  "excerpt": "Course excerpt",
  "status": "publish",
  "course_category": [1, 2],
  "meta": {
    "course_duration": "20 hours",
    "course_level": "intermediate"
  }
}
```

### Category Endpoints

#### Get Course Categories

```http
GET /wp-json/wp/v2/course_category
```

**Response:**
```json
[
  {
    "id": 1,
    "name": "Web Development",
    "slug": "web-development",
    "description": "Learn web development skills",
    "count": 25,
    "parent": 0
  }
]
```

### Custom QuickLearn Endpoints

#### Get Course Statistics

```http
GET /wp-json/quicklearn/v1/courses/{id}/stats
```

**Response:**
```json
{
  "course_id": 123,
  "enrollment_count": 150,
  "completion_count": 89,
  "completion_rate": 59.3,
  "average_rating": 4.5,
  "total_ratings": 67,
  "average_completion_time": "35 days"
}
```

#### Get User Enrollments

```http
GET /wp-json/quicklearn/v1/users/{user_id}/enrollments
```

**Response:**
```json
{
  "user_id": 456,
  "enrollments": [
    {
      "course_id": 123,
      "enrollment_date": "2024-01-15T10:30:00",
      "status": "active",
      "progress_percentage": 75,
      "completion_date": null,
      "last_activity": "2024-01-20T14:22:00"
    }
  ]
}
```

#### Enroll User in Course

```http
POST /wp-json/quicklearn/v1/courses/{course_id}/enroll
```

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Body:**
```json
{
  "user_id": 456
}
```

**Response:**
```json
{
  "success": true,
  "enrollment_id": 789,
  "message": "Successfully enrolled in course"
}
```

## AJAX Endpoints

### Course Filtering

**Endpoint:** `wp_ajax_filter_courses` / `wp_ajax_nopriv_filter_courses`

**Action:** `filter_courses`

**Parameters:**
- `category_id` (int) - Category to filter by (0 for all)
- `search` (string) - Search term
- `page` (int) - Page number
- `per_page` (int) - Results per page
- `nonce` (string) - Security nonce

**Request:**
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'filter_courses',
        category_id: 1,
        search: 'web development',
        page: 1,
        per_page: 12,
        nonce: quicklearn_ajax.nonce
    },
    success: function(response) {
        // Handle response
    }
});
```

**Response:**
```json
{
  "success": true,
  "data": {
    "courses": [
      {
        "id": 123,
        "title": "Course Title",
        "excerpt": "Course excerpt...",
        "thumbnail": "image-url",
        "categories": ["Web Development"],
        "permalink": "course-url",
        "rating": 4.5,
        "enrollment_count": 150
      }
    ],
    "total": 25,
    "pages": 3
  }
}
```

### User Enrollment

**Endpoint:** `wp_ajax_enroll_course`

**Action:** `enroll_course`

**Parameters:**
- `course_id` (int) - Course ID to enroll in
- `nonce` (string) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Successfully enrolled in course",
    "enrollment_id": 789,
    "redirect_url": "/dashboard"
  }
}
```

### Course Rating

**Endpoint:** `wp_ajax_rate_course`

**Action:** `rate_course`

**Parameters:**
- `course_id` (int) - Course ID
- `rating` (int) - Rating value (1-5)
- `review` (string) - Optional review text
- `nonce` (string) - Security nonce

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Rating submitted successfully",
    "new_average": 4.3,
    "total_ratings": 68
  }
}
```

## WordPress Hooks

### Action Hooks

#### Course Management

```php
// Fired when a course is created
do_action('quicklearn_course_created', $course_id, $course_data);

// Fired when a course is updated
do_action('quicklearn_course_updated', $course_id, $course_data);

// Fired when a course is deleted
do_action('quicklearn_course_deleted', $course_id);
```

#### User Enrollment

```php
// Fired when user enrolls in course
do_action('quicklearn_user_enrolled', $user_id, $course_id, $enrollment_id);

// Fired when user completes course
do_action('quicklearn_course_completed', $user_id, $course_id, $completion_data);

// Fired when user progress is updated
do_action('quicklearn_progress_updated', $user_id, $course_id, $progress_data);
```

#### Certificate Generation

```php
// Fired when certificate is generated
do_action('quicklearn_certificate_generated', $user_id, $course_id, $certificate_id);

// Fired when certificate is downloaded
do_action('quicklearn_certificate_downloaded', $user_id, $certificate_id);
```

### Filter Hooks

#### Course Display

```php
// Filter course content before display
$content = apply_filters('quicklearn_course_content', $content, $course_id);

// Filter course excerpt
$excerpt = apply_filters('quicklearn_course_excerpt', $excerpt, $course_id);

// Filter course categories display
$categories = apply_filters('quicklearn_course_categories', $categories, $course_id);
```

#### Enrollment Logic

```php
// Filter enrollment eligibility
$can_enroll = apply_filters('quicklearn_can_enroll', true, $user_id, $course_id);

// Filter enrollment data
$enrollment_data = apply_filters('quicklearn_enrollment_data', $data, $user_id, $course_id);
```

#### Rating System

```php
// Filter rating display
$rating_html = apply_filters('quicklearn_rating_display', $html, $course_id, $rating);

// Filter review content
$review_content = apply_filters('quicklearn_review_content', $content, $review_id);
```

## Database Schema

### Core Tables

#### Enrollments Table

```sql
CREATE TABLE wp_qlcm_enrollments (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    course_id bigint(20) NOT NULL,
    enrollment_date datetime DEFAULT CURRENT_TIMESTAMP,
    status varchar(20) DEFAULT 'active',
    completion_date datetime DEFAULT NULL,
    progress_percentage int(3) DEFAULT 0,
    last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_course (user_id, course_id),
    KEY user_id (user_id),
    KEY course_id (course_id),
    KEY status (status)
);
```

#### Course Progress Table

```sql
CREATE TABLE wp_qlcm_course_progress (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    enrollment_id bigint(20) NOT NULL,
    module_id varchar(50) NOT NULL,
    lesson_id varchar(50) DEFAULT NULL,
    completion_date datetime DEFAULT CURRENT_TIMESTAMP,
    progress_percentage int(3) DEFAULT 0,
    time_spent int(11) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY enrollment_module (enrollment_id, module_id),
    KEY enrollment_id (enrollment_id)
);
```

#### Course Ratings Table

```sql
CREATE TABLE wp_qlcm_course_ratings (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    course_id bigint(20) NOT NULL,
    rating int(1) NOT NULL,
    review_text text,
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status varchar(20) DEFAULT 'approved',
    PRIMARY KEY (id),
    UNIQUE KEY user_course_rating (user_id, course_id),
    KEY course_id (course_id),
    KEY rating (rating),
    KEY status (status)
);
```

#### Certificates Table

```sql
CREATE TABLE wp_qlcm_certificates (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    course_id bigint(20) NOT NULL,
    certificate_id varchar(50) UNIQUE NOT NULL,
    issue_date datetime DEFAULT CURRENT_TIMESTAMP,
    certificate_data longtext,
    download_count int(11) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY user_course_cert (user_id, course_id),
    UNIQUE KEY certificate_id (certificate_id),
    KEY user_id (user_id),
    KEY course_id (course_id)
);
```

### Database Queries

#### Common Queries

```php
// Get user enrollments
$enrollments = $wpdb->get_results($wpdb->prepare("
    SELECT e.*, p.title as course_title 
    FROM {$wpdb->prefix}qlcm_enrollments e
    JOIN {$wpdb->posts} p ON e.course_id = p.ID
    WHERE e.user_id = %d AND e.status = 'active'
    ORDER BY e.enrollment_date DESC
", $user_id));

// Get course statistics
$stats = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(*) as enrollment_count,
        AVG(progress_percentage) as avg_progress,
        COUNT(CASE WHEN progress_percentage = 100 THEN 1 END) as completion_count
    FROM {$wpdb->prefix}qlcm_enrollments 
    WHERE course_id = %d AND status = 'active'
", $course_id));

// Get course ratings
$rating_data = $wpdb->get_row($wpdb->prepare("
    SELECT 
        AVG(rating) as average_rating,
        COUNT(*) as total_ratings
    FROM {$wpdb->prefix}qlcm_course_ratings 
    WHERE course_id = %d AND status = 'approved'
", $course_id));
```

## Integration Examples

### Custom Theme Integration

```php
// functions.php - Add custom course data to REST API
function add_course_meta_to_api() {
    register_rest_field('quick_course', 'enrollment_count', array(
        'get_callback' => function($post) {
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}qlcm_enrollments 
                WHERE course_id = %d AND status = 'active'
            ", $post['id']));
        }
    ));
}
add_action('rest_api_init', 'add_course_meta_to_api');
```

### JavaScript Integration

```javascript
// Frontend course enrollment
class QuickLearnAPI {
    constructor(baseUrl, nonce) {
        this.baseUrl = baseUrl;
        this.nonce = nonce;
    }
    
    async enrollInCourse(courseId) {
        const response = await fetch(`${this.baseUrl}/wp-admin/admin-ajax.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'enroll_course',
                course_id: courseId,
                nonce: this.nonce
            })
        });
        
        return await response.json();
    }
    
    async getCourseProgress(courseId) {
        const response = await fetch(
            `${this.baseUrl}/wp-json/quicklearn/v1/courses/${courseId}/progress`,
            {
                credentials: 'include'
            }
        );
        
        return await response.json();
    }
}

// Usage
const api = new QuickLearnAPI(window.location.origin, quicklearn_ajax.nonce);
api.enrollInCourse(123).then(result => {
    if (result.success) {
        console.log('Enrolled successfully');
    }
});
```

### PHP Plugin Integration

```php
// Custom plugin integration example
class CustomQuickLearnIntegration {
    
    public function __construct() {
        add_action('quicklearn_user_enrolled', array($this, 'handle_enrollment'), 10, 3);
        add_action('quicklearn_course_completed', array($this, 'handle_completion'), 10, 3);
    }
    
    public function handle_enrollment($user_id, $course_id, $enrollment_id) {
        // Send welcome email
        $user = get_user_by('ID', $user_id);
        $course = get_post($course_id);
        
        wp_mail(
            $user->user_email,
            'Welcome to ' . $course->post_title,
            'You have successfully enrolled in the course.'
        );
        
        // Log to external system
        $this->log_to_external_system('enrollment', array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'timestamp' => current_time('mysql')
        ));
    }
    
    public function handle_completion($user_id, $course_id, $completion_data) {
        // Award points or badges
        $this->award_completion_points($user_id, $course_id);
        
        // Send completion certificate
        $this->generate_completion_certificate($user_id, $course_id);
    }
    
    private function log_to_external_system($event, $data) {
        // Integration with external analytics or CRM
        wp_remote_post('https://api.example.com/events', array(
            'body' => json_encode(array(
                'event' => $event,
                'data' => $data
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . get_option('external_api_key')
            )
        ));
    }
}

new CustomQuickLearnIntegration();
```

### Mobile App Integration

```javascript
// React Native example
import AsyncStorage from '@react-native-async-storage/async-storage';

class QuickLearnMobileAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }
    
    async authenticate(username, password) {
        const response = await fetch(`${this.baseUrl}/wp-json/jwt-auth/v1/token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.token) {
            await AsyncStorage.setItem('auth_token', data.token);
            return { success: true, token: data.token };
        }
        
        return { success: false, message: data.message };
    }
    
    async getCourses() {
        const token = await AsyncStorage.getItem('auth_token');
        
        const response = await fetch(`${this.baseUrl}/wp-json/wp/v2/quick_course`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        return await response.json();
    }
}
```

## Error Handling

### API Error Responses

```json
{
  "success": false,
  "error": {
    "code": "invalid_course_id",
    "message": "The specified course ID does not exist",
    "data": {
      "status": 404
    }
  }
}
```

### Common Error Codes

- `invalid_nonce` - Security nonce verification failed
- `insufficient_permissions` - User lacks required permissions
- `invalid_course_id` - Course does not exist
- `already_enrolled` - User already enrolled in course
- `enrollment_limit_reached` - Course enrollment limit exceeded
- `invalid_rating` - Rating value out of range (1-5)

### Error Handling Best Practices

```php
// PHP error handling
function handle_quicklearn_error($error_code, $message, $data = array()) {
    if (wp_doing_ajax()) {
        wp_send_json_error(array(
            'code' => $error_code,
            'message' => $message,
            'data' => $data
        ));
    } else {
        wp_die($message, $error_code, array('response' => 400));
    }
}
```

```javascript
// JavaScript error handling
async function handleAPICall(apiFunction) {
    try {
        const result = await apiFunction();
        
        if (!result.success) {
            throw new Error(result.error?.message || 'API call failed');
        }
        
        return result.data;
    } catch (error) {
        console.error('API Error:', error);
        // Show user-friendly error message
        showErrorMessage('Something went wrong. Please try again.');
        throw error;
    }
}
```

## Rate Limiting

### Implementation

```php
// Simple rate limiting for API endpoints
function quicklearn_rate_limit_check($action, $user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    $key = "quicklearn_rate_limit_{$action}_{$user_id}";
    
    $attempts = get_transient($key);
    
    if ($attempts >= 10) { // 10 attempts per minute
        return false;
    }
    
    set_transient($key, $attempts + 1, 60); // 1 minute
    return true;
}
```

## Webhooks

### Webhook Configuration

```php
// Register webhook endpoints
function quicklearn_register_webhooks() {
    add_action('quicklearn_user_enrolled', 'quicklearn_webhook_enrollment', 10, 3);
    add_action('quicklearn_course_completed', 'quicklearn_webhook_completion', 10, 3);
}

function quicklearn_webhook_enrollment($user_id, $course_id, $enrollment_id) {
    $webhook_url = get_option('quicklearn_enrollment_webhook_url');
    
    if ($webhook_url) {
        wp_remote_post($webhook_url, array(
            'body' => json_encode(array(
                'event' => 'user_enrolled',
                'user_id' => $user_id,
                'course_id' => $course_id,
                'enrollment_id' => $enrollment_id,
                'timestamp' => current_time('c')
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
    }
}
```

This API documentation provides comprehensive coverage of all integration points and technical details needed for developers working with the QuickLearn e-learning platform.