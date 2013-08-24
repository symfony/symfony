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
 * Represents a command line argument.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 *
 * @since v2.0.0
 */
class InputArgument
{
    const REQUIRED = 1;
    const OPTIONAL = 2;
    const IS_ARRAY = 4;

    private $name;
    private $mode;
    private $default;
    private $description;

    /**
     * Constructor.
     *
     * @param string  $name        The argument name
     * @param integer $mode        The argument mode: self::REQUIRED or self::OPTIONAL
     * @param string  $description A description text
     * @param mixed   $default     The default value (for self::OPTIONAL mode only)
     *
     * @throws \InvalidArgumentException When argument mode is not valid
     *
     * @api
     *
     * @since v2.0.0
     */
    public function __construct($name, $mode = null, $description = '', $default = null)
    {
        if (null === $mode) {
            $mode = self::OPTIONAL;
        } elseif (!is_int($mode) || $mode > 7 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Argument mode "%s" is not valid.', $mode));
        }

        $this->name        = $name;
        $this->mode        = $mode;
        $this->description = $description;

        $this->setDefault($default);
    }

    /**
     * Returns the argument name.
     *
     * @return string The argument name
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns true if the argument is required.
     *
     * @return Boolean true if parameter mode is self::REQUIRED, false otherwise
     *
     * @since v2.0.0
     */
    public function isRequired()
    {
        return self::REQUIRED === (self::REQUIRED & $this->mode);
    }

    /**
     * Returns true if the argument can take multiple values.
     *
     * @return Boolean true if mode is self::IS_ARRAY, false otherwise
     *
     * @since v2.0.0
     */
    public function isArray()
    {
        return self::IS_ARRAY === (self::IS_ARRAY & $this->mode);
    }

    /**
     * Sets the default value.
     *
     * @param mixed $default The default value
     *
     * @throws \LogicException When incorrect default value is given
     *
     * @since v2.0.0
     */
    public function setDefault($default = null)
    {
        if (self::REQUIRED === $this->mode && null !== $default) {
            throw new \LogicException('Cannot set a default value except for InputArgument::OPTIONAL mode.');
        }

        if ($this->isArray()) {
            if (null === $default) {
                $default = array();
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for an array argument must be an array.');
            }
        }

        $this->default = $default;
    }

    /**
     * Returns the default value.
     *
     * @return mixed The default value
     *
     * @since v2.0.0
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Returns the description text.
     *
     * @return string The description text
     *
     * @since v2.0.0
     */
    public function getDescription()
    {
        return $this->description;
    }
}
