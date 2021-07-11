<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsapi\Tests;

use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmsapiTransportTest extends TransportTestCase
{
    /**
     * @return SmsapiTransport
     */
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return (new SmsapiTransport('testToken', 'testFrom', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('test.host');
    }

    public function toStringProvider(): iterable
    {
        yield ['smsapi://test.host?from=testFrom', $this->createTransport()];
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

    public function createClient(int $statusCode, array $content): HttpClientInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('toArray')
            ->willReturn($content);

        $response
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->method('request')
            ->willReturn($response);

        return $client;
    }

    public function responseProvider(): iterable
    {
        $responses = [
            ['status' => 200, 'content' => '{"error":101,"message":"Authorization failed"}'],
            ['status' => 500, 'content' => '{}'],
            ['status' => 500, 'content' => '{"error":null,"message":"Unknown"}'],
            ['status' => 500, 'content' => '{"error":null,"message":null}'],
        ];

        foreach ($responses as $response) {
            yield [$response['status'], json_decode($response['content'], true)];
        }
    }

    /**
     * @dataProvider responseProvider
     */
    public function testThrowExceptionWhenMessageWasNotSent(int $statusCode, array $content)
    {
        $client = $this->createClient($statusCode, $content);
        $transport = $this->createTransport($client);
        $message = new SmsMessage('0611223344', 'Hello!');

        $this->expectException(TransportException::class);

        $transport->send($message);
    }

    /**
     * @dataProvider responseProvider
     */
    public function testTransportExceptionMessage(int $statusCode, array $content)
    {
        $client = $this->createClient($statusCode, $content);
        $transport = $this->createTransport($client);
        $message = new SmsMessage('0611223344', 'Hello!');

        try {
            $transport->send($message);
        } catch (TransportException $exception) {
            $this->assertEquals(sprintf('Unable to send the SMS: "%s".', $content['message'] ?? 'unknown error'), $exception->getMessage());
        }
    }
}
