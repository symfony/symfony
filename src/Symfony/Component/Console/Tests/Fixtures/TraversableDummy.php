<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use ArrayIterator;
use IteratorAggregate;

class TraversableDummy implements IteratorAggregate
{
    public function getIterator()
    {
        return new ArrayIterator(['foo', 'bar']);
    }
}
