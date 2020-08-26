<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Jordan Samouh <jordan.samouh@gmail.com>
 */
class SerializedNameWithGroupsDummy
{
    /**
     * @SerializedName("baz")
     */
    public $foo;

    public $bar;

    public $quux;

    /**
     * @SerializedName("bazs")
     */
    public $foos;

    /**
     * @SerializedName("bargroups", groups={"group1"})
     * @Groups({"group1"})
     */
    public $barWithGroup;

    /**
     * @SerializedName("quuxgroups2", groups={"group1", "group2"})
     * @SerializedName("quuxgroups1", groups={"group1"})
     * @Groups({"group1", "group2"})
     */
    public $quuxWithGroups;

    /**
     * @SerializedName("qux")
     * @Groups({"group1"})
     */
    public function getBar()
    {
        return $this->bar;
    }
}
