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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Middleware\LoggingMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class LoggingMiddlewareTest extends TestCase
{
    public function testDebugLogAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(2))
            ->method('debug')
        ;
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($message)
            ->willReturn('Hello')
        ;

        $result = (new LoggingMiddleware($logger))->handle($message, $next);

        $this->assertSame('Hello', $result);
    }

    /**
     * @expectedException \Exception
     */
    public function testWarningLogOnException()
    {
        $message = new DummyMessage('Hey');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
        ;
        $logger
            ->expects($this->once())
            ->method('warning')
        ;
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($message)
            ->willThrowException(new \Exception())
        ;

        (new LoggingMiddleware($logger))->handle($message, $next);
    }
}
