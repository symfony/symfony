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
 */
abstract class Token implements TokenInterface
{
    protected $roles;
    protected $authenticated;
    protected $user;
    protected $credentials;
    protected $immutable;

    /**
     * Constructor.
     *
     * @param Role[] $roles An array of roles
     */
    public function __construct(array $roles = array())
    {
        $this->roles = array();
        foreach ($roles as $role) {
            if (is_string($role)) {
                $role = new Role((string) $role);
            }
            $this->addRole($role);
        }
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
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
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
    public function setImmutable($value)
    {
        $this->immutable = (Boolean) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        // FIXME: don't serialize the user object, just the username (see ContextListener)
        //return serialize(array((string) $this, $this->credentials, $this->authenticated, $this->roles, $this->immutable));
        return serialize(array($this->user, $this->credentials, $this->authenticated, $this->roles, $this->immutable));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->user, $this->credentials, $this->authenticated, $this->roles, $this->immutable) = unserialize($serialized);
    }
}
