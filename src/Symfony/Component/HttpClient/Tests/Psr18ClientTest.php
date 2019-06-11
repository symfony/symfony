<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Psr18NetworkException;
use Symfony\Component\HttpClient\Psr18RequestException;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class Psr18ClientTest extends TestCase
{
    private static $server;

    public static function setUpBeforeClass()
    {
        TestHttpServer::start();
    }

    public function testSendRequest()
    {
        $factory = new Psr17Factory();
        $client = new Psr18Client(new NativeHttpClient(), $factory, $factory);

        $response = $client->sendRequest($factory->createRequest('GET', 'http://localhost:8057'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
    }

    public function testPostRequest()
    {
        $factory = new Psr17Factory();
        $client = new Psr18Client(new NativeHttpClient(), $factory, $factory);

        $request = $factory->createRequest('POST', 'http://localhost:8057/post')
            ->withBody($factory->createStream('foo=0123456789'));

        $response = $client->sendRequest($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testNetworkException()
    {
        $factory = new Psr17Factory();
        $client = new Psr18Client(new NativeHttpClient(), $factory, $factory);

        $this->expectException(Psr18NetworkException::class);
        $client->sendRequest($factory->createRequest('GET', 'http://localhost:8058'));
    }

    public function testRequestException()
    {
        $factory = new Psr17Factory();
        $client = new Psr18Client(new NativeHttpClient(), $factory, $factory);

        $this->expectException(Psr18RequestException::class);
        $client->sendRequest($factory->createRequest('BAD.METHOD', 'http://localhost:8057'));
    }

    public function test404()
    {
        $factory = new Psr17Factory();
        $client = new Psr18Client(new NativeHttpClient());

        $response = $client->sendRequest($factory->createRequest('GET', 'http://localhost:8057/404'));
        $this->assertSame(404, $response->getStatusCode());
    }
}
