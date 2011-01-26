<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\User\AccountInterface;
use Symfony\Component\Security\Core\User\AccountCheckerInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * UserProviderInterface retrieves users for UsernamePasswordToken tokens.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class UserAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $hideUserNotFoundExceptions;
    protected $accountChecker;
    protected $providerKey;

    /**
     * Constructor.
     *
     * @param AccountCheckerInterface $accountChecker             An AccountCheckerInterface interface
     * @param Boolean                 $hideUserNotFoundExceptions Whether to hide user not found exception or not
     */
    public function __construct(AccountCheckerInterface $accountChecker, $providerKey, $hideUserNotFoundExceptions = true)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->accountChecker = $accountChecker;
        $this->providerKey = $providerKey;
        $this->hideUserNotFoundExceptions = $hideUserNotFoundExceptions;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return null;
        }

        $username = null === $token->getUser() ? 'NONE_PROVIDED' : (string) $token;

        try {
            $user = $this->retrieveUser($username, $token);

            if (!$user instanceof AccountInterface) {
                throw new AuthenticationServiceException('retrieveUser() must return an AccountInterface.');
            }

            $this->accountChecker->checkPreAuth($user);
            $this->checkAuthentication($user, $token);
            $this->accountChecker->checkPostAuth($user);

            return new UsernamePasswordToken($user, $token->getCredentials(), $this->providerKey, $user->getRoles());
        } catch (UsernameNotFoundException $notFound) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials', 0, $notFound);
            }

            throw $notFound;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof UsernamePasswordToken && $this->providerKey === $token->getProviderKey();
    }

    /**
     * Retrieves the user from an implementation-specific location.
     *
     * @param string                $username The username to retrieve
     * @param UsernamePasswordToken $token    The Token
     *
     * @return array The user
     *
     * @throws AuthenticationException if the credentials could not be validated
     */
    abstract protected function retrieveUser($username, UsernamePasswordToken $token);

    /**
     * Does additional checks on the user and token (like validating the
     * credentials).
     *
     * @param AccountInterface      $account The retrieved AccountInterface instance
     * @param UsernamePasswordToken $token   The UsernamePasswordToken token to be authenticated
     *
     * @throws AuthenticationException if the credentials could not be validated
     */
    abstract protected function checkAuthentication(AccountInterface $account, UsernamePasswordToken $token);
}
