<?php

namespace Symfony\Tests\Bridge\Doctrine\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Entity;

/** @Entity */
class AssociationIdentEntity
{
    /** @Id @OneToOne(targetEntity="SingleIdentEntity") */
    protected $single;

    public function __construct($single) {
        $this->single = $single;
    }

    public function __toString()
    {
        return (string)$this->single;
    }
}
