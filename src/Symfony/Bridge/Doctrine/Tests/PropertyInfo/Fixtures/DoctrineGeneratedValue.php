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
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @Entity
 */
class DoctrineGeneratedValue
{
    /**
     * @Id
     * @GeneratedValue(strategy="AUTO")
     * @Column(type="integer")
     */
    public $id;

    /**
     * @Column
     */
    public $foo;

    /**
     * @var int
     * @Column(type="integer", name="gen_value_col_id")
     */
    public $valueId;

    /**
     * @OneToMany(targetEntity="DoctrineRelation", mappedBy="generatedValueRelation", indexBy="rguid_column", orphanRemoval=true)
     */
    protected $relationList;
}
