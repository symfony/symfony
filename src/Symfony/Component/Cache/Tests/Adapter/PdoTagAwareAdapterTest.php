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
}
