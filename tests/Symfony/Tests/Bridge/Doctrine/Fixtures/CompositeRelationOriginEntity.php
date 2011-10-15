<?php

namespace Symfony\Tests\Bridge\Doctrine\Form\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinColumns;
use Symfony\Tests\Bridge\Doctrine\Form\Fixtures\CompositeIdentEntity;

/** @Entity */
class CompositeRelationOriginEntity
{
    /** @Id @Column(type="integer") */
    protected $id;
    
    /**
     * @OneToOne(targetEntity="CompositeIdentEntity")
     * @JoinColumns({
     *  @JoinColumn(name="composite_ident_id1", referencedColumnName="id1"),
     *  @JoinColumn(name="composite_ident_id2", referencedColumnName="id2")
     * })
     */    
    protected $composite_ident;
    
    public function __construct($id, CompositeIdentEntity $composite_ident) {
        $this->id = $id;
        $this->composite_ident = $composite_ident;
    }
}
