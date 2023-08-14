<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[Entity]
class DoctrineRelation
{
    #[Id, Column(type: 'smallint')]
    public $id;

    #[Column(type: 'guid', name: 'rguid_column')]
    protected $rguid;

    #[Column(type: 'guid')]
    #[ManyToOne(targetEntity: DoctrineDummy::class, inversedBy: 'indexedFoo')]
    protected $foo;

    #[ManyToOne(targetEntity: DoctrineDummy::class)]
    protected $baz;

    #[Column(type: 'datetime')]
    private $dt;

    #[Column(type: 'foo')]
    private $customType;

    #[Column(type: 'guid', name: 'different_than_field')]
    #[ManyToOne(targetEntity: DoctrineDummy::class, inversedBy: 'indexedBuz')]
    protected $buzField;

    #[ManyToOne(targetEntity: DoctrineDummy::class, inversedBy: 'dummyGeneratedValueList')]
    private $dummyRelation;

    #[ManyToOne(targetEntity: DoctrineGeneratedValue::class, inversedBy: 'relationList')]
    #[JoinColumn(name: 'gen_value_col_id', referencedColumnName: 'gen_value_col_id')]
    private $generatedValueRelation;
}
