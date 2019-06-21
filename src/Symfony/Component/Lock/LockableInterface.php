<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

interface LockableInterface
{
    /**
     * Sets the current store.
     *
     * @param StoreInterface $store The store to be used
     */
    public function setStore(StoreInterface $store);
}
