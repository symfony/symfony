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

use Symfony\Component\Security\Core\Authentication\Token\AnonymousRequestToken;
use Symfony\Component\Security\Core\Authentication\Token\AuthenticatedAnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * AnonymousAuthenticationProvider validates AnonymousToken instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnonymousAuthenticationProvider implements AuthenticationProviderInterface
{
    /**
     * Used to determine if the token is created by the application
     * instead of a malicious client.
     *
     * @var string
     */
    private $secret;

    /**
     * Constructor.
     *
     * @param string $secret The secret shared with the AnonymousToken
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
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
            throw new BadCredentialsException('The Token does not contain the expected key.');
        }

        return new AuthenticatedAnonymousToken($token->getSecret(), $token->getUsername());
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        if ($token instanceof AnonymousRequestToken) {
            return true;
        }

        if (!$token instanceof AnonymousToken || $token instanceof AuthenticatedAnonymousToken) {
            return false;
        }

        @trigger_error('Support for AnonymousToken in the AnonymousAuthenticationProvider class is deprecated in 3.1 and will be removed in 4.0. Pass an AnonymousRequestToken object instead.', E_USER_DEPRECATED);
        return true;
    }
}
