<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendinblue\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SendinblueTransportTest extends TransportTestCase
{
    /**
     * @return SendinblueTransport
     */
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return (new SendinblueTransport('api-key', '0611223344', $client ?? self::createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['sendinblue://host.test?sender=0611223344', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $response = self::createMock(ResponseInterface::class);
        $response->expects(self::exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects(self::once())
            ->method('getContent')
            ->willReturn(json_encode(['code' => 400, 'message' => 'bad request']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        self::expectException(TransportException::class);
        self::expectExceptionMessage('Unable to send the SMS: bad request');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }
}
