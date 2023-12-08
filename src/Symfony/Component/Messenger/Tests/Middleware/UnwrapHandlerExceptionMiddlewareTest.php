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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Middleware\UnwrapHandlerExceptionMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class UnwrapHandlerExceptionMiddlewareTest extends MiddlewareTestCase
{
    public function testItThrowTheWrappedException()
    {
        $middleware = new UnwrapHandlerExceptionMiddleware();
        $envelope = new Envelope(new \stdClass());
        $wrappedException = new \RuntimeException('Wrapped exception.');
        $exception = new HandlerFailedException($envelope, ['single handler' => $wrappedException]);

        $this->expectException($wrappedException::class);
        $this->expectExceptionMessage($wrappedException->getMessage());

        $middleware->handle($envelope, $this->getThrowingStackMock($exception));
    }

    public function testItFailsWhenThereIsManyWrappedExceptions()
    {
        $middleware = new UnwrapHandlerExceptionMiddleware();
        $envelope = new Envelope(new \stdClass());
        $exception = new HandlerFailedException($envelope, [
            'first handler' => new \RuntimeException('Wrapped exception.'),
            'second handler' => new \RuntimeException('Wrapped exception.'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"Symfony\Component\Messenger\Middleware\UnwrapHandlerExceptionMiddleware" can only unwrap a single exception, but got 2.');

        $middleware->handle($envelope, $this->getThrowingStackMock($exception));
    }
}
