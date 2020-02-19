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
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * @Entity
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineRelation
{
    /**
     * @Id
     * @Column(type="smallint")
     */
    public $id;

    /**
     * @Column(type="guid")
     */
    protected $rguid;

    /**
     * @Column(type="guid")
     * @ManyToOne(targetEntity="DoctrineDummy", inversedBy="indexedFoo")
     */
    protected $foo;

    /**
     * @Column(type="datetime")
     */
    private $dt;

    /**
     * @Column(type="foo")
     */
    private $customType;
}
