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

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @throws \InvalidArgumentException
     */
    public function __construct(UserCheckerInterface $userChecker, string $providerKey, bool $hideUserNotFoundExceptions = true)
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
            throw new AuthenticationException('The token is not supported by this authentication provider.');
        }

        $username = $token->getUsername();
        if ('' === $username || null === $username) {
            $username = AuthenticationProviderInterface::USERNAME_NONE_PROVIDED;
        }

        try {
            $user = $this->retrieveUser($username, $token);
        } catch (UsernameNotFoundException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }
            $e->setUsername($username);

            throw $e;
        }

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException('retrieveUser() must return a UserInterface.');
        }

        try {
            $this->userChecker->checkPreAuth($user);
            $this->checkAuthentication($user, $token);
            $this->userChecker->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials.', 0, $e);
            }

            throw $e;
        }

        if ($token instanceof SwitchUserToken) {
            $authenticatedToken = new SwitchUserToken($user, $token->getCredentials(), $this->providerKey, $user->getRoles(), $token->getOriginalToken());
        } else {
            $authenticatedToken = new UsernamePasswordToken($user, $token->getCredentials(), $this->providerKey, $user->getRoles());
        }

        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof UsernamePasswordToken && $this->providerKey === $token->getFirewallName();
    }

    /**
     * Retrieves the user from an implementation-specific location.
     *
     * @return UserInterface The user
     *
     * @throws AuthenticationException if the credentials could not be validated
     */
    abstract protected function retrieveUser(string $username, UsernamePasswordToken $token);

    /**
     * Does additional checks on the user and token (like validating the
     * credentials).
     *
     * @throws AuthenticationException if the credentials could not be validated
     */
    abstract protected function checkAuthentication(UserInterface $user, UsernamePasswordToken $token);
}
