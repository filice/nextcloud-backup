<?php

namespace OCA\BackupPlugin\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;

class AdminSettingsController extends Controller {
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
        $fileBackupFolder = $this->config->getAppValue('backupplugin', 'file_backup_folder', '/backup/files');
        $dbBackupFolder = $this->config->getAppValue('backupplugin', 'db_backup_folder', '/backup/db');

        return new TemplateResponse('backupplugin', 'settings-admin', [
            'file_backup_folder' => $fileBackupFolder,
            'db_backup_folder' => $dbBackupFolder,
        ]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function save($fileBackupFolder, $dbBackupFolder) {
        $this->config->setAppValue('backupplugin', 'file_backup_folder', $fileBackupFolder);
        $this->config->setAppValue('backupplugin', 'db_backup_folder', $dbBackupFolder);

        return new DataResponse(['status' => 'success']);
    }

    public function saveInterval($interval) {
        $this->config->setAppValue('backupplugin', 'backup_interval', $interval);
        return ['status' => 'success'];
    }
    
}
