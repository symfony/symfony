<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Security;

use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\ExceptionInterface;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * LdapUserProvider is a simple user provider on top of LDAP.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class LdapUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private $ldap;
    private $baseDn;
    private $searchDn;
    private $searchPassword;
    private $defaultRoles;
    private $uidKey;
    private $defaultSearch;
    private $passwordAttribute;
    private $extraFields;

    public function __construct(LdapInterface $ldap, string $baseDn, string $searchDn = null, string $searchPassword = null, array $defaultRoles = [], string $uidKey = null, string $filter = null, string $passwordAttribute = null, array $extraFields = [])
    {
        if (null === $uidKey) {
            $uidKey = 'sAMAccountName';
        }

        if (null === $filter) {
            $filter = '({uid_key}={user_identifier})';
        }

        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->defaultRoles = $defaultRoles;
        $this->uidKey = $uidKey;
        $this->defaultSearch = str_replace('{uid_key}', $uidKey, $filter);
        $this->passwordAttribute = $passwordAttribute;
        $this->extraFields = $extraFields;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        trigger_deprecation('symfony/ldap', '5.3', 'Method "%s()" is deprecated, use loadUserByIdentifier() instead.', __METHOD__);

        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $this->ldap->bind($this->searchDn, $this->searchPassword);
            $identifier = $this->ldap->escape($identifier, '', LdapInterface::ESCAPE_FILTER);
            $query = str_replace(['{username}', '{user_identifier}'], $identifier, $this->defaultSearch);
            $search = $this->ldap->query($this->baseDn, $query, ['filter' => 0 == \count($this->extraFields) ? '*' : $this->extraFields]);
        } catch (ConnectionException $e) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $identifier), 0, $e);
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        $entries = $search->execute();
        $count = \count($entries);

        if (!$count) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        if ($count > 1) {
            $e = new UserNotFoundException('More than one user found.');
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        $entry = $entries[0];

        try {
            if (null !== $this->uidKey) {
                $identifier = $this->getAttributeValue($entry, $this->uidKey);
            }
        } catch (InvalidArgumentException $e) {
        }

        return $this->loadUser($identifier, $entry);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        return new LdapUser($user->getEntry(), $user->getUserIdentifier(), $user->getPassword(), $user->getRoles(), $user->getExtraFields());
    }

    /**
     * {@inheritdoc}
     *
     * @final
     */
    public function upgradePassword($user, string $newHashedPassword): void
    {
        if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        if (null === $this->passwordAttribute) {
            return;
        }

        try {
            $user->getEntry()->setAttribute($this->passwordAttribute, [$newHashedPassword]);
            $this->ldap->getEntryManager()->update($user->getEntry());
            $user->setPassword($newHashedPassword);
        } catch (ExceptionInterface $e) {
            // ignore failed password upgrades
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class)
    {
        return LdapUser::class === $class;
    }

    /**
     * Loads a user from an LDAP entry.
     *
     * @return UserInterface
     */
    protected function loadUser(string $identifier, Entry $entry)
    {
        $password = null;
        $extraFields = [];

        if (null !== $this->passwordAttribute) {
            $password = $this->getAttributeValue($entry, $this->passwordAttribute);
        }

        foreach ($this->extraFields as $field) {
            $extraFields[$field] = $this->getAttributeValue($entry, $field);
        }

        return new LdapUser($entry, $identifier, $password, $this->defaultRoles, $extraFields);
    }

    private function getAttributeValue(Entry $entry, string $attribute)
    {
        if (!$entry->hasAttribute($attribute)) {
            throw new InvalidArgumentException(sprintf('Missing attribute "%s" for user "%s".', $attribute, $entry->getDn()));
        }

        $values = $entry->getAttribute($attribute);

        if (1 !== \count($values)) {
            throw new InvalidArgumentException(sprintf('Attribute "%s" has multiple values.', $attribute));
        }

        return $values[0];
    }
}
