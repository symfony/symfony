<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * RoleHierarchyVoter uses a RoleHierarchy to determine the roles granted to
 * the user before voting.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchyVoter extends RoleVoter
{
    private RoleHierarchyInterface $roleHierarchy;

    public function __construct(RoleHierarchyInterface $roleHierarchy, string $prefix = 'ROLE_')
    {
        $this->roleHierarchy = $roleHierarchy;

        parent::__construct($prefix);
    }

    /**
     * @return array
     */
    protected function extractRoles(TokenInterface $token)
    {
        return $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
    }
}
