<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[MappedSuperclass]
abstract class EncryptedColumnsSuperclass
{
    #[Column]
    #[Encrypted(key: 'person.email')]
    private string $email = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }
}
