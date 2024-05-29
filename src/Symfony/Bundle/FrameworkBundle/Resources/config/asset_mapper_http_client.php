<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\AssetMapper\Command\ImportMapAuditCommand;
use Symfony\Component\AssetMapper\Command\ImportMapOutdatedCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JsDelivrEsmResolver;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('asset_mapper.importmap.auditor', ImportMapAuditor::class)
            ->args([
                service('asset_mapper.importmap.config_reader'),
                service('http_client'),
            ])

        ->set('asset_mapper.importmap.command.audit', ImportMapAuditCommand::class)
            ->args([service('asset_mapper.importmap.auditor')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.outdated', ImportMapOutdatedCommand::class)
            ->args([service('asset_mapper.importmap.update_checker')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.manager', ImportMapManager::class)
            ->args([
                service('asset_mapper'),
                service('asset_mapper.importmap.config_reader'),
                service('asset_mapper.importmap.remote_package_downloader'),
                service('asset_mapper.importmap.resolver'),
            ])
            ->alias(ImportMapManager::class, 'asset_mapper.importmap.manager')

        ->set('asset_mapper.importmap.remote_package_downloader', RemotePackageDownloader::class)
            ->args([
                service('asset_mapper.importmap.remote_package_storage'),
                service('asset_mapper.importmap.config_reader'),
                service('asset_mapper.importmap.resolver'),
            ])

        ->set('asset_mapper.importmap.resolver', JsDelivrEsmResolver::class)
            ->args([service('http_client')])

        ->set('asset_mapper.importmap.update_checker', ImportMapUpdateChecker::class)
            ->args([
                service('asset_mapper.importmap.config_reader'),
                service('http_client'),
            ])
    ;
};
