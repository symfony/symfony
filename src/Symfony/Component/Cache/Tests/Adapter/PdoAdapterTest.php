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
use Symfony\Component\Cache\Adapter\PdoAdapter;

#[RequiresPhpExtension('pdo_sqlite')]
#[Group('time-sensitive')]
class PdoAdapterTest extends AbstractPdoAdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
    }

    public function testShutdownResetKeepsAConnectionPassedToTheConstructor()
    {
        $cache = new PdoAdapter($pdo = new \PDO('sqlite:'.self::$dbFile));
        $cache->reset(true);

        $this->assertSame($pdo, (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache));
    }
}
