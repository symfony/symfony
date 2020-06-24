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

use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\RememberMe\InMemoryTokenProvider;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\PersistentTokenBasedRememberMeServices;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;
use Symfony\Component\Security\Http\RememberMe\TokenBasedRememberMeServices;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authentication.listener.rememberme', RememberMeListener::class)
            ->abstract()
            ->args([
                service('security.untracked_token_storage'),
                service('security.authentication.rememberme'),
                service('security.authentication.manager'),
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
                abstract_arg('Catch exception flag set in RememberMeFactory'),
                service('security.authentication.session_strategy'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authentication.provider.rememberme', RememberMeAuthenticationProvider::class)
            ->abstract()
            ->args([abstract_arg('User Checker')])

        ->set('security.rememberme.token.provider.in_memory', InMemoryTokenProvider::class)

        ->set('security.authentication.rememberme.services.abstract')
            ->abstract()
            ->args([
                [], // User Providers
                abstract_arg('Shared Token Key'),
                abstract_arg('Shared Provider Key'),
                [], // Options
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authentication.rememberme.services.persistent', PersistentTokenBasedRememberMeServices::class)
            ->parent('security.authentication.rememberme.services.abstract')
            ->abstract()

        ->set('security.authentication.rememberme.services.simplehash', TokenBasedRememberMeServices::class)
            ->parent('security.authentication.rememberme.services.abstract')
            ->abstract()

        ->set('security.rememberme.response_listener', ResponseListener::class)
            ->tag('kernel.event_subscriber')
    ;
};
