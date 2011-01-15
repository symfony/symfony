<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Exception\BadCredentialsException;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;

/**
 * AnonymousAuthenticationProvider validates AnonymousToken instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AnonymousAuthenticationProvider implements AuthenticationProviderInterface
{
    protected $key;

    /**
     * Constructor.
     *
     * @param string $key The key shared with the authentication token
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return null;
        }

        if ($this->key != $token->getKey()) {
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
