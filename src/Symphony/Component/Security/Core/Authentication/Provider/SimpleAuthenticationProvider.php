<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authentication\Provider;

use Symphony\Component\Security\Core\User\UserChecker;
use Symphony\Component\Security\Core\User\UserCheckerInterface;
use Symphony\Component\Security\Core\User\UserProviderInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimpleAuthenticationProvider implements AuthenticationProviderInterface
{
    private $simpleAuthenticator;
    private $userProvider;
    private $providerKey;
    private $userChecker;

    public function __construct(SimpleAuthenticatorInterface $simpleAuthenticator, UserProviderInterface $userProvider, string $providerKey, UserCheckerInterface $userChecker = null)
    {
        $this->simpleAuthenticator = $simpleAuthenticator;
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->userChecker = $userChecker ?: new UserChecker();
    }

    public function authenticate(TokenInterface $token)
    {
        $authToken = $this->simpleAuthenticator->authenticateToken($token, $this->userProvider, $this->providerKey);

        if (!$authToken instanceof TokenInterface) {
            throw new AuthenticationException('Simple authenticator failed to return an authenticated token.');
        }

        $user = $authToken->getUser();
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);

        return $authToken;
    }

    public function supports(TokenInterface $token)
    {
        return $this->simpleAuthenticator->supportsToken($token, $this->providerKey);
    }
}
