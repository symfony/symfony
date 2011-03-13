<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * UsernamePasswordToken implements a username and password token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UsernamePasswordToken extends AbstractToken
{
    private $credentials;
    private $providerKey;

    /**
     * Constructor.
     *
     * @param string $user The username (like a nickname, email address, etc.)
     * @param string $credentials This usually is the password of the user
     */
    public function __construct($user, $credentials, $providerKey, array $roles = array())
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->providerKey = $providerKey;

        parent::setAuthenticated(count($roles) > 0);
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

    public function getCredentials()
    {
        return $this->credentials;
    }

    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        parent::eraseCredentials();

        $this->credentials = null;
    }

    public function serialize()
    {
        return serialize(array($this->credentials, $this->providerKey, parent::serialize()));
    }

    public function unserialize($str)
    {
        list($this->credentials, $this->providerKey, $parentStr) = unserialize($str);
        parent::unserialize($parentStr);
    }
}
