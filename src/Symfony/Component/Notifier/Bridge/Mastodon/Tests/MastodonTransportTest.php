<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mastodon\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Notifier\Bridge\Mastodon\MastodonOptions;
use Symfony\Component\Notifier\Bridge\Mastodon\MastodonTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
class MastodonTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): MastodonTransport
    {
        return (new MastodonTransport('testAccessToken', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['mastodon://host.test', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello World!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello World!')];
        yield [new DummyMessage()];
    }

    public function testBasicStatus()
    {
        $transport = $this->createTransport(new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://host.test/api/v1/statuses', $url);
            $this->assertSame('{"status":"Hello World!"}', $options['body']);
            $this->assertArrayHasKey('authorization', $options['normalized_headers']);

            return new MockResponse('{"id":"103254962155278888"}');
        }));

        $result = $transport->send(new ChatMessage('Hello World!'));

        $this->assertSame('103254962155278888', $result->getMessageId());
    }

    public function testStatusWithPoll()
    {
        $transport = $this->createTransport(new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://host.test/api/v1/statuses', $url);
            $this->assertSame('{"poll":{"options":["choice1","choice2"],"expires_in":3600},"status":"Hello World!"}', $options['body']);
            $this->assertArrayHasKey('authorization', $options['normalized_headers']);

            return new MockResponse('{"id":"103254962155278888"}');
        }));

        $options = (new MastodonOptions())
            ->poll(['choice1', 'choice2'], 3600);
        $result = $transport->send(new ChatMessage('Hello World!', $options));

        $this->assertSame('103254962155278888', $result->getMessageId());
    }

    public function testStatusWithMedia()
    {
        $transport = $this->createTransport(new MockHttpClient((function () {
            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://host.test/api/v2/media', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"id":"256"}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://host.test/api/v1/statuses', $url);
                $this->assertSame('{"status":"Hello World!","media_ids":["256"]}', $options['body']);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"id":"103254962155278888"}');
            };
        })()));

        $options = (new MastodonOptions())
            ->attachMedia(new File(__DIR__.'/fixtures.gif'), new File(__DIR__.'/fixtures.gif'), 'A fixture', '1.0,0.75');
        $result = $transport->send(new ChatMessage('Hello World!', $options));

        $this->assertSame('103254962155278888', $result->getMessageId());
    }
}
