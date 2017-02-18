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

use Symfony\Component\Security\Core\Authentication\Token\AuthenticatedRememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\AuthenticatedUserToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeRequestToken;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class RememberMeAuthenticationProvider implements AuthenticationProviderInterface
{
    private $userChecker;
    private $secret;
    private $providerKey;

    /**
     * Constructor.
     *
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
            return;
        }

        if ($this->secret !== $token->getSecret()) {
            throw new BadCredentialsException('The presented secret does not match.');
        }

        $user = $token->getUser();
        $this->userChecker->checkPreAuth($user);

        $authenticatedToken = new AuthenticatedRememberMeToken($user, $this->providerKey, $this->secret);
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        if ($token instanceof RememberMeRequestToken) {
            return $token->getProviderKey() === $this->providerKey;
        }

        if ($token instanceof RememberMeToken) {
            @trigger_error('Support for RememberMeToken in the RememberMeAuthenticationProvider class is deprecated in 3.1 and will be removed in 4.0. Pass a RememberMeRequestToken object instead.', E_USER_DEPRECATED);

            return $token->getProviderKey() === $this->providerKey;
        }

        return false;
    }
}
