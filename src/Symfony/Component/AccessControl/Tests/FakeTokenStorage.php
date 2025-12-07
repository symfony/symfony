<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class FakeTokenStorage implements TokenStorageInterface
{
    private ?TokenInterface $token = null;

    public function getToken(): ?TokenInterface
    {
        return $this->token;
    }

    public function setToken(?TokenInterface $token): void
    {
        $this->token = $token;
    }
}
