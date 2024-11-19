<?php

namespace OCA\NextcloudBackup\Controller;

use OCA\NextcloudBackup\Service\BackupService;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

#[NoAdminRequired]
#[NoCSRFRequired]
class BackupController extends Controller {
    private $backupService;

    public function __construct($AppName, IRequest $request, BackupService $backupService) {
        parent::__construct($AppName, $request);
        $this->backupService = $backupService;
    }

    /**
     * Esegue il backup immediato
     */
    public function doBackupNow(): DataResponse {
        try {
            // Chiamata al servizio di backup
            $result = $this->backupService->performBackup();

            // Restituisci il risultato del backup
            return new DataResponse([
                'status' => 'success',
                'message' => 'Backup completato con successo.',
                'paths' => $result
            ]);
        } catch (\Exception $e) {
            // Log dell'errore e risposta
            $this->backupService->log('error', $e->getMessage());;
            return new DataResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
