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
 * doesn't need to check credentials (e.g. when using API tokens)
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface TokenAuthenticatedInterface
{
    /**
     * Extracts the token from the credentials.
     *
     * If you return null, the credentials will not be marked as
     * valid and a BadCredentialsException is thrown.
     *
     * @param mixed $credentials The user credentials
     *
     * @return mixed|null the token - if any - or null otherwise
     */
    public function getToken($credentials);
}
