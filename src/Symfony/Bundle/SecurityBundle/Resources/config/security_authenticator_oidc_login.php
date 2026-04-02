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

use Symfony\Component\Security\Http\Authenticator\Oidc\OidcAuthorizationCodeFlowState;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\EventListener\OidcEndSessionListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authenticator.oidc_login', OidcLoginAuthenticator::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                abstract_arg('OIDC client'),
                abstract_arg('authentication success handler'),
                abstract_arg('authentication failure handler'),
                abstract_arg('options'),
            ])

        ->set('security.authenticator.oidc_login.discovery', OidcDiscovery::class)
            ->abstract()
            ->args([
                service('http_client'),
                service('cache.app'),
                abstract_arg('OpenID configuration URL'),
                abstract_arg('cache TTL'),
            ])

        ->set('security.authenticator.oidc_login.state_storage', OidcAuthorizationCodeFlowState::class)
            ->abstract()
            ->args([
                service('request_stack'),
                abstract_arg('firewall name'),
            ])

        ->set('security.authenticator.oidc_login.client', OidcClient::class)
            ->abstract()
            ->args([
                service('http_client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('state storage'),
                abstract_arg('client ID'),
                abstract_arg('client secret'),
                abstract_arg('callback URL'),
                abstract_arg('scopes'),
                abstract_arg('PKCE enabled'),
                abstract_arg('PKCE method'),
                abstract_arg('token endpoint auth method'),
            ])

        ->set('security.authenticator.oidc_login.end_session_listener', OidcEndSessionListener::class)
            ->abstract()
            ->args([
                abstract_arg('OIDC client'),
                service('security.http_utils'),
                abstract_arg('post-logout redirect path'),
            ])
    ;
};
