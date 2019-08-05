<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard;

/**
 * An optional interface for "guard" authenticators that deal with user passwords.
 */
interface PasswordAuthenticatedInterface
{
    /**
     * Returns the clear-text password contained in credentials if any.
     *
     * @param mixed The user credentials
     */
    public function getPassword($credentials): ?string;
}
