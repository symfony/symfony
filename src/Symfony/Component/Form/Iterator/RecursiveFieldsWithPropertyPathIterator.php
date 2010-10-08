<?php

namespace Symfony\Component\Form\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\FieldGroupInterface;

class RecursiveFieldsWithPropertyPathIterator extends \IteratorIterator implements \RecursiveIterator
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
        return $this->current() instanceof FieldGroupInterface && $this->current()->getPropertyPath() === null;
    }
}
