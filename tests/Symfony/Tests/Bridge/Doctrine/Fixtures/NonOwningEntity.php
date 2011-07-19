<?php

namespace Symfony\Tests\Bridge\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * NonOwningEntity
 *
 * @ORM\Entity
 */
class NonOwningEntity
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
     * @ORM\OneToMany(targetEntity="OwningEntity", mappedBy="nonOwningEntity", cascade={"all"})
     */
    private $owningEntities;

    public function __construct()
    {
        $this->owningEntities = new \Doctrine\Common\Collections\ArrayCollection();
        $this->id = 0;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addOwningEntity(OwningEntity $oe)
    {
        $this->owningEntities[] = $oe;
    }

    public function getOwningEntities()
    {
        return $this->owningEntities;
    }
}
