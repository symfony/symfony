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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class ToManyEntity
{
    /** @var Collection<int, ToOneEntity> */
    #[OneToMany(targetEntity: ToOneEntity::class, mappedBy: 'parent')]
    public Collection $children;

    public function __construct(
        #[Id, Column(type: 'integer')]
        public int $id,
    ) {
        $this->children = new ArrayCollection();
    }
}
