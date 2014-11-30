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

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

/**
 * This definition decorates another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @api
 */
class DefinitionDecorator extends Definition
{
    private $parent;
    private $changes;

    /**
     * Constructor.
     *
     * @param string $parent The id of Definition instance to decorate.
     *
     * @api
     */
    public function __construct($parent)
    {
        parent::__construct();

        $this->parent = $parent;
        $this->changes = array();
    }

    /**
     * Returns the Definition being decorated.
     *
     * @return string
     *
     * @api
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns all changes tracked for the Definition object.
     *
     * @return array An array of changes for this Definition
     *
     * @api
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setClass($class)
    {
        $this->changes['class'] = true;

        return parent::setClass($class);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setFactoryClass($class)
    {
        $this->changes['factory_class'] = true;

        return parent::setFactoryClass($class);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setFactoryMethod($method)
    {
        $this->changes['factory_method'] = true;

        return parent::setFactoryMethod($method);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setFactoryService($service)
    {
        $this->changes['factory_service'] = true;

        return parent::setFactoryService($service);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setConfigurator($callable)
    {
        $this->changes['configurator'] = true;

        return parent::setConfigurator($callable);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setFile($file)
    {
        $this->changes['file'] = true;

        return parent::setFile($file);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setPublic($boolean)
    {
        $this->changes['public'] = true;

        return parent::setPublic($boolean);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function setLazy($boolean)
    {
        $this->changes['lazy'] = true;

        return parent::setLazy($boolean);
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
     *
     * @api
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
     * @return DefinitionDecorator      the current instance
     * @throws InvalidArgumentException when $index isn't an integer
     *
     * @api
     */
    public function replaceArgument($index, $value)
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException('$index must be an integer.');
        }

        $this->arguments['index_'.$index] = $value;

        return $this;
    }
}
