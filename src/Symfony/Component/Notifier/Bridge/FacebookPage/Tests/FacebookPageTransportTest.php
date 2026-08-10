<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\FacebookPage\FacebookPageOptions;
use Symfony\Component\Notifier\Bridge\FacebookPage\FacebookPageTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): FacebookPageTransport
    {
        return new FacebookPageTransport('page-access-token', '1895547427139786', 'v26.0', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['facebook-page://graph.facebook.com?page_id=1895547427139786&api_version=v26.0', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new ChatMessage('Hello!', (new FacebookPageOptions())->link('https://example.com'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendPostsMessageAndAccessTokenToPageFeed()
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://graph.facebook.com/v26.0/1895547427139786/feed', $url);

            parse_str($options['body'], $body);
            self::assertSame('Hello from Navi', $body['message'] ?? null);
            self::assertContains('Authorization: Bearer page-access-token', $options['headers']);
            self::assertArrayNotHasKey('access_token', $body);

            return new MockResponse(json_encode(['id' => '1895547427139786_42'], \JSON_THROW_ON_ERROR));
        });

        $sentMessage = self::createTransport($client)->send(new ChatMessage('Hello from Navi'));

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('1895547427139786_42', $sentMessage->getMessageId());
    }

    public function testSendIncludesOptionalLink()
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            parse_str($options['body'], $body);
            self::assertSame('https://example.com/article', $body['link'] ?? null);
            self::assertContains('Authorization: Bearer page-access-token', $options['headers']);
            self::assertArrayNotHasKey('access_token', $body);

            return new MockResponse(json_encode(['id' => 'page_1'], \JSON_THROW_ON_ERROR));
        });

        $message = (new ChatMessage('Read this'))->options((new FacebookPageOptions())->link('https://example.com/article'));
        $sentMessage = self::createTransport($client)->send($message);

        self::assertSame('page_1', $sentMessage->getMessageId());
    }

    public function testSendThrowsOnNonSuccessfulResponse()
    {
        $client = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'Invalid OAuth access token.']], \JSON_THROW_ON_ERROR),
            ['http_code' => 400],
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Invalid OAuth access token.');

        self::createTransport($client)->send(new ChatMessage('Hello'));
    }

    public function testSendThrowsTransportExceptionOnNonJsonErrorResponse()
    {
        $client = new MockHttpClient(new MockResponse('<html><body>502 Bad Gateway</body></html>', ['http_code' => 502]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('502 Bad Gateway');

        self::createTransport($client)->send(new ChatMessage('Hello'));
    }

    public function testSendThrowsTransportExceptionOnNonJsonSuccessfulResponse()
    {
        $client = new MockHttpClient(new MockResponse(''));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('malformed response from the Facebook Graph API');

        self::createTransport($client)->send(new ChatMessage('Hello'));
    }
}
