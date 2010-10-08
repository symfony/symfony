<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class ConstraintViolationList implements \IteratorAggregate, \Countable
{
    protected $violations = array();

    public function __toString()
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $root = $violation->getRoot();
            $class = is_object($root) ? get_class($root) : $root;
            $string .= <<<EOF
{$class}.{$violation->getPropertyPath()}:
    {$violation->getMessage()}

EOF;
        }

        return $string;
    }

    public function add(ConstraintViolation $violation)
    {
        $this->violations[] = $violation;
    }

    public function addAll(ConstraintViolationList $violations)
    {
        foreach ($violations->violations as $violation) {
            $this->violations[] = $violation;
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    public function count()
    {
        return count($this->violations);
    }
}