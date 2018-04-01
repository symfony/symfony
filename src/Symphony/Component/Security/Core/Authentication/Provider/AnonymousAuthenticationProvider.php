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

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Core\Exception\BadCredentialsException;
use Symphony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * AnonymousAuthenticationProvider validates AnonymousToken instances.
 *
 * @author Fabien Potencier <fabien@symphony.com>
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
     * @param string $secret The secret shared with the AnonymousToken
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
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
            throw new BadCredentialsException('The Token does not contain the expected key.');
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof AnonymousToken;
    }
}
