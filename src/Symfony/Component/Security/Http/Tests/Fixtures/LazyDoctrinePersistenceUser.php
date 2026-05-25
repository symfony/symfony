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

use Doctrine\Persistence\Proxy;
use Symfony\Component\Security\Core\User\UserInterface;

class LazyDoctrinePersistenceUser implements UserInterface, Proxy
{
    public bool $initialized = false;

    public function __load(): void
    {
        $this->initialized = true;
    }

    public function __isInitialized(): bool
    {
        return $this->initialized;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'test';
    }
}
