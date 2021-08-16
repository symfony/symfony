<?php

namespace Symfony\Component\PasswordHasher\Tests\Fixtures;

use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class TestLegacyPasswordAuthenticatedUser implements LegacyPasswordAuthenticatedUserInterface, UserInterface
{
    private $username;
    private $password;
    private $salt;
    private $roles;

    public function __construct(string $username, ?string $password = null, ?string $salt = null, array $roles = [])
    {
        $this->roles = $roles;
        $this->salt = $salt;
        $this->password = $password;
        $this->username = $username;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // Do nothing
        return;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
