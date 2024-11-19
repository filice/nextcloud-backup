<?php

namespace OCA\BackupPlugin\BackgroundJobs;

use OCP\BackgroundJob\TimedJob;
use OCA\BackupPlugin\Service\BackupService;

class BackupJob extends TimedJob {
    private $backupService;

    public function __construct(BackupService $backupService) {
        $this->backupService = $backupService;
    }

    protected function run($argument) {
        $interval = $this->backupService->getBackupInterval(); // Legge l'intervallo salvato
        $lastRun = $this->backupService->getLastBackupTime();

        if ((time() - $lastRun) >= ($interval * 3600)) { // Verifica se è ora di eseguire
            $this->backupService->performBackup();
            $this->backupService->setLastBackupTime(time());
        }
    }
}
