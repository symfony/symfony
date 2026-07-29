<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\ServerEvent;

class EventStreamResponseTest extends TestCase
{
    public function testInitializationWithDefaultValues()
    {
        $response = new EventStreamResponse();

        $this->assertSame('text/event-stream', $response->headers->get('content-type'));
        $this->assertSame('max-age=0, must-revalidate, no-cache, no-store, private', $response->headers->get('cache-control'));
        $this->assertSame('keep-alive', $response->headers->get('connection'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getRetry());
    }

    public function testPresentOfExpiresHeader()
    {
        $response = new EventStreamResponse();

        $this->assertTrue($response->headers->has('Expires'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function testStreamSingleEvent()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: 'foo',
                type: 'bar',
                retry: 100,
                id: '1',
                comment: 'bla bla',
            );
        });

        $expected = <<<STR
            : bla bla
            id: 1
            retry: 100
            event: bar
            data: foo


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testStreamEventsAndData()
    {
        $data = static function (): iterable {
            yield 'first line';
            yield 'second line';
            yield 'third line';
        };

        $response = new EventStreamResponse(static function () use ($data) {
            yield new ServerEvent('single line');
            yield new ServerEvent(['first line', 'second line']);
            yield new ServerEvent($data());
        });

        $expected = <<<STR
            data: single line

            data: first line
            data: second line

            data: first line
            data: second line
            data: third line


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testStreamEventsWithRetryFallback()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent('foo');
            yield new ServerEvent('bar');
            yield new ServerEvent('baz', retry: 1000);
        }, retry: 1500);

        $expected = <<<STR
            retry: 1500
            data: foo

            data: bar

            retry: 1000
            data: baz


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testRetryIsNotSharedBetweenServerEvents()
    {
        $first = new ServerEvent('foo', retry: 3000);
        $second = new ServerEvent('bar', retry: 3000);

        $this->assertSame("retry: 3000\ndata: foo\n\n", implode('', iterator_to_array($first)));
        $this->assertSame("retry: 3000\ndata: bar\n\n", implode('', iterator_to_array($second)));
    }

    public function testRetryIsNotSharedBetweenStreams()
    {
        $callback = static function () {
            yield new ServerEvent('foo');
            yield new ServerEvent('bar');
        };

        $expected = <<<STR
            retry: 2000
            data: foo

            data: bar


            STR;

        $this->assertSameResponseContent($expected, new EventStreamResponse($callback, retry: 2000));
        $this->assertSameResponseContent($expected, new EventStreamResponse($callback, retry: 2000));
    }

    public function testStreamEventWithSendMethod()
    {
        $response = new EventStreamResponse(static function (EventStreamResponse $response) {
            $response->sendEvent(new ServerEvent('foo'));
        });

        $this->assertSameResponseContent("data: foo\n\n", $response);
    }

    public function testStreamEventWith0Data()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: '0',
            );
        });

        $this->assertSameResponseContent("data: 0\n\n", $response);
    }

    public function testStreamEventEmptyStringIgnored()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: '',
            );
        });

        $this->assertSameResponseContent("\n", $response);
    }

    public function testStreamEventWithMultilineStringData()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: "first line\nsecond line\rthird line\r\nfourth line",
            );
        });

        $expected = <<<STR
            data: first line
            data: second line
            data: third line
            data: fourth line


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testStreamEventWithMultilineIterableData()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: ['first line', "second line\nthird line"],
            );
        });

        $expected = <<<STR
            data: first line
            data: second line
            data: third line


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testStreamEventCannotInjectFieldsThroughData()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: "legit\nevent: adminAlert\ndata: hijacked",
                type: 'message',
            );
        });

        $expected = <<<STR
            event: message
            data: legit
            data: event: adminAlert
            data: data: hijacked


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    public function testStreamEventCannotInjectFieldsThroughIdTypeAndComment()
    {
        $response = new EventStreamResponse(static function () {
            yield new ServerEvent(
                data: 'foo',
                type: "message\nretry: 1",
                id: "1\revent: adminAlert",
                comment: "bla\r\nbla",
            );
        });

        $expected = <<<STR
            : bla
            : bla
            id: 1event: adminAlert
            event: messageretry: 1
            data: foo


            STR;

        $this->assertSameResponseContent($expected, $response);
    }

    private function assertSameResponseContent(string $expected, EventStreamResponse $response): void
    {
        ob_start();
        $response->send();
        $actual = ob_get_clean();

        $this->assertSame($expected, $actual);
    }
}
