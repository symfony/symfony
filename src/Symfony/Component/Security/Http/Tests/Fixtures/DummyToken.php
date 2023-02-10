<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class DummyToken implements TokenInterface
{
    public function serialize()
    {
    }

    public function unserialize($data)
    {
    }

    public function __toString(): string
    {
    }

    public function getRoleNames(): array
    {
    }

    public function getCredentials(): mixed
    {
    }

    public function getUser(): ?UserInterface
    {
    }

    public function setUser($user)
    {
    }

    public function isAuthenticated(): bool
    {
    }

    public function setAuthenticated(bool $isAuthenticated)
    {
    }

    public function eraseCredentials(): void
    {
    }

    public function getAttributes(): array
    {
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function hasAttribute(string $name): bool
    {
    }

    public function getAttribute(string $name): mixed
    {
    }

    public function setAttribute(string $name, $value): void
    {
    }

    public function getUsername(): string
    {
    }

    public function getUserIdentifier(): string
    {
    }

    public function __serialize(): array
    {
    }

    public function __unserialize(array $data): void
    {
    }

    public function __call(string $name, array $arguments)
    {
    }
}
