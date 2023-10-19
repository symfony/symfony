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
    public static function createTransport(HttpClientInterface $client = null): LinkedInTransport
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
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('[]');

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('testErrorCode');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('testErrorCode');

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithOptions()
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '42']));

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
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });
        $transport = self::createTransport($client);

        $transport->send(new ChatMessage($message));
    }

    public function testSendWithNotification()
    {
        $message = 'testMessage';

        $response = $this->createMock(ResponseInterface::class);

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(201);

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['id' => '42']));

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
            $response,
            $expectedBody
        ): ResponseInterface {
            $this->assertSame($expectedBody, $options['body']);

            return $response;
        });

        $transport = self::createTransport($client);

        $transport->send($chatMessage);
    }

    public function testSendWithInvalidOptions()
    {
        $this->expectException(LogicException::class);

        $client = new MockHttpClient(fn (string $method, string $url, array $options = []): ResponseInterface => $this->createMock(ResponseInterface::class));

        $transport = self::createTransport($client);

        $transport->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }
}
