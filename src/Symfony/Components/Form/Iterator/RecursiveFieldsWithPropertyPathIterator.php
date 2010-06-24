<?php

namespace Symfony\Components\Form\Iterator;

use Symfony\Components\Form\FieldGroupInterface;

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
