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
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @final
 */
class LdapUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public function __construct(
        private Entry $entry,
        private string $identifier,
        #[\SensitiveParameter] private ?string $password,
        private array $roles = [],
        private array $extraFields = [],
    ) {
        if (!$identifier) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password ?? null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @deprecated since Symfony 7.3
     */
    #[\Deprecated(since: 'symfony/ldap 7.3')]
    public function eraseCredentials(): void
    {
        if (\PHP_VERSION_ID < 80400) {
            @trigger_error(\sprintf('Method %s::eraseCredentials() is deprecated since symfony/ldap 7.3', self::class), \E_USER_DEPRECATED);
        }

        $this->password = null;
    }

    public function getExtraFields(): array
    {
        return $this->extraFields;
    }

    public function setPassword(#[\SensitiveParameter] ?string $password): void
    {
        $this->password = $password;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if (($this->getPassword() ?? $user->getPassword()) !== $user->getPassword()) {
            return false;
        }

        if ($this->getSalt() !== $user->getSalt()) {
            return false;
        }

        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }

        return true;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        unset($data[\sprintf("\0%s\0password", self::class)]);

        return $data;
    }
}
