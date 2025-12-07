<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class FakeUser implements UserInterface
{
    public function __construct(
        private string $username = 'foo',
        private array $roles = ['ROLE_USER']
    )
    {
    }

    public function eraseCredentials(): void
    {
        //Nothing to do here
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}
