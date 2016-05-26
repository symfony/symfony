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
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
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
    private $passwordAttribute;

    /**
     * @param LdapInterface $ldap
     * @param string        $baseDn
     * @param string        $searchDn
     * @param string        $searchPassword
     * @param array         $defaultRoles
     * @param string        $uidKey
     * @param string        $filter
     */
    public function __construct(LdapInterface $ldap, $baseDn, $searchDn = null, $searchPassword = null, array $defaultRoles = array(), $uidKey = 'sAMAccountName', $filter = '({uid_key}={username})', $passwordAttribute = null)
    {
        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->defaultRoles = $defaultRoles;
        $this->defaultSearch = str_replace('{uid_key}', $uidKey, $filter);
        $this->passwordAttribute = $passwordAttribute;
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

    /**
     * Loads a user from an LDAP entry.
     *
     * @param string $username
     * @param Entry  $entry
     *
     * @return User
     */
    private function loadUser($username, Entry $entry)
    {
        $password = $this->getPassword($entry);

        return new User($username, $password, $this->defaultRoles);
    }

    /**
     * Fetches the password from an LDAP entry.
     *
     * @param null|Entry $entry
     */
    private function getPassword(Entry $entry)
    {
        if (null === $this->passwordAttribute) {
            return;
        }

        if (!$entry->hasAttribute($this->passwordAttribute)) {
            throw new InvalidArgumentException(sprintf('Missing attribute "%s" for user "%s".', $this->passwordAttribute, $entry->getDn()));
        }

        $values = $entry->getAttribute($this->passwordAttribute);

        if (1 !== count($values)) {
            throw new InvalidArgumentException(sprintf('Attribute "%s" has multiple values.', $this->passwordAttribute));
        }

        return $values[0];
    }
}
