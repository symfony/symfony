<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class AssociationEntity
{
    /**
     * @var int
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="SingleIntIdEntity")
     *
     * @var \Symphony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity
     */
    public $single;

    /**
     * @ORM\ManyToOne(targetEntity="CompositeIntIdEntity")
     * @ORM\JoinColumns({
     *  @ORM\JoinColumn(name="composite_id1", referencedColumnName="id1"),
     *  @ORM\JoinColumn(name="composite_id2", referencedColumnName="id2")
     * })
     *
     * @var \Symphony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity
     */
    public $composite;
}
