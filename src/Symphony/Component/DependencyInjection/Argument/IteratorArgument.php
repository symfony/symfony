<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Argument;

use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symphony\Component\DependencyInjection\Reference;

/**
 * Represents a collection of values to lazily iterate over.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class IteratorArgument implements ArgumentInterface
{
    private $values;

    /**
     * @param Reference[] $values
     */
    public function __construct(array $values)
    {
        $this->setValues($values);
    }

    /**
     * @return array The values to lazily iterate over
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param Reference[] $values The service references to lazily iterate over
     */
    public function setValues(array $values)
    {
        foreach ($values as $k => $v) {
            if (null !== $v && !$v instanceof Reference) {
                throw new InvalidArgumentException(sprintf('An IteratorArgument must hold only Reference instances, "%s" given.', is_object($v) ? get_class($v) : gettype($v)));
            }
        }

        $this->values = $values;
    }
}
