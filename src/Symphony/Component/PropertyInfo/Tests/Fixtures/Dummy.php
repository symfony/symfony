<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyInfo\Tests\Fixtures;

use Symphony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Dummy extends ParentDummy
{
    /**
     * @var string This is bar
     */
    private $bar;

    /**
     * Should be used.
     *
     * @var int Should be ignored
     */
    protected $baz;

    /**
     * @var \DateTime
     */
    public $bal;

    /**
     * @var ParentDummy
     */
    public $parent;

    /**
     * @var \DateTime[]
     * @Groups({"a", "b"})
     */
    public $collection;

    /**
     * @var ParentDummy
     */
    public $B;

    /**
     * @var int
     */
    protected $Id;

    /**
     * @var string
     */
    public $Guid;

    /**
     * Nullable array.
     *
     * @var array|null
     */
    public $array;

    /**
     * This should not be removed.
     *
     * @var
     */
    public $emptyVar;

    public static function getStatic()
    {
    }

    /**
     * @return string
     */
    public static function staticGetter()
    {
    }

    public static function staticSetter(\DateTime $d)
    {
    }

    /**
     * A.
     *
     * @return int
     */
    public function getA()
    {
    }

    /**
     * B.
     *
     * @param ParentDummy|null $parent
     */
    public function setB(ParentDummy $parent = null)
    {
    }

    /**
     * Date of Birth.
     *
     * @return \DateTime
     */
    public function getDOB()
    {
    }

    /**
     * @return int
     */
    public function getId()
    {
    }

    public function get123()
    {
    }
}
