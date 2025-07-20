#!/bin/bash

# QuickLearn Production Monitoring Setup Script
# This script sets up comprehensive monitoring for the QuickLearn e-learning platform

set -e

# Configuration
SITE_URL="${SITE_URL:-https://yourdomain.com}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@yourdomain.com}"
WP_ROOT="${WP_ROOT:-/var/www/html}"
LOG_DIR="/var/log/quicklearn"
MONITOR_DIR="/opt/quicklearn-monitoring"
CRON_USER="${CRON_USER:-$(whoami)}"

echo "Setting up QuickLearn production monitoring..."

# Create necessary directories
echo "Creating monitoring directories..."
mkdir -p $LOG_DIR 2>/dev/null || sudo mkdir -p $LOG_DIR
mkdir -p $MONITOR_DIR 2>/dev/null || sudo mkdir -p $MONITOR_DIR
mkdir -p /var/lib/quicklearn/monitoring 2>/dev/null || sudo mkdir -p /var/lib/quicklearn/monitoring

# Set ownership (skip if not root and user doesn't exist)
if id "$CRON_USER" >/dev/null 2>&1; then
    chown -R $CRON_USER:$(id -gn $CRON_USER) $LOG_DIR 2>/dev/null || sudo chown -R $CRON_USER:$(id -gn $CRON_USER) $LOG_DIR 2>/dev/null || true
    chown -R $CRON_USER:$(id -gn $CRON_USER) /var/lib/quicklearn 2>/dev/null || sudo chown -R $CRON_USER:$(id -gn $CRON_USER) /var/lib/quicklearn 2>/dev/null || true
fi

# Create health check endpoint
echo "Creating health check endpoint..."
cat > $WP_ROOT/quicklearn-health.php << 'EOF'
<?php
/**
 * QuickLearn Health Check Endpoint
 * Access via: /quicklearn-health.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$health = array(
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => get_option('quicklearn_db_version', 'unknown'),
    'checks' => array()
);

// Load WordPress
require_once __DIR__ . '/wp-load.php';

try {
    // Check database connection
    global $wpdb;
    $wpdb->get_var("SELECT 1");
    $health['checks']['database'] = array(
        'status' => 'ok',
        'response_time' => 0 // Could measure actual response time
    );
} catch (Exception $e) {
    $health['checks']['database'] = array(
        'status' => 'error',
        'message' => $e->getMessage()
    );
    $health['status'] = 'unhealthy';
}

// Check plugin activation
if (is_plugin_active('quicklearn-course-manager/quicklearn-course-manager.php')) {
    $health['checks']['plugin'] = array('status' => 'ok');
} else {
    $health['checks']['plugin'] = array(
        'status' => 'error',
        'message' => 'QuickLearn plugin not active'
    );
    $health['status'] = 'unhealthy';
}

// Check file permissions
$upload_dir = wp_upload_dir();
if (is_writable($upload_dir['basedir'])) {
    $health['checks']['file_permissions'] = array('status' => 'ok');
} else {
    $health['checks']['file_permissions'] = array(
        'status' => 'error',
        'message' => 'Upload directory not writable'
    );
    $health['status'] = 'unhealthy';
}

// Check memory usage
$memory_limit = ini_get('memory_limit');
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);

$health['checks']['memory'] = array(
    'status' => 'ok',
    'limit' => $memory_limit,
    'usage' => round($memory_usage / 1024 / 1024, 2) . 'MB',
    'peak' => round($memory_peak / 1024 / 1024, 2) . 'MB'
);

// Check disk space
$disk_free = disk_free_space(__DIR__);
$disk_total = disk_total_space(__DIR__);
$disk_usage_percent = (($disk_total - $disk_free) / $disk_total) * 100;

$health['checks']['disk_space'] = array(
    'status' => $disk_usage_percent > 90 ? 'warning' : 'ok',
    'usage_percent' => round($disk_usage_percent, 2),
    'free_space' => round($disk_free / 1024 / 1024 / 1024, 2) . 'GB'
);

if ($disk_usage_percent > 95) {
    $health['status'] = 'unhealthy';
}

// Check QuickLearn specific functionality
try {
    // Test course query
    $courses = get_posts(array(
        'post_type' => 'quick_course',
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ));
    
    $health['checks']['course_system'] = array(
        'status' => 'ok',
        'course_count' => count($courses)
    );
} catch (Exception $e) {
    $health['checks']['course_system'] = array(
        'status' => 'error',
        'message' => $e->getMessage()
    );
    $health['status'] = 'unhealthy';
}

// Set appropriate HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);
?>
EOF

# Create system monitoring script
echo "Creating system monitoring script..."
cat > $MONITOR_DIR/system-monitor.sh << 'EOF'
#!/bin/bash

# QuickLearn System Monitoring Script
# Run every 5 minutes via cron

LOG_FILE="/var/log/quicklearn/system-monitor.log"
ALERT_EMAIL="admin@yourdomain.com"
SITE_URL="https://yourdomain.com"
ALERT_FILE="/var/lib/quicklearn/monitoring/alerts.json"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

# Function to send alert
send_alert() {
    local alert_type="$1"
    local message="$2"
    local severity="${3:-warning}"
    
    # Log alert
    log_message "ALERT [$severity] $alert_type: $message"
    
    # Create alert record
    local alert_json="{\"timestamp\":\"$(date -Iseconds)\",\"type\":\"$alert_type\",\"message\":\"$message\",\"severity\":\"$severity\"}"
    echo "$alert_json" >> $ALERT_FILE
    
    # Send email for critical alerts
    if [ "$severity" = "critical" ]; then
        echo "QuickLearn Alert: $message" | mail -s "[$severity] QuickLearn Alert: $alert_type" $ALERT_EMAIL
    fi
}

# Check website availability
check_website() {
    local start_time=$(date +%s.%N)
    if curl -f -s -m 10 "$SITE_URL" > /dev/null; then
        local end_time=$(date +%s.%N)
        local response_time=$(echo "$end_time - $start_time" | bc)
        log_message "Website accessible (${response_time}s)"
        
        # Alert if response time is too slow
        if (( $(echo "$response_time > 5.0" | bc -l) )); then
            send_alert "slow_response" "Website response time: ${response_time}s" "warning"
        fi
    else
        send_alert "website_down" "Website is not accessible" "critical"
        return 1
    fi
}

# Check health endpoint
check_health_endpoint() {
    local health_response=$(curl -s -m 10 "$SITE_URL/quicklearn-health.php")
    if [ $? -eq 0 ]; then
        local status=$(echo "$health_response" | jq -r '.status' 2>/dev/null)
        if [ "$status" = "healthy" ]; then
            log_message "Health check passed"
        else
            send_alert "health_check_failed" "Health endpoint reports: $status" "warning"
        fi
    else
        send_alert "health_endpoint_down" "Health endpoint not accessible" "critical"
    fi
}

# Check database connectivity
check_database() {
    if mysql -u $DB_USER -p$DB_PASSWORD -e "SELECT 1" $DB_NAME > /dev/null 2>&1; then
        log_message "Database accessible"
    else
        send_alert "database_down" "Database connection failed" "critical"
        return 1
    fi
}

# Check disk space
check_disk_space() {
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    log_message "Disk usage: ${disk_usage}%"
    
    if [ $disk_usage -gt 95 ]; then
        send_alert "disk_space_critical" "Disk usage is ${disk_usage}%" "critical"
    elif [ $disk_usage -gt 85 ]; then
        send_alert "disk_space_warning" "Disk usage is ${disk_usage}%" "warning"
    fi
}

# Check memory usage
check_memory() {
    local memory_usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    log_message "Memory usage: ${memory_usage}%"
    
    if [ $memory_usage -gt 95 ]; then
        send_alert "memory_critical" "Memory usage is ${memory_usage}%" "critical"
    elif [ $memory_usage -gt 85 ]; then
        send_alert "memory_warning" "Memory usage is ${memory_usage}%" "warning"
    fi
}

# Check CPU usage
check_cpu() {
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    log_message "CPU usage: ${cpu_usage}%"
    
    if (( $(echo "$cpu_usage > 90" | bc -l) )); then
        send_alert "cpu_critical" "CPU usage is ${cpu_usage}%" "critical"
    elif (( $(echo "$cpu_usage > 75" | bc -l) )); then
        send_alert "cpu_warning" "CPU usage is ${cpu_usage}%" "warning"
    fi
}

# Check log file sizes
check_log_sizes() {
    local max_size_mb=100
    
    for log_file in /var/log/quicklearn/*.log; do
        if [ -f "$log_file" ]; then
            local size_mb=$(du -m "$log_file" | cut -f1)
            if [ $size_mb -gt $max_size_mb ]; then
                send_alert "large_log_file" "Log file $log_file is ${size_mb}MB" "warning"
            fi
        fi
    done
}

# Check SSL certificate expiration
check_ssl_certificate() {
    local domain=$(echo $SITE_URL | sed 's|https\?://||' | sed 's|/.*||')
    local expiry_date=$(echo | openssl s_client -servername $domain -connect $domain:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
    
    if [ -n "$expiry_date" ]; then
        local expiry_timestamp=$(date -d "$expiry_date" +%s)
        local current_timestamp=$(date +%s)
        local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
        
        log_message "SSL certificate expires in $days_until_expiry days"
        
        if [ $days_until_expiry -lt 7 ]; then
            send_alert "ssl_expiring_soon" "SSL certificate expires in $days_until_expiry days" "critical"
        elif [ $days_until_expiry -lt 30 ]; then
            send_alert "ssl_expiring" "SSL certificate expires in $days_until_expiry days" "warning"
        fi
    fi
}

# Main monitoring execution
log_message "Starting system monitoring"

check_website
check_health_endpoint
check_database
check_disk_space
check_memory
check_cpu
check_log_sizes
check_ssl_certificate

log_message "System monitoring completed"

# Rotate alert file if it gets too large
if [ -f "$ALERT_FILE" ] && [ $(wc -l < "$ALERT_FILE") -gt 1000 ]; then
    tail -500 "$ALERT_FILE" > "${ALERT_FILE}.tmp"
    mv "${ALERT_FILE}.tmp" "$ALERT_FILE"
fi
EOF

chmod +x $MONITOR_DIR/system-monitor.sh

# Create application monitoring script
echo "Creating application monitoring script..."
cat > $MONITOR_DIR/app-monitor.sh << 'EOF'
#!/bin/bash

# QuickLearn Application Monitoring Script
# Run every 15 minutes via cron

LOG_FILE="/var/log/quicklearn/app-monitor.log"
ALERT_EMAIL="admin@yourdomain.com"
SITE_URL="https://yourdomain.com"
WP_ROOT="/var/www/html"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

# Function to send alert
send_alert() {
    local message="$1"
    local severity="${2:-warning}"
    
    log_message "ALERT [$severity] $message"
    
    if [ "$severity" = "critical" ]; then
        echo "QuickLearn Application Alert: $message" | mail -s "[$severity] QuickLearn App Alert" $ALERT_EMAIL
    fi
}

# Check course functionality
check_course_functionality() {
    local courses_response=$(curl -s -m 10 "$SITE_URL/courses/")
    if [ $? -eq 0 ]; then
        if echo "$courses_response" | grep -q "course"; then
            log_message "Course pages accessible"
        else
            send_alert "Course pages may not be displaying correctly" "warning"
        fi
    else
        send_alert "Course pages not accessible" "critical"
    fi
}

# Check AJAX endpoints
check_ajax_endpoints() {
    local ajax_response=$(curl -s -m 10 -X POST "$SITE_URL/wp-admin/admin-ajax.php" -d "action=heartbeat")
    if [ $? -eq 0 ]; then
        log_message "AJAX endpoints accessible"
    else
        send_alert "AJAX endpoints not responding" "critical"
    fi
}

# Check database table integrity
check_database_integrity() {
    local table_check=$(mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "
        SELECT 
            CASE 
                WHEN COUNT(*) = 4 THEN 'OK' 
                ELSE 'MISSING_TABLES' 
            END as status
        FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' 
        AND table_name IN (
            '${DB_PREFIX}qlcm_enrollments',
            '${DB_PREFIX}qlcm_course_progress', 
            '${DB_PREFIX}qlcm_course_ratings',
            '${DB_PREFIX}qlcm_certificates'
        )
    " -s -N)
    
    if [ "$table_check" = "OK" ]; then
        log_message "Database tables intact"
    else
        send_alert "Database tables missing or corrupted" "critical"
    fi
}

# Check file permissions
check_file_permissions() {
    local upload_dir=$(wp eval 'echo wp_upload_dir()["basedir"];' --path=$WP_ROOT 2>/dev/null)
    
    if [ -d "$upload_dir" ] && [ -w "$upload_dir" ]; then
        log_message "Upload directory writable"
    else
        send_alert "Upload directory not writable" "warning"
    fi
    
    # Check plugin directory
    local plugin_dir="$WP_ROOT/wp-content/plugins/quicklearn-course-manager"
    if [ -d "$plugin_dir" ]; then
        log_message "Plugin directory exists"
    else
        send_alert "Plugin directory missing" "critical"
    fi
}

# Check error logs for recent issues
check_error_logs() {
    local error_count=$(grep -c "$(date '+%Y-%m-%d')" /var/log/quicklearn/*.log 2>/dev/null | awk -F: '{sum+=$2} END {print sum+0}')
    
    log_message "Today's error count: $error_count"
    
    if [ $error_count -gt 50 ]; then
        send_alert "High error count today: $error_count errors" "warning"
    fi
    
    # Check for critical errors in the last hour
    local critical_errors=$(grep -c "$(date -d '1 hour ago' '+%Y-%m-%d %H')" /var/log/quicklearn/*.log 2>/dev/null | grep -E "(CRITICAL|FATAL)" | wc -l)
    
    if [ $critical_errors -gt 0 ]; then
        send_alert "Critical errors detected in the last hour: $critical_errors" "critical"
    fi
}

# Check plugin activation status
check_plugin_status() {
    local plugin_status=$(wp plugin status quicklearn-course-manager --path=$WP_ROOT 2>/dev/null | grep "Status:")
    
    if echo "$plugin_status" | grep -q "Active"; then
        log_message "QuickLearn plugin is active"
    else
        send_alert "QuickLearn plugin is not active" "critical"
    fi
}

# Check enrollment system
check_enrollment_system() {
    # Check if enrollment table has recent activity
    local recent_enrollments=$(mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "
        SELECT COUNT(*) 
        FROM ${DB_PREFIX}qlcm_enrollments 
        WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    " -s -N 2>/dev/null)
    
    if [ $? -eq 0 ]; then
        log_message "Enrollment system accessible, $recent_enrollments new enrollments in 24h"
    else
        send_alert "Cannot access enrollment data" "warning"
    fi
}

# Main application monitoring execution
log_message "Starting application monitoring"

check_course_functionality
check_ajax_endpoints
check_database_integrity
check_file_permissions
check_error_logs
check_plugin_status
check_enrollment_system

log_message "Application monitoring completed"
EOF

chmod +x $MONITOR_DIR/app-monitor.sh

# Create log rotation configuration
echo "Setting up log rotation..."
cat > /etc/logrotate.d/quicklearn << 'EOF'
/var/log/quicklearn/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload rsyslog > /dev/null 2>&1 || true
    endscript
}
EOF

# Create monitoring dashboard script
echo "Creating monitoring dashboard..."
cat > $MONITOR_DIR/dashboard.php << 'EOF'
<?php
/**
 * QuickLearn Monitoring Dashboard
 * Simple web interface to view monitoring status
 */

// Basic authentication
$valid_users = array('admin' => 'change_this_password');
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if (!isset($valid_users[$user]) || $valid_users[$user] !== $pass) {
    header('WWW-Authenticate: Basic realm="QuickLearn Monitoring"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access denied';
    exit;
}

// Get monitoring data
$health_data = @file_get_contents('https://yourdomain.com/quicklearn-health.php');
$health = json_decode($health_data, true);

$alerts_file = '/var/lib/quicklearn/monitoring/alerts.json';
$recent_alerts = array();
if (file_exists($alerts_file)) {
    $alerts_content = file_get_contents($alerts_file);
    $alerts_lines = array_filter(explode("\n", $alerts_content));
    $recent_alerts = array_slice(array_reverse($alerts_lines), 0, 20);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>QuickLearn Monitoring Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .alert { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .alert-critical { background: #f8d7da; border-left: 4px solid #dc3545; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .refresh-btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
    <script>
        function refreshPage() {
            location.reload();
        }
        // Auto-refresh every 30 seconds
        setTimeout(refreshPage, 30000);
    </script>
</head>
<body>
    <div class="container">
        <h1>QuickLearn Monitoring Dashboard</h1>
        <button class="refresh-btn" onclick="refreshPage()">Refresh</button>
        
        <div class="grid">
            <div class="card">
                <h2>System Health</h2>
                <?php if ($health): ?>
                    <p>Status: <span class="status-<?php echo $health['status'] === 'healthy' ? 'ok' : 'error'; ?>">
                        <?php echo strtoupper($health['status']); ?>
                    </span></p>
                    <p>Last Check: <?php echo date('Y-m-d H:i:s', strtotime($health['timestamp'])); ?></p>
                    <p>Version: <?php echo $health['version']; ?></p>
                    
                    <h3>Component Status</h3>
                    <?php foreach ($health['checks'] as $component => $check): ?>
                        <p><?php echo ucwords(str_replace('_', ' ', $component)); ?>: 
                        <span class="status-<?php echo $check['status'] === 'ok' ? 'ok' : 'error'; ?>">
                            <?php echo strtoupper($check['status']); ?>
                        </span>
                        <?php if (isset($check['message'])): ?>
                            - <?php echo htmlspecialchars($check['message']); ?>
                        <?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="status-error">Health check unavailable</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Recent Alerts</h2>
                <?php if (!empty($recent_alerts)): ?>
                    <?php foreach ($recent_alerts as $alert_line): ?>
                        <?php $alert = json_decode($alert_line, true); ?>
                        <?php if ($alert): ?>
                            <div class="alert alert-<?php echo $alert['severity']; ?>">
                                <strong><?php echo $alert['type']; ?></strong><br>
                                <?php echo htmlspecialchars($alert['message']); ?><br>
                                <small><?php echo date('Y-m-d H:i:s', strtotime($alert['timestamp'])); ?></small>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent alerts</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <h2>System Information</h2>
            <pre><?php
                echo "Server Time: " . date('Y-m-d H:i:s') . "\n";
                echo "PHP Version: " . PHP_VERSION . "\n";
                echo "Server Load: " . sys_getloadavg()[0] . "\n";
                echo "Memory Usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
                echo "Disk Free: " . round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . " GB\n";
            ?></pre>
        </div>
    </div>
</body>
</html>
EOF

# Set up cron jobs
echo "Setting up cron jobs..."
(crontab -u $CRON_USER -l 2>/dev/null; cat << EOF
# QuickLearn Monitoring Cron Jobs
*/5 * * * * $MONITOR_DIR/system-monitor.sh >/dev/null 2>&1
*/15 * * * * $MONITOR_DIR/app-monitor.sh >/dev/null 2>&1
0 2 * * * find /var/log/quicklearn -name "*.log" -mtime +30 -delete >/dev/null 2>&1
EOF
) | crontab -u $CRON_USER -

# Create systemd service for monitoring (optional)
echo "Creating systemd monitoring service..."
cat > /etc/systemd/system/quicklearn-monitor.service << 'EOF'
[Unit]
Description=QuickLearn Monitoring Service
After=network.target

[Service]
Type=simple
User=www-data
ExecStart=/opt/quicklearn-monitoring/system-monitor.sh
Restart=always
RestartSec=300

[Install]
WantedBy=multi-user.target
EOF

# Enable and start the service
systemctl daemon-reload
systemctl enable quicklearn-monitor.service

# Create monitoring configuration file
echo "Creating monitoring configuration..."
cat > $MONITOR_DIR/config.conf << EOF
# QuickLearn Monitoring Configuration

# Site Configuration
SITE_URL="$SITE_URL"
WP_ROOT="$WP_ROOT"

# Database Configuration
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wp_user}"
DB_PREFIX="${DB_PREFIX:-wp_}"

# Alert Configuration
ALERT_EMAIL="$ADMIN_EMAIL"
ALERT_THRESHOLD_DISK=85
ALERT_THRESHOLD_MEMORY=85
ALERT_THRESHOLD_CPU=75

# Monitoring Intervals (minutes)
SYSTEM_CHECK_INTERVAL=5
APP_CHECK_INTERVAL=15
LOG_CLEANUP_INTERVAL=1440

# Log Configuration
LOG_RETENTION_DAYS=30
MAX_LOG_SIZE_MB=100
EOF

# Set proper permissions
chmod +x $MONITOR_DIR/*.sh
chmod 644 $MONITOR_DIR/config.conf
chmod 644 $MONITOR_DIR/dashboard.php

echo "Monitoring setup completed!"
echo ""
echo "Monitoring components installed:"
echo "- Health check endpoint: $SITE_URL/quicklearn-health.php"
echo "- System monitoring: $MONITOR_DIR/system-monitor.sh (runs every 5 minutes)"
echo "- Application monitoring: $MONITOR_DIR/app-monitor.sh (runs every 15 minutes)"
echo "- Monitoring dashboard: $MONITOR_DIR/dashboard.php"
echo "- Log files: $LOG_DIR/"
echo ""
echo "Next steps:"
echo "1. Update the monitoring configuration in $MONITOR_DIR/config.conf"
echo "2. Change the dashboard password in $MONITOR_DIR/dashboard.php"
echo "3. Configure email settings for alerts"
echo "4. Test the monitoring by accessing the health check endpoint"
echo "5. Set up external monitoring tools to check the health endpoint"
echo ""
echo "To view monitoring status:"
echo "curl $SITE_URL/quicklearn-health.php"
echo ""
echo "To check recent alerts:"
echo "tail -f /var/lib/quicklearn/monitoring/alerts.json"
EOF

chmod +x deployment/monitoring-setup.sh