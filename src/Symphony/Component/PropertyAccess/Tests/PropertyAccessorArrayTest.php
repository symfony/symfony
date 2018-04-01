<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyAccess\Tests;

class PropertyAccessorArrayTest extends PropertyAccessorCollectionTest
{
    protected function getContainer(array $array)
    {
        return $array;
    }
}
