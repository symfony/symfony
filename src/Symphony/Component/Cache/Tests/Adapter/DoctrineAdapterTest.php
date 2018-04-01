<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Adapter;

use Symphony\Component\Cache\Adapter\DoctrineAdapter;
use Symphony\Component\Cache\Tests\Fixtures\ArrayCache;

/**
 * @group time-sensitive
 */
class DoctrineAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayCache is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayCache is not.',
        'testNotUnserializable' => 'ArrayCache does not use serialize/unserialize',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        return new DoctrineAdapter(new ArrayCache($defaultLifetime), '', $defaultLifetime);
    }
}
