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

use Symfony\Bundle\SecurityBundle\Command\SecurityRoleHierarchyDumpCommand;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('security.command.role_hierarchy_dump', SecurityRoleHierarchyDumpCommand::class)
            ->args([
                service('security.role_hierarchy'),
            ])
            ->tag('console.command')
    ;
};
