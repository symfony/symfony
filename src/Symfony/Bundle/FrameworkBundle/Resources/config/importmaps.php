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

use Symfony\Component\ImportMaps\Command\AbstractCommand;
use Symfony\Component\ImportMaps\Command\ExportCommand;
use Symfony\Component\ImportMaps\Command\RemoveCommand;
use Symfony\Component\ImportMaps\Command\RequireCommand;
use Symfony\Component\ImportMaps\Command\UpdateCommand;
use Symfony\Component\ImportMaps\Controller\ImportmapController;
use Symfony\Component\ImportMaps\ImportMapManager;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set('importmaps.controller', ImportmapController::class)
            ->args([
                abstract_arg('assets directory'),
                service('importmaps.manager'),
            ])
            ->public()

        ->set('importmaps.manager', ImportMapManager::class)
            ->args([
                abstract_arg('importmap.php path'),
                abstract_arg('assets directory'),
                abstract_arg('public assets directory'),
                abstract_arg('assets URL'),
                abstract_arg('provider'),
                abstract_arg('debug'),
            ])

        ->set('importmaps.command.require', RequireCommand::class)
            ->args([service('importmaps.manager')])
            ->tag('console.command')

        ->set('importmaps.command.remove', RemoveCommand::class)
            ->args([service('importmaps.manager')])
            ->tag('console.command')

        ->set('importmaps.command.update', UpdateCommand::class)
            ->args([service('importmaps.manager')])
            ->tag('console.command')

        ->set('importmaps.command.export', ExportCommand::class)
            ->args([service('importmaps.manager')])
            ->tag('console.command')

    ;
};
