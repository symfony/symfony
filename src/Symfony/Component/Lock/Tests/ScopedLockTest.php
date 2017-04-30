<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\ScopedLock;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class ScopedLockTest extends TestCase
{
    public function testReleaseWhenLeavingScope()
    {
        $lock = $this->getMockBuilder(LockInterface::class)->getMock();

        $lock
            ->method('isAcquired')
            ->willReturn(true)
        ;
        $lock
            ->expects($this->once())
            ->method('release')
        ;

        new ScopedLock($lock);
    }
}
