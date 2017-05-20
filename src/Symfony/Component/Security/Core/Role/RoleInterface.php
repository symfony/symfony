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
 * RoleInterface represents a role granted to a user.
 *
 * A role must either have a string representation or it needs to be explicitly
 * supported by at least one AccessDecisionManager.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The RoleInterface is deprecated since version 3.3 and will be removed in 4.0. Extend the Symfony\Component\Security\Core\Role\Role class instead.
 */
interface RoleInterface
{
    /**
     * Returns the role.
     *
     * This method returns a string representation whenever possible.
     *
     * When the role cannot be represented with sufficient precision by a
     * string, it should return null.
     *
     * @return string|null A string representation of the role, or null
     */
    public function getRole();
}
