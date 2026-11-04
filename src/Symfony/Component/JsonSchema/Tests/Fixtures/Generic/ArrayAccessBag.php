<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures\Generic;

/**
 * @template T
 * @implements \ArrayAccess<int, T>
 */
class ArrayAccessBag implements \ArrayAccess
{
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }
    public function offsetGet(mixed $offset): mixed
    {
        return null;
    }
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }
    public function offsetUnset(mixed $offset): void
    {
    }
}
