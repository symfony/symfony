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
 * @method array<string, string> getParentRoleNames(string[] $roles)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RoleHierarchyInterface
{
    /**
     * @param string[] $roles
     *
     * @return array<string, string>
     */
    public function getReachableRoleNames(array $roles): array;
}
