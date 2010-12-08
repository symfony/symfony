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
 * UsernamePasswordToken implements a username and password token.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class UsernamePasswordToken extends Token
{
    /**
     * Constructor.
     */
    public function __construct($user, $credentials, $userProviderName = null, array $roles = array())
    {
        parent::__construct($roles);

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->userProviderName = $userProviderName;

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
