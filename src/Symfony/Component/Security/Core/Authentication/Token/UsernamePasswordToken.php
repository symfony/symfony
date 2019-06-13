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

use Symfony\Component\Security\Core\Role\RoleInterface;

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
     * @param string|object            $user        The username (like a nickname, email address, etc.), or a UserInterface instance or an object implementing a __toString method
     * @param mixed                    $credentials This usually is the password of the user
     * @param string                   $providerKey The provider key
     * @param (RoleInterface|string)[] $roles       An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($user, $credentials, $providerKey, array $roles = [])
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->setUser($user);
        $this->credentials = $credentials;
        $this->providerKey = $providerKey;

        parent::setAuthenticated(\count($roles) > 0);
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
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * Returns the provider key.
     *
     * @return string The provider key
     */
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

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        $serialized = [$this->credentials, $this->providerKey, parent::serialize(true)];

        return $this->doSerialize($serialized, \func_num_args() ? func_get_arg(0) : null);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->credentials, $this->providerKey, $parentStr) = \is_array($serialized) ? $serialized : unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
