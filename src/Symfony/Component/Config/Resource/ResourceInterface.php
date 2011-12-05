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
interface ResourceInterface extends \Serializable
{
    /**
     * Returns a string representation of the Resource.
     *
     * @return string A string representation of the Resource
     */
    function __toString();

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param integer $timestamp The last time the resource was loaded
     *
     * @return Boolean true if the resource has not been updated, false otherwise
     */
    function isFresh($timestamp);

    /**
     * Returns resource mtime.
     *
     * @return integer
     */
    function getModificationTime();

    /**
     * Returns true if the resource exists in the filesystem.
     *
     * @return Boolean
     */
    function exists();

    /**
     * Returns the resource tied to this Resource.
     *
     * @return mixed The resource
     */
    function getResource();

    /**
     * Returns unique resource ID.
     *
     * @return string
     */
    function getId();
}
