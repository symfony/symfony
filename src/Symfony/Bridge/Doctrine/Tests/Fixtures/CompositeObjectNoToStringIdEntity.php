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

use Doctrine\ORM\Mapping as ORM;

/**
 * an entity that has two objects (class without toString methods) as primary key.
 */
#[ORM\Entity]
class CompositeObjectNoToStringIdEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(cascade: ['persist'])]
        #[ORM\JoinColumn(name: 'object_one_id', nullable: false)]
        protected SingleIntIdNoToStringEntity $objectOne,

        #[ORM\Id]
        #[ORM\ManyToOne(cascade: ['persist'])]
        #[ORM\JoinColumn(name: 'object_two_id', nullable: false)]
        protected SingleIntIdNoToStringEntity $objectTwo,
    ) {
    }

    public function getObjectOne(): SingleIntIdNoToStringEntity
    {
        return $this->objectOne;
    }

    public function getObjectTwo(): SingleIntIdNoToStringEntity
    {
        return $this->objectTwo;
    }
}
