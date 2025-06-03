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

    public function testStreamSingleEvent()
    {
        $response = new EventStreamResponse(function () {
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

        $response = new EventStreamResponse(function () use ($data) {
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
        $response = new EventStreamResponse(function () {
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

    public function testStreamEventWithSendMethod()
    {
        $response = new EventStreamResponse(function (EventStreamResponse $response) {
            $response->sendEvent(new ServerEvent('foo'));
        });

        $this->assertSameResponseContent("data: foo\n\n", $response);
    }

    private function assertSameResponseContent(string $expected, EventStreamResponse $response): void
    {
        ob_start();
        $response->send();
        $actual = ob_get_clean();

        $this->assertSame($expected, $actual);
    }
}
