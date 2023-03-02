<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twitter\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Notifier\Bridge\Twitter\TwitterOptions;
use Symfony\Component\Notifier\Bridge\Twitter\TwitterTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TwitterTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): TwitterTransport
    {
        return new TwitterTransport('APIK', 'APIS', 'TOKEN', 'SECRET', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['twitter://api.twitter.com', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testBasicTweet()
    {
        $transport = $this->createTransport(new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.twitter.com/2/tweets', $url);
            $this->assertSame('{"text":"Hello World!"}', $options['body']);
            $this->assertArrayHasKey('authorization', $options['normalized_headers']);

            return new MockResponse('{"data":{"id":"abc123"}}');
        }));

        $result = $transport->send(new ChatMessage('Hello World!'));

        $this->assertSame('abc123', $result->getMessageId());
    }

    public function testTweetImage()
    {
        $transport = $this->createTransport(new MockHttpClient((function () {
            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=INIT&total_bytes=185&media_type=image/gif&media_category=tweet_image', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"media_id_string":"gif123"}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=APPEND&media_id=gif123&segment_index=0', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse();
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=FINALIZE&media_id=gif123', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"processing_info":{"state":"pending","check_after_secs": 0}}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('GET', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=STATUS&media_id=gif123', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"processing_info":{"state":"succeeded"}}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/metadata/create.json', $url);
                $this->assertSame('{"media_id":"gif123","alt_text":{"text":"A fixture"}}', $options['body']);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"processing_info":{"state":"succeeded"}}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.twitter.com/2/tweets', $url);
                $this->assertSame('{"text":"Hello World!","media":{"media_ids":["gif123"]}}', $options['body']);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"data":{"id":"abc123"}}');
            };
        })()));

        $result = $transport->send(new ChatMessage('Hello World!', (new TwitterOptions())
            ->attachImage(new File(__DIR__.'/fixtures.gif'), 'A fixture'))
        );

        $this->assertSame('abc123', $result->getMessageId());
    }

    public function testTweetVideo()
    {
        $transport = $this->createTransport(new MockHttpClient((function () {
            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=INIT&total_bytes=185&media_type=image/gif&media_category=tweet_video', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"media_id_string":"gif123"}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=INIT&total_bytes=185&media_type=image/gif&media_category=subtitles', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"media_id_string":"sub234"}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=APPEND&media_id=gif123&segment_index=0', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse();
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=APPEND&media_id=sub234&segment_index=0', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse();
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=FINALIZE&media_id=gif123', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/upload.json?command=FINALIZE&media_id=sub234', $url);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{}');
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://upload.twitter.com/1.1/media/subtitles/create.json', $url);
                $this->assertSame('{"media_id":"gif123","media_category":"tweet_video","subtitle_info":{"subtitles":[{"media_id":"sub234","language_code":"en","display_name":"English"}]}}', $options['body']);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse();
            };

            yield function (string $method, string $url, array $options) {
                $this->assertSame('POST', $method);
                $this->assertSame('https://api.twitter.com/2/tweets', $url);
                $this->assertSame('{"text":"Hello World!","media":{"media_ids":["gif123"]}}', $options['body']);
                $this->assertArrayHasKey('authorization', $options['normalized_headers']);

                return new MockResponse('{"data":{"id":"abc123"}}');
            };
        })()));

        $result = $transport->send(new ChatMessage('Hello World!', (new TwitterOptions())
            ->attachVideo(new File(__DIR__.'/fixtures.gif'), '', new File(__DIR__.'/fixtures.gif', 'English.en.srt')))
        );

        $this->assertSame('abc123', $result->getMessageId());
    }
}
