<?php

namespace OCA\NextcloudBackup\BackgroundJobs;

use OCP\BackgroundJob\TimedJob;
use OCA\NextcloudBackup\Service\BackupService;
use OCP\IConfig;
use function OCP\Log\logger;

class BackupJob extends TimedJob {
    private $backupService;
    private $config;

    public function __construct(BackupService $backupService, IConfig $config) {
        $this->backupService = $backupService;
        $this->config = $config;

        // Imposta l'intervallo dinamico basato sulla configurazione
        $backupInterval = $this->config->getAppValue('nextcloud_backup', 'backup_interval', '24'); // Default: 24 ore
        $this->setInterval((int)$backupInterval * 3600); // Converti ore in secondi
    }

    protected function run($argument) {
        try {
            $this->backupService->performBackup();
            logger('nextcloud_backup')->info('Backup completato con successo dal cronjob.');
        } catch (\Exception $e) {
            logger('nextcloud_backup')->error('Errore durante l\'esecuzione del cronjob di backup: ' . $e->getMessage());
        }
    }
}
