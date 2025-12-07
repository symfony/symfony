<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\AccessControl\Voter\RBAC\UserWithRoleInterface;

final readonly class FakeUserWithRole implements UserWithRoleInterface
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
