<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Octopush\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OctopushTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): OctopushTransport
    {
        return new OctopushTransport('userLogin', 'apiKey', 'from', 'type', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['octopush://www.octopush-dm.com?from=from&type=type', self::createTransport()];
        yield ['octopush://api.octopush.com?from=from&type=sms_premium', new OctopushTransport('userLogin', 'apiKey', 'from', 'sms_premium', new MockHttpClient())];
        yield ['octopush://api.octopush.com?from=from&type=sms_low_cost&purpose=wholesale', new OctopushTransport('userLogin', 'apiKey', 'from', 'sms_low_cost', new MockHttpClient(), purpose: 'wholesale')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('33611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithTheLegacyApi()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://www.octopush-dm.com/api/sms/json', $url);
            $this->assertStringContainsString('sms_type=FR', $options['body']);

            return new JsonMockResponse(['ticket' => 'legacy-ticket']);
        });

        $sentMessage = (new OctopushTransport('userLogin', 'apiKey', 'from', 'FR', $client))->send(new SmsMessage('33611223344', 'Hello!'));

        $this->assertSame('legacy-ticket', $sentMessage->getMessageId());
    }

    public function testSendWithTheApiV1()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.octopush.com/v1/public/sms-campaign/send', $url);
            $this->assertContains('api-login: user@example.com', $options['headers']);
            $this->assertContains('api-key: apiKey', $options['headers']);
            $this->assertSame([
                'recipients' => [['phone_number' => '+33611223344']],
                'text' => 'Hello!',
                'type' => 'sms_premium',
                'purpose' => 'alert',
                'sender' => 'from',
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['sms_ticket' => 'sms_5f6d7a', 'number_of_contacts' => 1], ['http_code' => 201]);
        });

        $sentMessage = (new OctopushTransport(urlencode('user@example.com'), 'apiKey', 'from', 'sms_premium', $client))->send(new SmsMessage('+33611223344', 'Hello!'));

        $this->assertSame('sms_5f6d7a', $sentMessage->getMessageId());
    }

    public function testSendWithTheApiV1AndAPurpose()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('wholesale', json_decode($options['body'], true)['purpose']);

            return new JsonMockResponse(['sms_ticket' => 'sms_5f6d7b'], ['http_code' => 201]);
        });

        $transport = new OctopushTransport('userLogin', 'apiKey', 'from', 'sms_low_cost', $client, purpose: 'wholesale');

        $this->assertSame('sms_5f6d7b', $transport->send(new SmsMessage('+33611223344', 'Hello!'))->getMessageId());
    }

    public function testApiV1ErrorIsReported()
    {
        $client = new MockHttpClient(new JsonMockResponse(['code' => 2001, 'message' => 'Not enough credit'], ['http_code' => 400]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: "Not enough credit" (code "2001").');

        (new OctopushTransport('userLogin', 'apiKey', 'from', 'sms_premium', $client))->send(new SmsMessage('+33611223344', 'Hello!'));
    }

    public function testPurposeRequiresAnApiV1Type()
    {
        $this->expectException(InvalidArgumentException::class);

        new OctopushTransport('userLogin', 'apiKey', 'from', 'FR', new MockHttpClient(), purpose: 'alert');
    }

    public function testPurposeMustBeKnown()
    {
        $this->expectException(InvalidArgumentException::class);

        new OctopushTransport('userLogin', 'apiKey', 'from', 'sms_premium', new MockHttpClient(), purpose: 'marketing');
    }
}
