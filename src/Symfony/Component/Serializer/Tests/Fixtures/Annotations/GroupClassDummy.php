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

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @Groups({"a"})
 */
class GroupClassDummy
{
    /**
     * @Groups({"b"})
     */
    private $foo;

    /**
     * @Groups({"c", "d"})
     */
    private $bar;

    private $baz;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar): void
    {
        $this->bar = $bar;
    }

    public function getBaz()
    {
        return $this->baz;
    }

    public function setBaz($baz): void
    {
        $this->baz = $baz;
    }
}
