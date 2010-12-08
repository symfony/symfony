<?php

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\User\AccountInterface;
use Symfony\Component\Security\User\AccountCheckerInterface;
use Symfony\Component\Security\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * UserProviderInterface retrieves users for UsernamePasswordToken tokens.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class UserAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $hideUserNotFoundExceptions;
    protected $accountChecker;

    /**
     * Constructor.
     *
     * @param AccountCheckerInterface $accountChecker             An AccountCheckerInterface interface
     * @param Boolean                 $hideUserNotFoundExceptions Whether to hide user not found exception or not
     */
    public function __construct(AccountCheckerInterface $accountChecker, $hideUserNotFoundExceptions = true)
    {
        $this->accountChecker = $accountChecker;
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
            $result = $this->retrieveUser($username, $token);
        } catch (UsernameNotFoundException $notFound) {
            if ($this->hideUserNotFoundExceptions) {
                throw new BadCredentialsException('Bad credentials', 0, $notFound);
            }

            throw $notFound;
        }

        if (!is_array($result) || 2 !== count($result)) {
            throw new AuthenticationServiceException('retrieveUser() did not return an array, or array had invalid format.'); 
        }
        list($user, $userProviderName) = $result;

        $this->accountChecker->checkPreAuth($user);
        $this->checkAuthentication($user, $token);
        $this->accountChecker->checkPostAuth($user);

        return new UsernamePasswordToken($user, $token->getCredentials(), $userProviderName,  $user->getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof UsernamePasswordToken;
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
