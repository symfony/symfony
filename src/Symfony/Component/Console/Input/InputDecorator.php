<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

/**
 * Allows to preset/override input options and arguments before/after input binding.
 * This is mainly useful for preserving input changes made from console events.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
class InputDecorator implements InputInterface
{
    private $inner;
    private $arguments = array();
    private $options = array();

    public function __construct(InputInterface $inner)
    {
        $this->inner = $inner;
    }

    public function getInner()
    {
        return $this->inner;
    }

    public function setArgument($key, $value)
    {
        $this->arguments[$key] = $value;

        return $this->inner->setArgument($key, $value);
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this->inner->setOption($key, $value);
    }

    public function getFirstArgument()
    {
        return $this->inner->getFirstArgument();
    }

    public function hasParameterOption($values, $onlyParams = false)
    {
        return $this->inner->hasParameterOption($values);
    }

    public function getParameterOption($values, $default = false, $onlyParams = false)
    {
        return $this->inner->getParameterOption($values, $default);
    }

    public function bind(InputDefinition $definition)
    {
        $ret = $this->inner->bind($definition);

        foreach ($this->arguments as $k => $v) {
            $this->inner->setArgument($k, $v);
        }

        foreach ($this->options as $k => $v) {
            $this->inner->setOption($k, $v);
        }

        return $ret;
    }

    public function validate()
    {
        return $this->inner->validate();
    }

    public function getArguments()
    {
        return $this->inner->getArguments();
    }

    public function getArgument($name)
    {
        return $this->inner->getArgument($name);
    }

    public function hasArgument($name)
    {
        return $this->inner->hasArgument($name);
    }

    public function getOptions()
    {
        return $this->inner->getOptions();
    }

    public function getOption($name)
    {
        return $this->inner->getOption($name);
    }

    public function hasOption($name)
    {
        return $this->inner->hasOption($name);
    }

    public function isInteractive()
    {
        return $this->inner->isInteractive();
    }

    public function setInteractive($interactive)
    {
        return $this->inner->setInteractive($interactive);
    }
}
