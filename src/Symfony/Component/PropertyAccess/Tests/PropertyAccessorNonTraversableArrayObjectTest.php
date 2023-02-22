<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use Symfony\Component\PropertyAccess\Tests\Fixtures\NonTraversableArrayObject;

class PropertyAccessorNonTraversableArrayObjectTest extends PropertyAccessorArrayAccessTestCase
{
    protected static function getContainer(array $array)
    {
        return new NonTraversableArrayObject($array);
    }
}
