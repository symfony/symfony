<?php

namespace Symfony\Component\Security\Authentication\Token;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PreAuthenticatedToken implements a pre-authenticated token.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PreAuthenticatedToken extends Token
{
    /**
     * Constructor.
     */
    public function __construct($user, $credentials, array $roles = null)
    {
        parent::__construct(null === $roles ? array() : $roles);
        if (null !== $roles) {
            $this->setAuthenticated(true);
        }

        $this->user = $user;
        $this->credentials = $credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->credentials = null;
    }
}
