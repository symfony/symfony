<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24\Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Lox24\Webhook\Lox24RequestParser;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24RequestParserTest extends TestCase
{
    private Lox24RequestParser $parser;

    protected function setUp(): void
    {
        $this->parser = new Lox24RequestParser();
    }

    public function testMissingBasicPayloadStructure()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required fields "id", "data" are missing from the payload.');

        $request = $this->getRequest(['name' => 'sms.delivery']);
        $this->parser->parse($request, '');
    }

    public function testSmsDeliveryMissingMsgId()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required field "id" is missing from the delivery event payload.');

        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => ['status_code' => 100],
        ]);
        $this->parser->parse($request, '');
    }

    public function testSmsDeliveryMissingBothCodes()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required field "status_code" or "dlr_code" is missing from the delivery event payload.');

        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => ['id' => '123'],
        ]);
        $this->parser->parse($request, '');
    }

    public function testSmsDeliveryStatusCode100()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'status_code' => 100,
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::DELIVERED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSmsDeliveryStatusCode0()
    {
        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'status_code' => 0,
            ],
        ]);

        $event = $this->parser->parse($request, '');
        $this->assertNull($event);
    }

    public function testSmsDeliveryWithDlrCodeDelivered()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 1,
                'callback_data' => 'test-callback',
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::DELIVERED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSmsDeliveryWithDlrCodeFailed()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 16,
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::FAILED, $event->getName());
    }

    public function testSmsDeliveryWithDlrCodePending()
    {
        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 2,
            ],
        ]);

        $event = $this->parser->parse($request, '');
        $this->assertNull($event);
    }

    public function testSmsDeliveryDryrun()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery.dryrun',
            'data' => [
                'id' => '123',
                'status_code' => 100,
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::DELIVERED, $event->getName());
    }

    public function testMissingIdField()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required fields "id" are missing from the payload.');

        $request = $this->getRequest([
            'name' => 'sms.delivery',
            'data' => ['id' => '123'],
        ]);
        $this->parser->parse($request, '');
    }

    public function testMissingNameField()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required fields "name" are missing from the payload.');

        $request = $this->getRequest([
            'id' => 'webhook-id',
            'data' => ['id' => '123'],
        ]);
        $this->parser->parse($request, '');
    }

    public function testMissingDataField()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The required fields "data" are missing from the payload.');

        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
        ]);
        $this->parser->parse($request, '');
    }

    public function testInvalidDataFieldNotArray()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The "data" field must be an array.');

        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => 'invalid-data',
        ]);
        $this->parser->parse($request, '');
    }

    public function testRemoteEventForUnknownEventType()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'custom.event',
            'data' => ['some' => 'data'],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(RemoteEvent::class, $event);
        $this->assertSame('webhook-id', $event->getId());
        $this->assertSame('custom.event', $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSmsDeliveryStatusCodeFailed()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'status_code' => 500,
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::FAILED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSmsDeliveryWithDlrCode0()
    {
        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 0,
            ],
        ]);

        $event = $this->parser->parse($request, '');
        $this->assertNull($event);
    }

    public function testSmsDeliveryWithDlrCode4()
    {
        $request = $this->getRequest([
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 4,
            ],
        ]);

        $event = $this->parser->parse($request, '');
        $this->assertNull($event);
    }

    public function testSmsDeliveryDlrCodePriorityOverStatusCode()
    {
        $payload = [
            'id' => 'webhook-id',
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'dlr_code' => 1,
                'status_code' => 500,
            ],
        ];

        $request = $this->getRequest($payload);
        $event = $this->parser->parse($request, '');

        $this->assertInstanceOf(SmsEvent::class, $event);
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::DELIVERED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    private function getRequest(array $data): Request
    {
        return Request::create('/test', 'POST', $data);
    }
}
