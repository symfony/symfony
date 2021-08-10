<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OvhCloudTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, string $sender = null): OvhCloudTransport
    {
        return (new OvhCloudTransport('applicationKey', 'applicationSecret', 'consumerKey', 'serviceName', $client ?? $this->createMock(HttpClientInterface::class)))->setSender($sender);
    }

    public function toStringProvider(): iterable
    {
        yield ['ovhcloud://eu.api.ovh.com?consumer_key=consumerKey&service_name=serviceName', $this->createTransport()];
        yield ['ovhcloud://eu.api.ovh.com?consumer_key=consumerKey&service_name=serviceName&sender=sender', $this->createTransport(null, 'sender')];
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

    public function validMessagesProvider(): iterable
    {
        yield 'without a slash' => ['hello'];
        yield 'including a slash' => ['hel/lo'];
    }

    /**
     * @group time-sensitive
     *
     * @dataProvider validMessagesProvider
     */
    public function testValidSignature(string $message)
    {
        $smsMessage = new SmsMessage('0611223344', $message);

        $time = time();

        $data = json_encode([
            'totalCreditsRemoved' => '1',
            'invalidReceivers' => [],
            'ids' => [
                '26929925',
            ],
            'validReceivers' => [
                '0611223344',
            ],
        ]);
        $lastResponse = new MockResponse($data);
        $responses = [
            new MockResponse((string) $time),
            $lastResponse,
        ];

        $transport = $this->createTransport(new MockHttpClient($responses));
        $transport->send($smsMessage);

        $body = $lastResponse->getRequestOptions()['body'];
        $headers = $lastResponse->getRequestOptions()['headers'];
        $signature = explode(': ', $headers[4])[1];

        $endpoint = 'https://eu.api.ovh.com/1.0/sms/serviceName/jobs';
        $toSign = 'applicationSecret+consumerKey+POST+'.$endpoint.'+'.$body.'+'.$time;
        $this->assertSame('$1$'.sha1($toSign), $signature);
    }
}
