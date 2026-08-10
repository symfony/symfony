<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Instagram\InstagramOptions;
use Symfony\Component\Notifier\Bridge\Instagram\InstagramTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, int $pollAttempts = InstagramTransport::POLL_ATTEMPTS): InstagramTransport
    {
        return new InstagramTransport('ig-access-token', '17841400000000000', 'v22.0', $client ?? new MockHttpClient(), null, $pollAttempts, 0.0);
    }

    public static function toStringProvider(): iterable
    {
        yield ['instagram://graph.instagram.com?user_id=17841400000000000&api_version=v22.0', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Caption', (new InstagramOptions())->imageUrl('https://example.com/a.jpg'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function testSendImageCreatesContainerPollsAndPublishes()
    {
        $request = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): MockResponse {
            ++$request;
            self::assertContains('Authorization: Bearer ig-access-token', $options['headers']);

            if (1 === $request) {
                self::assertSame('POST', $method);
                self::assertSame('https://graph.instagram.com/v22.0/17841400000000000/media', $url);
                parse_str($options['body'], $body);
                self::assertSame('https://cdn.example.com/photo.jpg', $body['image_url'] ?? null);
                self::assertSame('A Darkwood publication', $body['caption'] ?? null);
                self::assertArrayNotHasKey('access_token', $body);

                return new MockResponse(json_encode(['id' => 'container-1'], \JSON_THROW_ON_ERROR));
            }

            if (2 === $request) {
                self::assertSame('GET', $method);

                return new MockResponse(json_encode(['id' => 'container-1', 'status_code' => 'FINISHED'], \JSON_THROW_ON_ERROR));
            }

            self::assertSame('POST', $method);
            self::assertSame('https://graph.instagram.com/v22.0/17841400000000000/media_publish', $url);
            parse_str($options['body'], $body);
            self::assertSame('container-1', $body['creation_id'] ?? null);
            self::assertArrayNotHasKey('access_token', $body);

            return new MockResponse(json_encode(['id' => 'media-42'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('A Darkwood publication'))
            ->options((new InstagramOptions())->imageUrl('https://cdn.example.com/photo.jpg'));
        $sentMessage = self::createTransport($client)->send($message);

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('media-42', $sentMessage->getMessageId());
        self::assertSame(3, $request);
    }

    public function testSendReelUsesVideoUrlAndShareToFeed()
    {
        $request = 0;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): MockResponse {
            ++$request;
            if (1 === $request) {
                parse_str($options['body'], $body);
                self::assertSame('REELS', $body['media_type'] ?? null);
                self::assertSame('https://cdn.example.com/reel.mp4', $body['video_url'] ?? null);
                self::assertSame('true', $body['share_to_feed'] ?? null);
                self::assertArrayNotHasKey('access_token', $body);

                return new MockResponse(json_encode(['id' => 'reel-container'], \JSON_THROW_ON_ERROR));
            }

            if (2 === $request) {
                return new MockResponse(json_encode(['id' => 'reel-container', 'status_code' => 'FINISHED'], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'reel-media'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('Reel caption'))
            ->options((new InstagramOptions())->videoUrl('https://cdn.example.com/reel.mp4')->shareToFeed(true));
        $sentMessage = self::createTransport($client)->send($message);

        self::assertSame('reel-media', $sentMessage->getMessageId());
    }

    public function testSendRetriesUntilTheContainerIsReady()
    {
        $statusCalls = 0;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$statusCalls): MockResponse {
            if (str_contains($url, '/media_publish')) {
                return new MockResponse(json_encode(['id' => 'media-1'], \JSON_THROW_ON_ERROR));
            }

            if ('GET' === $method) {
                return new MockResponse(json_encode([
                    'id' => 'container-1',
                    'status_code' => ++$statusCalls < 3 ? 'IN_PROGRESS' : 'FINISHED',
                ], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-1'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('Caption'))->options((new InstagramOptions())->imageUrl('https://cdn.example.com/a.jpg'));

        self::assertSame('media-1', self::createTransport($client)->send($message)->getMessageId());
        self::assertSame(3, $statusCalls);
    }

    public function testSendThrowsWhenTheContainerFailsToProcess()
    {
        $client = new MockHttpClient(static function (string $method): MockResponse {
            if ('GET' === $method) {
                return new MockResponse(json_encode([
                    'id' => 'container-1',
                    'status_code' => 'ERROR',
                    'error_message' => 'Media download failed',
                ], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-1'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('Caption'))->options((new InstagramOptions())->imageUrl('https://cdn.example.com/a.jpg'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Instagram container processing failed (status "ERROR"): "Media download failed"');

        self::createTransport($client)->send($message);
    }

    public function testSendThrowsWhenTheContainerNeverBecomesReady()
    {
        $client = new MockHttpClient(static function (string $method): MockResponse {
            if ('GET' === $method) {
                return new MockResponse(json_encode(['id' => 'container-1', 'status_code' => 'IN_PROGRESS'], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode(['id' => 'container-1'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('Caption'))->options((new InstagramOptions())->imageUrl('https://cdn.example.com/a.jpg'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Instagram container "container-1" did not finish processing in time.');

        self::createTransport($client, 2)->send($message);
    }

    public function testSendRejectsANonHttpsMediaUrl()
    {
        $message = (new ChatMessage('Caption'))->options((new InstagramOptions())->imageUrl('http://cdn.example.com/a.jpg'));

        $this->expectException(InvalidArgumentException::class);

        self::createTransport()->send($message);
    }

    public function testSendThrowsTransportExceptionOnApiError()
    {
        $client = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'unsupported format', 'code' => 100, 'error_subcode' => 2207051, 'fbtrace_id' => 'xyz']], \JSON_THROW_ON_ERROR),
            ['http_code' => 400],
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to create the Instagram media container: HTTP 400, code 100, subcode 2207051, fbtrace_id xyz ("unsupported format")');

        $message = (new ChatMessage('Caption'))->options((new InstagramOptions())->imageUrl('https://cdn.example.com/a.jpg'));
        self::createTransport($client)->send($message);
    }
}
