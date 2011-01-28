<?php

namespace Symfony\Tests\Component\Form\Fixtures;

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