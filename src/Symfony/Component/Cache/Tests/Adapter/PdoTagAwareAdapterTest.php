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
}
