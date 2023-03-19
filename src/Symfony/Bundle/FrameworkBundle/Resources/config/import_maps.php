<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * @author Kévin Dunglas <kevin@dunglas.fr>
 */

use Symfony\Component\ImportMaps\Command\AbstractCommand;
use Symfony\Component\ImportMaps\Command\ExportCommand;
use Symfony\Component\ImportMaps\Command\RemoveCommand;
use Symfony\Component\ImportMaps\Command\RequireCommand;
use Symfony\Component\ImportMaps\Command\UpdateCommand;
use Symfony\Component\ImportMaps\Controller\ImportmapController;
use Symfony\Component\ImportMaps\ImportMapManager;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set(ImportmapController::class)
            ->args([
                abstract_arg('javascript directory'),
                service(ImportMapManager::class),
                service('filesystem'),
            ])
            ->public()

        ->set(ImportMapManager::class)
            ->args([
                abstract_arg('importmap.php path'),
                abstract_arg('assets directory'),
                abstract_arg('public assets directory'),
                abstract_arg('assets URL'),
                abstract_arg('provider'),
                service('http_client')->nullOnInvalid(),
                abstract_arg('JSPM API URL'),
                service('filesystem'),
            ])

        ->set(AbstractCommand::class)
            ->abstract()
            ->args([service(ImportMapManager::class)])

        ->set(RequireCommand::class)
            ->parent(AbstractCommand::class)
            ->tag('console.command')

        ->set(RemoveCommand::class)
            ->parent(AbstractCommand::class)
            ->tag('console.command')

        ->set(UpdateCommand::class)
            ->parent(AbstractCommand::class)
            ->tag('console.command')

        ->set(ExportCommand::class)
            ->parent(AbstractCommand::class)
            ->tag('console.command')

    ;
};
