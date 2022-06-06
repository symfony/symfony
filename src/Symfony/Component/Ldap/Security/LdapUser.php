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
    private $entry;
    private $username;
    private $password;
    private $roles;
    private $extraFields;

    public function __construct(Entry $entry, string $username, ?string $password, array $roles = [], array $extraFields = [])
    {
        if (!$username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->entry = $entry;
        $this->username = $username;
        $this->password = $password;
        $this->roles = $roles;
        $this->extraFields = $extraFields;
    }

    public function getEntry(): Entry
    {
        return $this->entry;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(): string
    {
        trigger_deprecation('symfony/ldap', '5.3', 'Method "%s()" is deprecated and will be removed in 6.0, use getUserIdentifier() instead.', __METHOD__);

        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        $this->password = null;
    }

    public function getExtraFields(): array
    {
        return $this->extraFields;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    /**
     * {@inheritdoc}
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getPassword() !== $user->getPassword()) {
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
}
