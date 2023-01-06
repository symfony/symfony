<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;

final class CustomUser implements UserInterface
{
    /** @var string */
    private $username;
    /** @var array */
    private $roles;

    public function __construct(string $username, array $roles)
    {
        $this->username = $username;
        $this->roles = $roles;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }
}
