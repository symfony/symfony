<?php

namespace Symfony\Tests\Bridge\Doctrine\Form\Fixtures;

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
    public $group_name;

    public function __construct($id, $name, $group_name) {
        $this->id = $id;
        $this->name = $name;
        $this->group_name = $group_name;
    }
}
