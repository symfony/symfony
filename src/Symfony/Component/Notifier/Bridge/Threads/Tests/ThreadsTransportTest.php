<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Threads\ThreadsOptions;
use Symfony\Component\Notifier\Bridge\Threads\ThreadsTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ThreadsTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, int $pollAttempts = ThreadsTransport::POLL_ATTEMPTS): ThreadsTransport
    {
        return new ThreadsTransport('threads-access-token', '1234567890', 'v1.0', $client ?? new MockHttpClient(), null, $pollAttempts, 0.0);
    }

    public static function toStringProvider(): iterable
    {
        yield ['threads://graph.threads.net?user_id=1234567890&api_version=v1.0', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new ChatMessage('Hello!', (new ThreadsOptions())->imageUrl('https://example.com/a.jpg'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function testSendTextCreatesContainerAndPublishes()
    {
        $request = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): MockResponse {
            ++$request;
            self::assertSame('POST', $method);
            self::assertContains('Authorization: Bearer threads-access-token', $options['headers']);
            parse_str($options['body'], $body);
            self::assertArrayNotHasKey('access_token', $body);

            if (1 === $request) {
                self::assertSame('https://graph.threads.net/v1.0/1234567890/threads', $url);
                self::assertSame('TEXT', $body['media_type'] ?? null);
                self::assertSame('Hello Threads', $body['text'] ?? null);

                return new MockResponse(json_encode(['id' => 'container-1'], \JSON_THROW_ON_ERROR));
            }

            self::assertSame('https://graph.threads.net/v1.0/1234567890/threads_publish', $url);
            self::assertSame('container-1', $body['creation_id'] ?? null);

            return new MockResponse(json_encode(['id' => 'post-99'], \JSON_THROW_ON_ERROR));
        });

        $sentMessage = self::createTransport($client)->send(new ChatMessage('Hello Threads'));

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('post-99', $sentMessage->getMessageId());
        self::assertSame(2, $request);
    }

    public function testSendImagePollsThenPublishes()
    {
        $request = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): MockResponse {
            ++$request;
            self::assertContains('Authorization: Bearer threads-access-token', $options['headers']);

            if (1 === $request) {
                parse_str($options['body'], $body);
                self::assertSame('IMAGE', $body['media_type'] ?? null);
                self::assertSame('https://cdn.example.com/a.jpg', $body['image_url'] ?? null);
                self::assertArrayNotHasKey('access_token', $body);

                return new MockResponse(json_encode(['id' => 'container-img'], \JSON_THROW_ON_ERROR));
            }

            if (2 === $request) {
                self::assertSame('GET', $method);
                self::assertStringContainsString('/container-img?', $url);

                return new MockResponse(json_encode(['id' => 'container-img', 'status' => 'FINISHED'], \JSON_THROW_ON_ERROR));
            }

            parse_str($options['body'], $body);
            self::assertSame('container-img', $body['creation_id'] ?? null);

            return new MockResponse(json_encode(['id' => 'post-img'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('With image'))->options((new ThreadsOptions())->imageUrl('https://cdn.example.com/a.jpg'));
        $sentMessage = self::createTransport($client)->send($message);

        self::assertSame('post-img', $sentMessage->getMessageId());
        self::assertSame(3, $request);
    }

    public function testSendRetriesUntilTheContainerIsReady()
    {
        $statusCalls = 0;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$statusCalls): MockResponse {
            if (str_contains($url, '/threads_publish')) {
                return new MockResponse(json_encode(['id' => 'post-img'], \JSON_THROW_ON_ERROR));
            }

            if ('GET' === $method) {
                return new MockResponse(json_encode([
                    'id' => 'container-img',
                    'status' => ++$statusCalls < 3 ? 'IN_PROGRESS' : 'FINISHED',
                ], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-img'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('With image'))->options((new ThreadsOptions())->imageUrl('https://cdn.example.com/a.jpg'));
        $sentMessage = self::createTransport($client)->send($message);

        self::assertSame('post-img', $sentMessage->getMessageId());
        self::assertSame(3, $statusCalls);
    }

    public function testSendThrowsWhenTheContainerFailsToProcess()
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if ('GET' === $method) {
                return new MockResponse(json_encode([
                    'id' => 'container-img',
                    'status' => 'ERROR',
                    'error_message' => 'Media download failed',
                ], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-img'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('With image'))->options((new ThreadsOptions())->imageUrl('https://cdn.example.com/a.jpg'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Threads container processing failed (status "ERROR"): "Media download failed"');

        self::createTransport($client)->send($message);
    }

    public function testSendThrowsWhenTheContainerNeverBecomesReady()
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if ('GET' === $method) {
                return new MockResponse(json_encode(['id' => 'container-img', 'status' => 'IN_PROGRESS'], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-img'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('With image'))->options((new ThreadsOptions())->imageUrl('https://cdn.example.com/a.jpg'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Threads container "container-img" did not finish processing in time.');

        self::createTransport($client, 2)->send($message);
    }

    public function testSendThrowsTransportExceptionOnApiError()
    {
        $client = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'bad token', 'code' => 190, 'fbtrace_id' => 'abc']], \JSON_THROW_ON_ERROR),
            ['http_code' => 400],
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to create the Threads media container: HTTP 400, code 190, subcode n/a, fbtrace_id abc ("bad token")');

        self::createTransport($client)->send(new ChatMessage('Hello'));
    }
}
