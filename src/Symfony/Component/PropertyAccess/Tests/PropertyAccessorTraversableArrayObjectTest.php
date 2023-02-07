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

use Symfony\Component\PropertyAccess\Tests\Fixtures\TraversableArrayObject;

class PropertyAccessorTraversableArrayObjectTest extends PropertyAccessorCollectionTestCase
{
    protected static function getContainer(array $array)
    {
        return new TraversableArrayObject($array);
    }
}
