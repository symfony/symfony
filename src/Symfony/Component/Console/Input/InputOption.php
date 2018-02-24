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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Represents a command line option.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InputOption
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;
    public const VALUE_IS_ARRAY = 8;
    public const VALUE_NEGATABLE = 16;
    public const VALUE_HIDDEN = 32;
    public const VALUE_BINARY = (self::VALUE_NONE | self::VALUE_NEGATABLE);

    private $name;
    private $shortcut;
    private $mode;
    private $default;
    private $description;

    /**
     * @param string                        $name        The option name
     * @param string|array|null             $shortcut    The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param int|null                      $mode        The option mode: One of the VALUE_* constants
     * @param string                        $description A description text
     * @param string|string[]|int|bool|null $default     The default value (must be null for self::VALUE_NONE)
     *
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function __construct(string $name, $shortcut = null, int $mode = null, string $description = '', $default = null)
    {
        if (0 === strpos($name, '--')) {
            $name = substr($name, 2);
        }

        if (empty($name)) {
            throw new InvalidArgumentException('An option name cannot be empty.');
        }

        if (empty($shortcut)) {
            $shortcut = null;
        }

        if (null !== $shortcut) {
            if (\is_array($shortcut)) {
                $shortcut = implode('|', $shortcut);
            }
            $shortcuts = preg_split('{(\|)-?}', ltrim($shortcut, '-'));
            $shortcuts = array_filter($shortcuts);
            $shortcut = implode('|', $shortcuts);

            if (empty($shortcut)) {
                throw new InvalidArgumentException('An option shortcut cannot be empty.');
            }
        }

        if (null === $mode) {
            $mode = self::VALUE_NONE;
        } elseif ($mode >= (self::VALUE_HIDDEN << 1) || $mode < 1) {
            throw new InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
        }

        $this->name = $name;
        $this->shortcut = $shortcut;
        $this->mode = $mode;
        $this->description = $description;

        if ($this->isArray() && !$this->acceptValue()) {
            throw new InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
        }

        $this->setDefault($default);
    }

    /**
     * Returns the option shortcut.
     *
     * @return string|null The shortcut
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

    public function effectiveName()
    {
        return $this->getName();
    }

    /**
     * Returns true if the option accepts a value.
     *
     * @return bool true if value mode is not self::VALUE_NONE, false otherwise
     */
    public function acceptValue()
    {
        return $this->isValueRequired() || $this->isValueOptional();
    }

    /**
     * Returns true if the option requires a value.
     *
     * @return bool true if value mode is self::VALUE_REQUIRED, false otherwise
     */
    public function isValueRequired()
    {
        return self::VALUE_REQUIRED === (self::VALUE_REQUIRED & $this->mode);
    }

    /**
     * Returns true if the option takes an optional value.
     *
     * @return bool true if value mode is self::VALUE_OPTIONAL, false otherwise
     */
    public function isValueOptional()
    {
        return self::VALUE_OPTIONAL === (self::VALUE_OPTIONAL & $this->mode);
    }

    /**
     * Returns true if the option can take multiple values.
     *
     * @return bool true if mode is self::VALUE_IS_ARRAY, false otherwise
     */
    public function isArray()
    {
        return self::VALUE_IS_ARRAY === (self::VALUE_IS_ARRAY & $this->mode);
    }

    /**
     * Returns true if the option is negatable (option --foo can be forced
     * to 'false' via the --no-foo option).
     *
     * @return bool true if mode is self::VALUE_NEGATABLE, false otherwise
     */
    public function isNegatable()
    {
        return self::VALUE_NEGATABLE === (self::VALUE_NEGATABLE & $this->mode);
    }

    /**
     * Returns true if the option should not be shown in help (e.g. a negated
     * option).
     *
     * @return bool true if mode is self::VALUE_HIDDEN, false otherwise
     */
    public function isHidden()
    {
        return self::VALUE_HIDDEN === (self::VALUE_HIDDEN & $this->mode);
    }

    /**
     * Returns true if the option is binary (can be --foo or --no-foo, and
     * nothing else).
     *
     * @return bool true if negatable and does not have a value.
     */
    public function isBinary()
    {
        return $this->isNegatable() && !$this->acceptValue();
    }

    /**
     * Sets the default value.
     *
     * @param string|string[]|int|bool|null $default The default value
     *
     * @throws LogicException When incorrect default value is given
     */
    public function setDefault($default = null)
    {
        if (self::VALUE_NONE === ((self::VALUE_NONE | self::VALUE_NEGATABLE) & $this->mode) && null !== $default) {
            throw new LogicException('Cannot set a default value when using InputOption::VALUE_NONE mode.');
        }

        if ($this->isArray()) {
            if (null === $default) {
                $default = [];
            } elseif (!\is_array($default)) {
                throw new LogicException('A default value for an array option must be an array.');
            }
        }

        $this->default = ($this->acceptValue() || $this->isNegatable()) ? $default : false;
    }

    /**
     * Returns the default value.
     *
     * @return string|string[]|int|bool|null The default value
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
     * Checks the validity of a value, and alters it as necessary
     *
     * @param mixed $value
     *
     * @return @mixed
     */
    public function checkValue($value)
    {
        if (null === $value) {
            if ($this->isValueRequired()) {
                throw new InvalidOptionException(sprintf('The "--%s" option requires a value.', $this->getName()));
            }

            if (!$this->isValueOptional()) {
                return true;
            }
        }
        return $value;
    }

    /**
     * Checks whether the given option equals this one.
     *
     * @return bool
     */
    public function equals(self $option)
    {
        return $option->getName() === $this->getName()
            && $option->getShortcut() === $this->getShortcut()
            && $option->getDefault() === $this->getDefault()
            && $option->isHidden() === $this->isHidden()
            && $option->isNegatable() === $this->isNegatable()
            && $option->isArray() === $this->isArray()
            && $option->isValueRequired() === $this->isValueRequired()
            && $option->isValueOptional() === $this->isValueOptional()
        ;
    }
}
