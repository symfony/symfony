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
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class SingleStringCastableIdEntity
{
    /**
     * @Id
     * @Column(type="string")
     * @GeneratedValue(strategy="NONE")
     */
    protected $id;

    /** @Column(type="string", nullable=true) */
    public $name;

    public function __construct($id, $name)
    {
        $this->id = new StringCastableObjectIdentity($id);
        $this->name = $name;
    }

    public function __toString()
    {
        return (string) $this->name;
    }
}

class StringCastableObjectIdentity
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return (string) $this->id;
    }
}
