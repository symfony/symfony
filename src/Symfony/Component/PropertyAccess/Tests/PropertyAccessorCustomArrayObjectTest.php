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

use Symfony\Component\PropertyAccess\Tests\Fixtures\CustomArrayObject;

class PropertyAccessorCustomArrayObjectTest extends PropertyAccessorCollectionTest
{
    protected function getCollection(array $array)
    {
        return new CustomArrayObject($array);
    }
}
