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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @experimental
 */
interface UserWithRoleInterface extends UserInterface
{
    /**
     * Returns the roles granted to the user.
     *
     * @return string[]
     */
    public function getRoles(): array;
}
