<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EsendexTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null): EsendexTransport
    {
        return (new EsendexTransport('email', 'password', 'testAccountReference', 'testFrom', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['esendex://host.test?accountreference=testAccountReference&from=testFrom', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500.');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithErrorResponseContainingDetailsThrowsTransportException()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['errors' => [['code' => 'accountreference_invalid', 'description' => 'Invalid Account Reference EX0000000']]]));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500. Details from Esendex: accountreference_invalid: "Invalid Account Reference EX0000000".');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithSuccessfulResponseDispatchesMessageEvent()
    {
        $messageId = Uuid::v4()->toRfc4122();
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['batch' => ['messageheaders' => [['id' => $messageId]]]]));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('phone', 'testMessage'));

        $this->assertSame($messageId, $sentMessage->getMessageId());
    }
}
