<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class OwningEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="NonOwningEntity", inversedBy="owningEntities")
     * @ORM\JoinColumn(nullable=false)
     */
    private $nonOwningEntity;

    public function __construct()
    {
        $this->id = 0;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($filename)
    {
        $this->name = $filename;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setNonOwningEntity(NonOwningEntity $noe)
    {
        $this->nonOwningEntity = $noe;
    }

    public function getNonOwningEntity()
    {
        return $this->nonOwningEntity;
    }
}

