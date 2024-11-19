<?php

namespace OCA\NextcloudBackup\AppInfo;

use OCA\NextcloudBackup\Controller\BackupController;
use OCA\NextcloudBackup\Service\BackupService;
use OCA\NextcloudBackup\BackgroundJobs\BackupJob;
use OCP\AppFramework\App;
use OCP\IConfig;
use OCP\Util;
use function OCP\Log\logger;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('nextcloud_backup', $urlParams);

        $container = $this->getContainer();
        $config = $container->get(IConfig::class); // Usa `get` invece di `query`

        // Registra servizi principali
        $this->registerServices($container, $config);

        // Registra il controller per il pulsante "Backup Now"
        $this->registerController($container);

        // Valori predefiniti per il plugin
        $this->initializeDefaultConfig($config);

        // Registra script e stili
        Util::addStyle('nextcloud_backup', 'admin-settings');
        Util::addScript('nextcloud_backup', 'admin-settings');
    }

    private function initializeDefaultConfig(IConfig $config) {
        if (!$config->getAppValue('nextcloud_backup', 'file_backup_folder', '')) {
            $config->setAppValue('nextcloud_backup', 'file_backup_folder', '/backup/nextcloud');
        }

        if (!$config->getAppValue('nextcloud_backup', 'db_backup_folder', '')) {
            $config->setAppValue('nextcloud_backup', 'db_backup_folder', '/backup/nextcloud-db');
        }

        if (!$config->getAppValue('nextcloud_backup', 'backup_interval', '')) {
            $config->setAppValue('nextcloud_backup', 'backup_interval', '24'); // Intervallo di default: 24 ore
        }
    }

    private function registerServices($container, IConfig $config) {
        $container->registerService(BackupService::class, function($c) use ($config) {
            // Determina dinamicamente il percorso root di Nextcloud
            $nextcloudRoot = dirname($_SERVER['SCRIPT_FILENAME'], 2);
            logger('nextcloud_backup')->info('Percorso root calcolato: ' . $nextcloudRoot);

            return new BackupService(
                $config,
                $nextcloudRoot
            );
        });
    }

    private function registerController($container) {
        $container->registerService(BackupController::class, function($c) {
            return new BackupController(
                $c->getAppName(),
                $c->get(IConfig::class)->getServer()->getRequest(), // Usa `get` per IConfig
                $c->get(BackupService::class) // Usa `get` per BackupService
            );
        });
    }
}
