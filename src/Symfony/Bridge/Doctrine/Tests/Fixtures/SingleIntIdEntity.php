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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

#[Entity(repositoryClass: SingleIntIdEntityRepository::class)]
class SingleIntIdEntity
{
    #[Column(type: Types::JSON, nullable: true)]
    public mixed $phoneNumbers = [];

    public function __construct(
        #[Id, Column(type: 'integer')]
        protected int $id,

        #[Column(type: 'string', nullable: true)]
        public ?string $name,
    ) {
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
