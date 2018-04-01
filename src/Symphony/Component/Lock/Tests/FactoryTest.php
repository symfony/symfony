<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symphony\Component\Lock\Factory;
use Symphony\Component\Lock\LockInterface;
use Symphony\Component\Lock\StoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class FactoryTest extends TestCase
{
    public function testCreateLock()
    {
        $store = $this->getMockBuilder(StoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $factory = new Factory($store);
        $factory->setLogger($logger);

        $lock = $factory->createLock('foo');

        $this->assertInstanceOf(LockInterface::class, $lock);
    }
}
