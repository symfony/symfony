<?php

namespace Symfony\Component\Security\Http\Authentication\Authenticator;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This interface should be implemented by authenticators that
 * require custom (not password related) authentication.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface CustomAuthenticatedInterface
{
    /**
     * Returns true if the credentials are valid.
     *
     * If false is returned, authentication will fail. You may also throw
     * an AuthenticationException if you wish to cause authentication to fail.
     *
     * @param mixed $credentials the value returned from getCredentials()
     *
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user): bool;
}
