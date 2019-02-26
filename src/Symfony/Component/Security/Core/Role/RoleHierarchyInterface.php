<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Role;

/**
 * RoleHierarchyInterface is the interface for a role hierarchy.
 *
 * The getReachableRoles(Role[] $roles) method that returns an array of all reachable Role objects is deprecated
 * since Symfony 4.3.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @method string[] getReachableRoleNames(string[] $roles) The associated roles - not implementing it is deprecated since Symfony 4.3
 */
interface RoleHierarchyInterface
{
}
