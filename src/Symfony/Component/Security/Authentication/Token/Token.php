<?php

namespace Symfony\Component\Security\Authentication\Token;

use Symfony\Component\Security\Role\RoleInterface;
use Symfony\Component\Security\Role\Role;
use Symfony\Component\Security\User\AccountInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base class for Token instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class Token implements TokenInterface
{
    protected $roles;
    protected $authenticated;
    protected $user;
    protected $userProviderName;
    protected $credentials;
    protected $immutable;

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
        if (!is_object($this->user)) {
            return (string) $this->user;
        } elseif ($this->user instanceof AccountInterface) {
            return $this->user->getUsername();
        } else {
            return 'n/a';
        }
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
        } else if (is_object($user) && !method_exists($user, '__toString')) {
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
     * {@inheritDoc}
     */
    public function getUserProviderName()
    {
        return $this->userProviderName;
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
    public function serialize()
    {
        return serialize(array($this->user, $this->userProviderName, $this->credentials, $this->authenticated, $this->roles, $this->immutable));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->user, $this->userProviderName, $this->credentials, $this->authenticated, $this->roles, $this->immutable) = unserialize($serialized);
    }
}
