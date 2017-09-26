<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * Allows the roles of a token to be updated when security is persisted across a session.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface RefreshableRolesTokenInterface
{
    /**
     * @param array $roles An array of roles
     */
    public function updateRoles(array $roles);
}
