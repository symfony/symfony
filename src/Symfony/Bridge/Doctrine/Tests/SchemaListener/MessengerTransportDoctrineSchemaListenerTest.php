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
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerTransportDoctrineSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchema()
    {
        $schema = new Schema();
        $dbalConnection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection, $this->callback(fn () => true));
        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects($this->never())
            ->method($this->anything());

        $subscriber = new MessengerTransportDoctrineSchemaListener([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }
}
