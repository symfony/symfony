<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A128GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\ECDHES;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer as JweCompactSerializer;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AccessTokenBundle\Security\Handler\IntrospectionResponseFactory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

class AccessTokenTest extends AbstractWebTestCase
{
    public function testNoTokenHandlerConfiguredShouldFail()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "token_handler" under "security.firewalls.main.access_token" must be configured.');
        $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_no_handler.yml']);
    }

    public function testNoTokenExtractorsConfiguredShouldFail()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "security.firewalls.main.access_token.token_extractors" should have at least 1 element(s) defined.');
        $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_no_extractors.yml']);
    }

    public function testProtectedResourceMetadataIsServedAndAdvertised()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_resource_metadata.yml']);

        $client->request('GET', '/foo', server: ['HTTP_AUTHORIZATION' => 'Bearer INVALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials.",resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $response->headers->get('WWW-Authenticate'));

        // the primary RFC 9728 discovery flow: a client holding no token yet is told where
        // the document is, which the firewall can only answer as its entry point
        $client->request('GET', '/foo');
        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $response->headers->get('WWW-Authenticate'));

        $client->request('GET', '/.well-known/oauth-protected-resource');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'resource' => 'http://localhost',
            'authorization_servers' => ['https://accounts.example.com'],
            'scopes_supported' => ['profile', 'email'],
            'bearer_methods_supported' => ['header', 'query'],
            'resource_name' => 'My API',
            'resource_documentation' => 'https://api.example.com/docs',
        ], json_decode($response->getContent(), true));
    }

    public function testNoProtectedResourceMetadataRouteWithoutTheConfiguration()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_default.yml']);

        $client->request('GET', '/.well-known/oauth-protected-resource');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testAccessControlGrantedOnTheScopesTheTokenCarries()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SCOPED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    /**
     * RFC 9728 §5.1 does not restrict "resource_metadata" to a 401, and a client denied for a missing
     * scope holds a token from an authorization server it may have to find again.
     */
    public function testTheInsufficientScopeChallengeAdvertisesTheResourceMetadata()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope_metadata.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="openid profile:read",resource_metadata="http://localhost/.well-known/oauth-protected-resource"', $response->headers->get('WWW-Authenticate'));
    }

    public function testAccessControlDeniedOnAMissingScope()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="openid profile:read"', $response->headers->get('WWW-Authenticate'));
    }

    /**
     * The scope challenge stands in only for the denials no handler of the application already
     * answers, so an application-wide access denied URL keeps being rendered.
     */
    public function testTheApplicationWideAccessDeniedUrlStillApplies()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope_denied_url.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(['message' => 'Welcome anonymous!'], json_decode($response->getContent(), true));
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    /**
     * A denial no scope took part in is handed back to the handler the application registered,
     * while a denial on a scope still gets the RFC 6750 challenge.
     */
    public function testTheApplicationWideAccessDeniedHandlerStillAnswersTheDenialsItUsedTo()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope_app_handler.yml']);

        $client->request('GET', '/bar', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['message' => 'Denied by the application.'], json_decode($response->getContent(), true));
        $this->assertFalse($response->headers->has('WWW-Authenticate'));

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="openid profile:read"', $response->headers->get('WWW-Authenticate'));
    }

    public function testIsGrantedDeniedOnAMissingScope()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope.yml']);
        $client->request('GET', '/scoped', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SCOPED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="profile:write"', $response->headers->get('WWW-Authenticate'));
    }

    public function testIsGrantedDeniedOnOneOfTheScopesAnAttributeRequires()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope.yml']);
        $client->request('GET', '/all-scopes', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SCOPED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="insufficient_scope",error_description="The request requires higher privileges than provided by the access token.",scope="openid profile:write"', $response->headers->get('WWW-Authenticate'));
    }

    public function testADenialNoScopeTookPartInKeepsThePlainForbiddenResponse()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_scope.yml']);
        $client->request('GET', '/bar', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testAnonymousAccessIsGranted()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_anonymous.yml']);
        $client->request('GET', '/bar');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome anonymous!'], json_decode($response->getContent(), true));
    }

    public function testDefaultFormEncodedBodySuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_default.yml']);
        $client->request('POST', '/foo', ['access_token' => 'VALID_ACCESS_TOKEN'], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('defaultFormEncodedBodyFailureData')]
    public function testDefaultFormEncodedBodyFailure(array $parameters, array $headers)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_default.yml']);
        $client->request('POST', '/foo', $parameters, [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    public function testDefaultMissingFormEncodedBodyFail()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_default.yml']);
        $client->request('GET', '/foo');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCustomFormEncodedBodySuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_custom.yml']);
        $client->request('POST', '/foo', ['secured_token' => 'VALID_ACCESS_TOKEN'], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Good game @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('customFormEncodedBodyFailure')]
    public function testCustomFormEncodedBodyFailure(array $parameters, array $headers)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_custom.yml']);
        $client->request('POST', '/foo', $parameters, [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'Something went wrong'], json_decode($response->getContent(), true));
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testCustomMissingFormEncodedBodyShouldFail()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_body_custom.yml']);
        $client->request('POST', '/foo');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public static function defaultFormEncodedBodyFailureData(): iterable
    {
        yield [['access_token' => 'INVALID_ACCESS_TOKEN'], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']];
    }

    public static function customFormEncodedBodyFailure(): iterable
    {
        yield [['secured_token' => 'INVALID_ACCESS_TOKEN'], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']];
    }

    public function testDefaultHeaderAccessTokenSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_default.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    public function testMultipleAccessTokenExtractorSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_multiple_extractors.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('defaultHeaderAccessTokenFailureData')]
    public function testDefaultHeaderAccessTokenFailure(array $headers)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_default.yml']);
        $client->request('GET', '/foo', [], [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    #[DataProvider('defaultMissingHeaderAccessTokenFailData')]
    public function testDefaultMissingHeaderAccessTokenFail(array $headers)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_default.yml']);
        $client->request('GET', '/foo', [], [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCustomHeaderAccessTokenSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_custom.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_X_AUTH_TOKEN' => 'VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Good game @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('customHeaderAccessTokenFailure')]
    public function testCustomHeaderAccessTokenFailure(array $headers, int $errorCode)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_custom.yml']);
        $client->request('GET', '/foo', [], [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($errorCode, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    #[DataProvider('customMissingHeaderAccessTokenShouldFail')]
    public function testCustomMissingHeaderAccessTokenShouldFail(array $headers)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_custom.yml']);
        $client->request('GET', '/foo', [], [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public static function defaultHeaderAccessTokenFailureData(): iterable
    {
        yield [['HTTP_AUTHORIZATION' => 'Bearer INVALID_ACCESS_TOKEN']];
    }

    public static function defaultMissingHeaderAccessTokenFailData(): iterable
    {
        yield [['HTTP_AUTHORIZATION' => 'JWT INVALID_TOKEN_TYPE']];
        yield [['HTTP_X_FOO' => 'Missing-Header']];
        yield [['HTTP_X_AUTH_TOKEN' => 'this is not a token']];
    }

    public static function customHeaderAccessTokenFailure(): iterable
    {
        yield [['HTTP_X_AUTH_TOKEN' => 'INVALID_ACCESS_TOKEN'], 500];
    }

    public static function customMissingHeaderAccessTokenShouldFail(): iterable
    {
        yield [[]];
        yield [['HTTP_AUTHORIZATION' => 'Bearer this is not a token']];
    }

    public function testDefaultQueryAccessTokenSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_default.yml']);
        $client->request('GET', '/foo?access_token=VALID_ACCESS_TOKEN');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('defaultQueryAccessTokenFailureData')]
    public function testDefaultQueryAccessTokenFailure(string $query)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_default.yml']);
        $client->request('GET', $query);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    public function testDefaultMissingQueryAccessTokenFail()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_default.yml']);
        $client->request('GET', '/foo');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCustomQueryAccessTokenSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_custom.yml']);
        $client->request('GET', '/foo?protection_token=VALID_ACCESS_TOKEN');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Good game @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('customQueryAccessTokenFailure')]
    public function testCustomQueryAccessTokenFailure(string $query)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_custom.yml']);
        $client->request('GET', $query);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'Something went wrong'], json_decode($response->getContent(), true));
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testCustomMissingQueryAccessTokenShouldFail()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_query_custom.yml']);
        $client->request('GET', '/foo');
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public static function defaultQueryAccessTokenFailureData(): iterable
    {
        yield ['/foo?access_token=INVALID_ACCESS_TOKEN'];
    }

    public static function customQueryAccessTokenFailure(): iterable
    {
        yield ['/foo?protection_token=INVALID_ACCESS_TOKEN'];
    }

    public function testSelfContainedTokens()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_self_contained_token.yml']);
        $client->catchExceptions(false);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SELF_CONTAINED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    public function testCustomUserLoader()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_custom_user_loader.yml']);
        $client->catchExceptions(false);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SELF_CONTAINED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('validAccessTokens')]
    #[RequiresPhpExtension('openssl')]
    public function testOidcSuccess(callable $tokenFactory)
    {
        try {
            $token = $tokenFactory();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oidc.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token)]);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    #[DataProvider('invalidAccessTokens')]
    #[RequiresPhpExtension('openssl')]
    public function testOidcFailure(callable $tokenFactory)
    {
        try {
            $token = $tokenFactory();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oidc.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token)]);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    #[RequiresPhpExtension('openssl')]
    public function testDpopBoundTokenIsAcceptedOnceWithItsProof()
    {
        $key = self::createDpopKey();
        $token = self::createDpopBoundToken($key);
        $server = ['HTTP_AUTHORIZATION' => 'DPoP '.$token, 'HTTP_DPOP' => self::createDpopProof($key, $token, 'http://localhost/foo')];

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_dpop.yml']);
        $client->request('GET', '/foo', [], [], $server);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($client->getResponse()->getContent(), true));

        $client->request('GET', '/foo', [], [], $server);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
        $this->assertSame('DPoP realm="My API",error="invalid_dpop_proof",error_description="Invalid credentials.",algs="ES256 PS256 RS256"', $client->getResponse()->headers->get('WWW-Authenticate'));
    }

    #[RequiresPhpExtension('openssl')]
    public function testDpopFirewallDoesNotReadABearerToken()
    {
        $key = self::createDpopKey();
        $token = self::createDpopBoundToken($key);

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_dpop.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_DPOP' => self::createDpopProof($key, $token, 'http://localhost/foo')]);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
        $this->assertSame('DPoP realm="My API",algs="ES256 PS256 RS256"', $client->getResponse()->headers->get('WWW-Authenticate'));
    }

    #[RequiresPhpExtension('openssl')]
    public function testOidcFailureWithJweEnforced()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oidc_jwe.yml']);
        $token = self::createJws([
            'iat' => time() - 1,
            'nbf' => time() - 1,
            'exp' => time() + 3600,
            'iss' => 'https://www.example.com',
            'aud' => 'Symfony OIDC',
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'username' => 'dunglas',
        ]);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token)]);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    public function testOAuth2IntrospectionSuccess()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oauth2.yml']);
        $endpoint = $client->getContainer()->get(IntrospectionResponseFactory::class);

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));

        ['method' => $method, 'url' => $url, 'options' => $options] = $endpoint->requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://authorization-server.example.com/token/introspect', $url);
        $this->assertSame(['Authorization: Basic '.base64_encode('client:password')], $options['normalized_headers']['authorization']);
        $this->assertSame('token=VALID_ACCESS_TOKEN&token_type_hint=access_token', $options['body']);
    }

    public function testOAuth2IntrospectionFailureOnAnInactiveToken()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oauth2.yml']);

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer INVALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    /**
     * The "issuer" the firewall declares reaches the handler, so a token the authorization server
     * reports as active but attributes to another issuer is still refused.
     */
    /**
     * RFC 9701: the endpoint is asked for a signed response, and the RFC 7662 members are read from
     * the "token_introspection" claim of the JWT it answers with.
     */
    #[RequiresPhpExtension('openssl')]
    public function testOAuth2IntrospectionSuccessWithASignedResponse()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oauth2_signed.yml']);
        $endpoint = $client->getContainer()->get(IntrospectionResponseFactory::class);

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer SIGNED_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));

        $this->assertSame(['Accept: application/token-introspection+jwt'], $endpoint->requests[0]['options']['normalized_headers']['accept']);
    }

    /**
     * An authorization server answering plain JSON to a request that asked for a JWT has given up
     * the guarantee the resource server required, so the response is refused.
     */
    #[RequiresPhpExtension('openssl')]
    public function testOAuth2IntrospectionFailureOnAnUnsignedResponse()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oauth2_signed.yml']);

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    public function testOAuth2IntrospectionFailureOnAForeignIssuer()
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oauth2.yml']);

        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => 'Bearer FOREIGN_ISSUER_ACCESS_TOKEN']);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    public function testCasSuccess()
    {
        $casResponse = new MockResponse(<<<BODY
                <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
                    <cas:authenticationSuccess>
                        <cas:user>dunglas</cas:user>
                        <cas:proxyGrantingTicket>PGTIOU-84678-8a9d</cas:proxyGrantingTicket>
                    </cas:authenticationSuccess>
                </cas:serviceResponse>
            BODY
        );

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_cas.yml']);
        $client->getContainer()->set('Symfony\Contracts\HttpClient\HttpClientInterface', new MockHttpClient($casResponse));

        $client->request('GET', '/foo?ticket=PGTIOU-84678-8a9d', [], [], []);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    public static function validAccessTokens(): array
    {
        if (!\extension_loaded('openssl')) {
            return [];
        }
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => 'Symfony OIDC',
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'username' => 'dunglas',
        ];

        return [
            [static fn () => self::createJws($claims)],
            [static fn () => self::createJwe(self::createJws($claims))],
        ];
    }

    public static function invalidAccessTokens(): array
    {
        if (!\extension_loaded('openssl')) {
            return [];
        }
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com',
            'aud' => 'Symfony OIDC',
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'username' => 'dunglas',
        ];

        return [
            [static fn () => self::createJws([...$claims, 'aud' => 'Invalid Audience'])],
            [static fn () => self::createJws([...$claims, 'iss' => 'Invalid Issuer'])],
            [static fn () => self::createJws([...$claims, 'exp' => $time - 3600])],
            [static fn () => self::createJws([...$claims, 'nbf' => $time + 3600])],
            [static fn () => self::createJws([...$claims, 'iat' => $time + 3600])],
            [static fn () => self::createJws([...$claims, 'username' => 'Invalid Username'])],
            [static fn () => self::createJwe(self::createJws($claims), ['exp' => $time - 3600])],
            [static fn () => self::createJwe(self::createJws($claims), ['cty' => 'x-specific'])],
            [static fn () => self::createJws($claims, ['typ' => 'JWT'])],
            [static fn () => self::createJws($claims, [])],
        ];
    }

    private static function createDpopKey(): JWK
    {
        return new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => 'WVnRsXoNEpNEzsNLkmDjaEtKkhpcP-KkihslW781L-I',
            'y' => '2KbDKglsg62x9FWBk740sp1etkmwoD_Dv416SQG3_mA',
            'd' => 'dYp0PmjMg_xzq-J3Srfzpghejc38uVOaoOOHQXgEhpw',
        ]);
    }

    private static function createDpopBoundToken(JWK $key): string
    {
        return self::createJws([
            'iat' => time() - 1,
            'nbf' => time() - 1,
            'exp' => time() + 3600,
            'iss' => 'https://www.example.com',
            'aud' => 'Symfony OIDC',
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'username' => 'dunglas',
            'cnf' => ['jkt' => $key->toPublic()->thumbprint('sha256')],
        ]);
    }

    private static function createDpopProof(JWK $key, string $token, string $url): string
    {
        return (new JwsCompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([new ES256()])))
            ->withPayload(json_encode([
                'jti' => bin2hex(random_bytes(16)),
                'htm' => 'GET',
                'htu' => $url,
                'iat' => time(),
                'ath' => rtrim(strtr(base64_encode(hash('sha256', $token, true)), '+/', '-_'), '='),
            ]))
            ->addSignature($key, ['typ' => 'dpop+jwt', 'alg' => 'ES256', 'jwk' => $key->toPublic()->all()])
            ->build()
        );
    }

    private static function createJws(array $claims, array $header = ['typ' => 'at+jwt']): string
    {
        return (new JwsCompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))
            ->withPayload(json_encode($claims))
            // tip: use https://mkjwk.org/ to generate a JWK
            ->addSignature(new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
            ]), [...$header, 'alg' => 'ES256'])
            ->build()
        );
    }

    private static function createJwe(string $input, array $header = []): string
    {
        $jwk = new JWK([
            'kty' => 'EC',
            'use' => 'enc',
            'crv' => 'P-256',
            'kid' => 'enc-1720876375',
            'x' => '4P27-OB2s5ZP3Zt5ExxQ9uFrgnGaMK6wT1oqd5bJozQ',
            'y' => 'CNh-ZbKJBvz6hJ8JOulXclACP2OuoO2PtqT6WC8tLcU',
        ]);

        return (new JweCompactSerializer())->serialize(
            (new JWEBuilder(new AlgorithmManager([
                new ECDHES(), new A128GCM(),
            ]), null))
                ->withPayload($input)
                ->withSharedProtectedHeader(['alg' => 'ECDH-ES', 'enc' => 'A128GCM', ...$header])
                // tip: use https://mkjwk.org/ to generate a JWK
                ->addRecipient($jwk)
                ->build()
        );
    }
}
