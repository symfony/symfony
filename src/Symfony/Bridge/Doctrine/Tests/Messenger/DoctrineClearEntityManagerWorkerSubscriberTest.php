<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Messenger\DoctrineClearEntityManagerWorkerSubscriber;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineClearEntityManagerWorkerSubscriberTest extends MiddlewareTestCase
{
    public function testMiddlewareClearEntityManager()
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager1->expects($this->once())
            ->method('clear');

        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        $entityManager2->expects($this->once())
            ->method('clear');

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManagers')
            ->with()
            ->willReturn([$entityManager1, $entityManager2]);

        $subscriber = new DoctrineClearEntityManagerWorkerSubscriber($managerRegistry);
        $subscriber->onWorkerMessageHandled();
    }
}
