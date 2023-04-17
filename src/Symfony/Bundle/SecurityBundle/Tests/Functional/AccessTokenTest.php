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
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

    /**
     * @dataProvider defaultFormEncodedBodyFailureData
     */
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

    /**
     * @dataProvider customFormEncodedBodyFailure
     */
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

    /**
     * @dataProvider defaultHeaderAccessTokenFailureData
     */
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

    /**
     * @dataProvider defaultMissingHeaderAccessTokenFailData
     */
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

    /**
     * @dataProvider customHeaderAccessTokenFailure
     */
    public function testCustomHeaderAccessTokenFailure(array $headers, int $errorCode)
    {
        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_header_custom.yml']);
        $client->request('GET', '/foo', [], [], $headers);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($errorCode, $response->getStatusCode());
        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    /**
     * @dataProvider customMissingHeaderAccessTokenShouldFail
     */
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

    /**
     * @dataProvider defaultQueryAccessTokenFailureData
     */
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

    /**
     * @dataProvider customQueryAccessTokenFailure
     */
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

    /**
     * @requires extension openssl
     */
    public function testOidcSuccess()
    {
        $time = time();
        $claims = [
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 3600,
            'iss' => 'https://www.example.com/',
            'aud' => 'Symfony OIDC',
            'sub' => 'e21bf182-1538-406e-8ccb-e25a17aba39f',
            'username' => 'dunglas',
        ];
        $token = (new CompactSerializer())->serialize((new JWSBuilder(new AlgorithmManager([
            new ES256(),
        ])))->create()
            ->withPayload(json_encode($claims))
            // tip: use https://mkjwk.org/ to generate a JWK
            ->addSignature(new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
            ]), ['alg' => 'ES256'])
            ->build()
        );

        $client = $this->createClient(['test_case' => 'AccessToken', 'root_config' => 'config_oidc.yml']);
        $client->request('GET', '/foo', [], [], ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)]);
        $response = $client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }
}
