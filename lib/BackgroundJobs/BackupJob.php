<?php

namespace OCA\NextcloudBackup\BackgroundJobs;

use OCP\BackgroundJob\TimedJob;
use OCA\NextcloudBackup\Service\BackupService;
use OCP\IConfig;
use function OCP\Log\logger;

class BackupJob extends TimedJob {
    private BackupService $backupService;
    private IConfig $config;

    public function __construct(BackupService $backupService, IConfig $config) {
        parent::__construct();
        
        $this->backupService = $backupService;
        $this->config = $config;

        $backupInterval = (int)$this->config->getAppValue('nextcloud_backup', 'backup_interval', '24');
        $this->setInterval($backupInterval * 3600);
    }

    protected function run($argument): void {
        try {
            $this->backupService->performBackup();
            logger('nextcloud_backup')->info('Backup pianificato completato con successo');
        } catch (\Exception $e) {
            logger('nextcloud_backup')->error('Errore nel backup pianificato: ' . $e->getMessage());
        }
    }
}
