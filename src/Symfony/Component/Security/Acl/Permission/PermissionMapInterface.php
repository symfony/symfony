<?php

namespace Symfony\Component\Security\Acl\Permission;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This is the interface that must be implemented by permission maps.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PermissionMapInterface
{
    /**
     * Returns an array of bitmasks.
     *
     * The security identity must have been granted access to at least one of
     * these bitmasks.
     *
     * @param string $permission
     * @return array
     */
    function getMasks($permission);

    /**
     * Whether this map contains the given permission
     *
     * @param string $permission
     * @return Boolean
     */
    function contains($permission);
}