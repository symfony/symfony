<?php

namespace Symfony\Tests\Bridge\Doctrine\Form\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class CompositeIdentEntity
{
    /** @Id @Column(type="integer") */
    protected $id1;

    /** @Id @Column(type="integer") */
    protected $id2;

    /** @Column(type="string") */
    public $name;

    public function __construct($id1, $id2, $name) {
        $this->id1 = $id1;
        $this->id2 = $id2;
        $this->name = $name;
    }
}
