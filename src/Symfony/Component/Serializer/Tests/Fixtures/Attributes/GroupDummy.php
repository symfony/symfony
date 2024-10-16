<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Tests\Fixtures\ChildOfGroupsAttributeDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GroupDummy extends GroupDummyParent implements GroupDummyInterface
{
    #[Groups(['a'])]
    private $foo;
    #[Groups(['b', 'c', 'name_converter'])]
    protected $bar;
    #[ChildOfGroupsAttributeDummy]
    protected $quux;
    private $fooBar;
    private $symfony;
    #[Groups(['Default'])]
    private $default;
    #[Groups(['GroupDummy'])]
    private $className;

    #[Groups(['b'])]
    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    #[Groups(['c'])]
    public function getBar()
    {
        return $this->bar;
    }

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFooBar($fooBar)
    {
        $this->fooBar = $fooBar;
    }

    #[Groups(['a', 'b', 'name_converter'])]
    public function isFooBar()
    {
        return $this->fooBar;
    }

    public function setSymfony($symfony)
    {
        $this->symfony = $symfony;
    }

    public function getSymfony()
    {
        return $this->symfony;
    }

    public function getQuux()
    {
        return $this->quux;
    }

    public function setQuux($quux): void
    {
        $this->quux = $quux;
    }

    public function setDefault($default)
    {
        $this->default = $default;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setClassName($className)
    {
        $this->className = $className;
    }

    public function getClassName()
    {
        return $this->className;
    }
}
