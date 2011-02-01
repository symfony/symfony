<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * UsernamePasswordToken implements a username and password token.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class UsernamePasswordToken extends Token
{
    /**
     * Constructor.
     *
     * @param string $user The username (like a nickname, email address, etc.)
     * @param string $credentials This usually is the password of the user
     */
    public function __construct($user, $credentials, $providerKey, array $roles = array())
    {
        parent::__construct($roles);

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->providerKey = $providerKey;

        parent::setAuthenticated((Boolean) count($roles));
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($isAuthenticated)
    {
        if ($isAuthenticated) {
            throw new \LogicException('Cannot set this token to trusted after instantiation.');
        }

        parent::setAuthenticated(false);
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
