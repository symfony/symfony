<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoTagAwareAdapter;

#[RequiresPhpExtension('pdo_sqlite')]
#[Group('time-sensitive')]
class PdoTagAwareAdapterTest extends AbstractPdoAdapterTestCase
{
    use TagAwareTestTrait;

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoTagAwareAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
    }

    public function testDeletedItemIsNotInvalidatedByItsFormerTags()
    {
        $pool = $this->createCachePool();
        $pool->save($pool->getItem('deleted_item')->set('foo')->tag('former_tag'));
        $pool->deleteItem('deleted_item');

        $pool->save($pool->getItem('deleted_item')->set('bar'));
        $pool->invalidateTags(['former_tag']);

        $this->assertTrue($pool->getItem('deleted_item')->isHit());
    }

    public function testStatementsDoNotMixNamedAndPositionalParameters()
    {
        $pdo = new class('sqlite:'.self::$dbFile) extends \PDO {
            public array $queries = [];

            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->queries[] = $query;

                return parent::prepare($query, $options);
            }
        };

        $pool = new PdoTagAwareAdapter($pdo, 'ns');
        $pool->save($pool->getItem('foo')->set('bar')->tag(['tag1', 'tag2']));
        $pool->save($pool->getItem('foo')->set('bar')->tag('tag1'));
        $pool->deleteItem('foo');
        $pool->invalidateTags(['tag1']);

        $this->assertNotEmpty($pdo->queries);
        foreach ($pdo->queries as $query) {
            $this->assertFalse(str_contains($query, '?') && preg_match('/:\w/', $query), \sprintf('Query "%s" mixes named and positional parameters.', $query));
        }
    }

    public function testInvalidateTagsWithMissingTableWhenExecutionIsDeferred()
    {
        $exception = new \PDOException('The tags table does not exist.');
        $exception->errorInfo = ['42P01', 7, $exception->getMessage()];
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->once())->method('execute')->willThrowException($exception);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION],
            [\PDO::ATTR_DRIVER_NAME, 'pgsql'],
        ]);
        $pdo->expects($this->once())->method('prepare')->willReturn($statement);
        $pdo->expects($this->never())->method('exec');

        $pool = new PdoTagAwareAdapter($pdo);

        $this->assertTrue($pool->invalidateTags(['unused']));
    }

    public function testPruneCreatesMissingTagsTableWhenExecutionIsDeferred()
    {
        $pool = new PdoTagAwareAdapter($this->createConnectionWithMissingTagsTable());

        $this->assertTrue($pool->prune());
    }

    public function testPruneWithoutTables()
    {
        $pool = new PdoTagAwareAdapter('sqlite:'.$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache'));

        try {
            $this->assertTrue($pool->prune());
            $this->assertTrue($pool->prune());
        } finally {
            @unlink($dbFile);
        }
    }

    public function testInvalidateTagsWithoutTables()
    {
        $pool = new PdoTagAwareAdapter('sqlite:'.$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache'));

        try {
            $this->assertTrue($pool->invalidateTags(['foo']));
            $this->assertTrue($pool->invalidateTags(['foo']));
        } finally {
            @unlink($dbFile);
        }
    }

    private function createConnectionWithMissingTagsTable(): \PDO
    {
        $exception = new \PDOException('The tags table does not exist.');
        $exception->errorInfo = ['42P01', 7, $exception->getMessage()];
        $attempts = 0;
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->exactly(2))->method('execute')->willReturnCallback(static function () use ($exception, &$attempts): bool {
            if (1 === ++$attempts) {
                throw $exception;
            }

            return true;
        });
        $pruneItems = $this->createStub(\PDOStatement::class);
        $pruneItems->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION],
            [\PDO::ATTR_DRIVER_NAME, 'pgsql'],
        ]);
        $pdo->expects($this->exactly(2))->method('prepare')->willReturnOnConsecutiveCalls($pruneItems, $statement);
        $pdo->expects($this->exactly(2))->method('exec')->willReturn(0);

        return $pdo;
    }
}
