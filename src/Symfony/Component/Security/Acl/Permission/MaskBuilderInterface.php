<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Permission;

/**
 * This is the interface that must be implemented by mask builders.
 */
interface MaskBuilderInterface
{
    /**
     * Set the mask of this permission.
     *
     * @param int $mask
     *
     * @return $this
     *
     * @throws \InvalidArgumentException if $mask is not an integer
     */
    public function set($mask);

    /**
     * Returns the mask of this permission.
     *
     * @return int
     */
    public function get();

    /**
     * Adds a mask to the permission.
     *
     * @param mixed $mask
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function add($mask);

    /**
     * Removes a mask from the permission.
     *
     * @param mixed $mask
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function remove($mask);

    /**
     * Resets the PermissionBuilder.
     *
     * @return $this
     */
    public function reset();

    /**
     * Returns the mask for the passed code.
     *
     * @param mixed $code
     *
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function resolveMask($code);
}
