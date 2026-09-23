<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Oidc;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClientInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokenRefresher;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\AbstractClientAssertion;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientAuthenticationInterface;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretBasic;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\NoClientAuthentication;

/**
 * The OIDC tokens, the authorization code, the PKCE verifier and the token request
 * body are credentials: PHP keeps the arguments marked #[\SensitiveParameter] out of
 * the stack traces an exception carries, which error pages and the profiler display.
 */
class OidcSensitiveParameterTest extends TestCase
{
    #[DataProvider('provideCredentialParameters')]
    public function testTheCredentialParametersAreSensitive(string $class, string $method, array $parameters)
    {
        $sensitiveParameters = [];
        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
            if ($parameter->getAttributes(\SensitiveParameter::class)) {
                $sensitiveParameters[] = $parameter->getName();
            }
        }

        $this->assertSame($parameters, $sensitiveParameters);
    }

    public static function provideCredentialParameters(): iterable
    {
        yield [OidcClientInterface::class, 'exchangeCode', ['code', 'codeVerifier']];
        yield [OidcClientInterface::class, 'refreshToken', ['refreshToken']];
        yield [OidcClientInterface::class, 'fetchUserInfo', ['accessToken']];
        yield [OidcClient::class, 'exchangeCode', ['code', 'codeVerifier']];
        yield [OidcClient::class, 'refreshToken', ['refreshToken']];
        yield [OidcClient::class, 'fetchUserInfo', ['accessToken']];
        yield [OidcClient::class, 'createTokenRequestOptions', ['body']];
        yield [OidcIdToken::class, 'decode', ['jwt']];
        yield [OidcSignatureVerifier::class, 'verify', ['idToken']];
        yield [OidcTokenRefresher::class, 'verifyRefreshedIdToken', ['idToken']];
        yield [OidcLoginAuthenticator::class, 'exchangeAuthorizationCode', ['code', 'codeVerifier']];
        yield [OidcLoginAuthenticator::class, 'fetchUserClaims', ['accessToken']];
        yield [OidcLoginAuthenticator::class, 'deriveCodeChallenge', ['codeVerifier']];
        yield [ClientAuthenticationInterface::class, 'authenticate', ['options']];
        yield [ClientSecretBasic::class, 'authenticate', ['options']];
        yield [ClientSecretPost::class, 'authenticate', ['options']];
        yield [NoClientAuthentication::class, 'authenticate', ['options']];
        yield [AbstractClientAssertion::class, 'authenticate', ['options']];
    }
}
