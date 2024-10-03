<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Controller\WebhookController;

class WebhookControllerTest extends TestCase
{
    public function testNoParserAvailable()
    {
        $controller = new WebhookController([], $this->createMock(MessageBusInterface::class));

        $response = $controller->handle('foo', new Request());

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * @dataProvider rejectedParseProvider
     */
    public function testParserRejectsPayload($return)
    {
        $secret = '1234';
        $request = new Request();
        $parser = $this->createMock(RequestParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with($request, $secret)
            ->willReturn($return);
        $parser
            ->expects($this->once())
            ->method('createRejectedResponse')
            ->with('Unable to parse the webhook payload.', $request)
            ->willReturn(new Response('Unable to parse the webhook payload.', 406));

        $controller = new WebhookController(
            ['foo' => ['parser' => $parser, 'secret' => $secret]],
            $this->createMock(MessageBusInterface::class)
        );

        $response = $controller->handle('foo', $request);

        $this->assertSame(406, $response->getStatusCode());
        $this->assertSame('Unable to parse the webhook payload.', $response->getContent());
    }

    public static function rejectedParseProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
    }

    public function testParserAcceptsPayloadAndReturnsSingleEvent()
    {
        $secret = '1234';
        $request = new Request();
        $event = new RemoteEvent('name', 'id', ['payload']);
        $parser = $this->createMock(RequestParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with($request, $secret)
            ->willReturn($event);
        $parser
            ->expects($this->once())
            ->method('createSuccessfulResponse')
            ->with($request)
            ->willReturn(new Response('', 202));
        $bus = new class implements MessageBusInterface {
            public ?object $message = null;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($this->message = $message, $stamps);
            }
        };

        $controller = new WebhookController(
            ['foo' => ['parser' => $parser, 'secret' => $secret]],
            $bus,
        );

        $response = $controller->handle('foo', $request);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertInstanceOf(ConsumeRemoteEventMessage::class, $bus->message);
        $this->assertSame('foo', $bus->message->getType());
        $this->assertEquals($event, $bus->message->getEvent());
    }

    public function testParserAcceptsPayloadAndReturnsMultipleEvents()
    {
        $secret = '1234';
        $request = new Request();
        $event1 = new RemoteEvent('name1', 'id1', ['payload1']);
        $event2 = new RemoteEvent('name2', 'id2', ['payload2']);
        $parser = $this->createMock(RequestParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with($request, $secret)
            ->willReturn([$event1, $event2]);
        $parser
            ->expects($this->once())
            ->method('createSuccessfulResponse')
            ->with($request)
            ->willReturn(new Response('', 202));
        $bus = new class implements MessageBusInterface {
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($this->messages[] = $message, $stamps);
            }
        };

        $controller = new WebhookController(
            ['foo' => ['parser' => $parser, 'secret' => $secret]],
            $bus,
        );

        $response = $controller->handle('foo', $request);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertCount(2, $bus->messages);
        $this->assertInstanceOf(ConsumeRemoteEventMessage::class, $bus->messages[0]);
        $this->assertSame('foo', $bus->messages[0]->getType());
        $this->assertEquals($event1, $bus->messages[0]->getEvent());
        $this->assertInstanceOf(ConsumeRemoteEventMessage::class, $bus->messages[1]);
        $this->assertSame('foo', $bus->messages[1]->getType());
        $this->assertEquals($event2, $bus->messages[1]->getEvent());
    }
}
