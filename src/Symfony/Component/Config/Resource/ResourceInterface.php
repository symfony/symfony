<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * ResourceInterface is the interface that must be implemented by all Resource classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ResourceInterface
{
    /**
     * Returns a string representation of the Resource.
     *
     * This method is necessary to allow for resource de-duplication, for example by means
     * of array_unique(). The string returned need not have a particular meaning, but has
     * to be identical for different ResourceInterface instances referring to the same
     * resource; and it should be unlikely to collide with that of other, unrelated
     * resource instances.
     *
     * @return string A string representation unique to the underlying Resource
     */
    public function __toString();

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param int $timestamp The last time the resource was loaded
     *
     * @return bool True if the resource has not been updated, false otherwise
     *
     * @deprecated since 2.8, to be removed in 3.0. If your resource can check itself for
     *             freshness implement the SelfCheckingResourceInterface instead.
     */
    public function isFresh($timestamp);

    /**
     * Returns the tied resource.
     *
     * @return mixed The resource
     *
     * @deprecated since 2.8, to be removed in 3.0. As there are many different kinds of resource,
     *             a single getResource() method does not make sense at the interface level. You
     *             can still call getResource() on implementing classes, probably after performing
     *             a type check. If you know the concrete type of Resource at hand, the return value
     *             of this method may make sense to you.
     */
    public function getResource();
}
