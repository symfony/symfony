<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Util;

use Symfony\Component\Intl\Exception\BadMethodCallException;

/**
 * Work-around for a bug in PHP's \ResourceBundle implementation.
 *
 * More information can be found on https://bugs.php.net/64356.
 * This class can be removed once that bug is fixed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class ArrayAccessibleResourceBundle implements \ArrayAccess, \IteratorAggregate, \Countable
{
    public function __construct(
        private \ResourceBundle $bundleImpl,
    ) {
    }

    public function get(int|string $offset): mixed
    {
        $value = $this->bundleImpl->get($offset);

        return $value instanceof \ResourceBundle ? new static($value) : $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return null !== $this->bundleImpl->get($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    public function getIterator(): \Traversable
    {
        return $this->bundleImpl;
    }

    public function count(): int
    {
        return $this->bundleImpl->count();
    }

    public function getErrorCode(): int
    {
        return $this->bundleImpl->getErrorCode();
    }

    public function getErrorMessage(): string
    {
        return $this->bundleImpl->getErrorMessage();
    }
}
