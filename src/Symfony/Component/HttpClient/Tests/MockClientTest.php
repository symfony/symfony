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
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MockClientTest extends TestCase
{
    public function testStream()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->any())->method('getContent')->willReturn('{"foo": "bar"}');

        $client = new MockClient();

        foreach ($client->stream($response) as $chunk) {
            $this->assertInstanceOf(ChunkInterface::class, $chunk);

            if ($chunk->isLast()) {
                $this->assertSame('{"foo": "bar"}', $chunk->getContent());
            }
        }
    }

    public function testStreamWithUnhappyResponse()
    {
        $client = new MockClient();
        $response = $this->createMock(ResponseInterface::class);
        $e = new TransportException('Something is broken :(');

        $response->method('getHeaders')->willThrowException($e);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($e->getMessage());

        $client->stream($response)->valid();
    }

    public function testRequest()
    {
        /** @var ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);
        $client = new MockClient();
        $client->addResponse($response);

        $this->assertSame($response, $client->request('GET', '/whatever?q=foo', ['base_uri' => 'http://example.org']));
    }

    public function testRequestWithoutResponse()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('No predefined response to send. Please add one or more using "addResponse" method.');

        (new MockClient())->request('GET', '/whatever?q=foo', ['base_uri' => 'http://example.org']);
    }

    public function testConstructWithInvalidType()
    {
        $this->expectException('TypeError');
        $this->expectExceptionMessage('Each predefined response must an instance of Symfony\Contracts\HttpClient\ResponseInterface, stdClass given.');

        new MockClient([new \stdClass()]);
    }
}
