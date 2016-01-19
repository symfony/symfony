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

use Cache\IntegrationTests\CachePoolTest;
use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;

/**
 * @group time-sensitive
 */
class DoctrineAdapterTest extends CachePoolTest
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayCache is not.',
        'testDeferredExpired' => 'Failing for now, needs to be fixed.',
    );

    public function createCachePool()
    {
        return new DoctrineAdapter(new ArrayCache());
    }
}
