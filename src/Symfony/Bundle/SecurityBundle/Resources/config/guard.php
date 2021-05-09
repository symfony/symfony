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

use Symfony\Component\Security\Guard\Firewall\GuardAuthenticationListener;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Guard\Provider\GuardAuthenticationProvider;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authentication.guard_handler', GuardAuthenticatorHandler::class)
            ->args([
                service('security.token_storage'),
                service('event_dispatcher')->nullOnInvalid(),
                abstract_arg('stateless firewall keys'),
            ])
            ->call('setSessionAuthenticationStrategy', [service('security.authentication.session_strategy')])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->alias(GuardAuthenticatorHandler::class, 'security.authentication.guard_handler')
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" alias is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.provider.guard', GuardAuthenticationProvider::class)
            ->abstract()
            ->args([
                abstract_arg('Authenticators'),
                abstract_arg('User Provider'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('User Checker'),
                service('security.password_hasher'),
            ])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.guard', GuardAuthenticationListener::class)
            ->abstract()
            ->args([
                service('security.authentication.guard_handler'),
                service('security.authentication.manager'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('Authenticators'),
                service('logger')->nullOnInvalid(),
                param('security.authentication.hide_user_not_found'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')
    ;
};
