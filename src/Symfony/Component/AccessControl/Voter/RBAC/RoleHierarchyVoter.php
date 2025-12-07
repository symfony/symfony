<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl\Voter\RBAC;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @experimental
 */
readonly class RoleHierarchyVoter extends RoleVoter
{
    public function __construct(
        private RoleHierarchyInterface $roleHierarchy,
        string $prefix = 'ROLE_',
    ){
        parent::__construct($prefix);
    }

    protected function extractRoles(?TokenInterface $requester): array
    {
        $roles = parent::extractRoles($requester);

        return $this->roleHierarchy->getReachableRoleNames($roles);
    }
}
