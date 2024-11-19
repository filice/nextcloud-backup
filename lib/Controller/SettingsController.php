<?php

namespace OCA\NextcloudBackup\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;

class SettingsController extends Controller {
    private $config;

    public function __construct($AppName, IRequest $request, IConfig $config) {
        parent::__construct($AppName, $request);
        $this->config = $config;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nexcloud');
        $dbBackupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nexcloud-db');
        $backupInterval = $this->config->getAppValue('nextcloud_backup', 'backup_interval', '24'); // Default: 24 ore

        return new TemplateResponse('nextcloud_backup', 'settings-admin', [
            'file_backup_folder' => $fileBackupFolder,
            'db_backup_folder' => $dbBackupFolder,
            'backup_interval' => $backupInterval,
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function save($fileBackupFolder, $dbBackupFolder) {
        $this->config->setAppValue('nextcloud_backup', 'file_backup_folder', $fileBackupFolder);
        $this->config->setAppValue('nextcloud_backup', 'db_backup_folder', $dbBackupFolder);

        return new DataResponse(['status' => 'success']);
    }

    public function saveInterval($interval) {
        $this->config->setAppValue('nextcloud_backup', 'backup_interval', $interval);
        return ['status' => 'success'];
    }
    
}
