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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class MockClientTest extends TestCase
{
    public function testStream()
    {
        $expected = [$this->createMock(ResponseInterface::class)];
        /** @var ResponseStreamInterface $response */
        $response = $this->createMock(ResponseStreamInterface::class);
        $client = new MockClient();
        $client->addResponseStream($response);

        $this->assertSame($response, $client->stream($expected));
        $requests = $client->getStreamedRequests();
        $this->assertCount(1, $requests);
        $this->assertSame($expected, $requests[0]);
    }

    public function testRequest()
    {
        /** @var ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);
        $client = new MockClient();
        $client->addResponse($response);

        $this->assertSame($response, $client->request('GET', '/whatever?q=foo', ['base_uri' => 'http://example.org']));
        $requests = $client->getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('GET', $requests[0]['method']);
        $this->assertSame('http://example.org/whatever?q=foo', implode('', $requests[0]['url']));
    }

    public function testRequestWithoutResponse()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('No predefined response to send. Please add one or more using "addResponse" method.');

        (new MockClient())->request('GET', '/whatever?q=foo', ['base_uri' => 'http://example.org']);
    }

    public function testStreamWithoutResponse()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('No predefined response to send. Please add one or more using "addResponseStream" method.');

        (new MockClient())->stream([]);
    }
}
