<?php

namespace OCA\NextcloudBackup\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;

class SettingsController extends Controller {
    private IConfig $config;

    public function __construct(string $AppName, IRequest $request, IConfig $config) {
        parent::__construct($AppName, $request);
        $this->config = $config;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        $params = [
            'file_backup_folder' => $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nextcloud'),
            'db_backup_folder' => $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nextcloud-db'),
            'backup_interval' => $this->config->getAppValue('nextcloud_backup', 'backup_interval', '24'),
        ];

        return new TemplateResponse('nextcloud_backup', 'settings-admin', $params);
    }

    /**
     * @NoAdminRequired
     */
    public function save(string $fileBackupFolder, string $dbBackupFolder, string $backupInterval): DataResponse {
        try {
            // Validazione
            if (!$this->validatePaths($fileBackupFolder, $dbBackupFolder)) {
                throw new \Exception('Percorsi non validi');
            }

            if (!$this->validateInterval($backupInterval)) {
                throw new \Exception('Intervallo non valido');
            }

            // Salvataggio
            $this->config->setAppValue('nextcloud_backup', 'file_backup_folder', $fileBackupFolder);
            $this->config->setAppValue('nextcloud_backup', 'db_backup_folder', $dbBackupFolder);
            $this->config->setAppValue('nextcloud_backup', 'backup_interval', $backupInterval);

            return new DataResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }

    private function validatePaths(string $fileBackupFolder, string $dbBackupFolder): bool {
        return !empty($fileBackupFolder) && !empty($dbBackupFolder);
    }

    private function validateInterval(string $interval): bool {
        return is_numeric($interval) && $interval > 0;
    }
}
