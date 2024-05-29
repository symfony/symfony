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

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\Authentication\CustomAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\CustomAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\EventListener\ClearSiteDataLogoutListener;
use Symfony\Component\Security\Http\EventListener\CookieClearingLogoutListener;
use Symfony\Component\Security\Http\EventListener\DefaultLogoutListener;
use Symfony\Component\Security\Http\EventListener\SessionLogoutListener;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\ChannelListener;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('security.channel_listener', ChannelListener::class)
            ->args([
                service('security.access_map'),
                service('logger')->nullOnInvalid(),
                inline_service('int')->factory([service('router.request_context'), 'getHttpPort']),
                inline_service('int')->factory([service('router.request_context'), 'getHttpsPort']),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.access_map', AccessMap::class)

        ->set('security.context_listener', ContextListener::class)
            ->args([
                service('security.untracked_token_storage'),
                [],
                abstract_arg('Provider Key'),
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
                service('security.authentication.trust_resolver'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.logout_listener', LogoutListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.http_utils'),
                abstract_arg('event dispatcher'),
                [], // Options
            ])

        ->set('security.logout.listener.session', SessionLogoutListener::class)
            ->abstract()

        ->set('security.logout.listener.clear_site_data', ClearSiteDataLogoutListener::class)
            ->abstract()

        ->set('security.logout.listener.cookie_clearing', CookieClearingLogoutListener::class)
            ->abstract()

        ->set('security.logout.listener.default', DefaultLogoutListener::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                abstract_arg('target url'),
            ])

        ->set('security.authentication.listener.abstract')
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.manager'),
                service('security.authentication.session_strategy'),
                service('security.http_utils'),
                abstract_arg('Provider-shared Key'),
                service('security.authentication.success_handler'),
                service('security.authentication.failure_handler'),
                [],
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authentication.custom_success_handler', CustomAuthenticationSuccessHandler::class)
            ->abstract()
            ->args([
                abstract_arg('The custom success handler service'),
                [], // Options
                abstract_arg('Provider-shared Key'),
            ])

        ->set('security.authentication.success_handler', DefaultAuthenticationSuccessHandler::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                [], // Options
                service('logger')->nullOnInvalid(),
            ])

        ->set('security.authentication.custom_failure_handler', CustomAuthenticationFailureHandler::class)
            ->abstract()
            ->args([
                abstract_arg('The custom failure handler service'),
                [], // Options
            ])

        ->set('security.authentication.failure_handler', DefaultAuthenticationFailureHandler::class)
            ->abstract()
            ->args([
                service('http_kernel'),
                service('security.http_utils'),
                [], // Options
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.exception_listener', ExceptionListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.trust_resolver'),
                service('security.http_utils'),
                abstract_arg('Provider-shared Key'),
                service('security.authentication.entry_point')->nullOnInvalid(),
                param('security.access.denied_url'),
                service('security.access.denied_handler')->nullOnInvalid(),
                service('logger')->nullOnInvalid(),
                false, // Stateless
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authentication.switchuser_listener', SwitchUserListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                abstract_arg('User Provider'),
                abstract_arg('User Checker'),
                abstract_arg('Provider Key'),
                service('security.access.decision_manager'),
                service('logger')->nullOnInvalid(),
                '_switch_user',
                'ROLE_ALLOWED_TO_SWITCH',
                service('event_dispatcher')->nullOnInvalid(),
                false, // Stateless
                service('router')->nullOnInvalid(),
                abstract_arg('Target Route'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.access_listener', AccessListener::class)
            ->args([
                service('security.token_storage'),
                service('security.access.decision_manager'),
                service('security.access_map'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.firewall.event_dispatcher_locator', ServiceLocator::class)
            ->args([[]])
    ;
};
