<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

/**
 * A Route describes a route and its parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Route implements \Serializable
{
    /**
     * @var string
     */
    private $pattern = '/';

    /**
     * @var string
     */
    private $hostnamePattern = '';

   /**
     * @var array
     */
    private $defaults = array();

    /**
     * @var array
     */
    private $requirements = array();

    /**
     * @var array
     */
    private $options = array();

    /**
     * @var null|RouteCompiler
     */
    private $compiled;

    private static $compilers = array();

    /**
     * Constructor.
     *
     * Available options:
     *
     *  * compiler_class: A class name able to compile this route instance (RouteCompiler by default)
     *
     * @param string $pattern         The path pattern to match
     * @param array  $defaults        An array of default parameter values
     * @param array  $requirements    An array of requirements for parameters (regexes)
     * @param array  $options         An array of options
     * @param string $hostnamePattern The hostname pattern to match
     *
     * @api
     */
    public function __construct($pattern, array $defaults = array(), array $requirements = array(), array $options = array(), $hostnamePattern = '')
    {
        $this->setPattern($pattern);
        $this->setDefaults($defaults);
        $this->setRequirements($requirements);
        $this->setOptions($options);
        $this->setHostnamePattern($hostnamePattern);
    }

    public function serialize()
    {
        return serialize(array(
            'pattern' => $this->pattern,
            'hostnamePattern' => $this->hostnamePattern,
            'defaults' => $this->defaults,
            'requirements' => $this->requirements,
            'options' => $this->options,
        ));
    }

    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->pattern = $data['pattern'];
        $this->hostnamePattern = $data['hostnamePattern'];
        $this->defaults = $data['defaults'];
        $this->requirements = $data['requirements'];
        $this->options = $data['options'];
    }

    /**
     * Returns the pattern for the path.
     *
     * @return string The pattern
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Sets the pattern for the path.
     *
     * This method implements a fluent interface.
     *
     * @param string $pattern The pattern
     *
     * @return Route The current Route instance
     */
    public function setPattern($pattern)
    {
        // A pattern must start with a slash and must not have multiple slashes at the beginning because the
        // generated path for this route would be confused with a network path, e.g. '//domain.com/path'.
        $this->pattern = '/' . ltrim(trim($pattern), '/');
        $this->compiled = null;

        return $this;
    }

    /**
     * Returns the hostname pattern.
     *
     * @return string The pattern
     */
    public function getHostnamePattern()
    {
        return $this->hostnamePattern;
    }

    /**
     * Sets the hostname pattern.
     *
     * @param string $pattern The pattern
     *
     * @return Route The current Route instance
     */
    public function setHostnamePattern($pattern)
    {
        $this->hostnamePattern = (string) $pattern;
        $this->compiled = null;

        return $this;
    }

    /**
     * Returns the options.
     *
     * @return array The options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets the options.
     *
     * This method implements a fluent interface.
     *
     * @param array $options The options
     *
     * @return Route The current Route instance
     */
    public function setOptions(array $options)
    {
        $this->options = array(
            'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler',
        );

        return $this->addOptions($options);
    }

    /**
     * Adds options.
     *
     * This method implements a fluent interface.
     *
     * @param array $options The options
     *
     * @return Route The current Route instance
     */
    public function addOptions(array $options)
    {
        foreach ($options as $name => $option) {
            $this->options[$name] = $option;
        }
        $this->compiled = null;

        return $this;
    }

    /**
     * Sets an option value.
     *
     * This method implements a fluent interface.
     *
     * @param string $name  An option name
     * @param mixed  $value The option value
     *
     * @return Route The current Route instance
     *
     * @api
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
        $this->compiled = null;

        return $this;
    }

    /**
     * Get an option value.
     *
     * @param string $name An option name
     *
     * @return mixed The option value or null when not given
     */
    public function getOption($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    /**
     * Checks if a an option has been set
     *
     * @param string $name An option name
     *
     * @return Boolean true if the option is set, false otherwise
     */
    public function hasOption($name)
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * Returns the defaults.
     *
     * @return array The defaults
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * Sets the defaults.
     *
     * This method implements a fluent interface.
     *
     * @param array $defaults The defaults
     *
     * @return Route The current Route instance
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = array();

        return $this->addDefaults($defaults);
    }

    /**
     * Adds defaults.
     *
     * This method implements a fluent interface.
     *
     * @param array $defaults The defaults
     *
     * @return Route The current Route instance
     */
    public function addDefaults(array $defaults)
    {
        foreach ($defaults as $name => $default) {
            $this->defaults[$name] = $default;
        }
        $this->compiled = null;

        return $this;
    }

    /**
     * Gets a default value.
     *
     * @param string $name A variable name
     *
     * @return mixed The default value or null when not given
     */
    public function getDefault($name)
    {
        return isset($this->defaults[$name]) ? $this->defaults[$name] : null;
    }

    /**
     * Checks if a default value is set for the given variable.
     *
     * @param string $name A variable name
     *
     * @return Boolean true if the default value is set, false otherwise
     */
    public function hasDefault($name)
    {
        return array_key_exists($name, $this->defaults);
    }

    /**
     * Sets a default value.
     *
     * @param string $name    A variable name
     * @param mixed  $default The default value
     *
     * @return Route The current Route instance
     *
     * @api
     */
    public function setDefault($name, $default)
    {
        $this->defaults[$name] = $default;
        $this->compiled = null;

        return $this;
    }

    /**
     * Returns the requirements.
     *
     * @return array The requirements
     */
    public function getRequirements()
    {
        return $this->requirements;
    }

    /**
     * Sets the requirements.
     *
     * This method implements a fluent interface.
     *
     * @param array $requirements The requirements
     *
     * @return Route The current Route instance
     */
    public function setRequirements(array $requirements)
    {
        $this->requirements = array();

        return $this->addRequirements($requirements);
    }

    /**
     * Adds requirements.
     *
     * This method implements a fluent interface.
     *
     * @param array $requirements The requirements
     *
     * @return Route The current Route instance
     */
    public function addRequirements(array $requirements)
    {
        foreach ($requirements as $key => $regex) {
            $this->requirements[$key] = $this->sanitizeRequirement($key, $regex);
        }
        $this->compiled = null;

        return $this;
    }

    /**
     * Returns the requirement for the given key.
     *
     * @param string $key The key
     *
     * @return string|null The regex or null when not given
     */
    public function getRequirement($key)
    {
        return isset($this->requirements[$key]) ? $this->requirements[$key] : null;
    }

    /**
     * Checks if a requirement is set for the given key.
     *
     * @param string $key A variable name
     *
     * @return Boolean true if a requirement is specified, false otherwise
     */
    public function hasRequirement($key)
    {
        return array_key_exists($key, $this->requirements);
    }

    /**
     * Sets a requirement for the given key.
     *
     * @param string $key   The key
     * @param string $regex The regex
     *
     * @return Route The current Route instance
     *
     * @api
     */
    public function setRequirement($key, $regex)
    {
        $this->requirements[$key] = $this->sanitizeRequirement($key, $regex);
        $this->compiled = null;

        return $this;
    }

    /**
     * Compiles the route.
     *
     * @return CompiledRoute A CompiledRoute instance
     *
     * @see RouteCompiler which is responsible for the compilation process
     */
    public function compile()
    {
        if (null !== $this->compiled) {
            return $this->compiled;
        }

        $class = $this->getOption('compiler_class');

        if (!isset(self::$compilers[$class])) {
            self::$compilers[$class] = new $class;
        }

        return $this->compiled = self::$compilers[$class]->compile($this);
    }

    private function sanitizeRequirement($key, $regex)
    {
        if (!is_string($regex)) {
            throw new \InvalidArgumentException(sprintf('Routing requirement for "%s" must be a string.', $key));
        }

        if ('' !== $regex && '^' === $regex[0]) {
            $regex = (string) substr($regex, 1); // returns false for a single character
        }

        if ('$' === substr($regex, -1)) {
            $regex = substr($regex, 0, -1);
        }

        if ('' === $regex) {
            throw new \InvalidArgumentException(sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
    }
}
