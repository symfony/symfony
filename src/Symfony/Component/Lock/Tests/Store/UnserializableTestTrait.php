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

use Symfony\Component\Lock\Exception\UnserializableKeyException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
trait UnserializableTestTrait
{
    /**
     * @see AbstractStoreTestCase::getStore()
     *
     * @return PersistingStoreInterface
     */
    abstract protected function getStore();

    public function testUnserializableKey()
    {
        $store = $this->getStore();

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $this->expectException(UnserializableKeyException::class);
        serialize($key);
    }
}
