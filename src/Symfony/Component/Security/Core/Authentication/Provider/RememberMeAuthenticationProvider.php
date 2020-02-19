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

use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
    public function __construct(UserCheckerInterface $userChecker, $secret, $providerKey)
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

        if (!$token->getUser() instanceof UserInterface) {
            throw new LogicException(sprintf('Method "%s::getUser()" must return a "%s" instance, "%s" returned.', \get_class($token), UserInterface::class, \is_object($user) ? \get_class($user) : \gettype($user)));
        }

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
