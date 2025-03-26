<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;

final class CustomUser implements UserInterface
{
    public function __construct(
        private string $username,
        private array $roles,
    ) {
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

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
