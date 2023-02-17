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
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OvhCloudTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null, string $sender = null, bool $noStopClause = false): OvhCloudTransport
    {
        return (new OvhCloudTransport('applicationKey', 'applicationSecret', 'consumerKey', 'serviceName', $client ?? new MockHttpClient()))->setSender($sender)->setNoStopClause($noStopClause);
    }

    public static function toStringProvider(): iterable
    {
        yield ['ovhcloud://eu.api.ovh.com?service_name=serviceName', self::createTransport()];
        yield ['ovhcloud://eu.api.ovh.com?service_name=serviceName', self::createTransport(null, null, true)];
        yield ['ovhcloud://eu.api.ovh.com?service_name=serviceName&sender=sender', self::createTransport(null, 'sender')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public static function validMessagesProvider(): iterable
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

        $transport = self::createTransport(new MockHttpClient($responses));
        $transport->send($smsMessage);

        $body = $lastResponse->getRequestOptions()['body'];
        $headers = $lastResponse->getRequestOptions()['headers'];
        $signature = explode(': ', $headers[4])[1];

        $endpoint = 'https://eu.api.ovh.com/1.0/sms/serviceName/jobs';
        $toSign = 'applicationSecret+consumerKey+POST+'.$endpoint.'+'.$body.'+'.$time;
        $this->assertSame('$1$'.sha1($toSign), $signature);
    }

    public function testInvalidReceiver()
    {
        $smsMessage = new SmsMessage('invalid_receiver', 'lorem ipsum');

        $data = json_encode([
            'totalCreditsRemoved' => '1',
            'invalidReceivers' => ['invalid_receiver'],
            'ids' => [],
            'validReceivers' => [],
        ]);
        $responses = [
            new MockResponse((string) time()),
            new MockResponse($data),
        ];

        $transport = self::createTransport(new MockHttpClient($responses));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Attempt to send the SMS to invalid receivers: "invalid_receiver"');
        $transport->send($smsMessage);
    }
}
