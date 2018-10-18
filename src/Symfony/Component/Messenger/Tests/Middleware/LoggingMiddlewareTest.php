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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\LoggingMiddleware;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class LoggingMiddlewareTest extends TestCase
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
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($envelope)
        ;

        (new LoggingMiddleware($logger))->handle($envelope, $next);
    }

    /**
     * @expectedException \Exception
     */
    public function testWarningLogOnException()
    {
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
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next
            ->expects($this->once())
            ->method('__invoke')
            ->with($envelope)
            ->willThrowException(new \Exception())
        ;

        (new LoggingMiddleware($logger))->handle($envelope, $next);
    }
}
