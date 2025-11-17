<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SchemaListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;

#[RequiresPhpExtension('pdo_sqlite')]
class AbstractSchemaListenerTest extends TestCase
{
    public function testSameDatabaseChecker()
    {
        $connectionParams = [
            'dbname' => ':memory:',
            'driver' => 'pdo_sqlite',
        ];
        // Create two distinct in-memory SQLite databases
        $connection1 = DriverManager::getConnection($connectionParams);
        $connection2 = DriverManager::getConnection($connectionParams);

        self::assertTrue($this->getIsSameDatabaseChecker($connection1)($connection1->executeStatement(...)));
        self::assertFalse($this->getIsSameDatabaseChecker($connection1)($connection2->executeStatement(...)));

        $remainingTables = $connection1->executeQuery('SELECT name FROM sqlite_schema WHERE name <> "sqlite_sequence"')->fetchFirstColumn();
        self::assertSame([], $remainingTables, 'Temporary table was dropped');
    }

    private function getIsSameDatabaseChecker(Connection $connection): \Closure
    {
        return (new class extends AbstractSchemaListener {
            public function postGenerateSchema($event): void
            {
            }

            public function getIsSameDatabaseChecker(Connection $connection): \Closure
            {
                return parent::getIsSameDatabaseChecker($connection);
            }
        })->getIsSameDatabaseChecker($connection);
    }
}
