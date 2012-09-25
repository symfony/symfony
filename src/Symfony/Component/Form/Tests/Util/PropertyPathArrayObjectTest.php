<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

class PropertyPathArrayObjectTest extends PropertyPathCollectionTest
{
    protected function getCollection(array $array)
    {
        return new \ArrayObject($array);
    }
}
