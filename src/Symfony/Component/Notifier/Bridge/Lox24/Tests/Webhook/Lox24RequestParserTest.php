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
use Symfony\Component\Notifier\Bridge\Lox24\Webhook\LOX24RequestParser;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24RequestParserTest extends TestCase
{
    private LOX24RequestParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LOX24RequestParser();
    }

    public function testInvalidNotificationName()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Notification name is not \'sms.delivery\'');
        $request = $this->getRequest(['name' => 'invalid_name', 'data' => ['status_code' => 100]]);

        $this->parser->parse($request, '');
    }

    public function testMissingMsgId()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');
        $request = $this->getRequest(['name' => 'sms.delivery', 'data' => ['status_code' => 100]]);

        $this->parser->parse($request, '');
    }

    public function testMissingMsgStatusCode()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');
        $request = $this->getRequest(['name' => 'sms.delivery', 'data' => ['id' => '123']]);

        $this->parser->parse($request, '');
    }

    public function testStatusCode100()
    {
        $payload = [
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'status_code' => 100,
            ],
        ];
        $request = $this->getRequest($payload);

        $event = $this->parser->parse($request, '');
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::DELIVERED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testStatusCode0()
    {
        $request = $this->getRequest(
            [
                'name' => 'sms.delivery',
                'data' => [
                    'id' => '123',
                    'status_code' => 0,
                ],
            ]
        );

        $event = $this->parser->parse($request, '');
        $this->assertNull($event);
    }

    public function testStatusCodeUnknown()
    {
        $payload = [
            'name' => 'sms.delivery',
            'data' => [
                'id' => '123',
                'status_code' => 410,
            ],
        ];
        $request = $this->getRequest($payload);

        $event = $this->parser->parse($request, '');
        $this->assertSame('123', $event->getId());
        $this->assertSame(SmsEvent::FAILED, $event->getName());
        $this->assertSame($payload, $event->getPayload());
    }

    private function getRequest(array $data): Request
    {
        return Request::create('/test', 'POST', $data);
    }
}
