<?php

namespace Symfony\Component\PasswordHasher\Tests\Fixtures;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class TestPasswordAuthenticatedUser implements PasswordAuthenticatedUserInterface
{
    private $password;

    public function __construct(?string $password = null)
    {
        $this->password = $password;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
