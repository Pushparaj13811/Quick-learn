# QuickLearn E-Learning Portal

A complete WordPress-based e-learning portal featuring course management, user enrollment, progress tracking, ratings, and reviews.

## Features

### Core Features
- **Custom Course Post Type**: Manage courses through WordPress admin
- **Course Categories**: Organize courses with hierarchical taxonomy
- **AJAX Filtering**: Dynamic course filtering without page refresh
- **Responsive Design**: Mobile-first responsive layout
- **SEO Optimized**: Structured data, meta tags, and sitemap integration

### Advanced Features
- **User Enrollment System**: Track user course enrollments and progress
- **Progress Tracking**: Automatic progress tracking based on scroll and time
- **Course Ratings & Reviews**: 5-star rating system with written reviews
- **User Dashboard**: Personal dashboard showing enrolled courses
- **Admin Analytics**: Comprehensive analytics and reporting
- **Security Features**: Input sanitization, nonce verification, rate limiting

## Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Setup Instructions

1. **Upload Theme and Plugin**
   ```bash
   # Copy theme to WordPress themes directory
   cp -r wp-content/themes/quicklearn-theme /path/to/wordpress/wp-content/themes/
   
   # Copy plugin to WordPress plugins directory
   cp -r wp-content/plugins/quicklearn-course-manager /path/to/wordpress/wp-content/plugins/
   ```

2. **Activate Theme and Plugin**
   - Go to WordPress Admin → Appearance → Themes
   - Activate "QuickLearn Theme"
   - Go to WordPress Admin → Plugins
   - Activate "QuickLearn Course Manager"

3. **Create Required Pages**
   - Create a page titled "Courses" with slug "courses"
   - Create a page titled "Dashboard" with slug "dashboard"
   - Assign the appropriate page templates

4. **Configure Settings**
   - Go to Courses → Settings to configure plugin options
   - Set up navigation menus in Appearance → Menus

## Usage

### Creating Courses

1. Go to WordPress Admin → Courses → Add New
2. Enter course title and content
3. Set featured image
4. Assign course categories
5. Publish the course

### Managing Enrollments

- View enrollments: Courses → Enrollments
- Track progress: Individual course edit screens show enrollment stats
- Export data: Use the export functionality in admin pages

### User Experience

- **Course Browsing**: Users can browse courses at `/courses`
- **Course Filtering**: Filter by category using the dropdown
- **Course Enrollment**: Click "Enroll Now" on course pages
- **Progress Tracking**: Automatic tracking when viewing course content
- **User Dashboard**: View enrolled courses at `/dashboard`
- **Ratings & Reviews**: Leave ratings and reviews after enrollment

## File Structure

```
├── wp-content/
│   ├── themes/
│   │   └── quicklearn-theme/
│   │       ├── style.css
│   │       ├── functions.php
│   │       ├── index.php
│   │       ├── header.php
│   │       ├── footer.php
│   │       ├── page-courses.php
│   │       ├── page-dashboard.php
│   │       ├── single-quick_course.php
│   │       ├── template-parts/
│   │       │   └── course-card.php
│   │       ├── css/
│   │       │   └── custom.css
│   │       └── js/
│   │           ├── navigation.js
│   │           └── course-filter.js
│   └── plugins/
│       └── quicklearn-course-manager/
│           ├── quicklearn-course-manager.php
│           ├── includes/
│           │   ├── course-cpt.php
│           │   ├── course-taxonomy.php
│           │   ├── ajax-handlers.php
│           │   ├── seo-optimization.php
│           │   ├── user-enrollment.php
│           │   ├── course-ratings.php
│           │   └── admin-pages.php
│           └── assets/
│               ├── css/
│               │   ├── admin.css
│               │   ├── enrollment.css
│               │   └── ratings.css
│               └── js/
│                   ├── admin.js
│                   ├── enrollment.js
│                   └── ratings.js
```

## Database Schema

### Enrollments Table (`wp_qlcm_enrollments`)
- `id`: Primary key
- `user_id`: WordPress user ID
- `course_id`: Course post ID
- `enrollment_date`: Enrollment timestamp
- `status`: active, completed
- `completion_date`: Completion timestamp

### Course Progress Table (`wp_qlcm_course_progress`)
- `id`: Primary key
- `enrollment_id`: Reference to enrollment
- `module_id`: Module identifier
- `completion_date`: Progress timestamp
- `progress_percentage`: Progress percentage (0-100)

### Course Ratings Table (`wp_qlcm_course_ratings`)
- `id`: Primary key
- `course_id`: Course post ID
- `user_id`: WordPress user ID
- `rating`: Rating value (1-5)
- `review_title`: Optional review title
- `review_content`: Optional review content
- `created_date`: Rating timestamp
- `status`: approved, pending, rejected

## Customization

### Theme Customization

1. **Colors and Styling**
   - Edit `wp-content/themes/quicklearn-theme/css/custom.css`
   - Modify CSS variables for consistent theming

2. **Layout Changes**
   - Modify template files in the theme directory
   - Use WordPress hooks for additional functionality

### Plugin Customization

1. **Add Custom Fields**
   - Use WordPress meta boxes or custom fields
   - Hook into course save actions

2. **Extend Functionality**
   - Use provided action hooks:
     - `quicklearn_before_course_content`
     - `quicklearn_after_course_content`
     - `quicklearn_course_card_meta`
     - `qlcm_user_enrolled`
     - `qlcm_rating_saved`

## Security Features

- **Input Sanitization**: All user inputs are sanitized
- **Nonce Verification**: AJAX requests use nonces
- **Capability Checks**: Admin functions check user permissions
- **Rate Limiting**: AJAX requests are rate limited
- **SQL Injection Prevention**: Prepared statements used
- **XSS Prevention**: Output escaping implemented

## Performance Optimization

- **Image Optimization**: Responsive images with lazy loading
- **AJAX Caching**: Course data cached for performance
- **Database Optimization**: Indexed database tables
- **Asset Minification**: CSS and JS assets optimized
- **CDN Ready**: Assets can be served from CDN

## SEO Features

- **Structured Data**: Schema.org markup for courses
- **Meta Tags**: Open Graph and Twitter Card tags
- **XML Sitemap**: Courses included in WordPress sitemap
- **Canonical URLs**: Proper canonical URL implementation
- **Breadcrumbs**: Breadcrumb navigation for better UX

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Accessibility

- **WCAG 2.1 AA Compliant**: Meets accessibility standards
- **Keyboard Navigation**: Full keyboard support
- **Screen Reader Support**: Proper ARIA labels
- **Color Contrast**: Sufficient color contrast ratios
- **Focus Indicators**: Clear focus indicators

## Testing

### Unit Tests
Run PHP unit tests:
```bash
cd tests/
php run-tests.php
```

### Browser Tests
Run JavaScript tests:
```bash
cd tests/
node browser-tests.js
```

### Integration Tests
Test complete workflows:
```bash
cd tests/integration/
php test-course-workflow.php
```

## Troubleshooting

### Common Issues

1. **AJAX Not Working**
   - Check if jQuery is loaded
   - Verify nonce values
   - Check browser console for errors

2. **Courses Not Displaying**
   - Verify plugin is activated
   - Check if courses are published
   - Ensure proper page template is assigned

3. **Enrollment Issues**
   - Check database tables exist
   - Verify user permissions
   - Check for JavaScript errors

### Debug Mode

Enable WordPress debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is licensed under the GPL-2.0-or-later license.

## Support

For support and questions:
- Check the documentation
- Review the code comments
- Test with the provided test suite
- Enable debug mode for troubleshooting

## Changelog

### Version 1.0.1 - Enhanced E-Learning Platform
**🎯 Major Feature Additions:**
- **User Enrollment System**: Complete enrollment workflow with progress tracking
- **Course Ratings & Reviews**: 5-star rating system with written reviews
- **SEO Optimization**: Structured data, Open Graph, Twitter Cards, XML sitemaps
- **Analytics Dashboard**: Comprehensive course performance metrics
- **Admin Interface**: Enhanced admin pages with enrollment and rating management

**🔧 Technical Improvements:**
- Database schema for enrollments, progress, and ratings
- AJAX-powered enrollment and rating submission
- Automatic progress tracking based on scroll and time
- User dashboard with enrolled courses
- Meta boxes for course statistics
- Security enhancements with rate limiting
- Responsive design improvements
- Accessibility compliance (WCAG 2.1 AA)

**📊 Analytics Features:**
- Course enrollment statistics
- Completion rate tracking
- Rating trends and averages
- Popular courses reporting
- User engagement metrics

**🔍 SEO Features:**
- Schema.org Course markup
- Breadcrumb structured data
- Social media meta tags
- Canonical URLs
- XML sitemap integration
- Rating rich snippets

### Version 1.0.0
- Initial release
- Basic course management
- AJAX filtering
- Responsive theme
- Security implementation