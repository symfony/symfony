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
 * Role is a simple implementation representing a role identified by a string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.3, to be removed in 5.0. Use strings as roles instead.
 */
class Role
{
    private $role;

    public function __construct(string $role)
    {
        if (\func_num_args() < 2 || func_get_arg(1)) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3 and will be removed in 5.0. Use strings as roles instead.', __CLASS__), \E_USER_DEPRECATED);
        }

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

    public function __toString(): string
    {
        return $this->role;
    }
}
