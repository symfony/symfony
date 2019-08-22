<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class CompositeIntIdEntity
{
    /** @Id @Column(type="integer") */
    protected $id1;

    /** @Id @Column(type="integer") */
    protected $id2;

    /** @Column(type="string") */
    public $name;

    public function __construct($id1, $id2, $name)
    {
        $this->id1 = $id1;
        $this->id2 = $id2;
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
