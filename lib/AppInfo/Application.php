<?php

namespace OCA\NextcloudBackup\AppInfo;

use OCA\NextcloudBackup\Controller\BackupController;
use OCA\NextcloudBackup\Controller\SettingsController;
use OCA\NextcloudBackup\Service\BackupService;
use OCA\NextcloudBackup\BackgroundJobs\BackupJob;
use OCP\AppFramework\App;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;
use function OCP\Log\logger;

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('nextcloud_backup', $urlParams);

        $container = $this->getContainer();
        $config = $container->get(IConfig::class);

        // Registra servizi e controller
        $this->registerServices($container, $config);
        $this->registerControllers($container);

        // Valori predefiniti
        $this->initializeDefaultConfig($config);

        // Script e stili
        $this->registerScriptsAndStyles();
    }

    private function initializeDefaultConfig(IConfig $config): void {
        $defaults = [
            'file_backup_folder' => '/backup/nextcloud',
            'db_backup_folder' => '/backup/nextcloud-db',
            'backup_interval' => '24',
            'backup_status' => 'idle'
        ];

        foreach ($defaults as $key => $value) {
            if (!$config->getAppValue('nextcloud_backup', $key, '')) {
                $config->setAppValue('nextcloud_backup', $key, $value);
            }
        }
    }

    private function registerServices($container, IConfig $config): void {
        $container->registerService(BackupService::class, function($c) use ($config) {
            $nextcloudRoot = realpath(__DIR__ . '/../../..');
            logger('nextcloud_backup')->info('Percorso root calcolato: ' . $nextcloudRoot);

            return new BackupService(
                $config,
                $nextcloudRoot
            );
        });

        $container->registerService(BackupJob::class, function($c) {
            return new BackupJob(
                $c->get(BackupService::class),
                $c->get(IConfig::class)
            );
        });
    }

    private function registerControllers($container): void {
        $container->registerService(BackupController::class, function($c) {
            return new BackupController(
                $c->getAppName(),
                $c->get(IRequest::class),
                $c->get(BackupService::class)
            );
        });

        $container->registerService(SettingsController::class, function($c) {
            return new SettingsController(
                $c->getAppName(),
                $c->get(IRequest::class),
                $c->get(IConfig::class)
            );
        });
    }

    private function registerScriptsAndStyles(): void {
        Util::addStyle('nextcloud_backup', 'admin-settings');
        Util::addScript('nextcloud_backup', 'admin-settings');
    }
}
