<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\LockStoreSchemaListener;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class LockStoreSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchemaLockPdo()
    {
        $schema = new Schema();
        $dbalConnection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $lockStore = $this->createMock(DoctrineDbalStore::class);
        $lockStore->expects($this->once())
            ->method('configureSchema')
            ->with($schema, fn () => true);

        $subscriber = new LockStoreSchemaListener([$lockStore]);
        $subscriber->postGenerateSchema($event);
    }
}
