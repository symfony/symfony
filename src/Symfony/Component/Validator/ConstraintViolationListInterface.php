<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Exception\OutOfBoundsException;

/**
 * A list of constraint violations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @extends \ArrayAccess<int, ConstraintViolationInterface>
 * @extends \Traversable<int, ConstraintViolationInterface>
 */
interface ConstraintViolationListInterface extends \Traversable, \Countable, \ArrayAccess
{
    /**
     * Adds a constraint violation to this list.
     */
    public function add(ConstraintViolationInterface $violation): void;

    /**
     * Merges an existing violation list into this list.
     */
    public function addAll(self $otherList): void;

    /**
     * Returns the violation at a given offset.
     *
     * @param int $offset The offset of the violation
     *
     * @throws OutOfBoundsException if the offset does not exist
     */
    public function get(int $offset): ConstraintViolationInterface;

    /**
     * Returns whether the given offset exists.
     *
     * @param int $offset The violation offset
     */
    public function has(int $offset): bool;

    /**
     * Sets a violation at a given offset.
     *
     * @param int $offset The violation offset
     */
    public function set(int $offset, ConstraintViolationInterface $violation): void;

    /**
     * Removes a violation at a given offset.
     *
     * @param int $offset The offset to remove
     */
    public function remove(int $offset): void;

    /**
     * Converts the violation into a string for debugging purposes.
     */
    public function __toString(): string;
}
