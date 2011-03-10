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
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\AccountInterface;

/**
 * Base class for Token instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class Token implements TokenInterface
{
    protected $roles;
    protected $authenticated;
    protected $user;
    protected $credentials;
    protected $immutable;
    protected $providerKey;
    protected $attributes;

    /**
     * Constructor.
     *
     * @param Role[] $roles An array of roles
     */
    public function __construct(array $roles = array())
    {
        $this->setRoles($roles);
        $this->authenticated = false;
        $this->immutable = false;
        $this->attributes = array();
    }

    /**
     * Adds a Role to the token.
     *
     * @param RoleInterface $role A RoleInterface instance
     */
    public function addRole(RoleInterface $role)
    {
        if ($this->immutable) {
            throw new \LogicException('This token is considered immutable.');
        }

        $this->roles[] = $role;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * {@inheritDoc}
     */
    public function setRoles(array $roles)
    {
        $this->roles = array();

        foreach ($roles as $role) {
            if (is_string($role)) {
                $role = new Role($role);
            }

            $this->addRole($role);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        if ($this->user instanceof AccountInterface) {
            return $this->user->getUsername();
        }

        return (string) $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($authenticated)
    {
        if ($this->immutable) {
            throw new \LogicException('This token is considered immutable.');
        }

        $this->authenticated = (Boolean) $authenticated;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser($user)
    {
        if ($this->immutable) {
            throw new \LogicException('This token is considered immutable.');
        }

        if (!is_string($user) && !is_object($user)) {
            throw new \InvalidArgumentException('$user must be an object, or a primitive string.');
        } else if (is_object($user) && !$user instanceof AccountInterface && !method_exists($user, '__toString')) {
            throw new \InvalidArgumentException('If $user is an object, it must implement __toString().');
        }

        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        if ($this->immutable) {
            throw new \LogicException('This token is considered immutable.');
        }

        if ($this->getCredentials() instanceof AccountInterface) {
            $this->getCredentials()->eraseCredentials();
        }

        if ($this->getUser() instanceof AccountInterface) {
            $this->getUser()->eraseCredentials();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isImmutable()
    {
        return $this->immutable;
    }

    /**
     * {@inheritdoc}
     */
    public function setImmutable()
    {
        $this->immutable = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderKey()
    {
        return $this->providerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->user, $this->credentials, $this->authenticated, $this->roles, $this->immutable, $this->providerKey, $this->attributes));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->user, $this->credentials, $this->authenticated, $this->roles, $this->immutable, $this->providerKey, $this->attributes) = unserialize($serialized);
    }

    /**
     * Returns the token attributes.
     *
     * @return array The token attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets the token attributes.
     *
     * @param array $attributes The token attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns true if the attribute exists.
     *
     * @param  string  $name  The attribute name
     *
     * @return Boolean true if the attribute exists, false otherwise
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Returns a attribute value.
     *
     * @param string $name The attribute name
     *
     * @return mixed The attribute value
     *
     * @throws \InvalidArgumentException When attribute doesn't exist for this token
     */
    public function getAttribute($name)
    {
        if (!array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException(sprintf('This token has no "%s" attribute.', $name));
        }

        return $this->attributes[$name];
    }

    /**
     * Sets a attribute.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }
}
