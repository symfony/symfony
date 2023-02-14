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
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\EventSourceException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class EventSourceHttpClientTest extends TestCase
{
    public function testGetServerSentEvents()
    {
        $data = <<<TXT
event: builderror
id: 46
data: {"foo": "bar"}

event: reload
id: 47
data: {}

event: reload
id: 48
data: {}

data: test
data:test
id: 49
event: testEvent


id: 50
data: <tag>
data
data:   <foo />
data
data: </tag>

id: 60
data
TXT;

        $chunk = new DataChunk(0, $data);
        $response = new MockResponse('', ['canceled' => false, 'http_method' => 'GET', 'url' => 'http://localhost:8080/events', 'response_headers' => ['content-type: text/event-stream']]);
        $responseStream = new ResponseStream((function () use ($response, $chunk) {
            yield $response => new FirstChunk();
            yield $response => $chunk;
            yield $response => new ErrorChunk(0, 'timeout');
        })());

        $hasCorrectHeaders = function ($options) {
            $this->assertSame(['Accept: text/event-stream', 'Cache-Control: no-cache'], $options['headers']);

            return true;
        };

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->with('GET', 'http://localhost:8080/events', $this->callback($hasCorrectHeaders))->willReturn($response);

        $httpClient->method('stream')->willReturn($responseStream);

        $es = new EventSourceHttpClient($httpClient);
        $res = $es->connect('http://localhost:8080/events');

        $expected = [
            new FirstChunk(),
            new ServerSentEvent("event: builderror\nid: 46\ndata: {\"foo\": \"bar\"}\n\n"),
            new ServerSentEvent("event: reload\nid: 47\ndata: {}\n\n"),
            new ServerSentEvent("event: reload\nid: 48\ndata: {}\n\n"),
            new ServerSentEvent("data: test\ndata:test\nid: 49\nevent: testEvent\n\n\n"),
            new ServerSentEvent("id: 50\ndata: <tag>\ndata\ndata:   <foo />\ndata\ndata: </tag>\n\n"),
        ];
        $i = 0;

        $this->expectExceptionMessage('Response has been canceled');
        while ($res) {
            if ($i > 0) {
                $res->cancel();
            }
            foreach ($es->stream($res) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                if ($chunk->isLast()) {
                    continue;
                }

                $this->assertEquals($expected[$i++], $chunk);
            }
        }
    }

    /**
     * @dataProvider contentTypeProvider
     */
    public function testContentType($contentType, $expected)
    {
        $chunk = new DataChunk(0, '');
        $response = new MockResponse('', ['canceled' => false, 'http_method' => 'GET', 'url' => 'http://localhost:8080/events', 'response_headers' => ['content-type: '.$contentType]]);
        $responseStream = new ResponseStream((function () use ($response, $chunk) {
            yield $response => new FirstChunk();
            yield $response => $chunk;
            yield $response => new ErrorChunk(0, 'timeout');
        })());

        $hasCorrectHeaders = function ($options) {
            $this->assertSame(['Accept: text/event-stream', 'Cache-Control: no-cache'], $options['headers']);

            return true;
        };

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->with('GET', 'http://localhost:8080/events', $this->callback($hasCorrectHeaders))->willReturn($response);

        $httpClient->method('stream')->willReturn($responseStream);

        $es = new EventSourceHttpClient($httpClient);
        $res = $es->connect('http://localhost:8080/events');

        if ($expected instanceof EventSourceException) {
            $this->expectExceptionMessage($expected->getMessage());
        }

        foreach ($es->stream($res) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }

            if ($chunk->isLast()) {
                return;
            }
        }
    }

    public static function contentTypeProvider()
    {
        return [
            ['text/event-stream', true],
            ['text/event-stream;charset=utf-8', true],
            ['text/event-stream;charset=UTF-8', true],
            ['Text/EVENT-STREAM;Charset="utf-8"', true],
            ['text/event-stream; charset="utf-8"', true],
            ['text/event-stream; charset=iso-8859-15', true],
            ['text/html', new EventSourceException('Response content-type is "text/html" while "text/event-stream" was expected for "http://localhost:8080/events".')],
            ['text/html; charset="utf-8"', new EventSourceException('Response content-type is "text/html; charset="utf-8"" while "text/event-stream" was expected for "http://localhost:8080/events".')],
            ['text/event-streambla', new EventSourceException('Response content-type is "text/event-streambla" while "text/event-stream" was expected for "http://localhost:8080/events".')],
        ];
    }
}
