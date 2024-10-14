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
use Symfony\Component\Ldap\Exception\ExceptionInterface;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\InvalidSearchCredentialsException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * LdapUserProvider is a simple user provider on top of LDAP.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @template-implements UserProviderInterface<LdapUser>
 */
class LdapUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private string $uidKey;
    private string $defaultSearch;
    private RoleFetcherInterface $roleFetcher;

    public function __construct(
        private LdapInterface $ldap,
        private string $baseDn,
        private ?string $searchDn = null,
        #[\SensitiveParameter] private ?string $searchPassword = null,
        private array $defaultRoles = [],
        ?string $uidKey = null,
        ?string $filter = null,
        private ?string $passwordAttribute = null,
        private array $extraFields = [],
        ?RoleFetcherInterface $roleFetcher = null
    ) {
        $uidKey ??= 'sAMAccountName';
        $filter ??= '({uid_key}={user_identifier})';

        $this->uidKey = $uidKey;
        $this->defaultSearch = str_replace('{uid_key}', $uidKey, $filter);
        $this->roleFetcher = $roleFetcher ?? new AssignDefaultRoles($defaultRoles);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $this->ldap->bind($this->searchDn, $this->searchPassword);
        } catch (InvalidCredentialsException) {
            throw new InvalidSearchCredentialsException();
        }

        $identifier = $this->ldap->escape($identifier, '', LdapInterface::ESCAPE_FILTER);
        $query = str_replace('{user_identifier}', $identifier, $this->defaultSearch);
        $search = $this->ldap->query($this->baseDn, $query, ['filter' => 0 == \count($this->extraFields) ? '*' : $this->extraFields]);

        $entries = $search->execute();
        $count = \count($entries);

        if (!$count) {
            $e = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
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
            $identifier = $this->getAttributeValue($entry, $this->uidKey);
        } catch (InvalidArgumentException) {
        }

        return $this->loadUser($identifier, $entry);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        return new LdapUser($user->getEntry(), $user->getUserIdentifier(), $user->getPassword(), $user->getRoles(), $user->getExtraFields());
    }

    /**
     * @final
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        if (null === $this->passwordAttribute) {
            return;
        }

        try {
            $user->getEntry()->setAttribute($this->passwordAttribute, [$newHashedPassword]);
            $this->ldap->getEntryManager()->update($user->getEntry());
            $user->setPassword($newHashedPassword);
        } catch (ExceptionInterface) {
            // ignore failed password upgrades
        }
    }

    public function supportsClass(string $class): bool
    {
        return LdapUser::class === $class;
    }

    /**
     * Loads a user from an LDAP entry.
     */
    protected function loadUser(string $identifier, Entry $entry): UserInterface
    {
        $password = null;
        $extraFields = [];

        if (null !== $this->passwordAttribute) {
            $password = $this->getAttributeValue($entry, $this->passwordAttribute);
        }

        foreach ($this->extraFields as $field) {
            $extraFields[$field] = $this->getAttributeValue($entry, $field);
        }

        $roles = $this->roleFetcher->fetchRoles($entry);

        return new LdapUser($entry, $identifier, $password, $roles, $extraFields);
    }

    private function getAttributeValue(Entry $entry, string $attribute): mixed
    {
        if (!$entry->hasAttribute($attribute)) {
            throw new InvalidArgumentException(\sprintf('Missing attribute "%s" for user "%s".', $attribute, $entry->getDn()));
        }

        $values = $entry->getAttribute($attribute);
        if (!\in_array($attribute, [$this->uidKey, $this->passwordAttribute])) {
            return $values;
        }

        if (1 !== \count($values)) {
            throw new InvalidArgumentException(\sprintf('Attribute "%s" has multiple values.', $attribute));
        }

        return $values[0];
    }
}
