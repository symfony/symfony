<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance\Model;

use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance\Item;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance\Model\Sub\GadgetTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance\Model\Sub\Thing;

class GrandParentInModel
{
}

class Widget
{
}

/**
 * @phpstan-type ItemList list<Item>
 */
class ParentInModel extends GrandParentInModel
{
    use GadgetTrait;

    /**
     * @return Thing
     */
    public function getThing(): object
    {
        return new Thing();
    }

    /**
     * @return Widget
     */
    public function getWidget(): object
    {
        return new Widget();
    }

    /**
     * @return self
     */
    public function getSelf(): object
    {
        return $this;
    }

    /**
     * @return static
     */
    public function getStatic(): object
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function getThis(): object
    {
        return $this;
    }

    /**
     * @return list<self>
     */
    public function getSelfList(): array
    {
        return [];
    }

    /**
     * @return parent
     */
    public function getParentObject(): object
    {
        return $this;
    }

    /**
     * @return ItemList
     */
    public function getAliased(): array
    {
        return [];
    }
}
