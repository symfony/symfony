<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        #[Id, Column]
        protected ?int $id1,

        #[Id, Column]
        protected ?int $id2,

        #[Column]
        public string $name,
    ) {
    }

    public function getRoles(): array
    {
    }

    public function getPassword(): ?string
    {
    }

    public function getUsername(): string
    {
        return $this->name;
    }

    public function getUserIdentifier(): string
    {
        return $this->name;
    }

    public function eraseCredentials(): void
    {
    }

    public function equals(UserInterface $user)
    {
    }
}
