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
        return $this->config->getSystemValue('datadirectory') . '../config';
    }

    public function getBackupStatus(): array
    {
        $status = $this->config->getAppValue('nextcloud_backup', 'backup_status', 'idle');
        return ['status' => $status];
    }

    public function setBackupStatus(string $status): void
    {
        logger('nextcloud_backup')->info($status);
        $this->config->setAppValue('nextcloud_backup', 'backup_status', $status);
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

    public function backupFiles()
    {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nextcloud');
        $dataDir = $this->getDataDir();
        $configDir = $this->getConfigDir();

        $fileBackupPath = "$fileBackupFolder";
        $configBackupPath = "$fileBackupFolder/config";

        $this->setBackupStatus('running_files');

        shell_exec("rsync -Aavx --delete $dataDir $fileBackupPath");
        shell_exec("rsync -Aavx --delete $configDir $configBackupPath");

        $this->setBackupStatus('success_files');
        return $fileBackupPath;
    }

    public function backupDatabase()
    {
        $timestamp = date('Ymd_His');
        $backupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nextcloud-db');
        $backupFile = "$backupFolder/nextcloud_db_$timestamp.sql";

        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0750, true);
        }

        $dbHost = $this->config->getSystemValue('dbhost', 'localhost');
        $dbPort = $this->config->getSystemValue('dbport', '5432');
        $dbName = $this->config->getSystemValue('dbname');
        $dbUser = $this->config->getSystemValue('dbuser');
        $dbPass = $this->config->getSystemValue('dbpassword');

        if (!$dbHost || !$dbName || !$dbUser || !$dbPass) {
            throw new Exception("Le credenziali del database non sono definite correttamente nella configurazione di sistema.");
        }

        $this->setBackupStatus('running_db');

        try {
            $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $backupData = $this->exportTables($pdo);
            $backupData .= $this->exportConstraints($pdo);
            $backupData .= $this->exportIndices($pdo);

            file_put_contents($backupFile, $backupData);

            $this->setBackupStatus('success_db');
            return $backupFile;
        } catch (Exception $e) {
            $this->setBackupStatus('error_db');
            throw new Exception("Errore durante il backup del database: " . $e->getMessage());
        }
    }

    private function exportTables(PDO $pdo): string
    {
        $tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public';")->fetchAll(PDO::FETCH_COLUMN);
        $backupData = '';

        foreach ($tables as $table) {
            $createTableQuery = "
                SELECT 'CREATE TABLE ' || quote_ident(c.table_name) || ' (' ||
                       string_agg(quote_ident(c.column_name) || ' ' || c.data_type || 
                                  CASE WHEN c.character_maximum_length IS NOT NULL 
                                       THEN '(' || c.character_maximum_length || ')' 
                                       ELSE '' END, ', ') || ');'
                FROM information_schema.columns c
                WHERE c.table_name = :table_name
                GROUP BY c.table_name;
            ";
            $stmt = $pdo->prepare($createTableQuery);
            $stmt->execute(['table_name' => $table]);
            $createTable = $stmt->fetchColumn();

            $backupData .= "\n-- Table: $table\n";
            $backupData .= $createTable . ";\n";

            $rows = $pdo->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $columns = implode(", ", array_keys($row));
                $values = implode(", ", array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row)));
                $backupData .= "INSERT INTO \"$table\" ($columns) VALUES ($values);\n";
            }
        }

        return $backupData;
    }

    private function exportConstraints(PDO $pdo): string
    {
        $constraintsQuery = "
            SELECT conname, pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE conrelid IN (SELECT oid FROM pg_class WHERE relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public'));
        ";
        $constraints = $pdo->query($constraintsQuery)->fetchAll(PDO::FETCH_ASSOC);
        $backupData = '';

        foreach ($constraints as $constraint) {
            $backupData .= "\n-- Constraint: {$constraint['conname']}\n";
            $backupData .= "ALTER TABLE ONLY {$constraint['definition']};\n";
        }

        return $backupData;
    }

    private function exportIndices(PDO $pdo): string
    {
        $indicesQuery = "
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = 'public';
        ";
        $indices = $pdo->query($indicesQuery)->fetchAll(PDO::FETCH_ASSOC);
        $backupData = '';

        foreach ($indices as $index) {
            $backupData .= "\n-- Index: {$index['indexname']}\n";
            $backupData .= "{$index['indexdef']};\n";
        }

        return $backupData;
    }

    public function performBackup(): array
    {
        $this->enableMaintenanceMode();

        try {
            $dbPath = $this->backupDatabase();
            $filePaths = $this->backupFiles();
            $this->disableMaintenanceMode();

            return [
                'files' => $filePaths,
                'database' => $dbPath,
                'status' => $this->getBackupStatus(),
            ];
        } catch (Exception $e) {
            $this->disableMaintenanceMode();
            throw $e;
        }
    }
}
