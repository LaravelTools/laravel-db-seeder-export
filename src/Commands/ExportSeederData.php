<?php

namespace LaravelToolkit\DbSeederExport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Exception;
use ZipArchive;
use LaravelToolkit\DbSeederExport\Mail\BackupCompleted;
use LaravelToolkit\DbSeederExport\Services\TelegramService;

class ExportSeederData extends Command
{
    protected $signature = 'db:export-seeder 
                            {tables?* : The names of tables to export (separate multiple tables with spaces), or leave empty to export all}
                            {--all : Export all tables in the database}
                            {--disable-foreign-keys : Temporarily disable foreign key constraints during seeding}
                            {--exclude=* : Tables to exclude from export (separate multiple tables with commas)}
                            {--exclude-pattern=* : Exclude tables matching these patterns (e.g., "telescope_*,log_*")}
                            {--include-migrations : Include the migrations table which is excluded by default}
                            {--schema-aware : Create schema-aware seeders that can adapt to column changes}
                            {--telegram : Send the backup to Telegram}
                            {--delete-after-send : Delete the backup files after sending to Telegram}
                            {--output-json : Format output as JSON (useful for API calls)}
                            {--notify-email=* : Send email notification to these addresses}
                            {--skip-empty-tables : Skip tables that have no data}
                            {--storage-disk= : Specify storage disk to save backup (default: local)}
                            {--max-execution-time= : Set maximum execution time in seconds}';
    
    protected $description = 'Export data from DB tables into Laravel Seeder classes';

    /**
     * Table dependencies map (parent => children)
     */
    protected $tableDependencies = [];

    /**
     * Default tables to exclude (from config)
     */
    protected $defaultExcludeTables = [];
    
    /**
     * Information to include in response/output
     */
    protected $results = [
        'success' => true,
        'message' => '',
        'stats' => [
            'tables_processed' => 0,
            'tables_exported' => 0,
            'tables_skipped' => 0,
            'rows_exported' => 0,
            'tables_list' => [],
            'excluded_tables' => [],
            'zip_size' => 0
        ],
        'errors' => [],
        'warnings' => []
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->defaultExcludeTables = config('db-seeder-export.excluded_tables', [
            'migrations', 
            'failed_jobs', 
            'password_resets', 
            'personal_access_tokens',
        ]);
    }

    public function handle()
    {
        // Process command options
        $tables = $this->argument('tables');
        $exportAll = $this->option('all');
        $disableForeignKeys = $this->option('disable-foreign-keys') ?: config('db-seeder-export.execution.disable_foreign_keys', true);
        $includeMigrations = $this->option('include-migrations');
        $schemaAware = $this->option('schema-aware') ?: config('db-seeder-export.execution.schema_aware', true);
        $sendToTelegram = $this->option('telegram') ?: config('db-seeder-export.telegram.enabled', false);
        $deleteAfterSend = $this->option('delete-after-send');
        $outputJson = $this->option('output-json');
        $skipEmptyTables = $this->option('skip-empty-tables') ?: config('db-seeder-export.execution.skip_empty_tables', false);
        $storageDisk = $this->option('storage-disk') ?: config('db-seeder-export.storage.disk', 'local');
        $maxExecutionTime = $this->option('max-execution-time') ?: config('db-seeder-export.execution.max_time', 0);
        $excludeTables = $this->getExcludedTables($includeMigrations);
        
        // Set custom execution time if provided
        if ($maxExecutionTime) {
            set_time_limit((int)$maxExecutionTime);
        }

        // Log the start of the backup
        Log::info('Database export started', [
            'tables' => $tables ?: 'all',
            'options' => $this->options()
        ]);
        
        // Store excluded tables in results
        $this->results['stats']['excluded_tables'] = $excludeTables;

        // If no tables specified and --all not used, ask user what to do
        if (empty($tables) && !$exportAll) {
            if ($this->confirm('No tables specified. Do you want to export all tables?', true)) {
                $exportAll = true;
            } else {
                $this->error('No tables specified for export.');
                $this->results['success'] = false;
                $this->results['message'] = 'No tables specified for export.';
                return $this->returnResult($outputJson);
            }
        }

        // Get all table names if --all option is used
        if ($exportAll) {
            $tables = $this->getAllTableNames($excludeTables);
        } else {
            // Check if any of the specified tables are in the exclude list
            $excludedSpecified = array_intersect($tables, $excludeTables);
            if (!empty($excludedSpecified)) {
                $this->warn('The following specified tables will be excluded based on your exclusion settings: ' . implode(', ', $excludedSpecified));
                $this->results['warnings'][] = 'The following specified tables will be excluded: ' . implode(', ', $excludedSpecified);
                $tables = array_diff($tables, $excludeTables);
            }
        }

        if (empty($tables)) {
            $this->error('No tables found to export after applying exclusions.');
            $this->results['success'] = false;
            $this->results['message'] = 'No tables found to export after applying exclusions.';
            return $this->returnResult($outputJson);
        }
        
        // Store total tables to process
        $this->results['stats']['tables_processed'] = count($tables);

        // Build dependencies map if we have multiple tables
        if (count($tables) > 1) {
            $this->buildTableDependencies($tables);
            
            // Sort tables based on dependencies
            $tables = $this->sortTablesByDependencies($tables);
        }

        // Create backup directory with timestamp
        $timestamp = round(microtime(true) * 1000);
        $backupDirName = "Backup{$timestamp}";
        $backupDir = database_path("seeders/{$backupDirName}");
        
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }
        
        // Create a DatabaseSeeder file that calls all individual seeders
        $masterSeederContent = $this->createMasterSeeder($backupDirName, $disableForeignKeys);
        $tablesSeeders = [];

        $this->info("Starting export to directory: {$backupDirName}");
        $this->info("Tables to export: " . count($tables));
        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        $totalRowsExported = 0;
        $exportedTables = [];
        $skippedTables = [];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->newLine();
                $this->warn("Table '{$table}' does not exist. Skipping.");
                $this->results['warnings'][] = "Table '{$table}' does not exist. Skipping.";
                $skippedTables[] = $table;
                $bar->advance();
                continue;
            }

            $data = DB::table($table)->get()->map(function ($item) {
                return (array) $item;
            })->toArray();

            if (empty($data)) {
                $this->newLine();
                $this->warn("Table '{$table}' is empty. " . ($skipEmptyTables ? "Skipping." : "Exporting anyway."));
                
                if ($skipEmptyTables) {
                    $this->results['warnings'][] = "Table '{$table}' is empty. Skipping.";
                    $skippedTables[] = $table;
                    $bar->advance();
                    continue;
                }
            }

            $className = Str::studly($table) . 'BackupSeeder';
            $tablesSeeders[] = $className;
            $filePath = "{$backupDir}/{$className}.php";

            $exportedData = var_export($data, true);

            // Decide which template to use based on schema-aware option
            if ($schemaAware) {
                $template = $this->createSchemaAwareTemplate($table, $className, $backupDirName, $exportedData);
            } else {
                $template = $this->createStandardTemplate($table, $className, $backupDirName, $exportedData);
            }

            File::put($filePath, $template);
            $exportedTables[] = $table;
            $totalRowsExported += count($data);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        // Update stats
        $this->results['stats']['tables_exported'] = count($exportedTables);
        $this->results['stats']['tables_skipped'] = count($skippedTables);
        $this->results['stats']['rows_exported'] = $totalRowsExported;
        $this->results['stats']['tables_list'] = $exportedTables;

        // Update the master seeder with all the table seeders
        $this->updateMasterSeeder($backupDir, $masterSeederContent, $tablesSeeders, $backupDirName);

        $this->info("Seeders created in: database/seeders/{$backupDirName}/");
        $this->info("Use the master seeder with: php artisan db:seed --class=\"Database\\Seeders\\{$backupDirName}\\DatabaseBackupSeeder\"");
        $this->info("Or use individual seeders with: php artisan db:seed --class=\"Database\\Seeders\\{$backupDirName}\\TableNameBackupSeeder\"");
        
        $this->results['message'] = "Backup successful: {$this->results['stats']['tables_exported']} tables exported with {$totalRowsExported} rows in total.";
        
        if ($this->tableDependencies) {
            $this->info("\nTable dependencies detected. Tables have been ordered to respect foreign key constraints.");
        }
        
        if ($disableForeignKeys) {
            $this->info("Foreign key checks will be disabled during seeding.");
        } else {
            $this->info("TIP: If you encounter foreign key constraint errors, try running the command with --disable-foreign-keys");
        }
        
        if (!empty($excludeTables)) {
            $this->info("The following tables were excluded: " . implode(', ', $excludeTables));
        }
        
        if ($includeMigrations) {
            $this->info("Migrations table was included as requested.");
        }
        
        if ($schemaAware) {
            $this->info("Schema-aware seeders were created. They will adapt to column changes between environments.");
        }

        // Handle Telegram sending if requested
        if ($sendToTelegram) {
            $this->sendToTelegram($backupDir, $backupDirName);

            // Delete backup files if requested
            if ($deleteAfterSend && $this->results['success']) {
                $this->info("Deleting backup files as requested...");
                File::deleteDirectory($backupDir);
                $this->info("Backup files deleted.");
            }
        }
        
        // Send email notifications if requested
        $notifyEmails = $this->option('notify-email') ?: config('db-seeder-export.notifications.mail.to');
        if (!empty($notifyEmails)) {
            $this->sendEmailNotifications($notifyEmails, $backupDirName);
        }
        
        // Log the completion
        Log::info('Database export completed', [
            'backup_name' => $backupDirName,
            'tables_exported' => count($exportedTables),
            'rows_exported' => $totalRowsExported
        ]);
        
        return $this->returnResult($outputJson);
    }
    
    /**
     * Format and return result
     */
    protected function returnResult($outputJson = false)
    {
        if ($outputJson) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
            return $this->results['success'] ? 0 : 1;
        }
        
        return $this->results['success'] ? 0 : 1;
    }
    
    /**
     * Send email notifications
     */
    protected function sendEmailNotifications($emails, $backupDirName)
    {
        $emails = is_array($emails) ? $emails : explode(',', $emails);
        
        $this->info("Sending email notifications to " . implode(', ', $emails));
        
        foreach ($emails as $email) {
            try {
                // Using Laravel's Mail facade with our package mail class
                Mail::to($email)->send(
                    new BackupCompleted($backupDirName, $this->results)
                );
            } catch (\Exception $e) {
                $this->warn("Failed to send email to {$email}: " . $e->getMessage());
                $this->results['warnings'][] = "Failed to send email to {$email}: " . $e->getMessage();
            }
        }
    }

    /**
     * Send backup to Telegram
     */
    protected function sendToTelegram($backupDir, $backupDirName)
    {
        $this->info("Preparing to send backup to Telegram...");

        try {
            // Create a ZIP archive of the backup
            $zipPath = storage_path("app/{$backupDirName}.zip");
            $this->info("Creating ZIP archive...");
            
            if (!$this->createZipArchive($backupDir, $zipPath)) {
                throw new Exception("Failed to create ZIP archive.");
            }
            
            $zipSize = filesize($zipPath) / (1024 * 1024); // Convert to MB
            $this->results['stats']['zip_size'] = round($zipSize, 2); // Round to 2 decimal places
            
            $this->info("ZIP archive created: {$zipPath} ({$this->results['stats']['zip_size']} MB)");

            // Check if file is not too large for Telegram (50MB limit)
            if ($zipSize > 50) {
                throw new Exception("ZIP file is too large for Telegram (> 50MB). Size: {$zipSize}MB");
            }

            // Send message about the backup
            $dbName = env('DB_DATABASE', 'database');
            $message = "📦 Database backup for '{$dbName}'\n";
            $message .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
            $message .= "📊 Tables: {$this->results['stats']['tables_exported']} (of {$this->results['stats']['tables_processed']} processed)\n";
            $message .= "📝 Backup name: {$backupDirName}";
            
            $this->info("Sending backup info message to Telegram...");
            
            // Use our package's TelegramService
            try {
                $messageResult = TelegramService::sendTelegramBotMessage($message, 'BACKUP');
                
                if (!isset($messageResult['ok']) || $messageResult['ok'] !== true) {
                    throw new Exception("Failed to send message to Telegram: " . json_encode($messageResult));
                }
                
                $this->info("Backup info message sent to Telegram.");
                
                // Send the ZIP file
                $this->info("Sending ZIP file to Telegram...");
                $fileResult = TelegramService::sendTelegramBotFile(
                    $zipPath, 
                    "Database backup {$backupDirName}.zip ({$this->results['stats']['zip_size']} MB)", 
                    'BACKUP'
                );
                
                if (!isset($fileResult['ok']) || $fileResult['ok'] !== true) {
                    throw new Exception("Failed to send file to Telegram: " . json_encode($fileResult));
                }
                
                $this->info("ZIP file sent to Telegram successfully.");
                
            } catch (Exception $e) {
                $this->error("Telegram Error: " . $e->getMessage());
                $this->warn("Make sure BACKUP_TELEGRAM_BOT_TOKEN and BACKUP_TELEGRAM_CHAT_ID are set in your .env file.");
                $this->warn("Alternatively, you can use the default TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID variables.");
                
                $this->results['errors'][] = "Telegram sending failed: " . $e->getMessage();
                $this->results['warnings'][] = "Check Telegram credentials in .env file";
            }
            
            // Delete the temporary ZIP file
            File::delete($zipPath);
            $this->info("Temporary ZIP file deleted.");
            
        } catch (Exception $e) {
            $this->error("Failed to send backup to Telegram: " . $e->getMessage());
            $this->results['errors'][] = "Failed to send backup to Telegram: " . $e->getMessage();
            $this->results['success'] = false;
            
            // Clean up temporary ZIP if it exists
            if (isset($zipPath) && File::exists($zipPath)) {
                File::delete($zipPath);
                $this->info("Temporary ZIP file deleted.");
            }
        }
    }

    /**
     * Create a ZIP archive of the backup directory
     */
    protected function createZipArchive($sourceDir, $zipPath)
    {
        if (!extension_loaded('zip')) {
            $this->error("PHP ZIP extension is not available. Cannot create ZIP archive.");
            $this->results['errors'][] = "PHP ZIP extension is not available. Cannot create ZIP archive.";
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot create ZIP file.");
            $this->results['errors'][] = "Cannot create ZIP file.";
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Get the list of tables to exclude based on options
     */
    protected function getExcludedTables($includeMigrations = false)
    {
        $excludeTables = $this->defaultExcludeTables;
        
        // If includeMigrations option is set, remove migrations from exclude list
        if ($includeMigrations) {
            $excludeTables = array_diff($excludeTables, ['migrations']);
        }
        
        // Add explicitly excluded tables
        $explicitExcludes = $this->option('exclude');
        if (!empty($explicitExcludes)) {
            foreach ($explicitExcludes as $excludeOption) {
                $tables = explode(',', $excludeOption);
                $excludeTables = array_merge($excludeTables, $tables);
            }
        }
        
        // Add pattern-based exclusions
        $patternExcludes = $this->option('exclude-pattern');
        if (!empty($patternExcludes)) {
            $allTables = $this->getRawTableNames();
            
            foreach ($patternExcludes as $patternOption) {
                $patterns = explode(',', $patternOption);
                
                foreach ($patterns as $pattern) {
                    $pattern = trim($pattern);
                    // Convert glob pattern to regex
                    $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], $pattern) . '$/i';
                    
                    $matchingTables = preg_grep($regex, $allTables);
                    $excludeTables = array_merge($excludeTables, $matchingTables);
                }
            }
        }
        
        return array_unique($excludeTables);
    }

    /**
     * Get all table names without applying exclusions
     */
    protected function getRawTableNames()
    {
        $connection = DB::connection();
        $driverName = $connection->getDriverName();
        
        // Different queries for different database systems
        switch ($driverName) {
            case 'mysql':
                return array_column(
                    $connection->select('SHOW TABLES'), 
                    'Tables_in_' . $connection->getDatabaseName()
                );
                
            case 'pgsql':
                return array_column(
                    $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"),
                    'table_name'
                );
                
            case 'sqlite':
                return array_column(
                    $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"),
                    'name'
                );
                
            case 'sqlsrv':
                return array_column(
                    $connection->select("SELECT table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE'"),
                    'table_name'
                );
                
            default:
                $this->error("Unsupported database driver: {$driverName}");
                $this->results['errors'][] = "Unsupported database driver: {$driverName}";
                return [];
        }
    }

    /**
     * Get all table names from the database using DB queries
     */
    protected function getAllTableNames($excludeTables = [])
    {
        $tables = $this->getRawTableNames();
        
        // Exclude specified tables
        if (!empty($excludeTables)) {
            $tables = array_diff($tables, $excludeTables);
        }
        
        return $tables;
    }

    /**
     * Build table dependencies based on foreign keys
     */
    protected function buildTableDependencies(array $tables)
    {
        $connection = DB::connection();
        $driverName = $connection->getDriverName();
        
        // Different approaches for different database systems
        if ($driverName === 'mysql') {
            $constraints = DB::select("
                SELECT 
                    TABLE_NAME as 'table', 
                    REFERENCED_TABLE_NAME as 'referenced_table'
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    REFERENCED_TABLE_NAME IS NOT NULL
                    AND TABLE_SCHEMA = ?
            ", [$connection->getDatabaseName()]);
            
            foreach ($constraints as $constraint) {
                if (in_array($constraint->table, $tables) && in_array($constraint->referenced_table, $tables)) {
                    if (!isset($this->tableDependencies[$constraint->referenced_table])) {
                        $this->tableDependencies[$constraint->referenced_table] = [];
                    }
                    $this->tableDependencies[$constraint->referenced_table][] = $constraint->table;
                }
            }
        } elseif ($driverName === 'pgsql') {
            $constraints = DB::select("
                SELECT
                    tc.table_name as table,
                    ccu.table_name AS referenced_table
                FROM 
                    information_schema.table_constraints AS tc 
                JOIN information_schema.constraint_column_usage AS ccu
                    ON ccu.constraint_name = tc.constraint_name
                    AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
            ");
            
            foreach ($constraints as $constraint) {
                if (in_array($constraint->table, $tables) && in_array($constraint->referenced_table, $tables)) {
                    if (!isset($this->tableDependencies[$constraint->referenced_table])) {
                        $this->tableDependencies[$constraint->referenced_table] = [];
                    }
                    $this->tableDependencies[$constraint->referenced_table][] = $constraint->table;
                }
            }
        } elseif ($driverName === 'sqlite') {
            // For SQLite, we need to query each table's info separately
            foreach ($tables as $table) {
                $foreignKeys = DB::select("PRAGMA foreign_key_list({$table})");
                
                foreach ($foreignKeys as $foreignKey) {
                    $referencedTable = $foreignKey->table;
                    
                    if (in_array($referencedTable, $tables)) {
                        if (!isset($this->tableDependencies[$referencedTable])) {
                            $this->tableDependencies[$referencedTable] = [];
                        }
                        
                        if (!in_array($table, $this->tableDependencies[$referencedTable])) {
                            $this->tableDependencies[$referencedTable][] = $table;
                        }
                    }
                }
            }
        }
    }

    /**
     * Sort tables to respect foreign key constraints
     */
    protected function sortTablesByDependencies(array $tables)
    {
        // If we couldn't detect any dependencies, return the original table list
        if (empty($this->tableDependencies)) {
            return $tables;
        }

        $sorted = [];
        $visited = [];
        
        // Do a topological sort on tables
        foreach ($tables as $table) {
            $this->visitTable($table, $visited, $sorted);
        }
        
        return $sorted;
    }
    
    /**
     * Recursive helper for topological sort
     */
    protected function visitTable($table, array &$visited, array &$sorted)
    {
        // If already fully processed, skip
        if (isset($visited[$table]) && $visited[$table] === true) {
            return;
        }
        
        // Detect cycles (which shouldn't happen in properly designed DB)
        if (isset($visited[$table]) && $visited[$table] === false) {
            $this->warn("Circular dependency detected for table: $table");
            $this->results['warnings'][] = "Circular dependency detected for table: $table";
            return;
        }
        
        // Mark as visiting
        $visited[$table] = false;
        
        // Visit parent tables first
        if (isset($this->tableDependencies[$table])) {
            foreach ($this->tableDependencies[$table] as $dependent) {
                $this->visitTable($dependent, $visited, $sorted);
            }
        }
        
        // Mark as visited and add to sorted list
        $visited[$table] = true;
        $sorted[] = $table;
    }

    /**
     * Create a master seeder content with runtime database detection
     */
    protected function createMasterSeeder($backupDirName, $disableForeignKeys)
    {
        $fkHandlingCode = '';
        
        if ($disableForeignKeys) {
            $fkHandlingCode = <<<'PHP'
        // Get the current database connection
        $connection = DB::connection();
        $driverName = $connection->getDriverName();
        
        // Disable foreign key checks based on database driver
        if ($driverName === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driverName === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            Schema::disableForeignKeyConstraints();
        }

PHP;

            $fkReenableCode = <<<'PHP'

        // Re-enable foreign key checks
        if ($driverName === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driverName === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::enableForeignKeyConstraints();
        }
PHP;
        } else {
            $fkHandlingCode = '';
            $fkReenableCode = '';
        }
            
        return <<<PHP
<?php

namespace Database\Seeders\\{$backupDirName};

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseBackupSeeder extends Seeder
{
    public function run()
    {
{$fkHandlingCode}
        // Individual table seeders will be called here
{$fkReenableCode}
    }
}
PHP;
    }

    /**
     * Update the master seeder with individual table seeders
     */
    protected function updateMasterSeeder($backupDir, $masterContent, $tablesSeeders, $backupDirName)
    {
        $seedCalls = '';
        foreach ($tablesSeeders as $seeder) {
            $seedCalls .= "        \$this->call({$seeder}::class);\n";
        }

        // Replace the placeholder comment with actual seeder calls
        $masterContent = str_replace(
            '        // Individual table seeders will be called here',
            rtrim($seedCalls),
            $masterContent
        );

        File::put("{$backupDir}/DatabaseBackupSeeder.php", $masterContent);
    }

    /**
     * Create a standard seeder template
     */
    protected function createStandardTemplate($table, $className, $backupDirName, $exportedData)
    {
        return <<<PHP
<?php

namespace Database\Seeders\\{$backupDirName};

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$className} extends Seeder
{
    public function run()
    {
        \$data = {$exportedData};

        DB::table('{$table}')->insert(\$data);
    }
}
PHP;
    }
    
    /**
     * Create a schema-aware seeder template that can handle column differences
     */
    protected function createSchemaAwareTemplate($table, $className, $backupDirName, $exportedData)
    {
        return <<<PHP
<?php

namespace Database\Seeders\\{$backupDirName};

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Command;

class {$className} extends Seeder
{
    /**
     * Get a console command instance for output
     */
    protected function getCommand()
    {
        // Return injected command if available, or create a new one
        return \$this->command ?? new Command();
    }

    public function run()
    {
        \$data = {$exportedData};
        
        // Get current table columns to handle schema differences
        \$tableColumns = Schema::getColumnListing('{$table}');
        \$processedData = [];
        \$skippedColumns = [];
        
        foreach (\$data as \$row) {
            \$filteredRow = [];
            foreach (\$row as \$column => \$value) {
                // Only include columns that exist in current schema
                if (in_array(\$column, \$tableColumns)) {
                    \$filteredRow[\$column] = \$value;
                } else {
                    if (!in_array(\$column, \$skippedColumns)) {
                        \$skippedColumns[] = \$column;
                    }
                }
            }
            \$processedData[] = \$filteredRow;
        }
        
        // Report skipped columns once (not for each row)
        \$command = \$this->getCommand();
        if (!empty(\$skippedColumns)) {
            \$command->warn("In '{$table}' table: The following columns from the backup don't exist in your current schema: " . implode(', ', \$skippedColumns));
        }
        
        if (!empty(\$processedData)) {
            // Use chunk insert to handle large datasets
            \$chunks = array_chunk(\$processedData, 100);
            foreach (\$chunks as \$chunk) {
                DB::table('{$table}')->insert(\$chunk);
            }
            \$command->info("Seeded '{$table}' table" . (!empty(\$skippedColumns) ? " (with some columns skipped)" : ""));
        } else {
            \$command->error("All data for '{$table}' was filtered out due to schema differences.");
        }
    }
}
PHP;
    }
}