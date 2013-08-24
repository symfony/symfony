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
 * Role is a simple implementation of a RoleInterface where the role is a
 * string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.1.0
 */
class Role implements RoleInterface
{
    private $role;

    /**
     * Constructor.
     *
     * @param string $role The role name
     *
     * @since v2.0.0
     */
    public function __construct($role)
    {
        $this->role = (string) $role;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getRole()
    {
        return $this->role;
    }
}
