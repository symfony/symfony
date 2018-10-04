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

/**
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
trait NotExpiringStoreTestTrait
{
    /**
     * @see AbstractStoreTest::getStore()
     */
    abstract protected function getStore();

    /**
     * @expectedException \Symfony\Component\Lock\Exception\NotExpirableStoreException
     */
    public function testPutOffExpirationThrowsException()
    {
        $store = $this->getStore();
        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $store->putOffExpiration($key, 10.0);
    }
}
