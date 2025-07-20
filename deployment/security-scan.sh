#!/bin/bash

# QuickLearn Security Scanning and Vulnerability Assessment Script
# Comprehensive security analysis for the QuickLearn e-learning platform

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_ROOT="${WP_ROOT:-/var/www/html}"
SITE_URL="${SITE_URL:-https://yourdomain.com}"
SCAN_DIR="/tmp/quicklearn-security-scan"
REPORT_DIR="/var/log/quicklearn/security"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="$REPORT_DIR/security_scan_$TIMESTAMP.txt"

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local color=""
    
    case "$level" in
        "ERROR") color="$RED" ;;
        "WARNING") color="$YELLOW" ;;
        "SUCCESS") color="$GREEN" ;;
        "INFO") color="$BLUE" ;;
    esac
    
    echo -e "${color}[$timestamp] [$level] $message${NC}" | tee -a "$REPORT_FILE"
}

# Initialize scan environment
initialize_scan() {
    log_message "INFO" "Initializing QuickLearn security scan..."
    
    # Create necessary directories
    mkdir -p "$SCAN_DIR"
    mkdir -p "$REPORT_DIR"
    
    # Create report header
    cat > "$REPORT_FILE" << EOF
QuickLearn Security Scan Report
===============================
Scan Date: $(date)
WordPress Root: $WP_ROOT
Site URL: $SITE_URL
Scan ID: $TIMESTAMP

EOF
    
    log_message "INFO" "Scan initialized. Report: $REPORT_FILE"
}

# WordPress Core Security Checks
check_wordpress_core() {
    log_message "INFO" "Checking WordPress core security..."
    
    local issues=0
    
    # Check WordPress version
    if [ -f "$WP_ROOT/wp-includes/version.php" ]; then
        local wp_version=$(grep "wp_version = " "$WP_ROOT/wp-includes/version.php" | cut -d"'" -f2)
        log_message "INFO" "WordPress version: $wp_version"
        
        # Check if version is outdated (simplified check)
        local latest_version=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | jq -r '.offers[0].version' 2>/dev/null || echo "unknown")
        if [ "$latest_version" != "unknown" ] && [ "$wp_version" != "$latest_version" ]; then
            log_message "WARNING" "WordPress version $wp_version is outdated. Latest: $latest_version"
            ((issues++))
        fi
    else
        log_message "ERROR" "Cannot determine WordPress version"
        ((issues++))
    fi
    
    # Check for debug mode
    if grep -q "define.*WP_DEBUG.*true" "$WP_ROOT/wp-config.php" 2>/dev/null; then
        log_message "WARNING" "Debug mode is enabled in production"
        ((issues++))
    fi
    
    # Check for file editing
    if ! grep -q "define.*DISALLOW_FILE_EDIT.*true" "$WP_ROOT/wp-config.php" 2>/dev/null; then
        log_message "WARNING" "File editing is not disabled in wp-config.php"
        ((issues++))
    fi
    
    # Check for default files
    local default_files=("readme.html" "license.txt" "wp-config-sample.php")
    for file in "${default_files[@]}"; do
        if [ -f "$WP_ROOT/$file" ]; then
            log_message "WARNING" "Default file found: $file"
            ((issues++))
        fi
    done
    
    # Check wp-config.php permissions
    if [ -f "$WP_ROOT/wp-config.php" ]; then
        local wp_config_perms=$(stat -c "%a" "$WP_ROOT/wp-config.php")
        if [ "$wp_config_perms" != "644" ] && [ "$wp_config_perms" != "600" ]; then
            log_message "WARNING" "wp-config.php has insecure permissions: $wp_config_perms"
            ((issues++))
        fi
    fi
    
    log_message "INFO" "WordPress core security check completed. Issues found: $issues"
    return $issues
}

# Plugin and Theme Security Checks
check_plugins_themes() {
    log_message "INFO" "Checking plugins and themes security..."
    
    local issues=0
    
    # Check for vulnerable plugins (simplified check)
    local plugin_dirs=("$WP_ROOT/wp-content/plugins"/* "$WP_ROOT/wp-content/themes"/*)
    
    for dir in "${plugin_dirs[@]}"; do
        if [ -d "$dir" ]; then
            local plugin_name=$(basename "$dir")
            
            # Check for common vulnerable files
            local vulnerable_files=("shell.php" "c99.php" "r57.php" "webshell.php")
            for vuln_file in "${vulnerable_files[@]}"; do
                if find "$dir" -name "$vuln_file" -type f 2>/dev/null | grep -q .; then
                    log_message "ERROR" "Potential malicious file found: $dir/$vuln_file"
                    ((issues++))
                fi
            done
            
            # Check for suspicious PHP code
            find "$dir" -name "*.php" -exec grep -l "eval\|base64_decode\|gzinflate\|str_rot13" {} \; 2>/dev/null | head -5 | while read -r file; do
                log_message "WARNING" "Suspicious PHP code found in: $file"
                ((issues++))
            done
        fi
    done
    
    # Check QuickLearn plugin specifically
    local quicklearn_plugin="$WP_ROOT/wp-content/plugins/quicklearn-course-manager"
    if [ -d "$quicklearn_plugin" ]; then
        log_message "INFO" "Checking QuickLearn plugin security..."
        
        # Check file permissions
        find "$quicklearn_plugin" -type f -perm 777 2>/dev/null | while read -r file; do
            log_message "WARNING" "File with 777 permissions: $file"
            ((issues++))
        done
        
        # Check for proper input sanitization
        local php_files=$(find "$quicklearn_plugin" -name "*.php" -type f)
        local sanitization_functions=("sanitize_text_field\|esc_html\|esc_url\|wp_verify_nonce")
        
        for php_file in $php_files; do
            if grep -q "\$_POST\|\$_GET\|\$_REQUEST" "$php_file" 2>/dev/null; then
                if ! grep -q "$sanitization_functions" "$php_file" 2>/dev/null; then
                    log_message "WARNING" "Potential unsanitized input in: $php_file"
                    ((issues++))
                fi
            fi
        done
    else
        log_message "WARNING" "QuickLearn plugin directory not found"
        ((issues++))
    fi
    
    log_message "INFO" "Plugins and themes security check completed. Issues found: $issues"
    return $issues
}

# File System Security Checks
check_file_system() {
    log_message "INFO" "Checking file system security..."
    
    local issues=0
    
    # Check file permissions
    log_message "INFO" "Checking file permissions..."
    
    # Check for world-writable files
    find "$WP_ROOT" -type f -perm 777 2>/dev/null | head -10 | while read -r file; do
        log_message "WARNING" "World-writable file: $file"
        ((issues++))
    done
    
    # Check for SUID/SGID files
    find "$WP_ROOT" -type f \( -perm -4000 -o -perm -2000 \) 2>/dev/null | while read -r file; do
        log_message "WARNING" "SUID/SGID file found: $file"
        ((issues++))
    done
    
    # Check uploads directory
    local uploads_dir="$WP_ROOT/wp-content/uploads"
    if [ -d "$uploads_dir" ]; then
        # Check for PHP files in uploads
        find "$uploads_dir" -name "*.php" -type f 2>/dev/null | while read -r file; do
            log_message "ERROR" "PHP file in uploads directory: $file"
            ((issues++))
        done
        
        # Check for .htaccess in uploads
        if [ ! -f "$uploads_dir/.htaccess" ]; then
            log_message "WARNING" "No .htaccess file in uploads directory"
            ((issues++))
        fi
    fi
    
    # Check for backup files
    local backup_patterns=("*.bak" "*.backup" "*.old" "*.orig" "*.save" "*~")
    for pattern in "${backup_patterns[@]}"; do
        find "$WP_ROOT" -name "$pattern" -type f 2>/dev/null | head -5 | while read -r file; do
            log_message "WARNING" "Backup file found: $file"
            ((issues++))
        done
    done
    
    log_message "INFO" "File system security check completed. Issues found: $issues"
    return $issues
}

# Database Security Checks
check_database_security() {
    log_message "INFO" "Checking database security..."
    
    local issues=0
    
    # Check database connection
    if ! mysql --version >/dev/null 2>&1; then
        log_message "WARNING" "MySQL client not available for database checks"
        return 1
    fi
    
    # Load WordPress configuration
    if [ -f "$WP_ROOT/wp-config.php" ]; then
        local db_name=$(grep "define.*DB_NAME" "$WP_ROOT/wp-config.php" | cut -d"'" -f4)
        local db_user=$(grep "define.*DB_USER" "$WP_ROOT/wp-config.php" | cut -d"'" -f4)
        local db_password=$(grep "define.*DB_PASSWORD" "$WP_ROOT/wp-config.php" | cut -d"'" -f4)
        local db_host=$(grep "define.*DB_HOST" "$WP_ROOT/wp-config.php" | cut -d"'" -f4)
        local table_prefix=$(grep "table_prefix" "$WP_ROOT/wp-config.php" | cut -d"'" -f2)
        
        # Test database connection
        if mysql -h "$db_host" -u "$db_user" -p"$db_password" -e "USE $db_name;" 2>/dev/null; then
            log_message "SUCCESS" "Database connection successful"
            
            # Check for default table prefix
            if [ "$table_prefix" = "wp_" ]; then
                log_message "WARNING" "Using default table prefix 'wp_'"
                ((issues++))
            fi
            
            # Check for admin user with ID 1
            local admin_check=$(mysql -h "$db_host" -u "$db_user" -p"$db_password" "$db_name" -e "SELECT user_login FROM ${table_prefix}users WHERE ID = 1;" -s -N 2>/dev/null)
            if [ "$admin_check" = "admin" ]; then
                log_message "WARNING" "Default admin username 'admin' found"
                ((issues++))
            fi
            
            # Check for users with weak passwords (simplified check)
            local weak_users=$(mysql -h "$db_host" -u "$db_user" -p"$db_password" "$db_name" -e "SELECT user_login FROM ${table_prefix}users WHERE user_pass LIKE '%5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8%';" -s -N 2>/dev/null)
            if [ -n "$weak_users" ]; then
                log_message "WARNING" "Users with weak passwords found: $weak_users"
                ((issues++))
            fi
            
            # Check QuickLearn tables
            local quicklearn_tables=$(mysql -h "$db_host" -u "$db_user" -p"$db_password" "$db_name" -e "SHOW TABLES LIKE '${table_prefix}qlcm_%';" -s -N 2>/dev/null | wc -l)
            if [ "$quicklearn_tables" -eq 0 ]; then
                log_message "WARNING" "No QuickLearn database tables found"
                ((issues++))
            else
                log_message "SUCCESS" "Found $quicklearn_tables QuickLearn database tables"
            fi
            
        else
            log_message "ERROR" "Cannot connect to database"
            ((issues++))
        fi
    else
        log_message "ERROR" "wp-config.php not found"
        ((issues++))
    fi
    
    log_message "INFO" "Database security check completed. Issues found: $issues"
    return $issues
}

# Network Security Checks
check_network_security() {
    log_message "INFO" "Checking network security..."
    
    local issues=0
    
    # Check SSL/TLS configuration
    if command -v openssl >/dev/null 2>&1; then
        local domain=$(echo "$SITE_URL" | sed 's|https\?://||' | sed 's|/.*||')
        
        log_message "INFO" "Checking SSL certificate for $domain..."
        
        # Check certificate validity
        local cert_info=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null)
        
        if [ -n "$cert_info" ]; then
            local not_after=$(echo "$cert_info" | grep "notAfter" | cut -d= -f2)
            local expiry_timestamp=$(date -d "$not_after" +%s 2>/dev/null)
            local current_timestamp=$(date +%s)
            
            if [ -n "$expiry_timestamp" ]; then
                local days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
                
                if [ $days_until_expiry -lt 30 ]; then
                    log_message "WARNING" "SSL certificate expires in $days_until_expiry days"
                    ((issues++))
                else
                    log_message "SUCCESS" "SSL certificate valid for $days_until_expiry days"
                fi
            fi
        else
            log_message "ERROR" "Cannot retrieve SSL certificate information"
            ((issues++))
        fi
        
        # Check SSL configuration
        local ssl_test=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | grep "Cipher")
        if echo "$ssl_test" | grep -q "RC4\|DES\|MD5"; then
            log_message "WARNING" "Weak SSL cipher detected"
            ((issues++))
        fi
    fi
    
    # Check HTTP security headers
    if command -v curl >/dev/null 2>&1; then
        log_message "INFO" "Checking HTTP security headers..."
        
        local headers=$(curl -s -I "$SITE_URL" 2>/dev/null)
        
        local security_headers=(
            "X-Content-Type-Options"
            "X-Frame-Options"
            "X-XSS-Protection"
            "Strict-Transport-Security"
            "Content-Security-Policy"
        )
        
        for header in "${security_headers[@]}"; do
            if ! echo "$headers" | grep -qi "$header"; then
                log_message "WARNING" "Missing security header: $header"
                ((issues++))
            fi
        done
        
        # Check for information disclosure
        if echo "$headers" | grep -qi "server:.*apache\|server:.*nginx"; then
            log_message "WARNING" "Server information disclosed in headers"
            ((issues++))
        fi
        
        if echo "$headers" | grep -qi "x-powered-by"; then
            log_message "WARNING" "X-Powered-By header reveals technology stack"
            ((issues++))
        fi
    fi
    
    log_message "INFO" "Network security check completed. Issues found: $issues"
    return $issues
}

# Malware and Suspicious Code Detection
check_malware() {
    log_message "INFO" "Scanning for malware and suspicious code..."
    
    local issues=0
    
    # Common malware signatures
    local malware_patterns=(
        "eval.*base64_decode"
        "gzinflate.*base64_decode"
        "str_rot13.*eval"
        "preg_replace.*\/e"
        "assert.*\$_"
        "create_function.*\$_"
        "file_get_contents.*php:\/\/input"
        "system.*\$_"
        "exec.*\$_"
        "shell_exec.*\$_"
        "passthru.*\$_"
    )
    
    # Scan PHP files
    find "$WP_ROOT" -name "*.php" -type f | while read -r php_file; do
        for pattern in "${malware_patterns[@]}"; do
            if grep -q "$pattern" "$php_file" 2>/dev/null; then
                log_message "ERROR" "Suspicious code pattern found in $php_file: $pattern"
                ((issues++))
            fi
        done
    done
    
    # Check for common malware file names
    local malware_files=(
        "c99.php"
        "r57.php"
        "shell.php"
        "webshell.php"
        "backdoor.php"
        "hack.php"
        "wso.php"
        "adminer.php"
    )
    
    for malware_file in "${malware_files[@]}"; do
        find "$WP_ROOT" -name "$malware_file" -type f 2>/dev/null | while read -r file; do
            log_message "ERROR" "Potential malware file found: $file"
            ((issues++))
        done
    done
    
    # Check for suspicious JavaScript
    find "$WP_ROOT" -name "*.js" -type f -exec grep -l "eval\|unescape\|fromCharCode" {} \; 2>/dev/null | head -5 | while read -r js_file; do
        log_message "WARNING" "Suspicious JavaScript found in: $js_file"
        ((issues++))
    done
    
    # Check for hidden files
    find "$WP_ROOT" -name ".*" -type f ! -name ".htaccess" ! -name ".well-known" 2>/dev/null | head -10 | while read -r hidden_file; do
        log_message "WARNING" "Hidden file found: $hidden_file"
        ((issues++))
    done
    
    log_message "INFO" "Malware scan completed. Issues found: $issues"
    return $issues
}

# QuickLearn Specific Security Checks
check_quicklearn_security() {
    log_message "INFO" "Checking QuickLearn specific security..."
    
    local issues=0
    
    # Check plugin files
    local plugin_dir="$WP_ROOT/wp-content/plugins/quicklearn-course-manager"
    if [ -d "$plugin_dir" ]; then
        # Check for proper nonce usage
        local php_files=$(find "$plugin_dir" -name "*.php" -type f)
        
        for php_file in $php_files; do
            # Check AJAX handlers have nonce verification
            if grep -q "wp_ajax_" "$php_file" 2>/dev/null; then
                if ! grep -q "wp_verify_nonce\|check_ajax_referer" "$php_file" 2>/dev/null; then
                    log_message "WARNING" "AJAX handler without nonce verification: $php_file"
                    ((issues++))
                fi
            fi
            
            # Check for SQL injection prevention
            if grep -q "\$wpdb->prepare\|\$wpdb->get_" "$php_file" 2>/dev/null; then
                if grep -q "\$wpdb->query.*\$_\|\$wpdb->get_.*\$_" "$php_file" 2>/dev/null; then
                    log_message "WARNING" "Potential SQL injection vulnerability: $php_file"
                    ((issues++))
                fi
            fi
            
            # Check for XSS prevention
            if grep -q "echo.*\$_\|print.*\$_" "$php_file" 2>/dev/null; then
                if ! grep -q "esc_html\|esc_attr\|esc_url" "$php_file" 2>/dev/null; then
                    log_message "WARNING" "Potential XSS vulnerability: $php_file"
                    ((issues++))
                fi
            fi
        done
        
        # Check upload directories
        local upload_dirs=(
            "$WP_ROOT/wp-content/uploads/quicklearn"
            "$WP_ROOT/wp-content/uploads/certificates"
        )
        
        for upload_dir in "${upload_dirs[@]}"; do
            if [ -d "$upload_dir" ]; then
                # Check for .htaccess protection
                if [ ! -f "$upload_dir/.htaccess" ]; then
                    log_message "WARNING" "No .htaccess protection in: $upload_dir"
                    ((issues++))
                fi
                
                # Check for PHP files in upload directories
                find "$upload_dir" -name "*.php" -type f 2>/dev/null | while read -r php_file; do
                    log_message "ERROR" "PHP file in upload directory: $php_file"
                    ((issues++))
                done
            fi
        done
        
    else
        log_message "WARNING" "QuickLearn plugin not found"
        ((issues++))
    fi
    
    # Check theme files
    local theme_dir="$WP_ROOT/wp-content/themes/quicklearn-theme"
    if [ -d "$theme_dir" ]; then
        # Check for proper escaping in templates
        find "$theme_dir" -name "*.php" -type f -exec grep -l "echo.*get_\|echo.*the_" {} \; 2>/dev/null | while read -r template_file; do
            if ! grep -q "esc_html\|esc_attr\|esc_url" "$template_file" 2>/dev/null; then
                log_message "WARNING" "Template without proper escaping: $template_file"
                ((issues++))
            fi
        done
    fi
    
    log_message "INFO" "QuickLearn security check completed. Issues found: $issues"
    return $issues
}

# Generate Security Report
generate_report() {
    local total_issues="$1"
    
    log_message "INFO" "Generating security report..."
    
    cat >> "$REPORT_FILE" << EOF

Security Scan Summary
====================
Total Issues Found: $total_issues
Scan Completed: $(date)

Recommendations:
===============
EOF

    if [ $total_issues -eq 0 ]; then
        cat >> "$REPORT_FILE" << EOF
✓ No security issues detected in this scan.
✓ Continue regular security monitoring and updates.
EOF
    else
        cat >> "$REPORT_FILE" << EOF
⚠ Security issues detected. Please review the detailed findings above.

Priority Actions:
1. Address all ERROR level issues immediately
2. Plan remediation for WARNING level issues
3. Update WordPress core, plugins, and themes
4. Review and strengthen user passwords
5. Implement missing security headers
6. Regular security monitoring and scanning

EOF
    fi
    
    cat >> "$REPORT_FILE" << EOF

Next Steps:
==========
1. Review this report with your security team
2. Create remediation plan for identified issues
3. Schedule regular security scans
4. Monitor security logs for suspicious activity
5. Keep all software components updated

For detailed remediation guidance, refer to:
- WordPress Security Documentation
- QuickLearn Security Best Practices
- OWASP Web Application Security Guidelines

EOF
    
    log_message "SUCCESS" "Security report generated: $REPORT_FILE"
}

# Email report function
email_report() {
    local email="$1"
    local severity="$2"
    
    if [ -n "$email" ] && command -v mail >/dev/null 2>&1; then
        local subject="QuickLearn Security Scan Report - $severity"
        mail -s "$subject" "$email" < "$REPORT_FILE"
        log_message "INFO" "Security report emailed to: $email"
    fi
}

# Main execution
main() {
    local email_recipient=""
    local scan_type="full"
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --email)
                email_recipient="$2"
                shift 2
                ;;
            --type)
                scan_type="$2"
                shift 2
                ;;
            --help)
                cat << EOF
QuickLearn Security Scanner

Usage: $0 [options]

Options:
    --email <address>    Email report to specified address
    --type <type>        Scan type: full, quick, malware, network
    --help              Show this help message

Examples:
    $0                                    # Full security scan
    $0 --type quick                       # Quick scan
    $0 --email admin@domain.com           # Email report
    $0 --type malware --email admin@domain.com

EOF
                exit 0
                ;;
            *)
                log_message "ERROR" "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    initialize_scan
    
    local total_issues=0
    local severity="LOW"
    
    case "$scan_type" in
        "full")
            log_message "INFO" "Starting full security scan..."
            total_issues=$((total_issues + $(check_wordpress_core || echo $?)))
            total_issues=$((total_issues + $(check_plugins_themes || echo $?)))
            total_issues=$((total_issues + $(check_file_system || echo $?)))
            total_issues=$((total_issues + $(check_database_security || echo $?)))
            total_issues=$((total_issues + $(check_network_security || echo $?)))
            total_issues=$((total_issues + $(check_malware || echo $?)))
            total_issues=$((total_issues + $(check_quicklearn_security || echo $?)))
            ;;
        "quick")
            log_message "INFO" "Starting quick security scan..."
            total_issues=$((total_issues + $(check_wordpress_core || echo $?)))
            total_issues=$((total_issues + $(check_quicklearn_security || echo $?)))
            ;;
        "malware")
            log_message "INFO" "Starting malware scan..."
            total_issues=$((total_issues + $(check_malware || echo $?)))
            ;;
        "network")
            log_message "INFO" "Starting network security scan..."
            total_issues=$((total_issues + $(check_network_security || echo $?)))
            ;;
        *)
            log_message "ERROR" "Invalid scan type: $scan_type"
            exit 1
            ;;
    esac
    
    # Determine severity
    if [ $total_issues -gt 10 ]; then
        severity="HIGH"
    elif [ $total_issues -gt 5 ]; then
        severity="MEDIUM"
    elif [ $total_issues -gt 0 ]; then
        severity="LOW"
    else
        severity="CLEAN"
    fi
    
    generate_report "$total_issues"
    
    if [ -n "$email_recipient" ]; then
        email_report "$email_recipient" "$severity"
    fi
    
    log_message "SUCCESS" "Security scan completed. Total issues: $total_issues (Severity: $severity)"
    
    # Cleanup
    rm -rf "$SCAN_DIR"
    
    # Exit with appropriate code
    if [ $total_issues -gt 0 ]; then
        exit 1
    else
        exit 0
    fi
}

# Run main function with all arguments
main "$@"