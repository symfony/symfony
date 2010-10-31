<?php

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\User\UserProviderInterface;
use Symfony\Component\Security\User\AccountCheckerInterface;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Authentication\Token\PreAuthenticatedToken;
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
 * Processes a pre-authenticated authentication request.
 *
 * This authentication provider will not perform any checks on authentication
 * requests, as they should already be pre-authenticated. However, the
 * UserProviderInterface implementation may still throw a
 * UsernameNotFoundException, for example.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PreAuthenticatedAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $userProvider;
    protected $accountChecker;

    /**
     * Constructor.
     *
     * @param UserProviderInterface   $userProvider   A UserProviderInterface instance
     * @param AccountCheckerInterface $accountChecker An AccountCheckerInterface instance
     */
    public function __construct(UserProviderInterface $userProvider, AccountCheckerInterface $accountChecker)
    {
        $this->userProvider = $userProvider;
        $this->accountChecker = $accountChecker;
    }

     /**
      * {@inheritdoc}
      */
     public function authenticate(TokenInterface $token)
     {
         if (!$this->supports($token)) {
             return null;
         }

        if (!$user = $token->getUser()) {
            throw new BadCredentialsException('No pre-authenticated principal found in request.');
        }
/*
        if (null === $token->getCredentials()) {
            throw new BadCredentialsException('No pre-authenticated credentials found in request.');
        }
*/
        $user = $this->userProvider->loadUserByUsername($user);

        $this->accountChecker->checkPostAuth($user);

        return new PreAuthenticatedToken($user, $token->getCredentials(), $user->getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof PreAuthenticatedToken;
    }
}
