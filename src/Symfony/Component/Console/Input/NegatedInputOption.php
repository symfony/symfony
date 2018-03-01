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
use Symfony\Component\Console\Exception\LogicException;

/**
 * Represents a command line option.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NegatedInputOption extends InputOption
{
    private $primaryOption;

    const VALUE_NONE = 1;
    const VALUE_REQUIRED = 2;
    const VALUE_OPTIONAL = 4;
    const VALUE_IS_ARRAY = 8;
    const VALUE_NEGATABLE = 16;
    const VALUE_HIDDEN = 32;
    const VALUE_BINARY = (self::VALUE_NONE | self::VALUE_NEGATABLE);

    private $name;
    private $shortcut;
    private $mode;
    private $default;
    private $description;

    /**
     * @param string       $name        The option name
     * @param string|array $shortcut    The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param int          $mode        The option mode: One of the VALUE_* constants
     * @param string       $description A description text
     * @param mixed        $default     The default value (must be null for self::VALUE_NONE)
     *
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function __construct(InputOption $primaryOption)
    {
        $this->primaryOption = $primaryOption;

        /* string $name, $shortcut = null, int $mode = null, string $description = '', $default = null */
        $name = 'no-' . $primaryOption->getName();
        $shortcut = null;
        $mode = self::VALUE_HIDDEN;
        $description = $primaryOption->getDescription();
        $default = $this->negate($primaryOption->getDefault());

        parent::__construct($name, $shortcut, $mode, $description, $default);
    }

    public function effectiveName()
    {
        return $this->primaryOption->getName();
    }

    /**
     * Sets the default value.
     *
     * @param mixed $default The default value
     *
     * @throws LogicException When incorrect default value is given
     */
    public function setDefault($default = null)
    {
        $this->primaryOption->setDefault($this->negate($default));
    }

    /**
     * Returns the default value.
     *
     * @return mixed The default value
     */
    public function getDefault()
    {
        return $this->negate($this->primaryOption->getDefault());
    }

    /**
     * @inheritdoc
     */
    public function checkValue($value)
    {
        return false;
    }

    /**
     * Negate a value if it is provided.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function negate($value)
    {
        if ($value === null) {
            return $value;
        }
        return !$value;
    }
}
