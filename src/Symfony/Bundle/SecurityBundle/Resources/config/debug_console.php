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

use Symfony\Bundle\SecurityBundle\Command\DebugFirewallCommand;
use Symfony\Bundle\SecurityBundle\Command\DebugRolesCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.command.debug_firewall', DebugFirewallCommand::class)
            ->args([
                param('security.firewalls'),
                service('security.firewall.context_locator'),
                tagged_locator('event_dispatcher.dispatcher', 'name'),
                [],
                false,
            ])
            ->tag('console.command', ['command' => 'debug:firewall'])
        ->set('security.command.debug_role_hierarchy', DebugRolesCommand::class)
            ->args([
                service('debug.security.role_hierarchy'),
            ])
            ->tag('console.command', ['command' => 'debug:roles'])
    ;
};
