<?php

namespace Symfony\Component\DependencyInjection;

/**
 * This definition decorates another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefinitionDecorator extends Definition
{
    private $parent;
    private $changes;

    /**
     * Constructor.
     *
     * @param Definition $parent The Definition instance to decorate.
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
     * @return Definition
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns all changes tracked for the Definition object.
     *
     * @return array An array of changes for this Definition
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * {@inheritDoc}
     */
    public function setClass($class)
    {
        $this->changes['class'] = true;

        return parent::setClass($class);
    }

    /**
     * {@inheritDoc}
     */
    public function setFactoryClass($class)
    {
        $this->changes['factory_class'] = true;

        return parent::setFactoryClass($class);
    }

    /**
     * {@inheritDoc}
     */
    public function setFactoryMethod($method)
    {
        $this->changes['factory_method'] = true;

        return parent::setFactoryMethod($method);
    }

    /**
     * {@inheritDoc}
     */
    public function setFactoryService($service)
    {
        $this->changes['factory_service'] = true;

        return parent::setFactoryService($service);
    }

    /**
     * {@inheritDoc}
     */
    public function setConfigurator($callable)
    {
        $this->changes['configurator'] = true;

        return parent::setConfigurator($callable);
    }

    /**
     * {@inheritDoc}
     */
    public function setFile($file)
    {
        $this->changes['file'] = true;

        return parent::setFile($file);
    }

    /**
     * {@inheritDoc}
     */
    public function setPublic($boolean)
    {
        $this->changes['public'] = true;

        return parent::setPublic($boolean);
    }

    /**
     * You should always use this method when overwriting existing arguments
     * of the parent definition.
     *
     * If you directly call setArguments() keep in mind that you must follow
     * certain conventions when you want to overwrite the arguments of the
     * parent definition, otherwise your arguments will only be appended.
     *
     * @param integer $index
     * @param mixed $value
     *
     * @return DefinitionDecorator the current instance
     * @throws \InvalidArgumentException when $index isnt an integer
     */
    public function setArgument($index, $value)
    {
        if (!is_int($index)) {
            throw new \InvalidArgumentException('$index must be an integer.');
        }

        $this->arguments['index_'.$index] = $value;

        return $this;
    }
}
