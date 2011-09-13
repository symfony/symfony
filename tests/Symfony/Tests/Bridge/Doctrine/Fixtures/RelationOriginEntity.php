<?php

namespace Symfony\Tests\Bridge\Doctrine\Form\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity;

/** @Entity */
class RelationOriginEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /**
     * @OneToOne(targetEntity="SingleIdentEntity")
     * @JoinColumn(name="single_ident_id", referencedColumnName="id")
     */
    protected $single_ident;

    public function __construct($id, SingleIdentEntity $single_ident) {
        $this->id = $id;
        $this->single_ident = $single_ident;
    }
}
