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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SendinblueTransportTest extends TestCase
{
    public function testToStringContainsProperties()
    {
        $transport = $this->createTransport();

        $this->assertSame('sendinblue://host.test?sender=0611223344', (string) $transport);
    }

    public function testSupportsMessageInterface()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendNonSmsMessageThrowsLogicException()
    {
        $transport = $this->createTransport();

        $this->expectException(UnsupportedMessageTypeException::class);

        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testSendWithErrorResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['code' => 400, 'message' => 'bad request']));

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: bad request');
        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    private function createTransport(?HttpClientInterface $client = null): SendinblueTransport
    {
        return (new SendinblueTransport('api-key', '0611223344', $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
