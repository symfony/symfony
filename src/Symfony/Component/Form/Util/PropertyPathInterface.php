<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PropertyPathInterface extends \Traversable
{
    /**
     * Returns the string representation of the property path
     *
     * @return string The path as string.
     */
    function __toString();

    /**
     * Returns the positions at which the elements of the path
     * start in the string.
     *
     * @return array The string offsets of the elements.
     */
    function getPositions();

    /**
     * Returns the length of the property path.
     *
     * @return integer The path length.
     */
    function getLength();

    /**
     * Returns the parent property path.
     *
     * The parent property path is the one that contains the same items as
     * this one except for the last one.
     *
     * If this property path only contains one item, null is returned.
     *
     * @return PropertyPath The parent path or null.
     */
    function getParent();

    /**
     * Returns the elements of the property path as array
     *
     * @return array An array of property/index names
     */
    function getElements();

    /**
     * Returns the element at the given index in the property path
     *
     * @param  integer $index The index key
     *
     * @return string A property or index name
     *
     * @throws \OutOfBoundsException If the offset is invalid.
     */
    function getElement($index);

    /**
     * Returns whether the element at the given index is a property
     *
     * @param  integer $index The index in the property path
     *
     * @return Boolean Whether the element at this index is a property
     *
     * @throws \OutOfBoundsException If the offset is invalid.
     */
    function isProperty($index);

    /**
     * Returns whether the element at the given index is an array index
     *
     * @param  integer $index The index in the property path
     *
     * @return Boolean Whether the element at this index is an array index
     *
     * @throws \OutOfBoundsException If the offset is invalid.
     */
    function isIndex($index);
}
