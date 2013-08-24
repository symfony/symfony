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
 *
 * @since v2.0.0
 */
class RoleHierarchyVoter extends RoleVoter
{
    private $roleHierarchy;

    /**
     * @since v2.0.0
     */
    public function __construct(RoleHierarchyInterface $roleHierarchy, $prefix = 'ROLE_')
    {
        $this->roleHierarchy = $roleHierarchy;

        parent::__construct($prefix);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    protected function extractRoles(TokenInterface $token)
    {
        return $this->roleHierarchy->getReachableRoles($token->getRoles());
    }
}
