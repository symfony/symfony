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
 * For users that can be authenticated using a password/salt couple.
 *
 * Once all password hashes have been upgraded to a modern algorithm via password migrations,
 * implement {@see PasswordAuthenticatedUserInterface} instead.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
interface LegacyPasswordAuthenticatedUserInterface extends PasswordAuthenticatedUserInterface
{
    /**
     * Returns the salt that was originally used to hash the password.
     */
    public function getSalt(): ?string;
}
