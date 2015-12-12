<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\InheritanceType;

/**
 * @Entity
 * @InheritanceType("SINGLE_TABLE")
 * @DiscriminatorColumn(name="discr", type="string")
 * @DiscriminatorMap({
 *     "top"  = "TopEntity",
 *     "good" = "GoodEntity",
 *     "low"  = "LowEntity",
 *     "side" = "SideEntity",
 *     "inst" = "InstanceEntity",
 * })
 *
 * The point of this hierarchy is to test the selection of the repository
 * by the UniqueEntityValidator. This hierarchy provides:
 *  - A top entity which is too high in the inheritance chain to be selected
 *  - Both a transient and a mapped super class targets
 *  - Upper mapped super and transient classes which should not be selected
 *  - A good Entity which is intended to provide the repo
 *  - A side branch including one of each Entity, Mapped Super and Transient
 *  - A lower part including one of each Entity, Mapped Super and Transient
 *    which are not intended to be selected since they lower than the GoodEntity
 *  - An InstanceEntity which is intended to be validated
 */
class TopEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /** @Column(type="string", nullable=true) */
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
