<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class SerializedNameDummy
{
    /**
     * @SerializedName("baz")
     */
    public $foo;

    public $bar;

    public $quux;

    /**
     * @var self
     */
    public $child;

    /**
     * @SerializedName("qux")
     */
    public function getBar()
    {
        return $this->bar;
    }

    public function getChild()
    {
        return $this->child;
    }
}
