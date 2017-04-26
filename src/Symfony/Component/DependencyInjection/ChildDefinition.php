<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

/**
 * This definition extends another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ChildDefinition extends Definition
{
    private $parent;

    /**
     * @param string $parent The id of Definition instance to decorate
     */
    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the Definition being decorated.
     *
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Sets the Definition being decorated.
     *
     * @param string $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * If replaceArgument() has been used to replace an argument, this method
     * will return the replacement value.
     *
     * @param int $index
     *
     * @return mixed The argument value
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function getArgument($index)
    {
        if (array_key_exists('index_'.$index, $this->arguments)) {
            return $this->arguments['index_'.$index];
        }

        $lastIndex = count(array_filter(array_keys($this->arguments), 'is_int')) - 1;

        if ($index < 0 || $index > $lastIndex) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, $lastIndex));
        }

        return $this->arguments[$index];
    }

    /**
     * You should always use this method when overwriting existing arguments
     * of the parent definition.
     *
     * If you directly call setArguments() keep in mind that you must follow
     * certain conventions when you want to overwrite the arguments of the
     * parent definition, otherwise your arguments will only be appended.
     *
     * @param int   $index
     * @param mixed $value
     *
     * @return self the current instance
     *
     * @throws InvalidArgumentException when $index isn't an integer
     */
    public function replaceArgument($index, $value)
    {
        if (is_int($index)) {
            $this->arguments['index_'.$index] = $value;
        } elseif (0 === strpos($index, '$')) {
            $this->arguments[$index] = $value;
        } else {
            throw new InvalidArgumentException('$index must be an integer.');
        }

        return $this;
    }

    /**
     * @internal
     */
    public function setAutoconfigured($autoconfigured)
    {
        throw new BadMethodCallException('A ChildDefinition cannot be autoconfigured.');
    }

    /**
     * @internal
     */
    public function setInstanceofConditionals(array $instanceof)
    {
        throw new BadMethodCallException('A ChildDefinition cannot have instanceof conditionals set on it.');
    }
}

class_alias(ChildDefinition::class, DefinitionDecorator::class);
