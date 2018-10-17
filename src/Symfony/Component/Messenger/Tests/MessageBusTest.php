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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    /**
     * @expectedException \TypeError
     * @expectedExceptionMessage Invalid argument provided to "Symfony\Component\Messenger\MessageBus::dispatch()": expected object, but got string.
     */
    public function testItDispatchInvalidMessageType()
    {
        (new MessageBus())->dispatch('wrong');
    }

    public function testItCallsMiddleware()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);
        $responseFromDepthMiddleware = 1234;

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($envelope, $next) {
                $next($envelope);
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
        ;

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
        ));

        $bus->dispatch($message);
    }

    public function testThatAMiddlewareCanAddSomeStampsToTheEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, new ReceivedStamp());
        $envelopeWithAnotherStamp = $envelope->with(new AnEnvelopeStamp());

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($envelope, $next) {
                $next($envelope->with(new AnEnvelopeStamp()));
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
            ->will($this->returnCallback(function ($envelope, $next) {
                $next($envelope);
            }));

        $thirdMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $thirdMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
        ;

        $bus = new MessageBus(array(
            $firstMiddleware,
            $secondMiddleware,
            $thirdMiddleware,
        ));

        $bus->dispatch($envelope);
    }

    public function testThatAMiddlewareCanUpdateTheMessageWhileKeepingTheEnvelopeStamps()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, ...$stamps = array(new ReceivedStamp()));

        $changedMessage = new DummyMessage('Changed');
        $expectedEnvelope = new Envelope($changedMessage, ...$stamps);

        $firstMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->will($this->returnCallback(function ($message, $next) use ($expectedEnvelope) {
                $next($expectedEnvelope);
            }));

        $secondMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
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
