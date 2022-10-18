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

/**
 * UserInterface implementation used by the in-memory user provider.
 *
 * This should not be used for anything else.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class InMemoryUser implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private string $username;
    private ?string $password;
    private bool $enabled;
    private array $roles;

    public function __construct(?string $username, ?string $password, array $roles = [], bool $enabled = true)
    {
        if ('' === $username || null === $username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->username = $username;
        $this->password = $password;
        $this->enabled = $enabled;
        $this->roles = $roles;
    }

    public function __toString(): string
    {
        return $this->getUserIdentifier();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Returns the identifier for this user (e.g. its username or email address).
     */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * Checks whether the user is enabled.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a DisabledException and prevent login.
     *
     * @return bool true if the user is enabled, false otherwise
     *
     * @see DisabledException
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function eraseCredentials()
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getPassword() !== $user->getPassword()) {
            return false;
        }

        $currentRoles = array_map('strval', (array) $this->getRoles());
        $newRoles = array_map('strval', (array) $user->getRoles());
        $rolesChanged = \count($currentRoles) !== \count($newRoles) || \count($currentRoles) !== \count(array_intersect($currentRoles, $newRoles));
        if ($rolesChanged) {
            return false;
        }

        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }

        if ($this->isEnabled() !== $user->isEnabled()) {
            return false;
        }

        return true;
    }
}
