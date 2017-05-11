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
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * @Entity
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DoctrineDummy
{
    /**
     * @Id
     * @Column(type="smallint")
     */
    public $id;

    /**
     * @ManyToOne(targetEntity="DoctrineRelation")
     */
    public $foo;

    /**
     * @ManyToMany(targetEntity="DoctrineRelation")
     */
    public $bar;

    /**
     * @ManyToMany(targetEntity="DoctrineRelation", indexBy="rguid")
     */
    protected $indexedBar;

    /**
     * @Column(type="guid")
     */
    protected $guid;

    /**
     * @Column(type="time")
     */
    private $time;

    /**
     * @Column(type="time_immutable")
     */
    private $timeImmutable;

    /**
     * @Column(type="dateinterval")
     */
    private $dateInterval;

    /**
     * @Column(type="json_array")
     */
    private $json;

    /**
     * @Column(type="simple_array")
     */
    private $simpleArray;

    /**
     * @Column(type="float")
     */
    private $float;

    /**
     * @Column(type="decimal", precision=10, scale=2)
     */
    private $decimal;

    /**
     * @Column(type="boolean")
     */
    private $bool;

    /**
     * @Column(type="binary")
     */
    private $binary;

    /**
     * @Column(type="custom_foo")
     */
    private $customFoo;

    /**
     * @Column(type="bigint")
     */
    private $bigint;

    public $notMapped;
}
