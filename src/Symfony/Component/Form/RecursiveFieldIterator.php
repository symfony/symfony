<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Iterator that traverses fields of a field group
 *
 * If the iterator encounters a virtual field group, it enters the field
 * group and traverses its children as well.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RecursiveFieldIterator extends \IteratorIterator implements \RecursiveIterator
{
    public function __construct(FieldGroupInterface $group)
    {
        parent::__construct($group);
    }

    public function getChildren()
    {
        return new self($this->current());
    }

    public function hasChildren()
    {
        return $this->current() instanceof FieldGroupInterface
                && $this->current()->isVirtual();
    }
}
