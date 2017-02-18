<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\Token\AuthenticatedUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordRequestToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\SwitchUserRole;

/**
 * UserProviderInterface retrieves users for UsernamePasswordToken tokens.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class UserAuthenticationProvider implements AuthenticationProviderInterface
{
    private $hideUserNotFoundExceptions;
    private $userChecker;
    private $providerKey;

    /**
     * Constructor.
     *
     * @param UserCheckerInterface $userChecker                An UserCheckerInterface interface
     * @param string               $providerKey                A provider key
     * @param bool                 $hideUserNotFoundExceptions Whether to hide user not found exception or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserCheckerInterface $userChecker, $providerKey, $hideUserNotFoundExceptions = true)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->userChecker = $userChecker;
        $this->providerKey = $providerKey;
        $this->hideUserNotFoundExceptions = $hideUserNotFoundExceptions;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return;
        }

        $username = $token->getUsername();
        if ('' === $username || null === $username) {
            $username = AuthenticationProviderInterface::USERNAME_NONE_PROVIDED;
        }

        // For backwards compatibility with <3.1. Deprecation notice is already triggered in supports().
        if (!$token instanceof UsernamePasswordRequestToken) {
            $newToken = new UsernamePasswordRequestToken($token->getUsername(), $token->getCredentials(), $token->getProviderKey(), $token->getRoles());
            $newToken->setAttributes($token->getAttributes());
            $token = $newToken;
        }

        try {
            $user = $this->getUserFromToken($username, $token);
        } catch (UsernameNotFoundException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }
            $e->setUsername($username);

            throw $e;
        }

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException('getUserFromToken() must return a UserInterface.');
        }

        try {
            $this->userChecker->checkPreAuth($user);
            $this->authenticateUser($user, $token);
            $this->userChecker->checkPostAuth($user);
        } catch (BadCredentialsException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }

            throw $e;
        }

        $authenticatedToken = new AuthenticatedUserToken($user, $user->getRoles(), $this->providerKey);
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        if ($token instanceof UsernamePasswordRequestToken) {
            return $this->providerKey === $token->getProviderKey();
        }

        if ($token instanceof UsernamePasswordToken) {
            @trigger_error('Support for UsernamePasswordToken in the '.__CLASS__.' class is deprecated in 3.1 and will be removed in 4.0. Pass a UsernamePasswordRequestToken object instead.', E_USER_DEPRECATED);

            return $this->providerKey === $token->getProviderKey();
        }

        return false;
    }

    /**
     * Retrieves roles from user and appends SwitchUserRole if original token contained one.
     *
     * @param UserInterface  $user  The user
     * @param TokenInterface $token The token
     *
     * @return array The user roles
     */
    private function getRoles(UserInterface $user, TokenInterface $token)
    {
        $roles = $user->getRoles();

        foreach ($token->getRoles() as $role) {
            if ($role instanceof SwitchUserRole) {
                $roles[] = $role;

                break;
            }
        }

        return $roles;
    }

    /**
     * @deprecated Since Symfony 3.1, to be removed in 4.0. Implement getUserFromToken() instead.
     */
    protected function retrieveUser($username, UsernamePasswordToken $token)
    {
        throw new \LogicException('Method UserAuthenticationProvider::getUserFromToken() needs to be implemented.');
    }

    /**
     * Retrieves the user by the provided username and information from the token.
     *
     * @param string                       $username
     * @param UsernamePasswordRequestToken $token
     *
     * @return UserInterface
     *
     * @throws AuthenticationException If no user could be found for the provided information
     */
    protected function getUserFromToken($username, UsernamePasswordRequestToken $token)
    {
        @trigger_error('Method '.__CLASS__.'::retrieveUser() is deprecated since version 3.1 and will be removed in 4.0. Override getUserFromToken() instead.', E_USER_DEPRECATED);

        return $this->retrieveUser($username, $token);
    }

    /**
     * Checks whether the user is correctly authenticated.
     *
     * This is done by e.g. comparing the user password to the provided credentials.
     *
     * @param UserInterface                $user  The user retrieved by the requested username
     * @param UsernamePasswordRequestToken $token
     *
     * @throws AuthenticationException If the user is not correctly authenticated.
     */
    protected function authenticateUser(UserInterface $user, UsernamePasswordRequestToken $token)
    {
        @trigger_error('Method '.__CLASS__.'::checkAuthentication() is deprecated since version 3.1 and will be removed in 4.0. Override authenticateUser() instead.', E_USER_DEPRECATED);

        return $this->checkAuthentication($user, $token);
    }

    /**
     * @deprecated Since Symfony 3.1, to be removed in 4.0. Implement authenticateUser() instead.
     */
    protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token)
    {
        throw new \LogicException('Method UserAuthenticationProvider::authenticateUser() needs to be implemented.');
    }
}
