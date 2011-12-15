<?php

namespace Symfony\Tests\Bridge\Doctrine\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class SingleIdentEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /** @Column(type="string", nullable=true) */
    public $name;

    public function __construct($id, $name) {
        $this->id = $id;
        $this->name = $name;
    }
}
