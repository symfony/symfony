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

/**
 * A list of constraint violations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
interface ConstraintViolationListInterface extends \Traversable, \Countable, \ArrayAccess
{
    /**
     * Adds a constraint violation to this list.
     *
     * @param ConstraintViolationInterface $violation The violation to add.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function add(ConstraintViolationInterface $violation);

    /**
     * Merges an existing violation list into this list.
     *
     * @param ConstraintViolationListInterface $otherList The list to merge.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function addAll(ConstraintViolationListInterface $otherList);

    /**
     * Returns the violation at a given offset.
     *
     * @param  integer $offset The offset of the violation.
     *
     * @return ConstraintViolationInterface The violation.
     *
     * @throws \OutOfBoundsException If the offset does not exist.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function get($offset);

    /**
     * Returns whether the given offset exists.
     *
     * @param  integer $offset The violation offset.
     *
     * @return Boolean Whether the offset exists.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function has($offset);

    /**
     * Sets a violation at a given offset.
     *
     * @param integer                      $offset    The violation offset.
     * @param ConstraintViolationInterface $violation The violation.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function set($offset, ConstraintViolationInterface $violation);

    /**
     * Removes a violation at a given offset.
     *
     * @param integer $offset The offset to remove.
     *
     * @api
     *
     * @since v2.2.0
     */
    public function remove($offset);
}
