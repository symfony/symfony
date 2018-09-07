<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeItem;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid type for message argument. Expected object, but got "string".
     */
    public function testItDispatchInvalidMessageType()
    {
        (new MessageBus())->dispatch('wrong');
    }

    public function testItCallsMiddlewareAndChainTheReturnValue()
    {
        $message = new DummyMessage('Hello');
        $responseFromDepthMiddleware = 1234;

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function ($message, $next) {
                return $next($message);
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->willReturn($responseFromDepthMiddleware);

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
        ));

        $this->assertEquals($responseFromDepthMiddleware, $bus->dispatch($message));
    }

    public function testItKeepsTheEnvelopeEvenThroughAMiddlewareThatIsNotEnvelopeAware()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, array(new ReceivedMessage()));

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function ($message, $next) {
                return $next($message);
            }));

        $secondMiddleware = $this->getMockBuilder(array(MiddlewareInterface::class, EnvelopeAwareInterface::class))->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
        ;

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
        ));

        $bus->dispatch($envelope);
    }

    public function testThatAMiddlewareCanAddSomeItemsToTheEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, array(new ReceivedMessage()));
        $envelopeWithAnotherItem = $envelope->with(new AnEnvelopeItem());

        $firstMiddleware = $this->getMockBuilder(array(MiddlewareInterface::class, EnvelopeAwareInterface::class))->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($message, $next) {
                return $next($message->with(new AnEnvelopeItem()));
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function ($message, $next) {
                return $next($message);
            }));

        $thirdMiddleware = $this->getMockBuilder(array(MiddlewareInterface::class, EnvelopeAwareInterface::class))->getMock();
        $thirdMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherItem, $this->anything())
        ;

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
            $thirdMiddleware,
        ));

        $bus->dispatch($envelope);
    }

    public function testThatAMiddlewareCanUpdateTheMessageWhileKeepingTheEnvelopeItems()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, $items = array(new ReceivedMessage()));

        $changedMessage = new DummyMessage('Changed');
        $expectedEnvelope = new Envelope($changedMessage, $items);

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($message, $this->anything())
            ->will($this->returnCallback(function ($message, $next) use ($changedMessage) {
                return $next($changedMessage);
            }));

        $secondMiddleware = $this->getMockBuilder(array(MiddlewareInterface::class, EnvelopeAwareInterface::class))->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($expectedEnvelope, $this->anything())
        ;

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
        ));

        $bus->dispatch($envelope);
    }
}
