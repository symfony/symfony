<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

/**
 * A sequence of property names or array indices.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @method bool isWildcard(int $index) Returns whether the element at the given index is wildcard. Not implementing it is deprecated since Symfony 7.1
 *
 * @extends \Traversable<int, string>
 */
interface PropertyPathInterface extends \Traversable, \Stringable
{
    /**
     * Returns the string representation of the property path.
     */
    public function __toString(): string;

    /**
     * Returns the length of the property path, i.e. the number of elements.
     */
    public function getLength(): int;

    /**
     * Returns the parent property path.
     *
     * The parent property path is the one that contains the same items as
     * this one except for the last one.
     *
     * If this property path only contains one item, null is returned.
     */
    public function getParent(): ?self;

    /**
     * Returns the elements of the property path as array.
     *
     * @return list<string>
     */
    public function getElements(): array;

    /**
     * Returns the element at the given index in the property path.
     *
     * @param int $index The index key
     *
     * @throws Exception\OutOfBoundsException If the offset is invalid
     */
    public function getElement(int $index): string;

    /**
     * Returns whether the element at the given index is a property.
     *
     * @param int $index The index in the property path
     *
     * @throws Exception\OutOfBoundsException If the offset is invalid
     */
    public function isProperty(int $index): bool;

    /**
     * Returns whether the element at the given index is an array index.
     *
     * @param int $index The index in the property path
     *
     * @throws Exception\OutOfBoundsException If the offset is invalid
     */
    public function isIndex(int $index): bool;

    /**
     * Returns whether the element at the given index is null safe.
     */
    public function isNullSafe(int $index): bool;
}
