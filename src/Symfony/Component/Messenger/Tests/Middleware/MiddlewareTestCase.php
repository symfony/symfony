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
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

abstract class MiddlewareTestCase extends TestCase
{
    protected function getStackMock(bool $nextIsCalled = true)
    {
        $nextMiddleware = $this->getMockBuilder(MiddlewareInterface::class)->getMock();
        $nextMiddleware
            ->expects($nextIsCalled ? $this->once() : $this->never())
            ->method('handle')
        ;

        $stack = $this->createMock(StackInterface::class);
        $stack
            ->expects($nextIsCalled ? $this->once() : $this->never())
            ->method('next')
            ->willReturn($nextMiddleware)
        ;

        return $stack;
    }
}
