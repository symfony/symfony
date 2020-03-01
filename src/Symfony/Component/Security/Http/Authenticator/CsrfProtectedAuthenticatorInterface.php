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
 * This interface can be implemented to automatically add CSF
 * protection to the authenticator.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface CsrfProtectedAuthenticatorInterface
{
    /**
     * An arbitrary string used to generate the value of the CSRF token.
     * Using a different string for each authenticator improves its security.
     */
    public function getCsrfTokenId(): string;

    /**
     * Returns the CSRF token contained in credentials if any.
     *
     * @param mixed $credentials the credentials returned by getCredentials()
     */
    public function getCsrfToken($credentials): ?string;
}
