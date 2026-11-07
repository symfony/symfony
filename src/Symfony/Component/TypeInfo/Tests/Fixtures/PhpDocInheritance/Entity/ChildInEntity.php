<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures\PhpDocInheritance\Entity;

use Symfony\Component\TypeInfo\Tests\Fixtures\PhpDocInheritance\Model\ParentInModel;

class Thing
{
}

class Widget
{
}

class ChildInEntity extends ParentInModel
{
    public function getThing(): object
    {
        return new Thing();
    }

    public function getWidget(): object
    {
        return new Widget();
    }

    public function getSelf(): object
    {
        return $this;
    }

    public function getStatic(): object
    {
        return $this;
    }

    public function getThis(): object
    {
        return $this;
    }

    public function getSelfList(): array
    {
        return [];
    }

    public function getParentObject(): object
    {
        return $this;
    }

    public function getAliased(): array
    {
        return [];
    }

    public function getGadget(): object
    {
        return $this;
    }
}
