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
 * Allows migrating session payloads from v4.
 *
 * @internal
 */
class Role
{
    private $role;

    private function __construct()
    {
    }

    public function __toString(): string
    {
        return $this->role;
    }
}
