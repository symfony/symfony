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
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\KeyManagement\BlindIndex\Email;
use Symfony\Component\KeyManagement\BlindIndex\EmailDomain;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

/**
 * Two indexes over the same value, and a private source, which is what an entity holding an
 * encrypted column actually looks like.
 */
#[Entity]
class BlindIndexedEntity
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public ?int $id = null;

    #[Column(type: 'string', nullable: true)]
    private ?string $email = null;

    #[Column(type: 'string', nullable: true)]
    #[BlindIndexed('email', Email::class)]
    private ?string $emailIndex = null;

    #[Column(type: 'string', nullable: true)]
    #[BlindIndexed('email', EmailDomain::class)]
    private ?string $emailDomainIndex = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $name = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getEmailIndex(): ?string
    {
        return $this->emailIndex;
    }

    public function setEmailIndex(?string $emailIndex): static
    {
        $this->emailIndex = $emailIndex;

        return $this;
    }

    public function getEmailDomainIndex(): ?string
    {
        return $this->emailDomainIndex;
    }
}
