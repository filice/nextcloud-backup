<?php

namespace OCA\BackupPlugin\AppInfo;

use OCP\AppFramework\App;
use OCP\IContainer;
use OCA\BackupPlugin\Controller\AdminSettingsController;
use OCA\BackupPlugin\Service\BackupService;
use OCP\Util;

Util::addStyle('backupplugin', 'admin-settings');
Util::addScript('backupplugin', 'admin-settings');

class Application extends App {
    public function __construct(array $urlParams = []) {
        parent::__construct('backupplugin', $urlParams);
        $container = $this->getContainer();

        $container->registerService('AdminSettingsController', function(IContainer $c) {
            return new AdminSettingsController(
                $c->getAppName(),
                $c->getServer()->getRequest(),
                $c->getServer()->getConfig()
            );
        });

        $container->registerService('BackupService', function(IContainer $c) {
            return new BackupService(
                $c->getServer()->getConfig(),
                $c->getServer()->getLogger()
            );
        });
    }
}
