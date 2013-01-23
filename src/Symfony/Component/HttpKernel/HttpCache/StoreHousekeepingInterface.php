<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

/**
 * Interface implemented by HTTP cache stores, the stores should provide a cleaner
 *
 * @author Giulio De Donato <liuggio@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface StoreHousekeepingInterface
{
     /**
     * clear all the stale entries
     *
     * @return int the number of the cleared entries
     */
    public function clear();

}
