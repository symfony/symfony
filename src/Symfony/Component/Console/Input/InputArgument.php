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
 */
class InputArgument
{
    const OPTIONAL = 0;
    const REQUIRED = 1;
    const MULTIPLE = 2;
    /* @deprecated in 2.2, to be removed in 3.0 */
    const IS_ARRAY = self::MULTIPLE;

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
     */
    public function __construct($name, $mode = null, $description = '', $default = null)
    {
        if (null === $mode) {
            $mode = self::OPTIONAL;
        } elseif (!is_int($mode) || $mode > 3 || $mode < 0) {
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
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Boolean true if the argument is required.
     */
    public function isRequired()
    {
        return (Boolean) (self::REQUIRED & $this->mode);
    }

    /**
     * @return Boolean true if the argument can take multiple values.
     */
    public function isMultiple()
    {
        return (Boolean) (self::MULTIPLE & $this->mode);
    }

    /**
     * @return Boolean true if the argument can take multiple values.
     *
     * @deprecated since 2.2, to be removed in 3.0. Use isMultiple() instead.
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
        if ($this->isRequired() && null !== $default) {
            throw new \LogicException('Cannot set a default value except for InputArgument::OPTIONAL mode.');
        }

        if ($this->isMultiple()) {
            if (null === $default) {
                $default = array();
            } elseif (!is_array($default)) {
                throw new \LogicException('A default value for a multiple argument must be an array.');
            }
        }

        $this->default = $default;
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
