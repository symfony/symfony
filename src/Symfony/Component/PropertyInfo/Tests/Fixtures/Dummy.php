<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Dummy extends ParentDummy
{
    /**
     * @var string This is bar.
     */
    private $bar;

    /**
     * Should be used.
     *
     * @var int Should be ignored.
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
}
