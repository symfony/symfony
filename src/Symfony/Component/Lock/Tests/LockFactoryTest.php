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
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockFactoryTest extends TestCase
{
    public function testCreateLock()
    {
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $factory = new LockFactory($store);
        $factory->setLogger($logger);

        $lock = $factory->createLock('foo');

        $this->assertInstanceOf(LockInterface::class, $lock);
    }
}
