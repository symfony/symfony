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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\LoggingMiddleware;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

/**
 * @group legacy
 */
class LoggingMiddlewareTest extends MiddlewareTestCase
{
    public function testDebugLogAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(2))
            ->method('debug')
        ;

        (new LoggingMiddleware($logger))->handle($envelope, $this->getStackMock());
    }

    public function testWarningLogOnException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thrown from next middleware.');
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
        ;
        $logger
            ->expects($this->once())
            ->method('warning')
        ;
        $stack = $this->getThrowingStackMock();

        (new LoggingMiddleware($logger))->handle($envelope, $stack);
    }
}
