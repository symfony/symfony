<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures;

use Symfony\Component\Routing\Loader\ObjectRouteLoader;

class TestObjectRouteLoader extends ObjectRouteLoader
{
    public $loaderMap = [];

    /**
     * @return object
     */
    protected function getServiceObject($id)
    {
        return $this->loaderMap[$id] ?? null;
    }
}
