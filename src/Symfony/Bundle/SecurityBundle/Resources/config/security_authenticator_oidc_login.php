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

use Jose\Component\Core\JWK;
use Symfony\Bundle\SecurityBundle\Controller\OidcLoginStartController;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\EventListener\OidcEndSessionListener;
use Symfony\Component\Security\Http\Firewall\OidcTokenRefreshListener;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretBasic;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretJwt;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\PrivateKeyJwt;
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
                service('clock'),
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

        ->set('security.authenticator.oidc_login.client', OidcClient::class)
            ->abstract()
            ->args([
                service('http_client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('client ID'),
                abstract_arg('client authentication'),
            ])

        // the only client authentication method that has nothing to configure, so that
        // declaring a public client takes no service of its own
        ->set('security.oauth2.client_authentication.none', NoClientAuthentication::class)

        ->set('security.oauth2.client_authentication.client_secret_basic', ClientSecretBasic::class)
            ->abstract()
            ->args([
                abstract_arg('client secret'),
            ])

        ->set('security.oauth2.client_authentication.client_secret_post', ClientSecretPost::class)
            ->abstract()
            ->args([
                abstract_arg('client secret'),
            ])

        ->set('security.oauth2.client_authentication.client_secret_jwt', ClientSecretJwt::class)
            ->abstract()
            ->args([
                abstract_arg('client secret'),
                abstract_arg('signature algorithm'),
                abstract_arg('assertion lifetime'),
                service('clock'),
            ])

        ->set('security.oauth2.client_authentication.private_key_jwt', PrivateKeyJwt::class)
            ->abstract()
            ->args([
                abstract_arg('client signing key'),
                abstract_arg('signature algorithm'),
                abstract_arg('assertion lifetime'),
                service('clock'),
            ])

        // the private key of the "private_key_jwt" method, parsed from the JSON-encoded JWK
        // the firewall configures, as the "oidc" access token handler parses its own keyset
        ->set('security.oauth2.client_authentication.private_key_jwt.signing_key', JWK::class)
            ->abstract()
            ->factory([JWK::class, 'createFromJson'])
            ->args([
                abstract_arg('JSON-encoded JWK'),
            ])

        // the target of the routes declared for the "start_path" of each oidc_login
        // firewall; public, as the routes reference it by id as their controller
        ->set('security.authenticator.oidc_login.start_controller', OidcLoginStartController::class)
            ->public()
            ->args([
                service_locator([]),
            ])

        ->set('security.authenticator.oidc_login.token_refresher', OidcTokenRefresher::class)
            ->abstract()
            ->args([
                abstract_arg('OIDC client'),
                abstract_arg('OIDC discovery'),
                abstract_arg('ID token'),
                abstract_arg('client ID'),
                // replaced by the firewall verifier, unless the ID token signature is not verified
                null,
                abstract_arg('leeway'),
                service('clock'),
            ])

        ->set('security.authenticator.oidc_login.token_refresh_listener', OidcTokenRefreshListener::class)
            ->abstract()
            ->args([
                service('security.token_storage'),
                abstract_arg('OIDC token refresher'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

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
