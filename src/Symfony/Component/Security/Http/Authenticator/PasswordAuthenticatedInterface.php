<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

/**
 * This interface should be implemented when the authenticator
 * uses a password to authenticate.
 *
 * The EncoderFactory will be used to automatically validate
 * the password.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
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
