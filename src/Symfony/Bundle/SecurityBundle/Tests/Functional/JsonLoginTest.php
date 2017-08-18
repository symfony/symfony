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
class JsonLoginTest extends WebTestCase
{
    public function testDefaultJsonLoginSuccess()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'config.yml'));
        $client->request('POST', '/chk', array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(array('message' => 'Welcome @dunglas!'), json_decode($response->getContent(), true));
    }

    public function testDefaultJsonLoginFailure()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'config.yml'));
        $client->request('POST', '/chk', array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(array('error' => 'Invalid credentials.'), json_decode($response->getContent(), true));
    }

    public function testCustomJsonLoginSuccess()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'custom_handlers.yml'));
        $client->request('POST', '/chk', array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"user": {"login": "dunglas", "password": "foo"}}');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(array('message' => 'Good game @dunglas!'), json_decode($response->getContent(), true));
    }

    public function testCustomJsonLoginFailure()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'custom_handlers.yml'));
        $client->request('POST', '/chk', array(), array(), array('CONTENT_TYPE' => 'application/json'), '{"user": {"login": "dunglas", "password": "bad"}}');
        $response = $client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(array('message' => 'Something went wrong'), json_decode($response->getContent(), true));
    }

    public function testDefaultJsonLoginBadRequest()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'config.yml'));
        $client->request('POST', '/chk', array(), array(), array('CONTENT_TYPE' => 'application/json'), 'Not a json content');
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertArraySubset(array('error' => array('code' => 400, 'message' => 'Bad Request')), json_decode($response->getContent(), true));
    }
}
