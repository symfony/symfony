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
use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Provider\LdapBindAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Provider\PreAuthenticatedAuthenticationProvider;
use Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\RemoteUserAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\X509AuthenticationListener;

return static function (ContainerConfigurator $container) {
    $container->services()

        // Authentication related services
        ->set('security.authentication.manager', AuthenticationProviderManager::class)
            ->args([
                abstract_arg('providers'),
                param('security.authentication.manager.erase_credentials'),
            ])
            ->call('setEventDispatcher', [service('event_dispatcher')])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')
        ->alias(AuthenticationManagerInterface::class, 'security.authentication.manager')
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" alias is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.anonymous', AnonymousAuthenticationListener::class)
            ->args([
                service('security.untracked_token_storage'),
                abstract_arg('Key'),
                service('logger')->nullOnInvalid(),
                service('security.authentication.manager'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.provider.anonymous', AnonymousAuthenticationProvider::class)
            ->args([abstract_arg('Key')])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.form', UsernamePasswordFormAuthenticationListener::class)
            ->parent('security.authentication.listener.abstract')
            ->abstract()
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.x509', X509AuthenticationListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.manager'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('x509 user'),
                abstract_arg('x509 credentials'),
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.json', UsernamePasswordJsonAuthenticationListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.manager'),
                service('security.http_utils'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('Failure handler'),
                abstract_arg('Success Handler'),
                [], // Options
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
                service('property_accessor')->nullOnInvalid(),
            ])
            ->call('setTranslator', [service('translator')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.remote_user', RemoteUserAuthenticationListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.manager'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('REMOTE_USER server env var'),
                service('logger')->nullOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.listener.basic', BasicAuthenticationListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                service('security.authentication.manager'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('Entry Point'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.provider.dao', DaoAuthenticationProvider::class)
            ->abstract()
            ->args([
                abstract_arg('User Provider'),
                abstract_arg('User Checker'),
                abstract_arg('Provider-shared Key'),
                service('security.password_hasher_factory'),
                param('security.authentication.hide_user_not_found'),
            ])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.provider.ldap_bind', LdapBindAuthenticationProvider::class)
            ->abstract()
            ->args([
                abstract_arg('User Provider'),
                abstract_arg('UserChecker'),
                abstract_arg('Provider-shared Key'),
                abstract_arg('LDAP'),
                abstract_arg('Base DN'),
                param('security.authentication.hide_user_not_found'),
                abstract_arg('search dn'),
                abstract_arg('search password'),
            ])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')

        ->set('security.authentication.provider.pre_authenticated', PreAuthenticatedAuthenticationProvider::class)
            ->abstract()
            ->args([
                abstract_arg('User Provider'),
                abstract_arg('UserChecker'),
            ])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use the new authenticator system instead.')
    ;
};
