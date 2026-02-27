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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\PdoSessionHandlerSchemaListener;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class PdoSessionHandlerSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchemaPdo()
    {
        $schema = new Schema();
        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $pdoSessionHandler = $this->createMock(PdoSessionHandler::class);
        $pdoSessionHandler->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $this->callback(static fn () => true));

        $subscriber = new PdoSessionHandlerSchemaListener($pdoSessionHandler);
        $subscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'sessions' !== $tableName);

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $pdoSessionHandler = $this->createStub(PdoSessionHandler::class);
        $pdoSessionHandler->method('configureSchema')
            ->willReturnCallback(static function (Schema $schema) {
                $table = $schema->createTable('sessions');
                $table->addColumn('sess_id', 'string');
            });

        $listener = new PdoSessionHandlerSchemaListener($pdoSessionHandler);
        $listener->postGenerateSchema($event);

        $this->assertFalse($schema->hasTable('sessions'));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testPostGenerateSchemaWithDifferentDatabaseDoesNotThrow()
    {
        $entityManager = DoctrineTestHelper::createTestEntityManager();
        $schema = new Schema();
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $sessionPdo = new \PDO('sqlite::memory:');
        $sessionPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $sessionHandler = new PdoSessionHandler($sessionPdo);
        $subscriber = new PdoSessionHandlerSchemaListener($sessionHandler);

        // When the session handler uses a different PDO connection than Doctrine's,
        // the schema listener must not fail (it should just detect databases differ).
        $subscriber->postGenerateSchema($event);

        self::assertFalse($schema->hasTable('sessions'));
    }
}
