<?php

namespace OCA\NextcloudBackup\Service;

use OCP\IConfig;
use PDO;
use Exception;
use function OCP\Log\logger;

class BackupService
{
    private $config;
    private $nextcloudRoot;

    private const BACKUP_STATUS_IDLE = 'idle';
    private const BACKUP_STATUS_RUNNING = 'running';
    private const BACKUP_STATUS_SUCCESS = 'success';
    private const BACKUP_STATUS_ERROR = 'error';

    private const SUPPORTED_DATABASES = [
        'mysql' => 'MySQL/MariaDB',
        'pgsql' => 'PostgreSQL',
        'sqlite3' => 'SQLite',
        'oci' => 'Oracle'
    ];

    public function __construct(IConfig $config, string $nextcloudRoot)
    {
        $this->config = $config;
        $this->nextcloudRoot = $nextcloudRoot;
    }

    private function getOccCommand(): string
    {
        return $this->nextcloudRoot . '/occ';
    }

    private function getDataDir(): string
    {
        return $this->config->getSystemValue('datadirectory');
    }

    private function getConfigDir(): string
    {
        return $this->config->getSystemValue('datadirectory') . '/../config';
    }

    private function setBackupStatus(string $status): void
    {
        logger('nextcloud_backup')->info('Setting backup status: ' . $status);
    }

    public function enableMaintenanceMode()
    {
        $command = $this->getOccCommand() . ' maintenance:mode --on';
        shell_exec($command);
        $this->setBackupStatus('maintenance_on');
    }

    public function disableMaintenanceMode()
    {
        $command = $this->getOccCommand() . ' maintenance:mode --off';
        shell_exec($command);
        $this->setBackupStatus('maintenance_off');
    }

    public function backupFiles(): string
    {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nextcloud');
        $dataDir = $this->getDataDir();
        $configDir = $this->getConfigDir();

        if (!is_dir($fileBackupFolder)) {
            if (!mkdir($fileBackupFolder, 0750, true)) {
                throw new Exception("Unable to create backup directory: $fileBackupFolder");
            }
        }

        if (!is_writable($fileBackupFolder)) {
            throw new Exception("Backup directory not writable: $fileBackupFolder");
        }

        $this->setBackupStatus('running_files');
        
        // Common exclusion list
        $excludeList = [
            '*.log',
            '*.log.*',
            'cache/',
            'tmp/',
            'files_trashbin/',
            'uploads/',
            'files_versions/'
        ];
        
        // Create temporary exclusion file
        $excludeFile = tempnam(sys_get_temp_dir(), 'rsync_exclude_');
        file_put_contents($excludeFile, implode("\n", $excludeList));

        try {
            // Optimized rsync options
            $rsyncOptions = [
                '-rlptD',           // recursive, preserve links, permissions, times, devices
                '--delete',         // remove files no longer in source
                '--delete-excluded',// also remove excluded files
                '--force',          // force removal of non-empty directories
                '--ignore-errors',  // continue even if errors occur
                '--stats',          // show statistics
                '--human-readable', // human readable sizes
                '--exclude-from=' . $excludeFile, // use exclusion file
            ];

            // Command for files
            $dataCommand = sprintf(
                'rsync %s %s/ %s/ 2>&1',
                implode(' ', $rsyncOptions),
                escapeshellarg($dataDir),
                escapeshellarg($fileBackupFolder)
            );

            // Command for configuration
            $configCommand = sprintf(
                'rsync %s %s/ %s/config/ 2>&1',
                implode(' ', $rsyncOptions),
                escapeshellarg($configDir),
                escapeshellarg($fileBackupFolder)
            );

            // Execute rsync for files
            $dataOutput = [];
            $dataReturnVar = 0;
            exec($dataCommand, $dataOutput, $dataReturnVar);

            if ($dataReturnVar > 0 && $dataReturnVar !== 24) { // 24 = some files were not transferred (permission errors)
                throw new Exception("Error during file backup: " . implode("\n", $dataOutput));
            }

            // Execute rsync for configuration
            $configOutput = [];
            $configReturnVar = 0;
            exec($configCommand, $configOutput, $configReturnVar);

            if ($configReturnVar > 0 && $configReturnVar !== 24) {
                throw new Exception("Error during configuration backup: " . implode("\n", $configOutput));
            }

            // Log results
            $logMessage = sprintf(
                "Backup completed:\nFiles: %s\nConfig: %s",
                implode("\n", $dataOutput),
                implode("\n", $configOutput)
            );
            logger('nextcloud_backup')->info($logMessage);

            $this->setBackupStatus('success_files');
            return $fileBackupFolder;

        } catch (Exception $e) {
            $this->setBackupStatus('error_files');
            throw $e;
        } finally {
            // Clean up temporary file
            if (file_exists($excludeFile)) {
                unlink($excludeFile);
            }
        }
    }

    public function backupDatabase()
    {
        $timestamp = date('Ymd_His');
        $backupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nextcloud-db');
        $backupFile = "$backupFolder/nextcloud_db_$timestamp.sql";

        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0750, true);
        }

        $dbType = $this->config->getSystemValue('dbtype', 'sqlite3');
        
        if (!array_key_exists($dbType, self::SUPPORTED_DATABASES)) {
            throw new Exception("Database type '$dbType' not supported");
        }

        $this->setBackupStatus('running_db');

        try {
            $pdo = $this->createPDOConnection($dbType);
            
            $backupData = "-- Nextcloud Database Backup\n";
            $backupData .= "-- Type: " . self::SUPPORTED_DATABASES[$dbType] . "\n";
            $backupData .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

            // Add database-specific pre-backup commands
            $backupData .= $this->getPreBackupCommands($dbType);
            
            // Export schema and data
            $backupData .= $this->exportSchema($pdo, $dbType);
            $backupData .= $this->exportData($pdo, $dbType);
            
            // Add database-specific post-backup commands
            $backupData .= $this->getPostBackupCommands($dbType);

            file_put_contents($backupFile, $backupData);
            $this->setBackupStatus('success_db');
            return $backupFile;

        } catch (Exception $e) {
            $this->setBackupStatus('error_db');
            throw new Exception("Error during database backup: " . $e->getMessage());
        }
    }

    private function createPDOConnection(string $dbType): PDO {
        $dbHost = $this->config->getSystemValue('dbhost', 'localhost');
        $dbPort = $this->config->getSystemValue('dbport', '');
        $dbName = $this->config->getSystemValue('dbname');
        $dbUser = $this->config->getSystemValue('dbuser');
        $dbPass = $this->config->getSystemValue('dbpassword');

        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $dsn = "mysql:host=$dbHost;port=" . ($dbPort ?: '3306') . ";dbname=$dbName;charset=utf8mb4";
                $options = [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ];
                break;

            case 'pgsql':
                $dsn = "pgsql:host=$dbHost;port=" . ($dbPort ?: '5432') . ";dbname=$dbName";
                $options = [];
                break;

            case 'sqlite3':
                $dsn = "sqlite:$dbName";
                $options = [];
                break;

            case 'oci':
                $dsn = "oci:dbname=//$dbHost:" . ($dbPort ?: '1521') . "/$dbName;charset=AL32UTF8";
                $options = [];
                break;

            default:
                throw new Exception("Unsupported database: $dbType");
        }

        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;

        return new PDO($dsn, $dbUser, $dbPass, $options);
    }

    private function getPreBackupCommands(string $dbType): string {
        $commands = "\n-- Pre-backup commands\n";
        
        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $commands .= "SET FOREIGN_KEY_CHECKS=0;\n";
                $commands .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
                break;
                
            case 'pgsql':
                $commands .= "SET statement_timeout = 0;\n";
                $commands .= "SET lock_timeout = 0;\n";
                $commands .= "SET client_encoding = 'UTF8';\n";
                $commands .= "SET standard_conforming_strings = on;\n";
                break;

            case 'oci':
                $commands .= "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';\n";
                $commands .= "ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS';\n";
                break;
        }

        return $commands . "\n";
    }

    private function getPostBackupCommands(string $dbType): string {
        $commands = "\n-- Post-backup commands\n";
        
        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $commands .= "SET FOREIGN_KEY_CHECKS=1;\n";
                break;
                
            case 'pgsql':
                $commands .= "SELECT pg_catalog.setval(pg_get_serial_sequence(quote_ident(table_name), quote_ident(column_name)), MAX(column_name)) FROM table_name;\n";
                break;
        }

        return $commands . "\n";
    }

    private function exportSchema(PDO $pdo, string $dbType): string {
        $schema = "\n-- Database schema\n\n";

        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    // Create table
                    $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                    $schema .= $createTable['Create Table'] . ";\n\n";
                }
                break;

            case 'pgsql':
                $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $schema .= $this->getPostgreSQLTableSchema($pdo, $table);
                }
                break;

            case 'sqlite3':
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $createTable = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'")->fetch();
                    $schema .= $createTable['sql'] . ";\n\n";
                }
                break;

            case 'oci':
                $tables = $pdo->query("SELECT table_name FROM user_tables")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    // Add logic to get Oracle table definition
                    $schema .= $this->getOracleTableSchema($pdo, $table);
                }
                break;
        }

        return $schema;
    }

    private function exportData(PDO $pdo, string $dbType): string {
        $data = "\n-- Table data\n\n";
        
        // Get table list based on database type
        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'pgsql':
                $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'sqlite3':
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            case 'oci':
                $tables = $pdo->query("SELECT table_name FROM user_tables")->fetchAll(PDO::FETCH_COLUMN);
                break;
                
            default:
                return "";
        }

        // Export data for each table
        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table, $dbType);
            $data .= "\n-- Data for table $quotedTable\n";
            
            $stmt = $pdo->query("SELECT * FROM $quotedTable");
            
            while ($row = $stmt->fetch()) {
                $columns = array_keys($row);
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    // Gestione dei tipi resource/BLOB
                    if (is_resource($value)) {
                        return 'NULL'; // oppure gestisci il BLOB in modo appropriato
                    }
                    // Converti in stringa se necessario
                    if (!is_string($value)) {
                        $value = (string)$value;
                    }
                    return $pdo->quote($value);
                }, array_values($row));

                $quotedColumns = array_map(
                    function($col) use ($dbType) { 
                        return $this->quoteIdentifier($col, $dbType); 
                    }, 
                    $columns
                );

                $data .= sprintf(
                    "INSERT INTO %s (%s) VALUES (%s);\n",
                    $quotedTable,
                    implode(', ', $quotedColumns),
                    implode(', ', $values)
                );
            }
        }

        return $data;
    }

    private function quoteIdentifier(string $identifier, string $dbType): string {
        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                return "`" . str_replace("`", "``", $identifier) . "`";
            case 'pgsql':
            case 'oci':
                return '"' . str_replace('"', '""', $identifier) . '"';
            case 'sqlite3':
                return "'" . str_replace("'", "''", $identifier) . "'";
            default:
                return $identifier;
        }
    }

    private function getPostgreSQLTableSchema(PDO $pdo, string $table): string {
        $schema = "\n-- Table: $table\n";
        
        // Get table definition
        $createTableQuery = "
            SELECT 'CREATE TABLE ' || quote_ident(c.table_name) || ' (' ||
                   string_agg(
                       quote_ident(c.column_name) || ' ' || 
                       c.data_type || 
                       CASE 
                           WHEN c.character_maximum_length IS NOT NULL 
                           THEN '(' || c.character_maximum_length || ')' 
                           ELSE '' 
                       END || 
                       CASE 
                           WHEN c.is_nullable = 'NO' 
                           THEN ' NOT NULL' 
                           ELSE '' 
                       END,
                       ', '
                   ) || ');'
            FROM information_schema.columns c
            WHERE c.table_name = :table_name
            GROUP BY c.table_name;
        ";
        
        $stmt = $pdo->prepare($createTableQuery);
        $stmt->execute(['table_name' => $table]);
        $schema .= $stmt->fetchColumn() . "\n";

        return $schema;
    }

    private function getOracleTableSchema(PDO $pdo, string $table): string {
        $schema = "\n-- Table: $table\n";
        
        // Get table definition
        $createTableQuery = "
            SELECT 'CREATE TABLE ' || quote_ident(c.table_name) || ' (' ||
                   string_agg(
                       quote_ident(c.column_name) || ' ' || 
                       c.data_type || 
                       CASE 
                           WHEN c.character_maximum_length IS NOT NULL 
                           THEN '(' || c.character_maximum_length || ')' 
                           ELSE '' 
                       END || 
                       CASE 
                           WHEN c.is_nullable = 'NO' 
                           THEN ' NOT NULL' 
                           ELSE '' 
                       END,
                       ', '
                   ) || ');'
            FROM information_schema.columns c
            WHERE c.table_name = :table_name
            GROUP BY c.table_name;
        ";
        
        $stmt = $pdo->prepare($createTableQuery);
        $stmt->execute(['table_name' => $table]);
        $schema .= $stmt->fetchColumn() . "\n";

        return $schema;
    }

    private function validateBackupPaths() {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '');
        $dbBackupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '');

        if (!$fileBackupFolder || !$dbBackupFolder) {
            throw new Exception('Backup paths are not properly configured');
        }

        if (!is_writable($fileBackupFolder) || !is_writable($dbBackupFolder)) {
            throw new Exception('Backup paths are not writable');
        }
        if (!is_writable($dbBackupFolder)) {
            throw new Exception('La cartella di backup del database non è scrivibile: ' . $dbBackupFolder);
        }        
    }

    public function performBackup(): array
    {
        try {
            $this->validateBackupPaths();
            $this->setBackupStatus(self::BACKUP_STATUS_RUNNING);
            $this->enableMaintenanceMode();

            $result = [
                'database' => $this->backupDatabase(),
                'files' => $this->backupFiles(),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->setBackupStatus(self::BACKUP_STATUS_SUCCESS);
            return $result;
        } catch (Exception $e) {
            $this->setBackupStatus(self::BACKUP_STATUS_ERROR);
            logger('nextcloud_backup')->error('Backup error: ' . $e->getMessage());
            throw $e;
        } finally {
            $this->disableMaintenanceMode();
        }
    }
}
