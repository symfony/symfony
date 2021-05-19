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

trigger_deprecation('symfony/security-guard', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', PasswordAuthenticatedInterface::class);

/**
 * An optional interface for "guard" authenticators that deal with user passwords.
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
interface PasswordAuthenticatedInterface
{
    /**
     * Returns the clear-text password contained in credentials if any.
     *
     * @param mixed $credentials The user credentials
     */
    public function getPassword($credentials): ?string;
}
