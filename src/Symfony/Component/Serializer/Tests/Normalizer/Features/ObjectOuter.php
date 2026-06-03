<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class ObjectOuter
{
    public $foo;
    public $bar;
    private $inner;
    private $date;

    /**
     * @var ObjectInner[]
     */
    private $inners;

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

    /**
     * @return ObjectInner
     */
    public function getInner()
    {
        return $this->inner;
    }

    public function setInner(ObjectInner $inner)
    {
        $this->inner = $inner;
    }

    public function setDate(\DateTimeInterface $date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setInners(array $inners)
    {
        $this->inners = $inners;
    }

    public function getInners()
    {
        return $this->inners;
    }
}
