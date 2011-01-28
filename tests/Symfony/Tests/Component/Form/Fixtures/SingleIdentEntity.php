<?php

namespace Symfony\Tests\Component\Form\Fixtures;

/** @Entity */
class SingleIdentEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /** @Column(type="string") */
    public $name;

    public function __construct($id, $name) {
        $this->id = $id;
        $this->name = $name;
    }
}