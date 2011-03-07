<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * An array-acting object that holds many ConstrainViolation instances.
 */
class ConstraintViolationList implements \IteratorAggregate, \Countable
{
    protected $violations = array();

    /**
     * @return string
     */
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

    /**
     * Add a ConstraintViolation to this list.
     *
     * @param ConstraintViolation $violation
     */
    public function add(ConstraintViolation $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * Merge an existing ConstraintViolationList into this list.
     *
     * @param ConstraintViolationList $violations
     */
    public function addAll(ConstraintViolationList $violations)
    {
        foreach ($violations->violations as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * @see IteratorAggregate
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * @see Countable
     */
    public function count()
    {
        return count($this->violations);
    }
}