<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\MissingOptionsException;
use Symfony\Component\Form\Exception\InvalidOptionsException;

/**
 * A class configurable via options
 *
 * Options can be passed to the constructor of this class. After constructions,
 * these options cannot be changed anymore. This way, options remain light
 * weight. There is no need to monitor changes of options.
 *
 * If you want options that can change, you're recommended to implement plain
 * properties with setters and getters.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
abstract class Configurable
{
    /**
     * The options and their values
     * @var array
     */
    private $options = array();

    /**
     * The names of the valid options
     * @var array
     */
    private $knownOptions = array();

    /**
     * The names of the required options
     * @var array
     */
    private $requiredOptions = array();

    /**
     * Reads, validates and stores the given options
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->options, $options);

        $this->configure();

        // check option names
        if ($diff = array_diff_key($this->options, $this->knownOptions)) {
            throw new InvalidOptionsException(sprintf('%s does not support the following options: "%s".', get_class($this), implode('", "', array_keys($diff))), array_keys($diff));
        }

        // check required options
        if ($diff = array_diff_key($this->requiredOptions, $this->options)) {
            throw new MissingOptionsException(sprintf('%s requires the following options: \'%s\'.', get_class($this), implode('", "', array_keys($diff))), array_keys($diff));
        }
    }

    /**
     * Configures the valid options
     *
     * This method should call addOption() or addRequiredOption() for every
     * accepted option.
     */
    protected function configure()
    {
    }

    /**
     * Returns an option value.
     *
     * @param  string $name  The option name
     *
     * @return mixed  The option value
     */
    public function getOption($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    /**
     * Adds a new option value with a default value.
     *
     * @param string $name   The option name
     * @param mixed  $value  The default value
     */
    protected function addOption($name, $value = null, array $allowedValues = array())
    {
        $this->knownOptions[$name] = true;

        if (!array_key_exists($name, $this->options)) {
            $this->options[$name] = $value;
        }

        if (count($allowedValues) > 0 && !in_array($this->options[$name], $allowedValues)) {
            throw new InvalidOptionsException(sprintf('The option "%s" is expected to be one of "%s", but is "%s"', $name, implode('", "', $allowedValues), $this->options[$name]), array($name));
        }
    }

    /**
     * Adds a required option.
     *
     * @param string $name  The option name
     */
    protected function addRequiredOption($name, array $allowedValues = array())
    {
        $this->knownOptions[$name] = true;
        $this->requiredOptions[$name] = true;

        // only test if the option is set, otherwise an error will be thrown
        // anyway
        if (isset($this->options[$name]) && count($allowedValues) > 0 && !in_array($this->options[$name], $allowedValues)) {
            throw new InvalidOptionsException(sprintf('The option "%s" is expected to be one of "%s", but is "%s"', $name, implode('", "', $allowedValues), $this->options[$name]), array($name));
        }
    }

    /**
     * Returns true if the option exists.
     *
     * @param  string $name  The option name
     *
     * @return Boolean true if the option is set, false otherwise
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    public function getOptions()
    {
        return $this->options;
    }
}
