<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MicrosoftTeamsTransportTest extends TransportTestCase
{
    /**
     * @return MicrosoftTeamsTransport
     */
    public function createTransport(?HttpClientInterface $client = null): TransportInterface
    {
        return (new MicrosoftTeamsTransport('/testPath', $client ?: $this->createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['microsoftteams://host.test/testPath', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testSendWithErrorResponseThrows()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options = []): ResponseInterface {
            return new MockResponse('testErrorMessage', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 400]);
        });

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/testErrorMessage/');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSendWithErrorRequestIdThrows()
    {
        $client = new MockHttpClient(new MockResponse());

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/request-id not found/');

        $transport->send(new ChatMessage('testMessage'));
    }

    public function testSend()
    {
        $message = 'testMessage';

        $expectedBody = json_encode(['title' => $message]);

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertJsonStringEqualsJsonString($expectedBody, $options['body']);

            return new MockResponse('1', ['response_headers' => ['request-id' => ['testRequestId']], 'http_code' => 200]);
        });

        $transport = $this->createTransport($client);

        $transport->send(new ChatMessage($message));
    }
}
