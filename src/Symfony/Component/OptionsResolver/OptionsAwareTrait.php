<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver;

use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/**
 * Exposes option utilities for generic purposes.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
trait OptionsAwareTrait
{
    private $strictOptions = true;
    private $defaultOptionValue = null;
    private $options = array();
    private $optionsResolver;

    private function initOptions(array $options = array())
    {
        $this->setOptions($options);
    }

    private function setOptions(array $options)
    {
        $this->options = $this->getOptionsResolver()->resolve($options);
    }

    private function setOption($name, $value)
    {
        $this->setOptions(array($name => $value) + $this->options);
    }

    private function getOptions()
    {
        return $this->options;
    }

    /**
     * @throws UndefinedOptionsException If the option name is undefined and strict options are enabled
     */
    private function getOption($name, $default = null)
    {
        if ($this->getOptionsResolver()->isDefined($name)) {
            return $this->options[$name];
        }
        if ($this->strictOptions) {
            throw new UndefinedOptionsException(sprintf(
               'The option "%s" does not exist. Defined options are: "%s".',
                $name,
                implode('", "', $this->getOptionsResolver()->getDefinedOptions())
            ));
        }

        return (func_num_args() > 1 ? $default : $this->defaultOptionValue);
    }

    private function getOptionsResolver()
    {
        if (null === $this->optionsResolver) {
            $this->optionsResolver = new OptionsResolver();
            $this->configureOptions($this->optionsResolver);
        }

        return $this->optionsResolver;
    }

    abstract protected function configureOptions(OptionsResolver $resolver);
}
