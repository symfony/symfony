<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Cache;

use Doctrine\Common\Cache\ArrayCache;
use Symfony\Bridge\PhpUnit\ErrorAssert;
use Symfony\Component\Validator\Mapping\Cache\DoctrineCache;

class DoctrineCacheTest extends AbstractCacheTest
{
    protected function setUp()
    {
        ErrorAssert::assertDeprecationsAreTriggered(
            array(sprintf('%s is deprecated since version 3.2', DoctrineCache::class)),
            function () {
                $this->cache = new DoctrineCache(new ArrayCache());
            }
        );
    }
}
