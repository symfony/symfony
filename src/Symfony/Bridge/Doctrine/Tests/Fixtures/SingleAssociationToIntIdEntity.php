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
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class SingleAssociationToIntIdEntity
{
    public function __construct(
        #[Id, OneToOne(cascade: ['ALL'])]
        #[JoinColumn(nullable: false)]
        protected SingleIntIdNoToStringEntity $entity,

        #[Column(nullable: true)]
        public ?string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
