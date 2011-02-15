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
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\AccountCheckerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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
    protected $providerKey;

    /**
     * Constructor.
     *
     * @param UserProviderInterface   $userProvider   A UserProviderInterface instance
     * @param AccountCheckerInterface $accountChecker An AccountCheckerInterface instance
     */
    public function __construct(UserProviderInterface $userProvider, AccountCheckerInterface $accountChecker, $providerKey)
    {
        $this->userProvider = $userProvider;
        $this->accountChecker = $accountChecker;
        $this->providerKey = $providerKey;
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

        return new PreAuthenticatedToken($user, $token->getCredentials(), $this->providerKey, $user->getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof PreAuthenticatedToken && $this->providerKey === $token->getProviderKey();
    }
}
