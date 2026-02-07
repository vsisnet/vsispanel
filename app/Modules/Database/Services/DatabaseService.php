<?php

declare(strict_types=1);

namespace App\Modules\Database\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Database\Models\DatabaseUser;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Domain\Models\Domain;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseService
{
    protected array $defaultPrivileges = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE',
        'CREATE', 'DROP', 'ALTER', 'INDEX',
        'CREATE TEMPORARY TABLES', 'LOCK TABLES',
        'EXECUTE', 'CREATE VIEW', 'SHOW VIEW',
        'CREATE ROUTINE', 'ALTER ROUTINE', 'EVENT', 'TRIGGER',
    ];

    public function __construct(
        protected SystemCommandExecutor $executor
    ) {}

    /**
     * Create a new database for a user.
     */
    public function createDatabase(User $user, string $name, ?Domain $domain = null, array $options = []): ManagedDatabase
    {
        $username = $this->getUsername($user);
        $prefixedName = "{$username}_{$name}";

        // Validate name format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            throw new RuntimeException('Invalid database name. Use only letters, numbers, and underscores. Must start with a letter.');
        }

        // Check if database already exists
        if (ManagedDatabase::where('name', $prefixedName)->exists()) {
            throw new RuntimeException("Database '{$name}' already exists for this user.");
        }

        $charset = $options['charset'] ?? 'utf8mb4';
        $collation = $options['collation'] ?? 'utf8mb4_unicode_ci';

        // DDL statements (CREATE DATABASE) cause implicit commit in MySQL
        // So we cannot use them inside a transaction. Execute DDL first, then create record.
        try {
            // Create database in MySQL (DDL - outside transaction)
            $this->executeMySqlStatement("CREATE DATABASE `{$prefixedName}` CHARACTER SET {$charset} COLLATE {$collation}");

            // Create record in panel database
            $database = ManagedDatabase::create([
                'user_id' => $user->id,
                'domain_id' => $domain?->id,
                'name' => $prefixedName,
                'original_name' => $name,
                'size_bytes' => 0,
                'charset' => $charset,
                'collation' => $collation,
                'status' => 'active',
            ]);

            Log::channel('commands')->info('Database created', [
                'database' => $prefixedName,
                'user_id' => $user->id,
            ]);

            return $database;
        } catch (\Exception $e) {
            // Try to clean up the MySQL database if record creation failed
            try {
                $this->executeMySqlStatement("DROP DATABASE IF EXISTS `{$prefixedName}`");
            } catch (\Exception $cleanupException) {
                Log::channel('commands')->warning('Failed to cleanup database after error', [
                    'database' => $prefixedName,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Delete a database.
     */
    public function deleteDatabase(ManagedDatabase $database): void
    {
        // Revoke all user access first
        foreach ($database->databaseUsers as $dbUser) {
            $this->revokeAccess($dbUser, $database);
        }

        // Drop database in MySQL (DDL - outside transaction)
        $this->executeMySqlStatement("DROP DATABASE IF EXISTS `{$database->name}`");

        // Soft delete record
        $database->update(['status' => 'deleted']);
        $database->delete();

        Log::channel('commands')->info('Database deleted', [
            'database' => $database->name,
        ]);
    }

    /**
     * Create a database user.
     */
    public function createDatabaseUser(User $user, string $username, string $password, string $host = 'localhost'): DatabaseUser
    {
        $panelUsername = $this->getUsername($user);
        $prefixedUsername = "{$panelUsername}_{$username}";

        // Validate username format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $username)) {
            throw new RuntimeException('Invalid username. Use only letters, numbers, and underscores. Must start with a letter.');
        }

        // MySQL username length limit is 32 characters
        if (strlen($prefixedUsername) > 32) {
            throw new RuntimeException('Username is too long. Maximum 32 characters including prefix.');
        }

        // Check if user already exists
        if (DatabaseUser::where('username', $prefixedUsername)->where('host', $host)->exists()) {
            throw new RuntimeException("Database user '{$username}' already exists for this host.");
        }

        // DDL statements (CREATE USER) cause implicit commit in MySQL
        // So we cannot use them inside a transaction. Execute DDL first, then create record.
        try {
            // Create user in MySQL (DDL - outside transaction)
            $escapedPassword = addslashes($password);
            $this->executeMySqlStatement("CREATE USER '{$prefixedUsername}'@'{$host}' IDENTIFIED BY '{$escapedPassword}'");

            // Create record in panel database with encrypted password
            $dbUser = DatabaseUser::create([
                'user_id' => $user->id,
                'username' => $prefixedUsername,
                'original_username' => $username,
                'host' => $host,
                'password_encrypted' => encrypt($password),
                'privileges' => [],
            ]);

            Log::channel('commands')->info('Database user created', [
                'username' => $prefixedUsername,
                'host' => $host,
                'user_id' => $user->id,
            ]);

            return $dbUser;
        } catch (\Exception $e) {
            // Try to clean up the MySQL user if record creation failed
            try {
                $this->executeMySqlStatement("DROP USER IF EXISTS '{$prefixedUsername}'@'{$host}'");
            } catch (\Exception $cleanupException) {
                Log::channel('commands')->warning('Failed to cleanup database user after error', [
                    'username' => $prefixedUsername,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Delete a database user.
     */
    public function deleteDatabaseUser(DatabaseUser $dbUser): void
    {
        // Revoke access from all databases first
        foreach ($dbUser->databases as $database) {
            $this->revokeAccess($dbUser, $database);
        }

        // Drop user in MySQL (DDL - outside transaction)
        $this->executeMySqlStatement("DROP USER IF EXISTS '{$dbUser->username}'@'{$dbUser->host}'");

        // Soft delete record
        $dbUser->delete();

        Log::channel('commands')->info('Database user deleted', [
            'username' => $dbUser->username,
        ]);
    }

    /**
     * Change database user password.
     */
    public function changeDatabaseUserPassword(DatabaseUser $dbUser, string $newPassword): void
    {
        $escapedPassword = addslashes($newPassword);
        $this->executeMySqlStatement("ALTER USER '{$dbUser->username}'@'{$dbUser->host}' IDENTIFIED BY '{$escapedPassword}'");
        $this->executeMySqlStatement("FLUSH PRIVILEGES");

        // Update encrypted password in database
        $dbUser->update(['password_encrypted' => encrypt($newPassword)]);

        Log::channel('commands')->info('Database user password changed', [
            'username' => $dbUser->username,
        ]);
    }

    /**
     * Grant access to a database.
     */
    public function grantAccess(DatabaseUser $dbUser, ManagedDatabase $database, ?array $privileges = null): void
    {
        $privileges = $privileges ?? $this->defaultPrivileges;
        $privilegeString = implode(', ', $privileges);

        // Grant privileges
        $this->executeMySqlStatement(
            "GRANT {$privilegeString} ON `{$database->name}`.* TO '{$dbUser->username}'@'{$dbUser->host}'"
        );
        $this->executeMySqlStatement("FLUSH PRIVILEGES");

        // Check if relation already exists
        $existingRelation = $dbUser->databases()->where('managed_database_id', $database->id)->exists();

        if ($existingRelation) {
            // Update existing relation
            $dbUser->databases()->updateExistingPivot($database->id, [
                'privileges' => json_encode($privileges),
            ]);
        } else {
            // Create new relation with explicit UUID
            DB::table('database_database_user')->insert([
                'id' => Str::uuid()->toString(),
                'database_user_id' => $dbUser->id,
                'managed_database_id' => $database->id,
                'privileges' => json_encode($privileges),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::channel('commands')->info('Database access granted', [
            'database' => $database->name,
            'username' => $dbUser->username,
            'privileges' => $privileges,
        ]);
    }

    /**
     * Revoke access from a database.
     */
    public function revokeAccess(DatabaseUser $dbUser, ManagedDatabase $database): void
    {
        // Revoke all privileges
        $this->executeMySqlStatement(
            "REVOKE ALL PRIVILEGES ON `{$database->name}`.* FROM '{$dbUser->username}'@'{$dbUser->host}'"
        );
        $this->executeMySqlStatement("FLUSH PRIVILEGES");

        // Remove from pivot table
        $dbUser->databases()->detach($database->id);

        Log::channel('commands')->info('Database access revoked', [
            'database' => $database->name,
            'username' => $dbUser->username,
        ]);
    }

    /**
     * Get database size.
     */
    public function getDatabaseSize(ManagedDatabase $database): int
    {
        $result = DB::select("
            SELECT SUM(data_length + index_length) as size
            FROM information_schema.TABLES
            WHERE table_schema = ?
        ", [$database->name]);

        $size = (int) ($result[0]->size ?? 0);

        // Update stored size
        $database->update(['size_bytes' => $size]);

        return $size;
    }

    /**
     * Backup a database to a file.
     */
    public function backupDatabase(ManagedDatabase $database): string
    {
        $backupDir = config('vsispanel.backup.path', '/var/backups/vsispanel') . '/databases';
        $filename = "{$database->name}_" . now()->format('YmdHis') . '.sql.gz';
        $backupPath = "{$backupDir}/{$filename}";

        // Ensure backup directory exists
        $this->executor->executeAsRoot('mkdir', ['-p', $backupDir]);

        // Get MySQL credentials from config
        $host = config('database.connections.mysql.host', 'localhost');
        $port = config('database.connections.mysql.port', 3306);
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Run mysqldump
        $command = "mysqldump --single-transaction --quick --host={$host} --port={$port} " .
                   "--user={$username} --password='{$password}' {$database->name} | gzip > {$backupPath}";

        $result = $this->executor->executeAsRoot('bash', ['-c', $command]);

        if (!$result->success) {
            throw new RuntimeException('Database backup failed: ' . $result->stderr);
        }

        Log::channel('commands')->info('Database backed up', [
            'database' => $database->name,
            'backup_path' => $backupPath,
        ]);

        return $backupPath;
    }

    /**
     * Restore a database from a backup file.
     */
    public function restoreDatabase(ManagedDatabase $database, string $backupPath): void
    {
        // Check if file exists and is readable using PHP native functions
        if (!file_exists($backupPath)) {
            throw new RuntimeException('Backup file not found: ' . $backupPath);
        }

        if (!is_readable($backupPath)) {
            throw new RuntimeException('Backup file is not readable: ' . $backupPath);
        }

        // Get MySQL credentials from config
        $host = config('database.connections.mysql.host', 'localhost');
        $port = config('database.connections.mysql.port', 3306);
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Escape password for shell
        $escapedPassword = escapeshellarg($password);

        // Determine if file is gzipped
        $isGzipped = str_ends_with($backupPath, '.gz');
        $catCommand = $isGzipped ? 'zcat' : 'cat';

        // Escape the backup path for shell
        $escapedPath = escapeshellarg($backupPath);

        // Restore database
        $command = "{$catCommand} {$escapedPath} | mysql --host={$host} --port={$port} " .
                   "--user={$username} --password={$escapedPassword} {$database->name}";

        $result = $this->executor->executeAsRoot('bash', ['-c', $command]);

        if (!$result->success) {
            throw new RuntimeException('Database restore failed: ' . $result->stderr);
        }

        Log::channel('commands')->info('Database restored', [
            'database' => $database->name,
            'backup_path' => $backupPath,
        ]);
    }

    /**
     * Import SQL file to a database.
     */
    public function importSql(ManagedDatabase $database, string $filePath): void
    {
        // Check if file exists
        if (!file_exists($filePath)) {
            throw new RuntimeException('Import file not found: ' . $filePath);
        }

        $extractedFile = null;

        // Handle zip files - extract and find SQL file
        if (str_ends_with(strtolower($filePath), '.zip')) {
            $extractedFile = $this->extractSqlFromZip($filePath);
            $filePath = $extractedFile;
        }

        try {
            $this->restoreDatabase($database, $filePath);
        } finally {
            // Clean up extracted file if any
            if ($extractedFile && file_exists($extractedFile)) {
                @unlink($extractedFile);
            }
        }
    }

    /**
     * Extract SQL file from a zip archive.
     */
    protected function extractSqlFromZip(string $zipPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open zip file.');
        }

        $tempDir = dirname($zipPath) . '/extracted_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            throw new RuntimeException('Failed to create temp directory for extraction.');
        }

        // Extract all files
        $zip->extractTo($tempDir);
        $zip->close();

        // Find SQL file in extracted contents
        $sqlFile = null;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if ($extension === 'sql' || $extension === 'gz') {
                $sqlFile = $file->getPathname();
                break;
            }
        }

        if (!$sqlFile) {
            // Clean up temp directory
            $this->deleteDirectory($tempDir);
            throw new RuntimeException('No SQL file found in zip archive.');
        }

        // Move SQL file to temp directory root for easier cleanup
        $newPath = dirname($zipPath) . '/import_' . uniqid() . '.' . pathinfo($sqlFile, PATHINFO_EXTENSION);
        rename($sqlFile, $newPath);

        // Clean up extracted directory
        $this->deleteDirectory($tempDir);

        // Delete original zip
        @unlink($zipPath);

        return $newPath;
    }

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * Get database tables.
     */
    public function getDatabaseTables(ManagedDatabase $database): array
    {
        $tables = DB::select("
            SELECT
                table_name,
                engine,
                table_rows,
                data_length,
                index_length,
                create_time,
                update_time
            FROM information_schema.TABLES
            WHERE table_schema = ?
            ORDER BY table_name
        ", [$database->name]);

        return array_map(fn($table) => [
            'name' => $table->table_name,
            'engine' => $table->engine,
            'rows' => (int) $table->table_rows,
            'data_size' => (int) $table->data_length,
            'index_size' => (int) $table->index_length,
            'total_size' => (int) ($table->data_length + $table->index_length),
            'created_at' => $table->create_time,
            'updated_at' => $table->update_time,
        ], $tables);
    }

    /**
     * Execute a MySQL statement.
     */
    protected function executeMySqlStatement(string $statement): void
    {
        try {
            DB::unprepared($statement);
        } catch (\Exception $e) {
            Log::channel('commands')->error('MySQL statement failed', [
                'statement' => Str::limit($statement, 200),
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('MySQL statement failed: ' . $e->getMessage());
        }
    }

    /**
     * Get username from user.
     */
    protected function getUsername(User $user): string
    {
        return $user->username ?? Str::slug($user->name, '_');
    }

    /**
     * Get default privileges list.
     */
    public function getDefaultPrivileges(): array
    {
        return $this->defaultPrivileges;
    }

    /**
     * Get all available privileges.
     */
    public function getAllPrivileges(): array
    {
        return [
            'SELECT' => 'Read data from tables',
            'INSERT' => 'Insert new rows into tables',
            'UPDATE' => 'Update existing rows in tables',
            'DELETE' => 'Delete rows from tables',
            'CREATE' => 'Create new tables or databases',
            'DROP' => 'Drop tables or databases',
            'ALTER' => 'Alter table structure',
            'INDEX' => 'Create and drop indexes',
            'CREATE TEMPORARY TABLES' => 'Create temporary tables',
            'LOCK TABLES' => 'Lock tables',
            'EXECUTE' => 'Execute stored procedures',
            'CREATE VIEW' => 'Create views',
            'SHOW VIEW' => 'Show view definitions',
            'CREATE ROUTINE' => 'Create stored routines',
            'ALTER ROUTINE' => 'Alter stored routines',
            'EVENT' => 'Create and alter events',
            'TRIGGER' => 'Create and drop triggers',
            'REFERENCES' => 'Create foreign keys',
        ];
    }

    /**
     * Change MySQL root password.
     */
    public function changeRootPassword(string $newPassword): void
    {
        $escapedPassword = addslashes($newPassword);

        // Update root password for all hosts
        $this->executeMySqlStatement("ALTER USER 'root'@'localhost' IDENTIFIED BY '{$escapedPassword}'");
        $this->executeMySqlStatement("FLUSH PRIVILEGES");

        // Update .env file with new password
        $this->updateEnvDatabasePassword($newPassword);

        // Update Laravel's database config at runtime
        config(['database.connections.mysql.password' => $newPassword]);

        Log::channel('commands')->info('MySQL root password changed');
    }

    /**
     * Update .env file with new database password.
     */
    protected function updateEnvDatabasePassword(string $newPassword): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            throw new RuntimeException('.env file not found');
        }

        $envContent = file_get_contents($envPath);
        $envContent = preg_replace(
            '/^DB_PASSWORD=.*/m',
            'DB_PASSWORD=' . $newPassword,
            $envContent
        );
        file_put_contents($envPath, $envContent);
    }

    /**
     * Get all databases from MySQL server (for sync).
     */
    public function getServerDatabases(): array
    {
        $systemDatabases = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];

        $result = DB::select("SHOW DATABASES");

        $databases = [];
        foreach ($result as $row) {
            $dbName = $row->Database;
            if (!in_array($dbName, $systemDatabases)) {
                // Get size
                $sizeResult = DB::select("
                    SELECT SUM(data_length + index_length) as size
                    FROM information_schema.TABLES
                    WHERE table_schema = ?
                ", [$dbName]);

                $databases[] = [
                    'name' => $dbName,
                    'size_bytes' => (int) ($sizeResult[0]->size ?? 0),
                    'exists_in_panel' => ManagedDatabase::where('name', $dbName)->exists(),
                ];
            }
        }

        return $databases;
    }

    /**
     * Sync databases from MySQL server to panel.
     */
    public function syncDatabasesFromServer(User $user, array $databaseNames): array
    {
        $synced = [];
        $skipped = [];

        foreach ($databaseNames as $dbName) {
            // Check if already exists in panel
            if (ManagedDatabase::where('name', $dbName)->exists()) {
                $skipped[] = $dbName;
                continue;
            }

            // Get database info
            $charsetResult = DB::select("
                SELECT default_character_set_name, default_collation_name
                FROM information_schema.SCHEMATA
                WHERE schema_name = ?
            ", [$dbName]);

            if (empty($charsetResult)) {
                $skipped[] = $dbName;
                continue;
            }

            $charset = $charsetResult[0]->default_character_set_name ?? 'utf8mb4';
            $collation = $charsetResult[0]->default_collation_name ?? 'utf8mb4_unicode_ci';

            // Get size
            $sizeResult = DB::select("
                SELECT SUM(data_length + index_length) as size
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ", [$dbName]);

            $size = (int) ($sizeResult[0]->size ?? 0);

            // Extract original name (remove user prefix if present)
            $username = $this->getUsername($user);
            $originalName = $dbName;
            if (str_starts_with($dbName, $username . '_')) {
                $originalName = substr($dbName, strlen($username) + 1);
            }

            // Create record
            ManagedDatabase::create([
                'user_id' => $user->id,
                'domain_id' => null,
                'name' => $dbName,
                'original_name' => $originalName,
                'size_bytes' => $size,
                'charset' => $charset,
                'collation' => $collation,
                'status' => 'active',
            ]);

            $synced[] = $dbName;

            Log::channel('commands')->info('Database synced from server', [
                'database' => $dbName,
                'user_id' => $user->id,
            ]);
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    /**
     * Get all database users from MySQL server (for sync).
     */
    public function getServerDatabaseUsers(): array
    {
        $systemUsers = ['root', 'mysql.sys', 'mysql.session', 'mysql.infoschema', 'debian-sys-maint', 'phpmyadmin'];

        $result = DB::select("SELECT user, host FROM mysql.user");

        $users = [];
        foreach ($result as $row) {
            if (!in_array($row->user, $systemUsers)) {
                $users[] = [
                    'username' => $row->user,
                    'host' => $row->host,
                    'exists_in_panel' => DatabaseUser::where('username', $row->user)
                        ->where('host', $row->host)
                        ->exists(),
                ];
            }
        }

        return $users;
    }

    /**
     * Get phpMyAdmin URL.
     */
    public function getPhpMyAdminUrl(): ?string
    {
        // Check common phpMyAdmin paths
        $possiblePaths = [
            '/phpmyadmin',
            '/phpMyAdmin',
            '/pma',
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir('/usr/share/phpmyadmin') || is_dir('/var/www/html/phpmyadmin')) {
                return $path;
            }
        }

        return config('vsispanel.phpmyadmin.url', '/phpmyadmin');
    }

    /**
     * Generate phpMyAdmin SSO token for auto-login.
     */
    public function generatePhpMyAdminToken(DatabaseUser $dbUser, ?ManagedDatabase $database = null): ?string
    {
        $password = $dbUser->getDecryptedPassword();
        if (empty($password)) {
            return null;
        }

        $secretKeyFile = storage_path('app/phpmyadmin_secret.key');
        if (!file_exists($secretKeyFile)) {
            throw new RuntimeException('phpMyAdmin SSO not configured: secret key not found');
        }

        $secretKey = trim(file_get_contents($secretKeyFile));

        // Build token data
        $data = [
            'user' => $dbUser->username,
            'pass' => $password,
            'host' => $dbUser->host === '%' ? 'localhost' : $dbUser->host,
            'db' => $database?->name ?? '',
            'exp' => time() + 60, // Token valid for 60 seconds
        ];

        // Sign the data
        $signature = hash_hmac('sha256', json_encode($data), $secretKey);

        // Create token
        $token = base64_encode(json_encode([
            'data' => $data,
            'sig' => $signature,
        ]));

        return $token;
    }

    /**
     * Get phpMyAdmin SSO URL for a database user.
     */
    public function getPhpMyAdminSsoUrl(DatabaseUser $dbUser, ?ManagedDatabase $database = null): ?string
    {
        $token = $this->generatePhpMyAdminToken($dbUser, $database);
        if (empty($token)) {
            return null;
        }

        return '/phpmyadmin/signon.php?token=' . urlencode($token);
    }
}
