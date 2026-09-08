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

use Symfony\Component\AssetMapper\Command\AssetMapperCompileCommand;
use Symfony\Component\AssetMapper\Command\CompressAssetsCommand;
use Symfony\Component\AssetMapper\Command\DebugAssetMapperCommand;
use Symfony\Component\AssetMapper\Command\ImportMapAuditCommand;
use Symfony\Component\AssetMapper\Command\ImportMapInstallCommand;
use Symfony\Component\AssetMapper\Command\ImportMapOutdatedCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRemoveCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRequireCommand;
use Symfony\Component\AssetMapper\Command\ImportMapUpdateCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('asset_mapper.command.compile', AssetMapperCompileCommand::class)
            ->args([
                service('asset_mapper.compiled_asset_mapper_config_reader'),
                service('asset_mapper'),
                service('asset_mapper.importmap.generator'),
                service('asset_mapper.local_public_assets_filesystem'),
                param('kernel.project_dir'),
                param('kernel.debug'),
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('asset_mapper.command.debug', DebugAssetMapperCommand::class)
            ->args([
                service('asset_mapper'),
                service('asset_mapper.repository'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.require', ImportMapRequireCommand::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                service('asset_mapper.importmap.version_checker'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.remove', ImportMapRemoveCommand::class)
            ->args([service('asset_mapper.importmap.manager')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.update', ImportMapUpdateCommand::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                service('asset_mapper.importmap.version_checker'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.install', ImportMapInstallCommand::class)
            ->args([
                service('asset_mapper.importmap.remote_package_downloader'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.audit', ImportMapAuditCommand::class)
            ->args([service('asset_mapper.importmap.auditor')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.outdated', ImportMapOutdatedCommand::class)
            ->args([service('asset_mapper.importmap.update_checker')])
            ->tag('console.command')

        ->set('asset_mapper.assets.command.compress', CompressAssetsCommand::class)
            ->args([service('asset_mapper.compressor')])
            ->tag('console.command')
    ;
};
