<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

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
