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

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class JsonLoginTest extends AbstractWebTestCase
{
    public function testDefaultJsonLoginSuccess()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    public function testDefaultJsonLoginFailure()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid credentials.'], json_decode($response->getContent(), true));
    }

    public function testCustomJsonLoginSuccess()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'custom_handlers.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Good game @dunglas!'], json_decode($response->getContent(), true));
    }

    public function testCustomJsonLoginFailure()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'custom_handlers.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['message' => 'Something went wrong'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testDefaultJsonLoginBadRequest()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], 'Not a json content');
        $response = $client->getResponse();

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame(['type' => 'https://tools.ietf.org/html/rfc2616#section-10', 'title' => 'An error occurred', 'status' => 400, 'detail' => 'Bad Request'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testLegacyDefaultJsonLoginSuccess()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Welcome @dunglas!'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testLegacyDefaultJsonLoginFailure()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'Invalid credentials.'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testLegacyCustomJsonLoginSuccess()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_custom_handlers.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['message' => 'Good game @dunglas!'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testLegacyCustomJsonLoginFailure()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_custom_handlers.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['message' => 'Something went wrong'], json_decode($response->getContent(), true));
    }

    /**
     * @group legacy
     */
    public function testLegacyDefaultJsonLoginBadRequest()
    {
        $client = self::createClient(['test_case' => 'JsonLogin', 'root_config' => 'legacy_config.yml']);
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], 'Not a json content');
        $response = $client->getResponse();

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame(['type' => 'https://tools.ietf.org/html/rfc2616#section-10', 'title' => 'An error occurred', 'status' => 400, 'detail' => 'Bad Request'], json_decode($response->getContent(), true));
    }
}
