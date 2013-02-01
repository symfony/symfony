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
 * Represents a command line option.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class InputOption
{
    const VALUE_NONE     = 1;
    const VALUE_REQUIRED = 2;
    const VALUE_OPTIONAL = 4;
    const VALUE_MULTIPLE = 8;
    /* @deprecated in 2.2, to be removed in 3.0 */
    const VALUE_IS_ARRAY = self::VALUE_MULTIPLE;

    private $name;
    private $shortcut;
    private $mode;
    private $default;
    private $description;

    /**
     * Constructor.
     *
     * @param string  $name        The option name
     * @param string  $shortcut    The shortcut (can be null)
     * @param integer $mode        The option mode: A combination of some VALUE_* constants
     * @param string  $description A description text
     * @param mixed   $default     The default value (must be null for self::VALUE_REQUIRED or self::VALUE_NONE)
     *
     * @throws \InvalidArgumentException If option mode is invalid or incompatible
     *
     * @api
     */
    public function __construct($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if (0 === strpos($name, '--')) {
            $name = substr($name, 2);
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('An option name cannot be empty.');
        }

        if (empty($shortcut)) {
            $shortcut = null;
        } elseif (null !== $shortcut) {
            if ('-' === $shortcut[0]) {
                $shortcut = substr($shortcut, 1);
            }

            if (empty($shortcut)) {
                throw new \InvalidArgumentException('An option shortcut cannot be empty.');
            }
        }

        if (null === $mode) {
            $mode = self::VALUE_NONE;
        } elseif (!is_int($mode) || $mode > 15 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
        }

        $this->name        = $name;
        $this->shortcut    = $shortcut;
        $this->mode        = $mode;
        $this->description = $description;

        if ($this->isMultiple() && !$this->acceptValue()) {
            throw new \InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        }

        $this->setDefault($default);
    }

    /**
     * Returns the option shortcut.
     *
     * @return string The shortcut
     */
    public function getShortcut()
    {
        return $this->shortcut;
    }

    /**
     * Returns the option name.
     *
     * @return string The name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Boolean true if the option can have a value
     */
    public function acceptValue()
    {
        return $this->isValueRequired() || $this->isValueOptional();
    }

    /**
     * @return Boolean true if the option requires a value.
     */
    public function isValueRequired()
    {
        return (Boolean) (self::VALUE_REQUIRED & $this->mode);
    }

    /**
     * @return Boolean true if the option value can optionally be provided.
     */
    public function isValueOptional()
    {
        return (Boolean) (self::VALUE_OPTIONAL & $this->mode);
    }

    /**
     * @return Boolean true if the option can have multiple values
     */
    public function isMultiple()
    {
        return (Boolean) (self::VALUE_MULTIPLE & $this->mode);
    }

    /**
     * @return Boolean true if the option can have multiple values
     *
     * @deprecated since 2.2 to be removed in 3.0, use isMultiple() instead.
     */
    public function isArray()
    {
        trigger_error('isArray() is deprecated since version 2.2 and will be removed in 3.0. Use isMultiple() instead.', E_USER_DEPRECATED);

        return $this->isMultiple();
    }

    /**
     * Sets the default value.
     *
     * @param mixed $default The default value
     *
     * @throws \LogicException When incorrect default value is given
     */
    public function setDefault($default = null)
    {
        if (self::VALUE_NONE === (self::VALUE_NONE & $this->mode) && null !== $default) {
            throw new \LogicException('Cannot set a default value when using InputOption::VALUE_NONE mode.');
        }

        if ($this->isMultiple()) {
            if (null === $default) {
                $default = array();
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for a multiple option must be an array.');
            }
        }

        $this->default = $this->acceptValue() ? $default : false;
    }

    /**
     * Returns the default value.
     *
     * @return mixed The default value
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Returns the description text.
     *
     * @return string The description text
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Checks whether the given option equals this one
     *
     * @param InputOption $option option to compare
     * @return Boolean
     */
    public function equals(InputOption $option)
    {
        return $option->getName() === $this->getName()
            && $option->getShortcut() === $this->getShortcut()
            && $option->getDefault() === $this->getDefault()
            && $option->isMultiple() === $this->isMultiple()
            && $option->isValueRequired() === $this->isValueRequired()
            && $option->isValueOptional() === $this->isValueOptional()
        ;
    }
}
