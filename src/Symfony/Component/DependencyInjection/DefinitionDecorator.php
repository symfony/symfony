<?php

namespace Symfony\Component\DependencyInjection;

/**
 * This definition decorates another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class DefinitionDecorator extends Definition
{
    protected $parent;
    protected $changes;

    public function __construct($parent)
    {
        parent::__construct();

        $this->parent = $parent;
        $this->changes = array();
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getChanges()
    {
        return $this->changes;
    }

    public function setClass($class)
    {
        $this->changes['class'] = true;

        return parent::setClass($class);
    }

    public function setFactoryService($service)
    {
        $this->changes['factory_service'] = true;

        return parent::setFactoryService($service);
    }

    public function setFactoryMethod($method)
    {
        $this->changes['factory_method'] = true;

        return parent::setFactoryMethod($method);
    }

    public function setConfigurator($callable)
    {
        $this->changes['configurator'] = true;

        return parent::setConfigurator($callable);
    }

    public function setFile($file)
    {
        $this->changes['file'] = true;

        return parent::setFile($file);
    }

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