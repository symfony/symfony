<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class ItemGroupEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /** @Column(type="string", nullable=true) */
    public $name;

    /** @Column(type="string", nullable=true) */
    public $groupName;

    public function __construct($id, $name, $groupName)
    {
        $this->id = $id;
        $this->name = $name;
        $this->groupName = $groupName;
    }
}
