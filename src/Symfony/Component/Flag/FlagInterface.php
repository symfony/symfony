<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag;

/**
 * FlagInterface represents a flag.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
interface FlagInterface extends \IteratorAggregate
{
    /**
     * Constructor.
     *
     * @param bool   $from     Class from where the searching flags is made
     * @param string $prefix   Prefix flags
     * @param int    $bitfield Bitfield value
     */
    public function __construct($from = false, $prefix = '', $bitfield = 0);

    /**
     * Returns a string representation of flag.
     *
     * @return string
     */
    public function __toString();

    /**
     * Sets bitfield value.
     *
     * @param int $bitfield Bitfield value
     *
     * @return $this
     */
    public function set($bitfield);

    /**
     * Gets bitfield value.
     *
     * @return int
     */
    public function get();

    /**
     * Adds a flag.
     *
     * @param int $flag Bitfield to add
     *
     * @return $this
     */
    public function add($flag);

    /**
     * Removes a flag.
     *
     * @param int $flag Bitfield to remove
     *
     * @return $this
     */
    public function remove($flag);

    /**
     * Checks if flag exists.
     *
     * @param int $flag Bitfield to check
     *
     * @return bool
     */
    public function has($flag);

    /**
     * Gets flags iterator.
     *
     * @param bool $flagged Filter to iterate only on flagged flags; define to false to iterate on all flags
     *
     * @return \ArrayIterator
     */
    public function getIterator($flagged = true);
}
