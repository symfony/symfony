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

use Symphony\Component\Security\Core\User\UserCheckerInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Exception\BadCredentialsException;

class RememberMeAuthenticationProvider implements AuthenticationProviderInterface
{
    private $userChecker;
    private $secret;
    private $providerKey;

    /**
     * @param UserCheckerInterface $userChecker An UserCheckerInterface interface
     * @param string               $secret      A secret
     * @param string               $providerKey A provider secret
     */
    public function __construct(UserCheckerInterface $userChecker, string $secret, string $providerKey)
    {
        $this->userChecker = $userChecker;
        $this->secret = $secret;
        $this->providerKey = $providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            throw new AuthenticationException('The token is not supported by this authentication provider.');
        }

        if ($this->secret !== $token->getSecret()) {
            throw new BadCredentialsException('The presented secret does not match.');
        }

        $user = $token->getUser();
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);

        $authenticatedToken = new RememberMeToken($user, $this->providerKey, $this->secret);
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof RememberMeToken && $token->getProviderKey() === $this->providerKey;
    }
}
