<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

class TraversableDummy implements \IteratorAggregate
{
    public $foo = 'foo';
    public $bar = 'bar';

    public function getIterator()
    {
        return new \ArrayIterator(get_object_vars($this));
    }
}
