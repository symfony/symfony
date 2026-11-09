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

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

trait FreshKeyTestTrait
{
    abstract protected function getStore(): PersistingStoreInterface;

    public function testExistsOnFreshKeyDoesNotCreateState()
    {
        $key = new Key(__METHOD__);
        $store = $this->getStore();

        $this->assertFalse($store->exists($key));
        $this->assertFalse($key->hasState($store::class));
    }

    public function testDeleteOnFreshKeyDoesNotCreateState()
    {
        $key = new Key(__METHOD__);
        $store = $this->getStore();

        $store->delete($key);
        $this->assertFalse($key->hasState($store::class));
    }
}
