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

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;

/**
 * LdapUserProvider is a simple user provider on top of ldap.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class LdapUserProvider implements UserProviderInterface
{
    private $ldap;
    private $baseDn;
    private $searchDn;
    private $searchPassword;
    private $defaultRoles;
    private $defaultSearch;

    /**
     * @param LdapInterface $ldap
     * @param string        $baseDn
     * @param string        $searchDn
     * @param string        $searchPassword
     * @param array         $defaultRoles
     * @param string        $uidKey
     * @param string        $filter
     */
    public function __construct(LdapInterface $ldap, $baseDn, $searchDn = null, $searchPassword = null, array $defaultRoles = array(), $uidKey = 'sAMAccountName', $filter = '({uid_key}={username})')
    {
        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->defaultRoles = $defaultRoles;
        $this->defaultSearch = str_replace('{uid_key}', $uidKey, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        try {
            $this->ldap->bind($this->searchDn, $this->searchPassword);
            $username = $this->ldap->escape($username, '', LdapInterface::ESCAPE_FILTER);
            $query = str_replace('{username}', $username, $this->defaultSearch);
            $search = $this->ldap->query($this->baseDn, $query);
        } catch (ConnectionException $e) {
            throw new UsernameNotFoundException(sprintf('User "%s" not found.', $username), 0, $e);
        }

        $entries = $search->execute();
        $count = count($entries);

        if (!$count) {
            throw new UsernameNotFoundException(sprintf('User "%s" not found.', $username));
        }

        if ($count > 1) {
            throw new UsernameNotFoundException('More than one user found');
        }

        return $this->loadUser($username, $entries[0]);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return new User($user->getUsername(), null, $user->getRoles());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }

    private function loadUser($username, Entry $entry)
    {
        return new User($username, $entry->getAttribute('userpassword'), $this->defaultRoles);
    }
}
