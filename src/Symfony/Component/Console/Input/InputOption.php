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
 * Represents a command line option.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InputOption
{
    const VALUE_NONE     = 1;
    const VALUE_REQUIRED = 2;
    const VALUE_OPTIONAL = 4;
    const VALUE_IS_ARRAY = 8;

    protected $name;
    protected $shortcut;
    protected $mode;
    protected $default;
    protected $description;

    /**
     * Constructor.
     *
     * @param string  $name        The option name
     * @param string  $shortcut    The shortcut (can be null)
     * @param integer $mode        The option mode: One of the VALUE_* constants
     * @param string  $description A description text
     * @param mixed   $default     The default value (must be null for self::VALUE_REQUIRED or self::VALUE_NONE)
     *
     * @throws \InvalidArgumentException If option mode is invalid or incompatible
     */
    public function __construct($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if ('--' === substr($name, 0, 2)) {
            $name = substr($name, 2);
        }

        if (empty($shortcut)) {
            $shortcut = null;
        }

        if (null !== $shortcut) {
            if ('-' === $shortcut[0]) {
                $shortcut = substr($shortcut, 1);
            }
        }

        if (null === $mode) {
            $mode = self::VALUE_NONE;
        } else if (!is_int($mode) || $mode > 15) {
            throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
        }

        $this->name        = $name;
        $this->shortcut    = $shortcut;
        $this->mode        = $mode;
        $this->description = $description;

        if ($this->isArray() && !$this->acceptValue()) {
            throw new \InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        }

        $this->setDefault($default);
    }

    /**
     * Returns the shortcut.
     *
     * @return string The shortcut
     */
    public function getShortcut()
    {
        return $this->shortcut;
    }

    /**
     * Returns the name.
     *
     * @return string The name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns true if the option accepts a value.
     *
     * @return Boolean true if value mode is not self::VALUE_NONE, false otherwise
     */
    public function acceptValue()
    {
        return $this->isValueRequired() || $this->isValueOptional();
    }

    /**
     * Returns true if the option requires a value.
     *
     * @return Boolean true if value mode is self::VALUE_REQUIRED, false otherwise
     */
    public function isValueRequired()
    {
        return self::VALUE_REQUIRED === (self::VALUE_REQUIRED & $this->mode);
    }

    /**
     * Returns true if the option takes an optional value.
     *
     * @return Boolean true if value mode is self::VALUE_OPTIONAL, false otherwise
     */
    public function isValueOptional()
    {
        return self::VALUE_OPTIONAL === (self::VALUE_OPTIONAL & $this->mode);
    }

    /**
     * Returns true if the option can take multiple values.
     *
     * @return Boolean true if mode is self::VALUE_IS_ARRAY, false otherwise
     */
    public function isArray()
    {
        return self::VALUE_IS_ARRAY === (self::VALUE_IS_ARRAY & $this->mode);
    }

    /**
     * Sets the default value.
     *
     * @param mixed $default The default value
     */
    public function setDefault($default = null)
    {
        if (self::VALUE_NONE === (self::VALUE_NONE & $this->mode) && null !== $default) {
            throw new \LogicException('Cannot set a default value when using Option::VALUE_NONE mode.');
        }

        if ($this->isArray()) {
            if (null === $default) {
                $default = array();
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for an array option must be an array.');
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
}
