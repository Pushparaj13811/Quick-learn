<?php
/**
 * QuickLearn Database Migration Script
 * 
 * This script handles database migrations for the QuickLearn e-learning platform.
 * It creates necessary tables, indexes, and handles data migrations between versions.
 * 
 * Usage: php migrate-database.php [--dry-run] [--force] [--version=x.x.x]
 * 
 * @package QuickLearn
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    $wp_load_paths = [
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../../../wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die("Error: Could not locate WordPress installation.\n");
    }
}

class QuickLearnDatabaseMigrator {
    
    private $wpdb;
    private $current_version;
    private $target_version;
    private $dry_run;
    private $force;
    private $migrations;
    
    public function __construct($options = []) {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->current_version = get_option('quicklearn_db_version', '0.0.0');
        $this->target_version = $options['version'] ?? '2.0.0';
        $this->dry_run = $options['dry_run'] ?? false;
        $this->force = $options['force'] ?? false;
        
        $this->init_migrations();
    }
    
    /**
     * Initialize migration definitions
     */
    private function init_migrations() {
        $this->migrations = [
            '1.0.0' => [
                'description' => 'Initial database setup',
                'tables' => [
                    'enrollments' => $this->get_enrollments_table_sql(),
                    'course_progress' => $this->get_course_progress_table_sql(),
                ],
                'indexes' => [
                    'enrollments' => [
                        'idx_user_id' => 'ALTER TABLE {prefix}qlcm_enrollments ADD INDEX idx_user_id (user_id)',
                        'idx_course_id' => 'ALTER TABLE {prefix}qlcm_enrollments ADD INDEX idx_course_id (course_id)',
                        'idx_status' => 'ALTER TABLE {prefix}qlcm_enrollments ADD INDEX idx_status (status)',
                    ],
                ],
            ],
            '1.5.0' => [
                'description' => 'Add rating and review system',
                'tables' => [
                    'course_ratings' => $this->get_course_ratings_table_sql(),
                ],
                'indexes' => [
                    'course_ratings' => [
                        'idx_course_rating' => 'ALTER TABLE {prefix}qlcm_course_ratings ADD INDEX idx_course_rating (course_id, rating)',
                        'idx_status' => 'ALTER TABLE {prefix}qlcm_course_ratings ADD INDEX idx_status (status)',
                    ],
                ],
            ],
            '2.0.0' => [
                'description' => 'Add certificate system and enhanced features',
                'tables' => [
                    'certificates' => $this->get_certificates_table_sql(),
                ],
                'columns' => [
                    'enrollments' => [
                        'last_activity' => 'ALTER TABLE {prefix}qlcm_enrollments ADD COLUMN last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                    ],
                    'course_progress' => [
                        'time_spent' => 'ALTER TABLE {prefix}qlcm_course_progress ADD COLUMN time_spent int(11) DEFAULT 0',
                        'lesson_id' => 'ALTER TABLE {prefix}qlcm_course_progress ADD COLUMN lesson_id varchar(50) DEFAULT NULL',
                    ],
                ],
                'indexes' => [
                    'certificates' => [
                        'idx_certificate_id' => 'ALTER TABLE {prefix}qlcm_certificates ADD UNIQUE INDEX idx_certificate_id (certificate_id)',
                        'idx_issue_date' => 'ALTER TABLE {prefix}qlcm_certificates ADD INDEX idx_issue_date (issue_date)',
                    ],
                    'enrollments' => [
                        'idx_enrollment_date' => 'ALTER TABLE {prefix}qlcm_enrollments ADD INDEX idx_enrollment_date (enrollment_date)',
                        'idx_user_status' => 'ALTER TABLE {prefix}qlcm_enrollments ADD INDEX idx_user_status (user_id, status)',
                    ],
                ],
                'data_migrations' => [
                    'update_progress_calculation' => [$this, 'migrate_progress_calculation'],
                    'generate_missing_certificates' => [$this, 'migrate_generate_certificates'],
                ],
            ],
        ];
    }
    
    /**
     * Run the migration process
     */
    public function migrate() {
        $this->log("Starting QuickLearn database migration...");
        $this->log("Current version: {$this->current_version}");
        $this->log("Target version: {$this->target_version}");
        
        if ($this->dry_run) {
            $this->log("DRY RUN MODE - No changes will be made");
        }
        
        // Check if migration is needed
        if (!$this->force && version_compare($this->current_version, $this->target_version, '>=')) {
            $this->log("Database is already up to date (version {$this->current_version})");
            return true;
        }
        
        // Backup database before migration
        if (!$this->dry_run) {
            $this->create_backup();
        }
        
        // Run migrations
        $success = true;
        foreach ($this->migrations as $version => $migration) {
            if (version_compare($this->current_version, $version, '<') && 
                version_compare($version, $this->target_version, '<=')) {
                
                $this->log("Applying migration for version {$version}: {$migration['description']}");
                
                if (!$this->apply_migration($version, $migration)) {
                    $success = false;
                    break;
                }
            }
        }
        
        if ($success && !$this->dry_run) {
            update_option('quicklearn_db_version', $this->target_version);
            $this->log("Migration completed successfully! Database version updated to {$this->target_version}");
        } elseif ($success && $this->dry_run) {
            $this->log("Dry run completed successfully! No changes were made.");
        } else {
            $this->log("Migration failed! Database version remains at {$this->current_version}");
        }
        
        return $success;
    }
    
    /**
     * Apply a single migration
     */
    private function apply_migration($version, $migration) {
        try {
            // Create tables
            if (isset($migration['tables'])) {
                foreach ($migration['tables'] as $table_name => $sql) {
                    $this->log("Creating table: {$table_name}");
                    if (!$this->execute_sql($sql)) {
                        return false;
                    }
                }
            }
            
            // Add columns
            if (isset($migration['columns'])) {
                foreach ($migration['columns'] as $table_name => $columns) {
                    foreach ($columns as $column_name => $sql) {
                        $this->log("Adding column {$column_name} to table {$table_name}");
                        if (!$this->execute_sql($sql, true)) { // Allow failures for existing columns
                            $this->log("Column {$column_name} may already exist, continuing...");
                        }
                    }
                }
            }
            
            // Create indexes
            if (isset($migration['indexes'])) {
                foreach ($migration['indexes'] as $table_name => $indexes) {
                    foreach ($indexes as $index_name => $sql) {
                        $this->log("Creating index {$index_name} on table {$table_name}");
                        if (!$this->execute_sql($sql, true)) { // Allow failures for existing indexes
                            $this->log("Index {$index_name} may already exist, continuing...");
                        }
                    }
                }
            }
            
            // Run data migrations
            if (isset($migration['data_migrations'])) {
                foreach ($migration['data_migrations'] as $migration_name => $callback) {
                    $this->log("Running data migration: {$migration_name}");
                    if (is_callable($callback)) {
                        if (!call_user_func($callback)) {
                            $this->log("Data migration {$migration_name} failed");
                            return false;
                        }
                    }
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log("Error applying migration {$version}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute SQL with error handling
     */
    private function execute_sql($sql, $allow_failure = false) {
        // Replace table prefix placeholder
        $sql = str_replace('{prefix}', $this->wpdb->prefix, $sql);
        
        if ($this->dry_run) {
            $this->log("Would execute: " . $sql);
            return true;
        }
        
        $result = $this->wpdb->query($sql);
        
        if ($result === false) {
            $error = $this->wpdb->last_error;
            $this->log("SQL Error: " . $error);
            $this->log("Failed SQL: " . $sql);
            
            if (!$allow_failure) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create database backup
     */
    private function create_backup() {
        $backup_dir = WP_CONTENT_DIR . '/backups';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . '/quicklearn_migration_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        $tables = [
            $this->wpdb->prefix . 'qlcm_enrollments',
            $this->wpdb->prefix . 'qlcm_course_progress',
            $this->wpdb->prefix . 'qlcm_course_ratings',
            $this->wpdb->prefix . 'qlcm_certificates',
        ];
        
        $this->log("Creating backup: " . $backup_file);
        
        // Simple backup using mysqldump if available
        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASSWORD,
            DB_NAME,
            implode(' ', $tables),
            $backup_file
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            $this->log("Backup created successfully");
        } else {
            $this->log("Warning: Could not create backup using mysqldump");
        }
    }
    
    /**
     * Data migration: Update progress calculation
     */
    private function migrate_progress_calculation() {
        if ($this->dry_run) {
            $this->log("Would recalculate progress for all enrollments");
            return true;
        }
        
        $enrollments = $this->wpdb->get_results("
            SELECT id, user_id, course_id 
            FROM {$this->wpdb->prefix}qlcm_enrollments 
            WHERE status = 'active'
        ");
        
        foreach ($enrollments as $enrollment) {
            // Recalculate progress based on completed modules
            $completed_modules = $this->wpdb->get_var($this->wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$this->wpdb->prefix}qlcm_course_progress 
                WHERE enrollment_id = %d AND progress_percentage = 100
            ", $enrollment->id));
            
            // Get total modules for course (simplified - in real implementation, 
            // this would query course structure)
            $total_modules = 5; // Default assumption
            
            $progress = $total_modules > 0 ? ($completed_modules / $total_modules) * 100 : 0;
            
            $this->wpdb->update(
                $this->wpdb->prefix . 'qlcm_enrollments',
                ['progress_percentage' => min(100, $progress)],
                ['id' => $enrollment->id],
                ['%d'],
                ['%d']
            );
        }
        
        $this->log("Progress recalculated for " . count($enrollments) . " enrollments");
        return true;
    }
    
    /**
     * Data migration: Generate missing certificates
     */
    private function migrate_generate_certificates() {
        if ($this->dry_run) {
            $this->log("Would generate certificates for completed courses");
            return true;
        }
        
        $completed_enrollments = $this->wpdb->get_results("
            SELECT e.id, e.user_id, e.course_id, e.completion_date
            FROM {$this->wpdb->prefix}qlcm_enrollments e
            LEFT JOIN {$this->wpdb->prefix}qlcm_certificates c ON e.user_id = c.user_id AND e.course_id = c.course_id
            WHERE e.progress_percentage = 100 
            AND e.completion_date IS NOT NULL 
            AND c.id IS NULL
        ");
        
        foreach ($completed_enrollments as $enrollment) {
            $certificate_id = 'CERT-' . strtoupper(wp_generate_password(12, false));
            
            $this->wpdb->insert(
                $this->wpdb->prefix . 'qlcm_certificates',
                [
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'certificate_id' => $certificate_id,
                    'issue_date' => $enrollment->completion_date,
                    'certificate_data' => json_encode([
                        'generated_by' => 'migration',
                        'template' => 'default'
                    ])
                ],
                ['%d', '%d', '%s', '%s', '%s']
            );
        }
        
        $this->log("Generated " . count($completed_enrollments) . " missing certificates");
        return true;
    }
    
    /**
     * Get table creation SQL
     */
    private function get_enrollments_table_sql() {
        return "
            CREATE TABLE IF NOT EXISTS {prefix}qlcm_enrollments (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                course_id bigint(20) NOT NULL,
                enrollment_date datetime DEFAULT CURRENT_TIMESTAMP,
                status varchar(20) DEFAULT 'active',
                completion_date datetime DEFAULT NULL,
                progress_percentage int(3) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY user_course (user_id, course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    
    private function get_course_progress_table_sql() {
        return "
            CREATE TABLE IF NOT EXISTS {prefix}qlcm_course_progress (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                enrollment_id bigint(20) NOT NULL,
                module_id varchar(50) NOT NULL,
                completion_date datetime DEFAULT CURRENT_TIMESTAMP,
                progress_percentage int(3) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY enrollment_module (enrollment_id, module_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    
    private function get_course_ratings_table_sql() {
        return "
            CREATE TABLE IF NOT EXISTS {prefix}qlcm_course_ratings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                course_id bigint(20) NOT NULL,
                rating int(1) NOT NULL,
                review_text text,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status varchar(20) DEFAULT 'approved',
                PRIMARY KEY (id),
                UNIQUE KEY user_course_rating (user_id, course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    
    private function get_certificates_table_sql() {
        return "
            CREATE TABLE IF NOT EXISTS {prefix}qlcm_certificates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                course_id bigint(20) NOT NULL,
                certificate_id varchar(50) UNIQUE NOT NULL,
                issue_date datetime DEFAULT CURRENT_TIMESTAMP,
                certificate_data longtext,
                download_count int(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY user_course_cert (user_id, course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }
    
    /**
     * Log message with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
        
        // Also log to file if possible
        $log_file = WP_CONTENT_DIR . '/logs/quicklearn-migration.log';
        $log_dir = dirname($log_file);
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get migration status
     */
    public function get_status() {
        return [
            'current_version' => $this->current_version,
            'target_version' => $this->target_version,
            'migrations_available' => array_keys($this->migrations),
            'needs_migration' => version_compare($this->current_version, $this->target_version, '<'),
        ];
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $options = [];
    
    // Parse command line arguments
    for ($i = 1; $i < $argc; $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--force') {
            $options['force'] = true;
        } elseif (strpos($arg, '--version=') === 0) {
            $options['version'] = substr($arg, 10);
        } elseif ($arg === '--status') {
            $options['status_only'] = true;
        } elseif ($arg === '--help') {
            echo "QuickLearn Database Migration Script\n\n";
            echo "Usage: php migrate-database.php [options]\n\n";
            echo "Options:\n";
            echo "  --dry-run          Show what would be done without making changes\n";
            echo "  --force            Force migration even if version is up to date\n";
            echo "  --version=x.x.x    Migrate to specific version\n";
            echo "  --status           Show current migration status\n";
            echo "  --help             Show this help message\n\n";
            exit(0);
        }
    }
    
    $migrator = new QuickLearnDatabaseMigrator($options);
    
    if (isset($options['status_only'])) {
        $status = $migrator->get_status();
        echo "Migration Status:\n";
        echo "Current Version: " . $status['current_version'] . "\n";
        echo "Target Version: " . $status['target_version'] . "\n";
        echo "Needs Migration: " . ($status['needs_migration'] ? 'Yes' : 'No') . "\n";
        echo "Available Migrations: " . implode(', ', $status['migrations_available']) . "\n";
    } else {
        $success = $migrator->migrate();
        exit($success ? 0 : 1);
    }
}
?>