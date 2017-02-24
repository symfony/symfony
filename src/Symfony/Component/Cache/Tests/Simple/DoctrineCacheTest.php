<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Cache\Simple\DoctrineCache;

/**
 * @group time-sensitive
 */
class DoctrineCacheTest extends CacheTestCase
{
    protected $skippedTests = array(
        'testObjectDoesNotChangeInCache' => 'ArrayCache does not use serialize/unserialize',
        'testNotUnserializable' => 'ArrayCache does not use serialize/unserialize',
    );

    public function createSimpleCache($defaultLifetime = 0)
    {
        return new DoctrineCache(new ArrayCache($defaultLifetime), '', $defaultLifetime);
    }
}
