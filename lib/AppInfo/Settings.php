<?php

namespace OCA\NextcloudBackup\AppInfo;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Settings implements ISettings {
    private $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    public function getForm() {
        $fileBackupFolder = $this->config->getAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nexcloud');
        $dbBackupFolder = $this->config->getAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nexcloud-db');
        $backupInterval = $this->config->getAppValue('nextcloud_backup', 'backup_interval', '24');

        // Usa TemplateResponse per caricare il template
        return new TemplateResponse('nextcloud_backup', 'settings-admin', [
            'file_backup_folder' => $fileBackupFolder,
            'db_backup_folder' => $dbBackupFolder,
            'backup_interval' => $backupInterval,
        ]);
    }

    public function getSection() {
        return 'security'; // ID unico della sezione
    }

    public function getPriority() {
        return 6; // Priorità di ordinamento
    }
}
