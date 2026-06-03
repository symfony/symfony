<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Bridge\Slack\SlackSentMessage;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SlackTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?string $channel = null): SlackTransport
    {
        return new SlackTransport('xoxb-TestToken', $channel, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['slack://slack.com', self::createTransport()];
        yield ['slack://slack.com?channel=test+Channel', self::createTransport(null, 'test Channel')];
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

    public function testInstatiatingWithAnInvalidSlackTokenThrowsInvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid Slack token needs to start with "xoxb-", "xoxp-" or "xoxa-2". See https://api.slack.com/authentication/token-types for further information.');

        new SlackTransport('token', 'testChannel', new MockHttpClient());
    }

    public function testSendWithEmptyArrayResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);

        $client = new MockHttpClient(new MockResponse('[]', ['http_code' => 500]));

        $transport = self::createTransport($client, 'testChannel');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testErrorCode/');

        $client = new MockHttpClient(new MockResponse(json_encode(['error' => 'testErrorCode']), ['http_code' => 400]));

        $transport = self::createTransport($client, 'testChannel');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $expectedBody = json_encode(['channel' => $channel, 'text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['ok' => true, 'ts' => '1503435956.000247', 'channel' => 'C123456']));
        });

        $transport = self::createTransport($client, $channel);

        $sentMessage = $transport->send(new ChatMessage('testMessage'));

        $this->assertSame('1503435956.000247', $sentMessage->getMessageId());
        $this->assertInstanceOf(SlackSentMessage::class, $sentMessage);
        $this->assertSame('C123456', $sentMessage->getChannelId());
    }

    public function testSendWithNotification()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);
        $options = SlackOptions::fromNotification($notification);

        $expectedBody = json_encode([
            'blocks' => $options->toArray()['blocks'],
            'channel' => $channel,
            'text' => $message,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['ok' => true, 'ts' => '1503435956.000247', 'channel' => 'C123456']));
        });

        $transport = self::createTransport($client, $channel);

        $sentMessage = $transport->send($chatMessage);

        $this->assertSame('1503435956.000247', $sentMessage->getMessageId());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testSendWithBooleanOptionValue(bool $value)
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $options = new SlackOptions();
        $options->asUser($value);
        $options->linkNames($value);
        $options->mrkdwn($value);
        $options->unfurlLinks($value);
        $options->unfurlMedia($value);
        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);
        $chatMessage->options($options);

        $expectedBody = json_encode([
            'as_user' => $value,
            'channel' => $channel,
            'link_names' => $value,
            'mrkdwn' => $value,
            'text' => $message,
            'unfurl_links' => $value,
            'unfurl_media' => $value,
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['ok' => true, 'ts' => '1503435956.000247', 'channel' => 'C123456']));
        });

        $transport = self::createTransport($client, $channel);

        $transport->send($chatMessage);
    }

    public function testSendWith200ResponseButNotOk()
    {
        $channel = 'testChannel';
        $message = 'testMessage';

        $this->expectException(TransportException::class);

        $expectedBody = json_encode(['channel' => $channel, 'text' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse(json_encode(['ok' => false, 'error' => 'testErrorCode']));
        });

        $transport = self::createTransport($client, $channel);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendIncludesContentTypeWithCharset()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            $this->assertContains('Content-Type: application/json; charset=utf-8', $options['headers']);

            return new MockResponse(json_encode(['ok' => true, 'ts' => '1503435956.000247', 'channel' => 'C123456']));
        });

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorsIncluded()
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'ok' => false,
            'error' => 'invalid_blocks',
            'errors' => ['no more than 50 items allowed [json-pointer:/blocks]'],
        ])));

        $transport = self::createTransport($client, 'testChannel');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to post the Slack message: "invalid_blocks" (no more than 50 items allowed [json-pointer:/blocks]).');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testUpdateMessage()
    {
        $sentMessage = new SlackSentMessage(new ChatMessage('Hello'), 'slack', 'C123456', '1503435956.000247');
        $chatMessage = $sentMessage->getUpdateMessage('Hello World');

        $expectedBody = json_encode([
            'channel' => 'C123456',
            'ts' => '1503435956.000247',
            'text' => 'Hello World',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);
            $this->assertStringEndsWith('chat.update', $url);

            return new MockResponse(json_encode(['ok' => true, 'ts' => '1503435956.000247', 'channel' => 'C123456']));
        });

        $transport = $this->createTransport($client, 'another-channel');

        $sentMessage = $transport->send($chatMessage);

        $this->assertSame('1503435956.000247', $sentMessage->getMessageId());
    }
}
