<?php

namespace Symfony\Component\Security\Role;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Role is a simple implementation of a RoleInterface where the role is a
 * string.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Role implements RoleInterface
{
    protected $role;

    /**
     * Constructor.
     *
     * @param string $role The role name
     */
    public function __construct($role)
    {
        $this->role = (string) $role;
    }

    /**
     * {@inheritdoc}
     */
    public function getRole()
    {
        return $this->role;
    }
}
