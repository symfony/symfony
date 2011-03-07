<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Iterator that traverses fields of a field group
 *
 * If the iterator encounters a virtual field group, it enters the field
 * group and traverses its children as well.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class RecursiveFieldIterator extends \IteratorIterator implements \RecursiveIterator
{
    public function __construct(FormInterface $group)
    {
        parent::__construct($group);
    }

    public function getChildren()
    {
        return new self($this->current());
    }

    public function hasChildren()
    {
        return $this->current() instanceof FormInterface
                && $this->current()->isVirtual();
    }
}
