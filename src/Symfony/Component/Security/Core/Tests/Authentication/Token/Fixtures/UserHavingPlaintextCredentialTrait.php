<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

trait UserHavingPlaintextCredentialTrait
{
    public string $plainPassword = 'plaintext';

    public function eraseCredentials(): void
    {
        $this->plainPassword = '';
    }

    public function getRoles(): array
    {
        return [
            'ROLE_USER'
        ];
    }

    public function getUserIdentifier(): string
    {
        return 'username';
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }
}
