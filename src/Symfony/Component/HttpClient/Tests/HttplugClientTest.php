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

use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class HttplugClientTest extends TestCase
{
    private static $server;

    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start();
    }

    public function testSendRequest()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $response = $client->sendRequest($client->createRequest('GET', 'http://localhost:8057'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
    }

    public function testPostRequest()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $request = $client->createRequest('POST', 'http://localhost:8057/post')
            ->withBody($client->createStream('foo=0123456789'));

        $response = $client->sendRequest($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testNetworkException()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $this->expectException(NetworkException::class);
        $client->sendRequest($client->createRequest('GET', 'http://localhost:8058'));
    }

    public function testRequestException()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $this->expectException(RequestException::class);
        $client->sendRequest($client->createRequest('BAD.METHOD', 'http://localhost:8057'));
    }
}
