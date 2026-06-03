<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @template TKey of array-key
 * @template TValue
 */
final class DummyCollection implements \IteratorAggregate
{
    public function getIterator(): \Traversable
    {
        return [];
    }
}
