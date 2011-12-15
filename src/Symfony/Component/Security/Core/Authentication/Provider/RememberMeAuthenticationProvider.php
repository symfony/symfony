<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class RememberMeAuthenticationProvider implements AuthenticationProviderInterface
{
    private $userChecker;
    private $key;
    private $providerKey;

    public function __construct(UserCheckerInterface $userChecker, $key, $providerKey)
    {
        $this->userChecker = $userChecker;
        $this->key = $key;
        $this->providerKey = $providerKey;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return;
        }

        if ($this->key !== $token->getKey()) {
            throw new BadCredentialsException('The presented key does not match.');
        }

        $user = $token->getUser();
        $this->userChecker->checkPostAuth($user);

        $authenticatedToken = new RememberMeToken($user, $this->providerKey, $this->key);
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof RememberMeToken && $token->getProviderKey() === $this->providerKey;
    }
}
