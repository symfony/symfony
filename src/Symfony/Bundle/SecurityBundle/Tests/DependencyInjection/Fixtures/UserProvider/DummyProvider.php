<?php

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\UserProvider;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class DummyProvider implements UserProviderInterface
{
    public function __construct(string $foo)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new \Exception('Not implemented');
    }

    public function supportsClass(string $class): bool
    {
        throw new \Exception('Not implemented');
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \Exception('Not implemented');
    }
}
