<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Role;

/**
 * Role is a simple implementation representing a role identified by a string.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class Role
{
    private $role;

    public function __construct(string $role)
    {
        $this->role = $role;
    }

    /**
     * Returns a string representation of the role.
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }
}
