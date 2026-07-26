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
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class ToOneEntity
{
    public function __construct(
        #[Id, Column(type: 'integer')]
        public int $id,

        #[ManyToOne(targetEntity: ToManyEntity::class, inversedBy: 'children')]
        public ?ToManyEntity $parent = null,
    ) {
    }
}
