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
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * Encrypted columns declared in every place the ORM reads them from.
 *
 * One key is used for columns of three types, so it needs three encrypted types.
 */
#[Entity]
class EncryptedColumnsEntity extends EncryptedColumnsSuperclass
{
    #[Id, Column, GeneratedValue]
    public ?int $id = null;

    #[Column(nullable: true)]
    #[Encrypted(key: 'person.contact')]
    public ?string $phone = null;

    #[Column(type: 'text')]
    #[Encrypted(key: 'person.contact')]
    private string $notes = '';

    #[Column(nullable: true)]
    #[Encrypted(key: 'person.contact')]
    public ?\DateTimeImmutable $birthday = null;

    #[Column(nullable: true)]
    public ?string $name = null;

    #[Embedded(class: EncryptedColumnsAddress::class)]
    public EncryptedColumnsAddress $address;

    public function __construct()
    {
        $this->address = new EncryptedColumnsAddress();
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
