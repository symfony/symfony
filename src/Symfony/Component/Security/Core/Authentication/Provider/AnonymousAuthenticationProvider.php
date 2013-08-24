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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * AnonymousAuthenticationProvider validates AnonymousToken instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class AnonymousAuthenticationProvider implements AuthenticationProviderInterface
{
    private $key;

    /**
     * Constructor.
     *
     * @param string $key The key shared with the authentication token
     *
     * @since v2.0.0
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return null;
        }

        if ($this->key !== $token->getKey()) {
            throw new BadCredentialsException('The Token does not contain the expected key.');
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function supports(TokenInterface $token)
    {
        return $token instanceof AnonymousToken;
    }
}
