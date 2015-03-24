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
     * @return string A string representation of the Resource
     */
    public function __toString();

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param int $timestamp The last time the resource was loaded
     *
     * @return bool True if the resource has not been updated, false otherwise
     */
    public function isFresh($timestamp);

    /**
     * Returns the tied resource.
     *
     * @return mixed The resource
     */
    public function getResource();
}
