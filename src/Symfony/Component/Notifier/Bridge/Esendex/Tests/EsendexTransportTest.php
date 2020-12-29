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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EsendexTransportTest extends TestCase
{
    public function testToString()
    {
        $transport = $this->createTransport();

        $this->assertSame('esendex://host.test?accountreference=testAccountReference&from=testFrom', (string) $transport);
    }

    public function testSupportsSmsMessage()
    {
        $transport = $this->createTransport();

        $this->assertTrue($transport->supports(new SmsMessage('phone', 'testSmsMessage')));
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
            ->willReturn(500);

        $client = new MockHttpClient(static function () use ($response): ResponseInterface {
            return $response;
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500.');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithErrorResponseContainingDetailsThrows()
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

    private function createTransport(?HttpClientInterface $client = null): EsendexTransport
    {
        return (new EsendexTransport('testEmail', 'testPassword', 'testAccountReference', 'testFrom', $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }
}
