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

use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', PreAuthenticatedAuthenticationProvider::class);

/**
 * Processes a pre-authenticated authentication request.
 *
 * This authentication provider will not perform any checks on authentication
 * requests, as they should already be pre-authenticated. However, the
 * UserProviderInterface implementation may still throw a
 * UserNotFoundException, for example.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class PreAuthenticatedAuthenticationProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $userChecker;
    private $providerKey;

    public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, string $providerKey)
    {
        $this->userProvider = $userProvider;
        $this->userChecker = $userChecker;
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

        if (!$user = $token->getUser()) {
            throw new BadCredentialsException('No pre-authenticated principal found in request.');
        }

        $userIdentifier = method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername();
        // @deprecated since 5.3, change to $this->userProvider->loadUserByIdentifier() in 6.0
        if (method_exists($this->userProvider, 'loadUserByIdentifier')) {
            $user = $this->userProvider->loadUserByIdentifier($userIdentifier);
        } else {
            trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($this->userProvider));

            $user = $this->userProvider->loadUserByUsername($userIdentifier);
        }

        $this->userChecker->checkPostAuth($user);

        $authenticatedToken = new PreAuthenticatedToken($user, $token->getCredentials(), $this->providerKey, $user->getRoles());
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof PreAuthenticatedToken && $this->providerKey === $token->getFirewallName();
    }
}
