<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Symphony\Bridge\Doctrine\Tests\Fixtures\Type\StringWrapper;

/** @Entity */
class SingleIntIdStringWrapperNameEntity
{
    /** @Id @Column(type="integer") */
    protected $id;

    /** @Column(type="string_wrapper", nullable=true) */
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
