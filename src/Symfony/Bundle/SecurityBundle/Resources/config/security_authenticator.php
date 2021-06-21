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

use Symfony\Bundle\SecurityBundle\Security\UserAuthenticator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\RemoteUserAuthenticator;
use Symfony\Component\Security\Http\Authenticator\X509Authenticator;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\EventListener\CheckCredentialsListener;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;
use Symfony\Component\Security\Http\EventListener\PasswordMigratingListener;
use Symfony\Component\Security\Http\EventListener\SessionStrategyListener;
use Symfony\Component\Security\Http\EventListener\UserCheckerListener;
use Symfony\Component\Security\Http\EventListener\UserProviderListener;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;

return static function (ContainerConfigurator $container) {
    $container->services()

        // Manager
        ->set('security.authenticator.manager', AuthenticatorManager::class)
            ->abstract()
            ->args([
                abstract_arg('authenticators'),
                service('security.token_storage'),
                service('event_dispatcher'),
                abstract_arg('provider key'),
                service('logger')->nullOnInvalid(),
                param('security.authentication.manager.erase_credentials'),
                param('security.authentication.hide_user_not_found'),
                abstract_arg('required badges'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.managers_locator', ServiceLocator::class)
            ->args([[]])

        ->set('security.user_authenticator', UserAuthenticator::class)
            ->args([
                service('security.firewall.map'),
                service('security.authenticator.managers_locator'),
                service('request_stack'),
            ])
        ->alias(UserAuthenticatorInterface::class, 'security.user_authenticator')

        ->set('security.firewall.authenticator', AuthenticatorManagerListener::class)
            ->abstract()
            ->args([
                abstract_arg('authenticator manager'),
            ])

        // Listeners
        ->set('security.listener.check_authenticator_credentials', CheckCredentialsListener::class)
            ->args([
               service('security.password_hasher_factory'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('security.listener.user_provider', UserProviderListener::class)
            ->args([
                service('security.user_providers'),
            ])
            ->tag('kernel.event_listener', ['event' => CheckPassportEvent::class, 'priority' => 1024, 'method' => 'checkPassport'])

        ->set('security.listener.user_provider.abstract', UserProviderListener::class)
            ->abstract()
            ->args([
                abstract_arg('user provider'),
            ])

        ->set('security.listener.password_migrating', PasswordMigratingListener::class)
            ->args([
                service('security.password_hasher_factory'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('security.listener.user_checker', UserCheckerListener::class)
            ->abstract()
            ->args([
                abstract_arg('user checker'),
            ])

        ->set('security.listener.session', SessionStrategyListener::class)
            ->abstract()
            ->args([
                service('security.authentication.session_strategy'),
            ])

        ->set('security.listener.login_throttling', LoginThrottlingListener::class)
            ->abstract()
            ->args([
                service('request_stack'),
                abstract_arg('request rate limiter'),
            ])

        // Authenticators
        ->set('security.authenticator.http_basic', HttpBasicAuthenticator::class)
            ->abstract()
            ->args([
                abstract_arg('realm name'),
                abstract_arg('user provider'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.form_login', FormLoginAuthenticator::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                abstract_arg('user provider'),
                abstract_arg('authentication success handler'),
                abstract_arg('authentication failure handler'),
                abstract_arg('options'),
            ])

        ->set('security.authenticator.json_login', JsonLoginAuthenticator::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                abstract_arg('user provider'),
                abstract_arg('authentication success handler'),
                abstract_arg('authentication failure handler'),
                abstract_arg('options'),
                service('property_accessor')->nullOnInvalid(),
            ])
            ->call('setTranslator', [service('translator')->ignoreOnInvalid()])

        ->set('security.authenticator.x509', X509Authenticator::class)
            ->abstract()
            ->args([
                abstract_arg('user provider'),
                service('security.token_storage'),
                abstract_arg('firewall name'),
                abstract_arg('user key'),
                abstract_arg('credentials key'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.remote_user', RemoteUserAuthenticator::class)
            ->abstract()
            ->args([
                abstract_arg('user provider'),
                service('security.token_storage'),
                abstract_arg('firewall name'),
                abstract_arg('user key'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
    ;
};
