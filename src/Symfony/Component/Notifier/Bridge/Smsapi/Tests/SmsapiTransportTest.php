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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmsapiTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, bool $fast = false, bool $test = false): SmsapiTransport
    {
        return (new SmsapiTransport('testToken', 'testFrom', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('test.host')->setFast($fast)->setTest($test);
    }

    public function toStringProvider(): iterable
    {
        yield ['smsapi://test.host?from=testFrom', $this->createTransport()];
        yield ['smsapi://test.host?from=testFrom&fast=1', $this->createTransport(null, true)];
        yield ['smsapi://test.host?from=testFrom&test=1', $this->createTransport(null, false, true)];
        yield ['smsapi://test.host?from=testFrom&fast=1&test=1', $this->createTransport(null, true, true)];
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

    public function createClient(int $statusCode, string $content): HttpClientInterface
    {
        return new MockHttpClient(new MockResponse($content, ['http_code' => $statusCode]));
    }

    public function responseProvider(): iterable
    {
        $responses = [
            ['status' => 200, 'content' => '{"error":101,"message":"Authorization failed"}', 'errorMessage' => 'Unable to send the SMS: "Authorization failed".'],
            ['status' => 500, 'content' => '{}', 'errorMessage' => 'Unable to send the SMS: "unknown error".'],
            ['status' => 500, 'content' => '{"error":null,"message":"Unknown"}', 'errorMessage' => 'Unable to send the SMS: "Unknown".'],
            ['status' => 500, 'content' => '{"error":null,"message":null}', 'errorMessage' => 'Unable to send the SMS: "unknown error".'],
            ['status' => 500, 'content' => 'Internal error', 'errorMessage' => 'Could not decode body to an array.'],
            ['status' => 200, 'content' => 'Internal error', 'errorMessage' => 'Could not decode body to an array.'],
        ];

        foreach ($responses as $response) {
            yield [$response['status'], $response['content'], $response['errorMessage']];
        }
    }

    /**
     * @dataProvider responseProvider
     */
    public function testThrowExceptionWhenMessageWasNotSent(int $statusCode, string $content, string $errorMessage)
    {
        $client = $this->createClient($statusCode, $content);
        $transport = $this->createTransport($client);
        $message = new SmsMessage('0611223344', 'Hello!');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($errorMessage);

        $transport->send($message);
    }
}
