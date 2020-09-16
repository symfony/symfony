<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\SemaphoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SemaphoreFactoryTest extends TestCase
{
    public function testCreateSemaphore()
    {
        $store = $this->getMockBuilder(PersistingStoreInterface::class)->getMock();
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $factory = new SemaphoreFactory($store);
        $factory->setLogger($logger);

        $semaphore = $factory->createSemaphore('foo', 4);

        $this->assertInstanceOf(SemaphoreInterface::class, $semaphore);
    }
}
