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
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;

#[Entity, InheritanceType('SINGLE_TABLE'), DiscriminatorColumn(name: 'discr', type: 'string'), DiscriminatorMap(['person' => 'Person', 'employee' => 'Employee'])]
class Person
{
    public function __construct(
        #[Id, Column]
        protected int $id,

        #[Column]
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
