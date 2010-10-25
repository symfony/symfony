<?php

namespace Symfony\Component\Console\Input;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * InputInterface is the interface implemented by all input classes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface InputInterface
{
    /**
     * Returns the first argument from the raw parameters (not parsed).
     *
     * @return string The value of the first argument or null otherwise
     */
    function getFirstArgument();

    /**
     * Returns true if the raw parameters (not parsed) contains a value.
     *
     * This method is to be used to introspect the input parameters
     * before it has been validated. It must be used carefully.
     *
     * @param string $value The value to look for in the raw parameters
     *
     * @return Boolean true if the value is contained in the raw parameters
     */
    function hasParameterOption($value);

    /**
     * Binds the current Input instance with the given arguments and options.
     *
     * @param InputDefinition $definition A InputDefinition instance
     */
    function bind(InputDefinition $definition);

    function validate();

    function getArguments();

    function getArgument($name);

    function getOptions();

    function getOption($name);
}
