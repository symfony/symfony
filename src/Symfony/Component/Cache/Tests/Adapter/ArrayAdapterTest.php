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

use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @group time-sensitive
 */
class ArrayAdapterTest extends AdapterTestCase
{
    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
    );

    public function createCachePool($defaultLifetime = 0)
    {
        return new ArrayAdapter($defaultLifetime);
    }
}
