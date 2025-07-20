#!/bin/bash

# QuickLearn Backup and Recovery Script
# Comprehensive backup solution for the QuickLearn e-learning platform

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${CONFIG_FILE:-$SCRIPT_DIR/backup-config.conf}"

# Default configuration
WP_ROOT="${WP_ROOT:-/var/www/html}"
BACKUP_DIR="${BACKUP_DIR:-/backups/quicklearn}"
DB_NAME="${DB_NAME:-wordpress}"
DB_USER="${DB_USER:-wp_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-localhost}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
COMPRESSION_LEVEL="${COMPRESSION_LEVEL:-6}"
NOTIFICATION_EMAIL="${NOTIFICATION_EMAIL:-admin@yourdomain.com}"
CLOUD_BACKUP="${CLOUD_BACKUP:-false}"
AWS_S3_BUCKET="${AWS_S3_BUCKET:-}"
ENCRYPTION_KEY="${ENCRYPTION_KEY:-}"

# Load configuration if exists
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
fi

# Logging
LOG_FILE="$BACKUP_DIR/backup.log"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Functions
log_message() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] $message" | tee -a "$LOG_FILE"
}

send_notification() {
    local subject="$1"
    local message="$2"
    local priority="${3:-normal}"
    
    if [ -n "$NOTIFICATION_EMAIL" ]; then
        echo "$message" | mail -s "$subject" "$NOTIFICATION_EMAIL"
    fi
    
    log_message "NOTIFICATION: $subject - $message"
}

create_backup_directory() {
    local backup_path="$1"
    mkdir -p "$backup_path" 2>/dev/null || {
        echo "Creating backup directory with sudo..."
        sudo mkdir -p "$backup_path"
        sudo chown $(whoami):$(id -gn) "$backup_path" 2>/dev/null || true
    }
    chmod 750 "$backup_path" 2>/dev/null || sudo chmod 750 "$backup_path" 2>/dev/null || true
}

# Backup functions
backup_database() {
    local backup_path="$1"
    local db_backup_file="$backup_path/database_$TIMESTAMP.sql"
    
    log_message "Starting database backup..."
    
    # Create database backup
    if mysqldump \
        --host="$DB_HOST" \
        --user="$DB_USER" \
        --password="$DB_PASSWORD" \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-table \
        --extended-insert \
        --quick \
        --lock-tables=false \
        "$DB_NAME" > "$db_backup_file"; then
        
        log_message "Database backup created: $db_backup_file"
        
        # Compress database backup
        if gzip -"$COMPRESSION_LEVEL" "$db_backup_file"; then
            log_message "Database backup compressed: ${db_backup_file}.gz"
            echo "${db_backup_file}.gz"
        else
            log_message "ERROR: Failed to compress database backup"
            return 1
        fi
    else
        log_message "ERROR: Database backup failed"
        return 1
    fi
}

backup_files() {
    local backup_path="$1"
    local files_backup_file="$backup_path/files_$TIMESTAMP.tar.gz"
    
    log_message "Starting files backup..."
    
    # Create exclusion list
    local exclude_file="$backup_path/exclude_$TIMESTAMP.txt"
    cat > "$exclude_file" << EOF
$WP_ROOT/wp-content/cache
$WP_ROOT/wp-content/uploads/cache
$WP_ROOT/wp-content/debug.log
$WP_ROOT/wp-content/backup-*
$WP_ROOT/wp-content/ai1wm-backups
$WP_ROOT/wp-content/updraft
$WP_ROOT/.git
$WP_ROOT/node_modules
$WP_ROOT/*.tmp
$WP_ROOT/*.log
EOF

    # Create files backup
    if tar \
        --create \
        --gzip \
        --file="$files_backup_file" \
        --exclude-from="$exclude_file" \
        --directory="$(dirname "$WP_ROOT")" \
        "$(basename "$WP_ROOT")"; then
        
        log_message "Files backup created: $files_backup_file"
        rm -f "$exclude_file"
        echo "$files_backup_file"
    else
        log_message "ERROR: Files backup failed"
        rm -f "$exclude_file"
        return 1
    fi
}

backup_quicklearn_data() {
    local backup_path="$1"
    local quicklearn_backup_file="$backup_path/quicklearn_data_$TIMESTAMP.tar.gz"
    
    log_message "Starting QuickLearn specific data backup..."
    
    # Backup QuickLearn specific directories
    local quicklearn_paths=(
        "$WP_ROOT/wp-content/plugins/quicklearn-course-manager"
        "$WP_ROOT/wp-content/themes/quicklearn-theme"
        "$WP_ROOT/wp-content/uploads/quicklearn"
        "$WP_ROOT/wp-content/uploads/certificates"
    )
    
    local existing_paths=()
    for path in "${quicklearn_paths[@]}"; do
        if [ -e "$path" ]; then
            existing_paths+=("$path")
        fi
    done
    
    if [ ${#existing_paths[@]} -gt 0 ]; then
        if tar \
            --create \
            --gzip \
            --file="$quicklearn_backup_file" \
            "${existing_paths[@]}"; then
            
            log_message "QuickLearn data backup created: $quicklearn_backup_file"
            echo "$quicklearn_backup_file"
        else
            log_message "ERROR: QuickLearn data backup failed"
            return 1
        fi
    else
        log_message "WARNING: No QuickLearn data found to backup"
        return 0
    fi
}

encrypt_backup() {
    local file_path="$1"
    local encrypted_file="${file_path}.enc"
    
    if [ -n "$ENCRYPTION_KEY" ]; then
        log_message "Encrypting backup: $(basename "$file_path")"
        
        if openssl enc -aes-256-cbc -salt -in "$file_path" -out "$encrypted_file" -k "$ENCRYPTION_KEY"; then
            rm -f "$file_path"
            log_message "Backup encrypted: $(basename "$encrypted_file")"
            echo "$encrypted_file"
        else
            log_message "ERROR: Failed to encrypt backup"
            return 1
        fi
    else
        echo "$file_path"
    fi
}

verify_backup() {
    local backup_file="$1"
    
    log_message "Verifying backup: $(basename "$backup_file")"
    
    if [[ "$backup_file" == *.gz ]]; then
        if gzip -t "$backup_file"; then
            log_message "Backup verification successful: $(basename "$backup_file")"
            return 0
        else
            log_message "ERROR: Backup verification failed: $(basename "$backup_file")"
            return 1
        fi
    elif [[ "$backup_file" == *.tar.gz ]]; then
        if tar -tzf "$backup_file" > /dev/null; then
            log_message "Backup verification successful: $(basename "$backup_file")"
            return 0
        else
            log_message "ERROR: Backup verification failed: $(basename "$backup_file")"
            return 1
        fi
    elif [[ "$backup_file" == *.enc ]]; then
        # For encrypted files, we can only check if the file exists and has content
        if [ -s "$backup_file" ]; then
            log_message "Encrypted backup verification successful: $(basename "$backup_file")"
            return 0
        else
            log_message "ERROR: Encrypted backup verification failed: $(basename "$backup_file")"
            return 1
        fi
    fi
    
    return 0
}

upload_to_cloud() {
    local backup_file="$1"
    
    if [ "$CLOUD_BACKUP" = "true" ] && [ -n "$AWS_S3_BUCKET" ]; then
        log_message "Uploading to cloud storage: $(basename "$backup_file")"
        
        if aws s3 cp "$backup_file" "s3://$AWS_S3_BUCKET/quicklearn/$(basename "$backup_file")"; then
            log_message "Cloud upload successful: $(basename "$backup_file")"
        else
            log_message "WARNING: Cloud upload failed: $(basename "$backup_file")"
        fi
    fi
}

cleanup_old_backups() {
    local backup_path="$1"
    
    log_message "Cleaning up backups older than $RETENTION_DAYS days..."
    
    find "$backup_path" -name "*.gz" -mtime +$RETENTION_DAYS -delete
    find "$backup_path" -name "*.enc" -mtime +$RETENTION_DAYS -delete
    find "$backup_path" -name "*.sql" -mtime +$RETENTION_DAYS -delete
    
    # Clean up cloud backups if configured
    if [ "$CLOUD_BACKUP" = "true" ] && [ -n "$AWS_S3_BUCKET" ]; then
        local cutoff_date=$(date -d "$RETENTION_DAYS days ago" +%Y-%m-%d)
        aws s3 ls "s3://$AWS_S3_BUCKET/quicklearn/" | while read -r line; do
            local file_date=$(echo "$line" | awk '{print $1}')
            local file_name=$(echo "$line" | awk '{print $4}')
            
            if [[ "$file_date" < "$cutoff_date" ]]; then
                aws s3 rm "s3://$AWS_S3_BUCKET/quicklearn/$file_name"
                log_message "Removed old cloud backup: $file_name"
            fi
        done
    fi
    
    log_message "Backup cleanup completed"
}

# Recovery functions
list_backups() {
    local backup_path="$1"
    
    echo "Available backups in $backup_path:"
    echo "=================================="
    
    # List local backups
    find "$backup_path" -name "database_*.sql.gz" -o -name "database_*.sql.gz.enc" | sort -r | head -10 | while read -r file; do
        local size=$(du -h "$file" | cut -f1)
        local date=$(stat -c %y "$file" | cut -d' ' -f1,2 | cut -d'.' -f1)
        echo "Database: $(basename "$file") - $size - $date"
    done
    
    find "$backup_path" -name "files_*.tar.gz" -o -name "files_*.tar.gz.enc" | sort -r | head -10 | while read -r file; do
        local size=$(du -h "$file" | cut -f1)
        local date=$(stat -c %y "$file" | cut -d'.' -f1)
        echo "Files: $(basename "$file") - $size - $date"
    done
    
    # List cloud backups if configured
    if [ "$CLOUD_BACKUP" = "true" ] && [ -n "$AWS_S3_BUCKET" ]; then
        echo ""
        echo "Cloud backups:"
        echo "=============="
        aws s3 ls "s3://$AWS_S3_BUCKET/quicklearn/" --human-readable | head -10
    fi
}

decrypt_backup() {
    local encrypted_file="$1"
    local decrypted_file="${encrypted_file%.enc}"
    
    if [ -n "$ENCRYPTION_KEY" ]; then
        log_message "Decrypting backup: $(basename "$encrypted_file")"
        
        if openssl enc -aes-256-cbc -d -in "$encrypted_file" -out "$decrypted_file" -k "$ENCRYPTION_KEY"; then
            log_message "Backup decrypted: $(basename "$decrypted_file")"
            echo "$decrypted_file"
        else
            log_message "ERROR: Failed to decrypt backup"
            return 1
        fi
    else
        log_message "ERROR: No encryption key provided for decryption"
        return 1
    fi
}

restore_database() {
    local backup_file="$1"
    local temp_db="${DB_NAME}_restore_temp"
    
    log_message "Starting database restore from: $(basename "$backup_file")"
    
    # Decrypt if necessary
    if [[ "$backup_file" == *.enc ]]; then
        backup_file=$(decrypt_backup "$backup_file")
        if [ $? -ne 0 ]; then
            return 1
        fi
    fi
    
    # Decompress if necessary
    local sql_file="$backup_file"
    if [[ "$backup_file" == *.gz ]]; then
        sql_file="${backup_file%.gz}"
        gunzip -c "$backup_file" > "$sql_file"
    fi
    
    # Create temporary database for testing
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $temp_db;"
    
    # Test restore to temporary database
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$temp_db" < "$sql_file"; then
        log_message "Test restore successful"
        
        # Backup current database before restore
        local safety_backup="$BACKUP_DIR/safety_backup_$TIMESTAMP.sql"
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$safety_backup"
        log_message "Safety backup created: $safety_backup"
        
        # Perform actual restore
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$sql_file"; then
            log_message "Database restore completed successfully"
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE $temp_db;"
            
            # Clean up temporary files
            if [[ "$sql_file" != "$backup_file" ]]; then
                rm -f "$sql_file"
            fi
            
            return 0
        else
            log_message "ERROR: Database restore failed"
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE $temp_db;"
            return 1
        fi
    else
        log_message "ERROR: Test restore failed"
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "DROP DATABASE $temp_db;"
        return 1
    fi
}

restore_files() {
    local backup_file="$1"
    local restore_path="${2:-$WP_ROOT}"
    
    log_message "Starting files restore from: $(basename "$backup_file")"
    
    # Decrypt if necessary
    if [[ "$backup_file" == *.enc ]]; then
        backup_file=$(decrypt_backup "$backup_file")
        if [ $? -ne 0 ]; then
            return 1
        fi
    fi
    
    # Create safety backup of current files
    local safety_backup="$BACKUP_DIR/safety_files_backup_$TIMESTAMP.tar.gz"
    tar -czf "$safety_backup" -C "$(dirname "$restore_path")" "$(basename "$restore_path")"
    log_message "Safety backup created: $safety_backup"
    
    # Extract backup
    if tar -xzf "$backup_file" -C "$(dirname "$restore_path")"; then
        log_message "Files restore completed successfully"
        
        # Set proper permissions
        chown -R www-data:www-data "$restore_path"
        find "$restore_path" -type d -exec chmod 755 {} \;
        find "$restore_path" -type f -exec chmod 644 {} \;
        
        return 0
    else
        log_message "ERROR: Files restore failed"
        return 1
    fi
}

# Main functions
perform_backup() {
    local backup_date_path="$BACKUP_DIR/$(date +%Y-%m-%d)"
    
    log_message "Starting QuickLearn backup process..."
    
    create_backup_directory "$backup_date_path"
    
    local backup_files=()
    local backup_success=true
    
    # Database backup
    if db_backup=$(backup_database "$backup_date_path"); then
        if encrypted_db=$(encrypt_backup "$db_backup"); then
            if verify_backup "$encrypted_db"; then
                backup_files+=("$encrypted_db")
                upload_to_cloud "$encrypted_db"
            else
                backup_success=false
            fi
        else
            backup_success=false
        fi
    else
        backup_success=false
    fi
    
    # Files backup
    if files_backup=$(backup_files "$backup_date_path"); then
        if encrypted_files=$(encrypt_backup "$files_backup"); then
            if verify_backup "$encrypted_files"; then
                backup_files+=("$encrypted_files")
                upload_to_cloud "$encrypted_files"
            else
                backup_success=false
            fi
        else
            backup_success=false
        fi
    else
        backup_success=false
    fi
    
    # QuickLearn data backup
    if quicklearn_backup=$(backup_quicklearn_data "$backup_date_path"); then
        if [ -n "$quicklearn_backup" ]; then
            if encrypted_quicklearn=$(encrypt_backup "$quicklearn_backup"); then
                if verify_backup "$encrypted_quicklearn"; then
                    backup_files+=("$encrypted_quicklearn")
                    upload_to_cloud "$encrypted_quicklearn"
                else
                    backup_success=false
                fi
            else
                backup_success=false
            fi
        fi
    fi
    
    # Create backup manifest
    local manifest_file="$backup_date_path/manifest_$TIMESTAMP.txt"
    {
        echo "QuickLearn Backup Manifest"
        echo "========================="
        echo "Backup Date: $(date)"
        echo "Backup Location: $backup_date_path"
        echo "WordPress Root: $WP_ROOT"
        echo "Database: $DB_NAME"
        echo ""
        echo "Backup Files:"
        for file in "${backup_files[@]}"; do
            echo "- $(basename "$file") ($(du -h "$file" | cut -f1))"
        done
    } > "$manifest_file"
    
    # Cleanup old backups
    cleanup_old_backups "$BACKUP_DIR"
    
    if [ "$backup_success" = true ]; then
        log_message "Backup process completed successfully"
        send_notification "QuickLearn Backup Success" "Backup completed successfully on $(date). Files: ${#backup_files[@]}"
        return 0
    else
        log_message "Backup process completed with errors"
        send_notification "QuickLearn Backup Warning" "Backup completed with some errors on $(date). Check logs for details." "warning"
        return 1
    fi
}

perform_restore() {
    local backup_date="$1"
    local restore_type="${2:-full}"
    
    if [ -z "$backup_date" ]; then
        echo "Usage: $0 restore <backup_date> [database|files|full]"
        echo "Example: $0 restore 2024-01-15 full"
        return 1
    fi
    
    local backup_path="$BACKUP_DIR/$backup_date"
    
    if [ ! -d "$backup_path" ]; then
        log_message "ERROR: Backup directory not found: $backup_path"
        return 1
    fi
    
    log_message "Starting restore process for $backup_date (type: $restore_type)"
    
    case "$restore_type" in
        "database"|"db")
            local db_backup=$(find "$backup_path" -name "database_*.sql.gz*" | head -1)
            if [ -n "$db_backup" ]; then
                restore_database "$db_backup"
            else
                log_message "ERROR: No database backup found for $backup_date"
                return 1
            fi
            ;;
        "files")
            local files_backup=$(find "$backup_path" -name "files_*.tar.gz*" | head -1)
            if [ -n "$files_backup" ]; then
                restore_files "$files_backup"
            else
                log_message "ERROR: No files backup found for $backup_date"
                return 1
            fi
            ;;
        "full")
            # Restore database first
            local db_backup=$(find "$backup_path" -name "database_*.sql.gz*" | head -1)
            if [ -n "$db_backup" ]; then
                if ! restore_database "$db_backup"; then
                    log_message "ERROR: Database restore failed"
                    return 1
                fi
            fi
            
            # Then restore files
            local files_backup=$(find "$backup_path" -name "files_*.tar.gz*" | head -1)
            if [ -n "$files_backup" ]; then
                if ! restore_files "$files_backup"; then
                    log_message "ERROR: Files restore failed"
                    return 1
                fi
            fi
            ;;
        *)
            log_message "ERROR: Invalid restore type: $restore_type"
            return 1
            ;;
    esac
    
    log_message "Restore process completed"
    send_notification "QuickLearn Restore Completed" "Restore process completed for $backup_date (type: $restore_type)"
}

# Command line interface
case "${1:-backup}" in
    "backup")
        perform_backup
        ;;
    "restore")
        perform_restore "$2" "$3"
        ;;
    "list")
        list_backups "$BACKUP_DIR"
        ;;
    "cleanup")
        cleanup_old_backups "$BACKUP_DIR"
        ;;
    "test")
        # Test backup and restore process
        log_message "Starting backup and restore test..."
        if perform_backup; then
            log_message "Test backup successful"
            # Could add restore test here
        else
            log_message "Test backup failed"
            exit 1
        fi
        ;;
    "help"|"--help"|"-h")
        cat << EOF
QuickLearn Backup and Recovery Script

Usage: $0 [command] [options]

Commands:
    backup              Perform full backup (default)
    restore <date> [type]   Restore from backup
                           Types: database, files, full (default)
    list                List available backups
    cleanup             Clean up old backups
    test                Test backup process
    help                Show this help message

Examples:
    $0 backup
    $0 restore 2024-01-15 full
    $0 restore 2024-01-15 database
    $0 list
    $0 cleanup

Configuration:
    Edit $CONFIG_FILE to customize backup settings.

EOF
        ;;
    *)
        echo "Unknown command: $1"
        echo "Use '$0 help' for usage information."
        exit 1
        ;;
esac