<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\NullStore;

class NullStoreTest extends TestCase
{
    public function testExistsAlwaysReturnsFalse()
    {
        $store = new NullStore();
        $key = new Key('foo');

        $this->assertFalse($store->exists($key));
    }

    public function testSaveDoesNothing()
    {
        $store = new NullStore();
        $key = new Key('foo');

        $store->save($key);

        $this->assertFalse($store->exists($key));
    }
}
