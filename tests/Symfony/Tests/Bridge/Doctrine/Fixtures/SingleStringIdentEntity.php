<?php

namespace Symfony\Tests\Bridge\Doctrine\Form\Fixtures;

/** @Entity */
class SingleStringIdentEntity
{
    /** @Id @Column(type="string") */
    protected $id;

    /** @Column(type="string") */
    public $name;

    public function __construct($id, $name) {
        $this->id = $id;
        $this->name = $name;
    }
}
