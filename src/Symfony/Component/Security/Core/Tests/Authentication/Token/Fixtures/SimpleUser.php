<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

use http\Client\Curl\User;
use Symfony\Component\Security\Core\User\UserInterface;

class SimpleUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return 'user identifier';
    }
}
