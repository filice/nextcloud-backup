<?php

namespace OCA\BackupPlugin\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCA\BackupPlugin\Service\BackupService;

class BackupController extends Controller {
    private $backupService;

    public function __construct($AppName, IRequest $request, BackupService $backupService) {
        parent::__construct($AppName, $request);
        $this->backupService = $backupService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function doBackupNow() {
        try {
            $this->backupService->performBackup();
            return ['status' => 'success'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
