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

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;

return static function (ContainerConfigurator $container) {
    $container->services()

        // Authentication related services
        ->set('security.authentication.manager', AuthenticationProviderManager::class)
            ->args([
                abstract_arg('providers'),
                param('security.authentication.manager.erase_credentials'),
            ])
            ->call('setEventDispatcher', [service('event_dispatcher')])
        ->alias(AuthenticationManagerInterface::class, 'security.authentication.manager')
    ;
};
