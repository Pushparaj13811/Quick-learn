# Deployment Guide - QuickLearn E-Learning Portal

This comprehensive guide covers production deployment, monitoring, backup, and security procedures for the QuickLearn e-learning platform.

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Production Environment Setup](#production-environment-setup)
3. [Deployment Process](#deployment-process)
4. [Monitoring and Logging](#monitoring-and-logging)
5. [Backup and Recovery](#backup-and-recovery)
6. [Security Configuration](#security-configuration)
7. [Performance Optimization](#performance-optimization)
8. [Maintenance Procedures](#maintenance-procedures)

## Pre-Deployment Checklist

### System Requirements Verification

**Server Requirements:**
- [ ] PHP 7.4+ (8.0+ recommended)
- [ ] MySQL 5.7+ or MariaDB 10.2+
- [ ] Apache 2.4+ or Nginx 1.18+
- [ ] SSL certificate installed
- [ ] Minimum 1GB RAM (2GB+ recommended)
- [ ] SSD storage recommended

**WordPress Requirements:**
- [ ] WordPress 5.0+ (6.0+ recommended)
- [ ] mod_rewrite enabled (Apache)
- [ ] PHP extensions: mysqli, gd, curl, zip, mbstring
- [ ] PHP memory limit: 256MB minimum (512MB recommended)
- [ ] PHP max execution time: 300 seconds minimum

### Code Quality Checks

**Pre-deployment Testing:**
- [ ] All unit tests passing
- [ ] Integration tests completed
- [ ] Cross-browser testing completed
- [ ] Mobile responsiveness verified
- [ ] Performance benchmarks met
- [ ] Security scan completed
- [ ] Accessibility compliance verified

**Code Review:**
- [ ] Code follows WordPress coding standards
- [ ] All functions properly documented
- [ ] No debug code or console.log statements
- [ ] Error handling implemented
- [ ] Input sanitization verified
- [ ] Output escaping implemented

### Database Preparation

**Database Setup:**
- [ ] Production database created
- [ ] Database user with appropriate permissions
- [ ] Database collation set to utf8mb4_unicode_ci
- [ ] Required tables will be created on plugin activation
- [ ] Database backup strategy in place

### File Structure Verification

```
wordpress-root/
├── wp-content/
│   ├── themes/
│   │   └── quicklearn-theme/
│   │       ├── style.css
│   │       ├── index.php
│   │       ├── functions.php
│   │       ├── header.php
│   │       ├── footer.php
│   │       ├── page-courses.php
│   │       ├── single-quick_course.php
│   │       ├── js/
│   │       │   └── course-filter.js
│   │       └── css/
│   │           └── custom.css
│   └── plugins/
│       └── quicklearn-course-manager/
│           ├── quicklearn-course-manager.php
│           ├── includes/
│           │   ├── course-cpt.php
│           │   ├── course-taxonomy.php
│           │   ├── ajax-handlers.php
│           │   ├── enrollment-system.php
│           │   ├── rating-system.php
│           │   ├── certificate-system.php
│           │   └── analytics.php
│           └── assets/
│               ├── css/
│               └── js/
└── docs/
    ├── README.md
    ├── user-guide.md
    ├── admin-guide.md
    ├── api-documentation.md
    ├── troubleshooting.md
    ├── faq.md
    └── deployment-guide.md
```

## Production Environment Setup

### Server Configuration

**Apache Configuration (.htaccess):**
```apache
# WordPress SEO-friendly URLs
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'"

# Cache control
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>

# Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

**Nginx Configuration:**
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    root /var/www/html;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # WordPress permalinks
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~* /(?:uploads|files)/.*\.php$ {
        deny all;
    }
}
```

### PHP Configuration

**php.ini Settings:**
```ini
; Memory and execution limits
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
max_input_vars = 3000

; File upload settings
upload_max_filesize = 64M
post_max_size = 64M
file_uploads = On

; Session settings
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Error reporting (production)
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

### WordPress Configuration

**wp-config.php Production Settings:**
```php
<?php
// Database settings
define('DB_NAME', 'production_database');
define('DB_USER', 'production_user');
define('DB_PASSWORD', 'secure_password');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Security keys (generate unique keys)
define('AUTH_KEY',         'your-unique-auth-key');
define('SECURE_AUTH_KEY',  'your-unique-secure-auth-key');
define('LOGGED_IN_KEY',    'your-unique-logged-in-key');
define('NONCE_KEY',        'your-unique-nonce-key');
define('AUTH_SALT',        'your-unique-auth-salt');
define('SECURE_AUTH_SALT', 'your-unique-secure-auth-salt');
define('LOGGED_IN_SALT',   'your-unique-logged-in-salt');
define('NONCE_SALT',       'your-unique-nonce-salt');

// Production settings
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Security settings
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
define('FORCE_SSL_ADMIN', true);
define('WP_AUTO_UPDATE_CORE', 'minor');

// Performance settings
define('WP_CACHE', true);
define('COMPRESS_CSS', true);
define('COMPRESS_SCRIPTS', true);
define('CONCATENATE_SCRIPTS', true);

// Custom settings for QuickLearn
define('QUICKLEARN_UPLOAD_PATH', WP_CONTENT_DIR . '/uploads/quicklearn/');
define('QUICKLEARN_CERTIFICATE_PATH', WP_CONTENT_DIR . '/uploads/certificates/');
define('QUICKLEARN_MAX_ENROLLMENT_BATCH', 100);

$table_prefix = 'wp_';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
```

## Deployment Process

### Automated Deployment Script

**deploy.sh:**
```bash
#!/bin/bash

# QuickLearn Production Deployment Script
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/quicklearn"
WP_ROOT="/var/www/html"
PLUGIN_DIR="$WP_ROOT/wp-content/plugins/quicklearn-course-manager"
THEME_DIR="$WP_ROOT/wp-content/themes/quicklearn-theme"

echo "Starting QuickLearn deployment for $ENVIRONMENT environment..."

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup current installation
echo "Creating backup..."
tar -czf "$BACKUP_DIR/quicklearn_backup_$TIMESTAMP.tar.gz" \
    "$PLUGIN_DIR" "$THEME_DIR" 2>/dev/null || true

# Backup database
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME > "$BACKUP_DIR/database_backup_$TIMESTAMP.sql"

# Deploy plugin files
echo "Deploying plugin files..."
rsync -av --delete ./wp-content/plugins/quicklearn-course-manager/ $PLUGIN_DIR/

# Deploy theme files
echo "Deploying theme files..."
rsync -av --delete ./wp-content/themes/quicklearn-theme/ $THEME_DIR/

# Set proper permissions
echo "Setting file permissions..."
find $PLUGIN_DIR -type f -exec chmod 644 {} \;
find $PLUGIN_DIR -type d -exec chmod 755 {} \;
find $THEME_DIR -type f -exec chmod 644 {} \;
find $THEME_DIR -type d -exec chmod 755 {} \;

# Clear cache if caching plugin is active
echo "Clearing cache..."
wp cache flush --path=$WP_ROOT 2>/dev/null || true

# Run database updates if needed
echo "Checking for database updates..."
wp plugin activate quicklearn-course-manager --path=$WP_ROOT

# Verify deployment
echo "Verifying deployment..."
if wp plugin is-active quicklearn-course-manager --path=$WP_ROOT; then
    echo "✓ Plugin is active"
else
    echo "✗ Plugin activation failed"
    exit 1
fi

if wp theme is-active quicklearn-theme --path=$WP_ROOT; then
    echo "✓ Theme is active"
else
    echo "✓ Theme deployed (not necessarily active)"
fi

# Test critical functionality
echo "Running post-deployment tests..."
curl -f -s "$SITE_URL/courses/" > /dev/null && echo "✓ Courses page accessible" || echo "✗ Courses page failed"
curl -f -s "$SITE_URL/wp-admin/admin-ajax.php" > /dev/null && echo "✓ AJAX endpoint accessible" || echo "✗ AJAX endpoint failed"

echo "Deployment completed successfully!"
echo "Backup created: $BACKUP_DIR/quicklearn_backup_$TIMESTAMP.tar.gz"
```

### Manual Deployment Steps

1. **Pre-deployment Backup:**
   ```bash
   # Backup files
   tar -czf quicklearn_backup_$(date +%Y%m%d).tar.gz \
       wp-content/plugins/quicklearn-course-manager \
       wp-content/themes/quicklearn-theme
   
   # Backup database
   mysqldump -u username -p database_name > quicklearn_db_backup_$(date +%Y%m%d).sql
   ```

2. **Upload Files:**
   ```bash
   # Upload plugin
   rsync -av quicklearn-course-manager/ /var/www/html/wp-content/plugins/quicklearn-course-manager/
   
   # Upload theme
   rsync -av quicklearn-theme/ /var/www/html/wp-content/themes/quicklearn-theme/
   ```

3. **Set Permissions:**
   ```bash
   # Set proper file permissions
   find /var/www/html/wp-content/plugins/quicklearn-course-manager -type f -exec chmod 644 {} \;
   find /var/www/html/wp-content/plugins/quicklearn-course-manager -type d -exec chmod 755 {} \;
   find /var/www/html/wp-content/themes/quicklearn-theme -type f -exec chmod 644 {} \;
   find /var/www/html/wp-content/themes/quicklearn-theme -type d -exec chmod 755 {} \;
   ```

4. **Activate Components:**
   ```bash
   # Activate plugin
   wp plugin activate quicklearn-course-manager
   
   # Activate theme (if needed)
   wp theme activate quicklearn-theme
   ```

### Database Migration

**Migration Script (migrate.php):**
```php
<?php
// Database migration script for QuickLearn
// Run this script after deployment to ensure database is up to date

require_once 'wp-config.php';
require_once 'wp-includes/wp-db.php';

global $wpdb;

// Check if migration is needed
$current_version = get_option('quicklearn_db_version', '0.0.0');
$target_version = '2.0.0';

if (version_compare($current_version, $target_version, '<')) {
    echo "Migrating database from version $current_version to $target_version...\n";
    
    // Create or update tables
    $tables = array(
        'enrollments' => "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}qlcm_enrollments (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        'course_progress' => "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}qlcm_course_progress (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        'course_ratings' => "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}qlcm_course_ratings (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        'certificates' => "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}qlcm_certificates (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    );
    
    foreach ($tables as $table_name => $sql) {
        $result = $wpdb->query($sql);
        if ($result === false) {
            echo "Error creating table $table_name: " . $wpdb->last_error . "\n";
        } else {
            echo "Table $table_name created/updated successfully\n";
        }
    }
    
    // Update version
    update_option('quicklearn_db_version', $target_version);
    echo "Database migration completed successfully!\n";
} else {
    echo "Database is already up to date (version $current_version)\n";
}
?>
```

## Monitoring and Logging

### Application Monitoring

**Health Check Endpoint (health-check.php):**
```php
<?php
// QuickLearn Health Check Endpoint
// Access via: /health-check.php

header('Content-Type: application/json');

$health = array(
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => array()
);

// Check database connection
try {
    global $wpdb;
    $wpdb->get_var("SELECT 1");
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['checks']['database'] = 'error';
    $health['status'] = 'unhealthy';
}

// Check plugin activation
if (is_plugin_active('quicklearn-course-manager/quicklearn-course-manager.php')) {
    $health['checks']['plugin'] = 'ok';
} else {
    $health['checks']['plugin'] = 'error';
    $health['status'] = 'unhealthy';
}

// Check file permissions
$upload_dir = wp_upload_dir();
if (is_writable($upload_dir['basedir'])) {
    $health['checks']['file_permissions'] = 'ok';
} else {
    $health['checks']['file_permissions'] = 'error';
    $health['status'] = 'unhealthy';
}

// Check memory usage
$memory_limit = ini_get('memory_limit');
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);

$health['checks']['memory'] = array(
    'limit' => $memory_limit,
    'usage' => round($memory_usage / 1024 / 1024, 2) . 'MB',
    'peak' => round($memory_peak / 1024 / 1024, 2) . 'MB'
);

// Set appropriate HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);
?>
```

### Logging Configuration

**Custom Logging (logger.php):**
```php
<?php
class QuickLearnLogger {
    private $log_file;
    private $log_level;
    
    public function __construct($log_file = null, $log_level = 'INFO') {
        $this->log_file = $log_file ?: WP_CONTENT_DIR . '/logs/quicklearn.log';
        $this->log_level = $log_level;
        
        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }
    
    public function log($level, $message, $context = array()) {
        $levels = array('DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3);
        
        if ($levels[$level] >= $levels[$this->log_level]) {
            $timestamp = date('Y-m-d H:i:s');
            $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
            $log_entry = "[$timestamp] [$level] $message$context_str" . PHP_EOL;
            
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }
    
    public function info($message, $context = array()) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = array()) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = array()) {
        $this->log('ERROR', $message, $context);
    }
    
    public function debug($message, $context = array()) {
        $this->log('DEBUG', $message, $context);
    }
}

// Usage in plugin
$logger = new QuickLearnLogger();
$logger->info('User enrolled in course', array(
    'user_id' => $user_id,
    'course_id' => $course_id
));
```

### Server Monitoring

**System Monitoring Script (monitor.sh):**
```bash
#!/bin/bash

# QuickLearn System Monitoring Script
# Run via cron every 5 minutes

LOG_FILE="/var/log/quicklearn-monitor.log"
ALERT_EMAIL="admin@yourdomain.com"
SITE_URL="https://yourdomain.com"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

# Check website availability
check_website() {
    if curl -f -s "$SITE_URL" > /dev/null; then
        log_message "Website is accessible"
        return 0
    else
        log_message "ERROR: Website is not accessible"
        echo "QuickLearn website is down!" | mail -s "Website Down Alert" $ALERT_EMAIL
        return 1
    fi
}

# Check database connectivity
check_database() {
    if mysql -u $DB_USER -p$DB_PASSWORD -e "SELECT 1" $DB_NAME > /dev/null 2>&1; then
        log_message "Database is accessible"
        return 0
    else
        log_message "ERROR: Database connection failed"
        echo "QuickLearn database connection failed!" | mail -s "Database Alert" $ALERT_EMAIL
        return 1
    fi
}

# Check disk space
check_disk_space() {
    DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ $DISK_USAGE -gt 85 ]; then
        log_message "WARNING: Disk usage is ${DISK_USAGE}%"
        echo "Disk usage is ${DISK_USAGE}%" | mail -s "Disk Space Warning" $ALERT_EMAIL
    else
        log_message "Disk usage is ${DISK_USAGE}%"
    fi
}

# Check memory usage
check_memory() {
    MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    if [ $MEMORY_USAGE -gt 90 ]; then
        log_message "WARNING: Memory usage is ${MEMORY_USAGE}%"
        echo "Memory usage is ${MEMORY_USAGE}%" | mail -s "Memory Usage Warning" $ALERT_EMAIL
    else
        log_message "Memory usage is ${MEMORY_USAGE}%"
    fi
}

# Run checks
log_message "Starting system monitoring"
check_website
check_database
check_disk_space
check_memory
log_message "Monitoring completed"
```

## Backup and Recovery

### Automated Backup Script

**backup.sh:**
```bash
#!/bin/bash

# QuickLearn Automated Backup Script
# Run daily via cron

BACKUP_DIR="/backups/quicklearn"
WP_ROOT="/var/www/html"
DB_NAME="your_database"
DB_USER="your_user"
DB_PASSWORD="your_password"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR/{files,database}

# Backup WordPress files
echo "Backing up WordPress files..."
tar -czf "$BACKUP_DIR/files/wordpress_$DATE.tar.gz" \
    --exclude="$WP_ROOT/wp-content/cache" \
    --exclude="$WP_ROOT/wp-content/uploads/cache" \
    "$WP_ROOT"

# Backup database
echo "Backing up database..."
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME | gzip > "$BACKUP_DIR/database/database_$DATE.sql.gz"

# Backup QuickLearn specific data
echo "Backing up QuickLearn data..."
tar -czf "$BACKUP_DIR/files/quicklearn_data_$DATE.tar.gz" \
    "$WP_ROOT/wp-content/plugins/quicklearn-course-manager" \
    "$WP_ROOT/wp-content/themes/quicklearn-theme" \
    "$WP_ROOT/wp-content/uploads/quicklearn" \
    "$WP_ROOT/wp-content/uploads/certificates"

# Clean up old backups
echo "Cleaning up old backups..."
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete

# Verify backup integrity
echo "Verifying backup integrity..."
if tar -tzf "$BACKUP_DIR/files/wordpress_$DATE.tar.gz" > /dev/null 2>&1; then
    echo "WordPress backup verified successfully"
else
    echo "ERROR: WordPress backup verification failed"
    exit 1
fi

if gunzip -t "$BACKUP_DIR/database/database_$DATE.sql.gz" > /dev/null 2>&1; then
    echo "Database backup verified successfully"
else
    echo "ERROR: Database backup verification failed"
    exit 1
fi

echo "Backup completed successfully: $DATE"

# Optional: Upload to cloud storage
# aws s3 sync $BACKUP_DIR s3://your-backup-bucket/quicklearn/
```

### Recovery Procedures

**Recovery Script (restore.sh):**
```bash
#!/bin/bash

# QuickLearn Recovery Script
# Usage: ./restore.sh [backup_date]

BACKUP_DATE=$1
BACKUP_DIR="/backups/quicklearn"
WP_ROOT="/var/www/html"
DB_NAME="your_database"
DB_USER="your_user"
DB_PASSWORD="your_password"

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: $0 [backup_date]"
    echo "Available backups:"
    ls -la $BACKUP_DIR/files/ | grep wordpress_
    exit 1
fi

echo "Starting recovery process for backup: $BACKUP_DATE"

# Confirm recovery
read -p "This will overwrite current installation. Continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Recovery cancelled"
    exit 1
fi

# Create current backup before restore
echo "Creating safety backup..."
SAFETY_DATE=$(date +%Y%m%d_%H%M%S)
tar -czf "$BACKUP_DIR/files/safety_backup_$SAFETY_DATE.tar.gz" "$WP_ROOT"
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME > "$BACKUP_DIR/database/safety_backup_$SAFETY_DATE.sql"

# Restore files
echo "Restoring WordPress files..."
tar -xzf "$BACKUP_DIR/files/wordpress_$BACKUP_DATE.tar.gz" -C /

# Restore database
echo "Restoring database..."
gunzip -c "$BACKUP_DIR/database/database_$BACKUP_DATE.sql.gz" | mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data $WP_ROOT
find $WP_ROOT -type d -exec chmod 755 {} \;
find $WP_ROOT -type f -exec chmod 644 {} \;

# Clear cache
echo "Clearing cache..."
rm -rf $WP_ROOT/wp-content/cache/*

echo "Recovery completed successfully!"
echo "Safety backup created: safety_backup_$SAFETY_DATE"
```

## Security Configuration

### Security Hardening Checklist

**File Security:**
- [ ] Remove default WordPress files (readme.html, license.txt)
- [ ] Disable file editing in WordPress admin
- [ ] Set proper file permissions (644 for files, 755 for directories)
- [ ] Protect wp-config.php with server-level restrictions
- [ ] Hide WordPress version information

**Access Control:**
- [ ] Strong admin passwords enforced
- [ ] Two-factor authentication enabled
- [ ] Limited login attempts
- [ ] Admin area IP restrictions (if applicable)
- [ ] Regular user access audits

**Database Security:**
- [ ] Unique database table prefix
- [ ] Database user with minimal required permissions
- [ ] Regular database backups
- [ ] Database connection encryption

### Security Monitoring

**Security Scan Script (security-scan.sh):**
```bash
#!/bin/bash

# QuickLearn Security Scan Script

WP_ROOT="/var/www/html"
SCAN_LOG="/var/log/quicklearn-security.log"

echo "$(date) - Starting security scan" >> $SCAN_LOG

# Check for suspicious files
echo "Checking for suspicious files..."
find $WP_ROOT -name "*.php" -exec grep -l "eval\|base64_decode\|gzinflate" {} \; >> $SCAN_LOG 2>&1

# Check file permissions
echo "Checking file permissions..."
find $WP_ROOT -type f -perm 777 >> $SCAN_LOG 2>&1

# Check for outdated plugins/themes
echo "Checking for updates..."
wp plugin list --update=available --path=$WP_ROOT >> $SCAN_LOG 2>&1
wp theme list --update=available --path=$WP_ROOT >> $SCAN_LOG 2>&1

# Check for failed login attempts
echo "Checking failed login attempts..."
grep "authentication failure" /var/log/auth.log | tail -10 >> $SCAN_LOG 2>&1

echo "$(date) - Security scan completed" >> $SCAN_LOG
```

### SSL/TLS Configuration

**SSL Configuration Verification:**
```bash
#!/bin/bash

# SSL Configuration Check
DOMAIN="yourdomain.com"

echo "Checking SSL configuration for $DOMAIN..."

# Check certificate validity
openssl s_client -connect $DOMAIN:443 -servername $DOMAIN < /dev/null 2>/dev/null | openssl x509 -noout -dates

# Check SSL rating
curl -s "https://api.ssllabs.com/api/v3/analyze?host=$DOMAIN" | jq '.grade'

# Check HTTPS redirect
curl -I "http://$DOMAIN" | grep -i location

echo "SSL check completed"
```

## Performance Optimization

### Caching Configuration

**Redis Configuration (redis.conf):**
```
# Redis configuration for QuickLearn
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

**Object Cache Configuration:**
```php
<?php
// wp-content/object-cache.php
// Redis object cache for QuickLearn

$redis_server = array(
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 1,
    'database' => 0,
);

// Cache groups that should not be cached
$global_groups = array(
    'users',
    'userlogins',
    'usermeta',
    'user_meta',
    'site-transient',
    'site-options',
    'site-lookup',
    'blog-lookup',
    'blog-details',
    'rss',
    'global-posts',
    'blog-id-cache'
);
```

### Database Optimization

**Database Optimization Script (optimize-db.sql):**
```sql
-- QuickLearn Database Optimization

-- Add indexes for better performance
ALTER TABLE wp_qlcm_enrollments ADD INDEX idx_user_status (user_id, status);
ALTER TABLE wp_qlcm_enrollments ADD INDEX idx_course_status (course_id, status);
ALTER TABLE wp_qlcm_enrollments ADD INDEX idx_enrollment_date (enrollment_date);

ALTER TABLE wp_qlcm_course_progress ADD INDEX idx_enrollment_progress (enrollment_id, progress_percentage);
ALTER TABLE wp_qlcm_course_progress ADD INDEX idx_completion_date (completion_date);

ALTER TABLE wp_qlcm_course_ratings ADD INDEX idx_course_rating_status (course_id, rating, status);
ALTER TABLE wp_qlcm_course_ratings ADD INDEX idx_created_date (created_date);

ALTER TABLE wp_qlcm_certificates ADD INDEX idx_issue_date (issue_date);
ALTER TABLE wp_qlcm_certificates ADD INDEX idx_user_certificates (user_id, issue_date);

-- Optimize tables
OPTIMIZE TABLE wp_qlcm_enrollments;
OPTIMIZE TABLE wp_qlcm_course_progress;
OPTIMIZE TABLE wp_qlcm_course_ratings;
OPTIMIZE TABLE wp_qlcm_certificates;

-- Update table statistics
ANALYZE TABLE wp_qlcm_enrollments;
ANALYZE TABLE wp_qlcm_course_progress;
ANALYZE TABLE wp_qlcm_course_ratings;
ANALYZE TABLE wp_qlcm_certificates;
```

## Maintenance Procedures

### Regular Maintenance Tasks

**Daily Tasks:**
- Monitor system health and performance
- Check error logs for issues
- Verify backup completion
- Review security logs

**Weekly Tasks:**
- Update WordPress core, plugins, and themes
- Clean up temporary files and cache
- Review user activity and enrollments
- Check database performance

**Monthly Tasks:**
- Full security audit
- Performance optimization review
- Backup verification and testing
- User access review and cleanup

### Maintenance Script

**maintenance.sh:**
```bash
#!/bin/bash

# QuickLearn Maintenance Script
# Run weekly via cron

WP_ROOT="/var/www/html"
LOG_FILE="/var/log/quicklearn-maintenance.log"

echo "$(date) - Starting maintenance tasks" >> $LOG_FILE

# Update WordPress core
echo "Updating WordPress core..."
wp core update --path=$WP_ROOT >> $LOG_FILE 2>&1

# Update plugins
echo "Updating plugins..."
wp plugin update --all --path=$WP_ROOT >> $LOG_FILE 2>&1

# Update themes
echo "Updating themes..."
wp theme update --all --path=$WP_ROOT >> $LOG_FILE 2>&1

# Clean up database
echo "Optimizing database..."
wp db optimize --path=$WP_ROOT >> $LOG_FILE 2>&1

# Clear cache
echo "Clearing cache..."
wp cache flush --path=$WP_ROOT >> $LOG_FILE 2>&1
rm -rf $WP_ROOT/wp-content/cache/*

# Clean up old files
echo "Cleaning up old files..."
find $WP_ROOT/wp-content/uploads -name "*.tmp" -mtime +7 -delete
find /var/log -name "*.log.*" -mtime +30 -delete

# Check file permissions
echo "Checking file permissions..."
find $WP_ROOT -type f ! -perm 644 -exec chmod 644 {} \;
find $WP_ROOT -type d ! -perm 755 -exec chmod 755 {} \;

echo "$(date) - Maintenance tasks completed" >> $LOG_FILE
```

### Update Procedures

**Update Checklist:**
1. [ ] Create full backup before updates
2. [ ] Test updates in staging environment
3. [ ] Update WordPress core
4. [ ] Update QuickLearn plugin and theme
5. [ ] Update other plugins and themes
6. [ ] Test critical functionality
7. [ ] Monitor for issues post-update
8. [ ] Document any changes or issues

This deployment guide provides comprehensive procedures for successfully deploying and maintaining the QuickLearn e-learning platform in production environments.