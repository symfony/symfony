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

use Symfony\Bundle\SecurityBundle\Controller\OidcLoginStartController;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcConfidentialClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcPublicClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\EventListener\OidcEndSessionListener;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authenticator.oidc_login', OidcLoginAuthenticator::class)
            ->abstract()
            ->args([
                service('security.http_utils'),
                abstract_arg('user provider'),
                abstract_arg('OIDC client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('ID token'),
                abstract_arg('client ID'),
                abstract_arg('authentication success handler'),
                abstract_arg('authentication failure handler'),
                abstract_arg('options'),
                abstract_arg('authorization params'),
                // replaced by the firewall verifier, unless the ID token signature is not verified
                null,
            ])

        ->set('security.authenticator.oidc_login.signature_verifier', OidcSignatureVerifier::class)
            ->abstract()
            ->args([
                abstract_arg('OIDC discovery'),
                service('cache.app'),
                service('http_client'),
                abstract_arg('signature algorithms'),
                abstract_arg('default JWKS cache TTL'),
                abstract_arg('enforce key usage verification'),
                service('clock'),
            ])

        ->set('security.authenticator.oidc_login.id_token', OidcIdToken::class)
            ->abstract()
            ->args([
                service('clock'),
                0,
            ])

        ->set('security.authenticator.oidc_login.discovery', OidcDiscovery::class)
            ->abstract()
            ->args([
                service('http_client'),
                service('cache.app'),
                // relative to the issuer below, which OidcDiscovery normalizes at runtime
                '.well-known/openid-configuration',
                abstract_arg('issuer'),
                abstract_arg('cache TTL'),
                // the cache key is derived from the configuration URL
                null,
                abstract_arg('endpoints that must be announced and must not downgrade to plain HTTP the transport of the discovery document'),
            ])

        ->set('security.authenticator.oidc_login.client', OidcConfidentialClient::class)
            ->abstract()
            ->args([
                service('http_client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('client ID'),
                abstract_arg('client secret'),
                abstract_arg('token endpoint auth method'),
            ])

        ->set('security.authenticator.oidc_login.public_client', OidcPublicClient::class)
            ->abstract()
            ->args([
                service('http_client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('client ID'),
            ])

        // the target of the routes declared for the "start_path" of each oidc_login
        // firewall; public, as the routes reference it by id as their controller
        ->set('security.authenticator.oidc_login.start_controller', OidcLoginStartController::class)
            ->public()
            ->args([
                service_locator([]),
            ])

        ->set('security.authenticator.oidc_login.end_session_listener', OidcEndSessionListener::class)
            ->abstract()
            ->args([
                abstract_arg('OIDC discovery'),
                service('security.http_utils'),
                abstract_arg('post-logout redirect path'),
                service('logger')->nullOnInvalid(),
            ])
    ;
};
