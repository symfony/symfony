<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

/**
 * For users that can be authenticated using a password.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface PasswordAuthenticatedUserInterface
{
    /**
     * Returns the hashed password used to authenticate the user.
     *
     * Usually on authentication, a plain-text password will be compared to this value.
     */
    public function getPassword(): ?string;
}
