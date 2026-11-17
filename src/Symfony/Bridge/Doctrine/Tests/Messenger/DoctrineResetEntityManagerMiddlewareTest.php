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
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Messenger\DoctrineResetEntityManagerMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrineResetEntityManagerMiddlewareTest extends MiddlewareTestCase
{
    public function testResetsClosedManagerBeforeHandlingWorkerMessage()
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($manager);
        $registry->expects($this->once())->method('resetManager')->with('default');

        $middleware = new DoctrineResetEntityManagerMiddleware($registry, 'default');
        $middleware->handle($this->workerEnvelope(), $this->getStackMock());
    }

    public function testLeavesOpenManagersUntouched()
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($manager);
        $registry->expects($this->never())->method('resetManager');

        (new DoctrineResetEntityManagerMiddleware($registry, 'default'))
            ->handle($this->workerEnvelope(), $this->getStackMock());
    }

    public function testResetsClosedManagersWhenNoManagerNameIsSpecified()
    {
        $open = $this->createStub(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);

        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerNames')->willReturn([
            'open' => 'doctrine.orm.open_entity_manager',
            'closed' => 'doctrine.orm.closed_entity_manager',
        ]);
        $registry->method('getManager')->willReturnMap([
            ['open', $open],
            ['closed', $closed],
        ]);
        $registry->expects($this->once())->method('resetManager')->with('closed');

        (new DoctrineResetEntityManagerMiddleware($registry))
            ->handle($this->workerEnvelope(), $this->getStackMock());
    }

    public function testIgnoresNonOrmManagers()
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.odm.default_manager']);
        $registry->method('getManager')->with('default')->willReturn($this->createStub(ObjectManager::class));
        $registry->expects($this->never())->method('resetManager');

        (new DoctrineResetEntityManagerMiddleware($registry))
            ->handle($this->workerEnvelope(), $this->getStackMock());
    }

    public function testDoesNotResetInNonWorkerContext()
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManager');
        $registry->expects($this->never())->method('resetManager');

        (new DoctrineResetEntityManagerMiddleware($registry))
            ->handle(new Envelope(new \stdClass()), $this->getStackMock());
    }

    public function testUnknownManagerThrowsUnrecoverableMessageHandlingException()
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new \InvalidArgumentException('Unknown manager'));

        $this->expectException(UnrecoverableMessageHandlingException::class);

        (new DoctrineResetEntityManagerMiddleware($registry, 'missing'))
            ->handle($this->workerEnvelope(), $this->getStackMock(false));
    }

    private function workerEnvelope(): Envelope
    {
        return new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]);
    }
}
