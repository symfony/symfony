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
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class InMemoryStoreTest extends AbstractStoreTestCase
{
    use SharedLockStoreTestTrait;

    public function getStore(): PersistingStoreInterface
    {
        return new InMemoryStore();
    }

    public function testExistsOnFreshKeyDoesNotCreateState()
    {
        $key = new Key(__METHOD__);

        $this->assertFalse($this->getStore()->exists($key));
        $this->assertFalse($key->hasState(InMemoryStore::class));
    }
}
