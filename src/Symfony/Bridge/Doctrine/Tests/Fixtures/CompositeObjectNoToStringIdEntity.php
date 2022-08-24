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
 *
 * @ORM\Entity
 */
class CompositeObjectNoToStringIdEntity
{
    /**
     * @var SingleIntIdNoToStringEntity
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="SingleIntIdNoToStringEntity", cascade={"persist"})
     * @ORM\JoinColumn(name="object_one_id")
     */
    protected $objectOne;

    /**
     * @var SingleIntIdNoToStringEntity
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="SingleIntIdNoToStringEntity", cascade={"persist"})
     * @ORM\JoinColumn(name="object_two_id")
     */
    protected $objectTwo;

    public function __construct(SingleIntIdNoToStringEntity $objectOne, SingleIntIdNoToStringEntity $objectTwo)
    {
        $this->objectOne = $objectOne;
        $this->objectTwo = $objectTwo;
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
