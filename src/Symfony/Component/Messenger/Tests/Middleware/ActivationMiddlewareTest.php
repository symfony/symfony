<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\ActivationMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ActivationMiddlewareTest extends MiddlewareTestCase
{
    public function testExecuteMiddlewareOnActivated()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $stack = $this->getStackMock(false);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())->method('handle')->with($envelope, $stack)->willReturn($envelope);

        $decorator = new ActivationMiddleware($middleware, true);

        $decorator->handle($envelope, $stack);
    }

    public function testExecuteMiddlewareOnActivatedWithCallable()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $activated = $this->createPartialMock(ActivationMiddlewareTestCallable::class, ['__invoke']);
        $activated->expects($this->once())->method('__invoke')->with($envelope)->willReturn(true);

        $stack = $this->getStackMock(false);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())->method('handle')->with($envelope, $stack)->willReturn($envelope);

        $decorator = new ActivationMiddleware($middleware, $activated);

        $decorator->handle($envelope, $stack);
    }

    public function testExecuteMiddlewareOnDeactivated()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->never())->method('handle');

        $decorator = new ActivationMiddleware($middleware, false);

        $decorator->handle($envelope, $this->getStackMock());
    }
}

class ActivationMiddlewareTestCallable
{
    public function __invoke()
    {
    }
}
