<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Ldap\LdapInterface;

/**
 * LdapUserProvider is a simple user provider on top of ldap.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class LdapUserProvider implements LdapUserProviderInterface
{
    private $ldap;

    /**
     * @param LdapInterface $ldap
     */
    public function __construct(LdapInterface $ldap)
    {
        $this->ldap = $ldap;
    }

    /**
     * {@inheritDoc}
     *
     * Due to Ldap limitation, this method should never be called.
     * Call loadUserByUsernameAndPassword instead.
     */
    public function loadUserByUsername($username)
    {
        throw new \BadMethodCallException(sprintf('You should not call the method "%s"', __METHOD__));
    }

    /**
     * {@inheritDoc}
     */
    public function loadUserByUsernameAndPassword($username, $password)
    {
        $this->ldap->setUsername($username);
        $this->ldap->setPassword($password);

        try {
            $this->ldap->bind();
        } catch (ConnectionException $e) {
            throw new UsernameNotFoundException(sprintf('The presented password is invalid. "%s"', $e->getMessage()));
        }

        return new User($username, null, $this->loadRoles());
    }

    /**
     * {@inheritDoc}
     */
    public function loadRoles()
    {
        return array('ROLE_USER');
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return new User($user->getUsername(), null, $user->getRoles());
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }

}
