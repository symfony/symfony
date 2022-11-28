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
use Symfony\Component\ImportMaps\ImportMapManager;

return static function (ContainerConfigurator $container): void {
    $container->services()

        ->set(ImportMapManager::class)
            ->args([param('kernel.project_dir').'/importmap.php', null, service('filesystem')])

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
