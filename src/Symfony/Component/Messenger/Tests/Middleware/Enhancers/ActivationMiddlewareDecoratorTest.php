<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware\Enhancers;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EnvelopeAwareInterface;
use Symfony\Component\Messenger\Middleware\Enhancers\ActivationMiddlewareDecorator;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ActivationMiddlewareDecoratorTest extends TestCase
{
    public function testExecuteMiddlewareOnActivated()
    {
        $message = new DummyMessage('Hello');
        $envelope = Envelope::wrap($message);

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->never())->method('__invoke');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())->method('handle')->with($message, $next)->willReturn('Hello from middleware');

        $decorator = new ActivationMiddlewareDecorator($middleware, true);

        $this->assertSame('Hello from middleware', $decorator->handle($envelope, $next));
    }

    public function testExecuteMiddlewareOnActivatedWithCallable()
    {
        $message = new DummyMessage('Hello');
        $envelope = Envelope::wrap($message);

        $activated = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $activated->expects($this->once())->method('__invoke')->with($envelope)->willReturn(true);

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->never())->method('__invoke');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())->method('handle')->with($message, $next)->willReturn('Hello from middleware');

        $decorator = new ActivationMiddlewareDecorator($middleware, $activated);

        $this->assertSame('Hello from middleware', $decorator->handle($envelope, $next));
    }

    public function testExecuteEnvelopeAwareMiddlewareWithEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = Envelope::wrap($message);

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->never())->method('__invoke');

        $middleware = $this->createMock(array(MiddlewareInterface::class, EnvelopeAwareInterface::class));
        $middleware->expects($this->once())->method('handle')->with($envelope, $next)->willReturn('Hello from middleware');

        $decorator = new ActivationMiddlewareDecorator($middleware, true);

        $this->assertSame('Hello from middleware', $decorator->handle($envelope, $next));
    }

    public function testExecuteMiddlewareOnDeactivated()
    {
        $message = new DummyMessage('Hello');
        $envelope = Envelope::wrap($message);

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->with($envelope)->willReturn('Hello from $next');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->never())->method('handle');

        $decorator = new ActivationMiddlewareDecorator($middleware, false);

        $this->assertSame('Hello from $next', $decorator->handle($envelope, $next));
    }
}
