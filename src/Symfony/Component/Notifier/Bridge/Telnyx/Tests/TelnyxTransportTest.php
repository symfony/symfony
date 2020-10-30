<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 */
final class TelnyxTransportTest extends TestCase
{
    /**
     * @var HttpClientInterface|MockObject
     */
    private $client;

    public function testShouldSupportSmsMessageInstance()
    {
        $transport = $this->createTelnyxTransport();
        $smsMessage = new SmsMessage('37100101010101', 'Hello');
        $chatMessage = new ChatMessage('Hello');

        $this->assertTrue($transport->supports($smsMessage));
        $this->assertFalse($transport->supports($chatMessage));
    }

    public function testShouldThrowExceptionWhenPassedNotSmsMessageInstance()
    {
        $transport = $this->createTelnyxTransport();
        $chatMessage = new ChatMessage('Hello');
        $this->expectException(LogicException::class);

        $transport->send($chatMessage);
    }

    public function testShouldSuccessfullySendSmsMessage()
    {
        $transport = $this->createTelnyxTransport();
        $message = new SmsMessage('37100101010101', 'Hello');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $content = <<<JSON
           {
              "data": {
                "record_type": "message",
                "direction": "outbound",
                "id": "403w75a7-5c8a-4bbc-981f-symfony0f81d",
                "type": "SMS",
                "organization_id": "3xxxxxx9-955a-4970-961d-bxxxxxxxx6a8",
                "messaging_profile_id": "4xxxxxxa-803d-411d-a488-8xxxxxxxx493",
                "from": "Sender",
                "to": [
                  {
                    "phone_number": "37100101010101",
                    "status": "queued",
                    "carrier": "",
                    "line_type": ""
                  }
                ],
                "text": "Hello",
                "media": [],
                "webhook_url": "http://telnyxwebhooks.com:8084/563xxxx4-dxx0-4cxx-9xx8-7fxxxxxxxx23",
                "webhook_failover_url": "",
                "encoding": "GSM-7",
                "parts": 1,
                "tags": [],
                "cost": null,
                "received_at": "2020-11-08T10:17:41.049+00:00",
                "sent_at": null,
                "completed_at": null,
                "valid_until": "2020-11-08T11:17:41.049+00:00",
                "errors": []
              }
            }
JSON;

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(json_decode($content, true));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://test.host/v2/messages', [
                'auth_bearer' => 'apikey',
                'json' => [
                    'to' => '37100101010101',
                    'from' => '37100000000',
                    'text' => 'Hello',
                ],
            ])
            ->willReturn($response);

        $sentMessage = $transport->send($message);

        $this->assertEquals('403w75a7-5c8a-4bbc-981f-symfony0f81d', $sentMessage->getMessageId());
        $this->assertEquals('telnyx://test.host?from=37100000000', $sentMessage->getTransport());
    }

    public function testShouldThrowTransportException()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: "Invalid phone number"');

        $transport = $this->createTelnyxTransport();
        $message = new SmsMessage('fake number', 'Hello');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(404);

        $content = <<<JSON
           {
                "errors": [
                    {
                        "code": "10002",
                        "title": "Invalid phone number",
                        "detail": "Invalid destination number",
                        "meta": {
                            "url": "https://developers.telnyx.com/docs/overview/errors/10002"
                        }
                    }
                ]
           }
JSON;

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn(json_decode($content, true));

        $this->client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://test.host/v2/messages', [
                'auth_bearer' => 'apikey',
                'json' => [
                    'to' => 'fake number',
                    'from' => '37100000000',
                    'text' => 'Hello',
                ],
            ])
            ->willReturn($response);

        $transport->send($message);
    }

    private function createTelnyxTransport(): TelnyxTransport
    {
        $this->client = $this->createMock(HttpClientInterface::class);

        return (new TelnyxTransport('apikey', '37100000000', $this->client))
            ->setHost('test.host');
    }
}
