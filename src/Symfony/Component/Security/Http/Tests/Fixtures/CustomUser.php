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

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class CustomUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $username,
        private array $roles,
        private ?string $password,
        private ?bool $hashPassword,
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
        return $this->password ?? null;
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $passwordKey = \sprintf("\0%s\0password", self::class);

        if ($this->hashPassword) {
            $data[$passwordKey] = hash('crc32c', $this->password);
        } elseif (null !== $this->hashPassword) {
            unset($data[$passwordKey]);
        }

        return $data;
    }
}
