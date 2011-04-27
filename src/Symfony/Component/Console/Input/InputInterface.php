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
 * InputInterface is the interface implemented by all input classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
     * @param string|array $value The values to look for in the raw parameters (can be an array)
     *
     * @return Boolean true if the value is contained in the raw parameters
     */
    function hasParameterOption($values);

    /**
     * Returns the value of a raw option (not parsed).
     *
     * This method is to be used to introspect the input parameters
     * before it has been validated. It must be used carefully.
     *
     * @param string|array $values The value(s) to look for in the raw parameters (can be an array)
     * @param mixed $default The default value to return if no result is found
     *
     * @return mixed The option value
     */
    function getParameterOption($values, $default = false);

    /**
     * Binds the current Input instance with the given arguments and options.
     *
     * @param InputDefinition $definition A InputDefinition instance
     */
    function bind(InputDefinition $definition);

    /**
     * Validate if arguments given are correct.
     *
     * Throws an exception when not enough arguments are given.
     *
     * @throws \RuntimeException
     */
    function validate();

    /**
     * Returns all the given arguments merged with the default values.
     *
     * @return array
     */
    function getArguments();

    /**
     * Get argument by name.
     *
     * @param string $name The name of the argument
     * @return mixed
     */
    function getArgument($name);

    /**
     * @return array
     */
    function getOptions();

    /**
     * Get an option by name.
     *
     * @param string $name The name of the option
     * @return mixed
     */
    function getOption($name);

    /**
     * Is this input means interactive?
     *
     * @return Boolean
     */
    function isInteractive();
}
