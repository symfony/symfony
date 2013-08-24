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

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * @since v2.0.0
 */
class RememberMeAuthenticationProvider implements AuthenticationProviderInterface
{
    private $userChecker;
    private $key;
    private $providerKey;

    /**
     * @since v2.0.0
     */
    public function __construct(UserCheckerInterface $userChecker, $key, $providerKey)
    {
        $this->userChecker = $userChecker;
        $this->key = $key;
        $this->providerKey = $providerKey;
    }

    /**
     * @since v2.0.0
     */
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

    /**
     * @since v2.0.0
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof RememberMeToken && $token->getProviderKey() === $this->providerKey;
    }
}
