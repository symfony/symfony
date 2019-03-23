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
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * RoleHierarchyVoter uses a RoleHierarchy to determine the roles granted to
 * the user before voting.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchyVoter extends RoleVoter
{
    private $roleHierarchy;

    public function __construct(RoleHierarchyInterface $roleHierarchy, string $prefix = 'ROLE_')
    {
        if (!method_exists($roleHierarchy, 'getReachableRoleNames')) {
            @trigger_error(sprintf('Not implementing the getReachableRoleNames() method in %s which implements %s is deprecated since Symfony 4.3.', \get_class($roleHierarchy), RoleHierarchyInterface::class), E_USER_DEPRECATED);
        }

        $this->roleHierarchy = $roleHierarchy;

        parent::__construct($prefix);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractRoles(TokenInterface $token)
    {
        if (method_exists($this->roleHierarchy, 'getReachableRoleNames')) {
            if (method_exists($token, 'getRoleNames')) {
                $roles = $token->getRoleNames();
            } else {
                @trigger_error(sprintf('Not implementing the getRoleNames() method in %s which implements %s is deprecated since Symfony 4.3.', \get_class($token), TokenInterface::class), E_USER_DEPRECATED);

                $roles = array_map(function (Role $role) { return $role->getRole(); }, $token->getRoles(false));
            }

            return $this->roleHierarchy->getReachableRoleNames($roles);
        }

        return $this->roleHierarchy->getReachableRoles($token->getRoles(false));
    }
}
