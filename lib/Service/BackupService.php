<?php

namespace OCA\NextcloudBackup\Service;

use OCP\IConfig;
use function OCP\Log\logger;

class BackupService {
    private $config;
    private $nextcloudRoot;

    public function __construct(IConfig $config, string $nextcloudRoot) {
        $this->config = $config;
        $this->nextcloudRoot = $nextcloudRoot;
    }


    /**
     * Ottiene il path completo del comando OCC
     */
    private function getOccCommand(): string {
        return $this->nextcloudRoot . '/occ';
    }

    /**
     * Ottiene il path della directory dei dati
     */
    private function getDataDir(): string {
        return $this->config->getSystemValue('datadirectory', $this->nextcloudRoot . '/data');
    }

    /**
     * Ottiene il path della directory di configurazione
     */
    private function getConfigDir(): string {
        return $this->nextcloudRoot . '/config';
    }

    /**
     * Ottiene il path del file di configurazione
     */
    private function getConfigFile(): string {
        return $this->getConfigDir() . '/config.php';
    }


    public function getBackupStatus() {
        // Ad esempio, salva lo stato in un file temporaneo o una chiave di configurazione
        $status = $this->config->getAppValue('nextcloud_backup', 'backup_status', 'idle'); // idle, running, success, error
        return new DataResponse(['status' => $status]);
    }

    /**
     * Abilita la modalità di manutenzione
     */
    public function enableMaintenanceMode() {
        $command = $this->getOccCommand() . ' maintenance:mode --on';
        $result = shell_exec($command);        
        logger('nextcloud_backup')->info('Modalità di manutenzione abilitata.'  . $result);

    }

    /**
     * Disabilita la modalità di manutenzione
     */
    public function disableMaintenanceMode() {
        $command = $this->getOccCommand() . ' maintenance:mode --off';
        $result = shell_exec($command);
        logger('nextcloud_backup')->info('Modalità di manutenzione disabilitata.'  . $result);
    }

    /**
     * Esegue il backup dei file
     */
    public function backupFiles() {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nextcloud');
        $dataDir = $this->getDataDir();
        $configDir = $this->getConfigDir();

        $timestamp = date('Ymd_His');
        $fileBackupPath = "$fileBackupFolder/data";
        $configBackupPath = "$fileBackupFolder/config";
        $this->config->setAppValue('nextcloud_backup', 'backup_status', 'running files');
        // Backup dei file e della configurazione
        shell_exec("rsync -a --delete $dataDir $fileBackupFolder");
        logger('nextcloud_backup')->info('Backup dei file completato: '  . $fileBackupPath);

        shell_exec("rsync -a --delete $configDir $configBackupPath");
        logger('nextcloud_backup')->info('Backup della configurazione completato: '  . $configBackupPath);

        $this->config->setAppValue('nextcloud_backup', 'backup_status', 'success files');

        return $fileBackupPath;
    }

    /**
     * Esegue il backup del database
     */
    public function backupDatabase() {
        $dbBackupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nextcloud-db');
        $configFile = $this->getConfigFile();

        // Legge le credenziali del database dal file di configurazione
        if (!file_exists($configFile)) {
            throw new \Exception("Il file di configurazione $configFile non esiste.");
        }

        $config = include $configFile;
        if (!isset($config['dbuser'], $config['dbpassword'], $config['dbname'])) {
            throw new \Exception("Le credenziali del database non sono definite nel file di configurazione.");
        }

        $dbUser = $config['dbuser'];
        $dbPass = $config['dbpassword'];
        $dbName = $config['dbname'];
        $dbHost = $config['dbhost'] ?? 'localhost';

        $timestamp = date('Ymd_His');
        $dbBackupPath = "$dbBackupFolder/nextcloud_db_$timestamp.sql";

        // Esegui il comando pg_dump
        $this->config->setAppValue('nextcloud_backup', 'backup_status', 'running db');

        putenv("PGPASSWORD=$dbPass");
        $pgDumpCommand = "pg_dump -h $dbHost -U $dbUser -d $dbName -F c > $dbBackupPath";
        shell_exec($pgDumpCommand);

        logger('nextcloud_backup')->info('Backup del database completato: '  . $dbBackupPath);

        $this->config->setAppValue('nextcloud_backup', 'backup_status', 'success db');

        return $dbBackupPath;
    }

    public function log($level, $message) {
        logger('nextcloud_backup')->$level($message);
    }
        
    
    /**
     * Esegue il backup completo (con manutenzione)
     */
    public function performBackup(): array {
        $this->enableMaintenanceMode();
    
        try {

            // Esegui il backup del database
            $dbPath = $this->backupDatabase();
                        
            // Esegui il backup dei file
            $filePaths = $this->backupFiles();
    
            $this->disableMaintenanceMode();
    
            return [
                'files' => $filePaths,
                'database' => $dbPath
            ];
        } catch (\Exception $e) {
            $this->config->setAppValue('nextcloud_backup', 'backup_status', 'error');
            $this->disableMaintenanceMode();
            throw $e;
        }
    }
}
