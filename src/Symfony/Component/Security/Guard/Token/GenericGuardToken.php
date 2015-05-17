<?php

namespace Symfony\Component\Security\Guard\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A generic token used by the AbstractGuardAuthenticator
 *
 * This is meant to be used as an "authenticated" token, though it
 * could be set to not-authenticated later.
 *
 * You're free to use this (it's simple) or use any other token for
 * your authenticated token
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
class GenericGuardToken extends AbstractToken
{
    private $providerKey;

    /**
     * @param UserInterface            $user        The user!
     * @param string                   $providerKey The provider (firewall) key
     * @param RoleInterface[]|string[] $roles       An array of roles
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(UserInterface $user, $providerKey, array $roles)
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey (i.e. firewall key) must not be empty.');
        }

        $this->setUser($user);
        $this->providerKey = $providerKey;

        // this token is meant to be used after authentication success, so it is always authenticated
        // you could set it as non authenticated later if you need to
        parent::setAuthenticated(true);
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
     * This is meant to be only an authenticated token, where credentials
     * have already been used and are thus cleared.
     *
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        return array();
    }

    /**
     * Returns the provider (firewall) key.
     *
     * @return string
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
        return serialize(array($this->providerKey, parent::serialize()));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->providerKey, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
