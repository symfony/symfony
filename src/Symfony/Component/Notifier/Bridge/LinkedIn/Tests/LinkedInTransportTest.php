<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LinkedInTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): LinkedInTransport
    {
        return (new LinkedInTransport('AuthToken', 'AccountId', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['linkedin://host.test', self::createTransport()];
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

    public function testSendWithEmptyArrayResponseThrowsTransportException()
    {
        $client = new MockHttpClient(new MockResponse('[]', ['http_code' => 500]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('testErrorCode');

        $client = new MockHttpClient(new MockResponse('testErrorCode', ['http_code' => 400]));

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $message = 'testMessage';

        $expectedBody = json_encode([
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'attributes' => [],
                        'text' => 'testMessage',
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
            'lifecycleState' => 'PUBLISHED',
            'author' => 'urn:li:person:AccountId',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new MockResponse(json_encode(['id' => '42']), ['http_code' => 201]);
        });
        $transport = self::createTransport($client);

        $transport->send(new ChatMessage($message));
    }

    public function testSendWithNotification()
    {
        $message = 'testMessage';

        $notification = new Notification($message);
        $chatMessage = ChatMessage::fromNotification($notification);

        $expectedBody = json_encode([
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'attributes' => [],
                        'text' => 'testMessage',
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
            'lifecycleState' => 'PUBLISHED',
            'author' => 'urn:li:person:AccountId',
        ]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use (
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return new MockResponse(json_encode(['id' => '42']), ['http_code' => 201]);
        });

        $transport = self::createTransport($client);

        $transport->send($chatMessage);
    }

    public function testSendWithInvalidOptions()
    {
        $this->expectException(LogicException::class);

        $client = new MockHttpClient(new MockResponse());

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage', $this->createStub(MessageOptionsInterface::class)));
    }
}
