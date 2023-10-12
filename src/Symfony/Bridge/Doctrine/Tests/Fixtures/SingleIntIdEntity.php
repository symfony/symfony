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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
#[Entity]
class SingleIntIdEntity
{
    /** @Id @Column(type="integer") */
    #[Id, Column(type: 'integer')]
    protected $id;

    /** @Column(type="string", nullable=true) */
    #[Column(type: 'string', nullable: true)]
    public $name;

    /** @Column(type="json", nullable=true) */
    #[Column(type: Types::JSON, nullable: true)]
    public $phoneNumbers = [];

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
